<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\Attendance\Connectors\MockHardwareConnector;
use App\Services\Attendance\Connectors\ZkHardwareConnector;
use App\Services\Attendance\Contracts\DeviceConnectorInterface;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AttendanceDeviceSyncService
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Resolve connector according to protocol and environment.
     */
    public function resolveConnector(AttendanceDevice $device, bool $forceMock = false): DeviceConnectorInterface
    {
        if ($forceMock || $device->protocol === 'mock' || $device->ip_address === 'mock' || $device->ip_address === '127.0.0.1') {
            return new MockHardwareConnector;
        }

        return new ZkHardwareConnector;
    }

    /**
     * Ping / Test device network connectivity.
     */
    public function pingDevice(AttendanceDevice $device, bool $forceMock = false): array
    {
        $connector = $this->resolveConnector($device, $forceMock);
        $result = $connector->ping($device);

        $device->update([
            'last_status' => $result['success'] ? 'online' : 'offline',
            'last_status_message' => $result['message'],
        ]);

        return $result;
    }

    /**
     * Sync attendance punch logs directly from the device into accounting records.
     */
    public function syncDevice(
        AttendanceDevice $device,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $duplicateMode = 'replace',
        bool $forceMock = false
    ): array {
        $connector = $this->resolveConnector($device, $forceMock);

        try {
            $punches = $connector->fetchAttendanceLogs($device, $device->last_sync_at?->toDateTimeString());
        } catch (Exception $e) {
            // If direct network failed and in local/dev or requested simulation
            if ($forceMock || app()->environment('local', 'testing')) {
                Log::warning("Direct clock connection failed, falling back to simulation: " . $e->getMessage());
                $connector = new MockHardwareConnector;
                $punches = $connector->fetchAttendanceLogs($device, $device->last_sync_at?->toDateTimeString());
            } else {
                $device->update([
                    'last_status' => 'error',
                    'last_status_message' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'imported' => 0,
                    'skipped' => 0,
                    'unknown_pins' => [],
                    'total_punches' => 0,
                ];
            }
        }

        $result = $this->processRawPunches($punches, $device->company_id, $dateFrom, $dateTo, $duplicateMode);

        $device->update([
            'last_sync_at' => now(),
            'last_log_count' => $result['imported'],
            'last_status' => 'synced',
            'last_status_message' => "همگام‌سازی موفق: {$result['imported']} رکورد تردد درج و بازیافت شد.",
        ]);

        return array_merge(['success' => true], $result);
    }

    /**
     * Process normalized raw punch logs and upsert into AttendanceLog with automated calculations.
     *
     * @param  array<int, array{device_id: string, timestamp: string, log_type: int}>  $punches
     */
    public function processRawPunches(
        array $punches,
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $duplicateMode = 'replace'
    ): array {
        /** @var Collection<string, Employee> $deviceMap */
        $deviceMap = Employee::where('company_id', $companyId)
            ->whereNotNull('device_id')
            ->get()
            ->keyBy('device_id');

        $imported = 0;
        $skipped = 0;
        $unknownPins = [];
        $pending = [];

        foreach ($punches as $punch) {
            $pin = trim((string) ($punch['device_id'] ?? ''));
            $datetimeStr = trim((string) ($punch['timestamp'] ?? ''));
            $logType = (int) ($punch['log_type'] ?? 0); // 0 = in, 1 = out

            if (empty($pin) || empty($datetimeStr)) {
                $skipped++;
                continue;
            }

            if (! isset($deviceMap[$pin])) {
                if (! in_array($pin, $unknownPins, true)) {
                    $unknownPins[] = $pin;
                }
                $skipped++;
                continue;
            }

            $employee = $deviceMap[$pin];

            try {
                $dt = Carbon::parse($datetimeStr);
            } catch (Exception) {
                $skipped++;
                continue;
            }

            $logDate = $dt->toDateString();
            $logTime = $dt->format('H:i:s');

            if ($dateFrom !== null && $logDate < $dateFrom) {
                $skipped++;
                continue;
            }
            if ($dateTo !== null && $logDate > $dateTo) {
                $skipped++;
                continue;
            }

            if (! isset($pending[$employee->id][$logDate])) {
                $pending[$employee->id][$logDate] = ['entry_time' => null, 'exit_time' => null];
            }

            // 0 = check-in, 1 = check-out
            if ($logType === 0) {
                // Keep earliest entry time
                if (empty($pending[$employee->id][$logDate]['entry_time']) || $logTime < $pending[$employee->id][$logDate]['entry_time']) {
                    $pending[$employee->id][$logDate]['entry_time'] = $logTime;
                }
            } else {
                // Keep latest exit time
                if (empty($pending[$employee->id][$logDate]['exit_time']) || $logTime > $pending[$employee->id][$logDate]['exit_time']) {
                    $pending[$employee->id][$logDate]['exit_time'] = $logTime;
                }
            }
        }

        // Upsert one AttendanceLog per employee per date
        foreach ($pending as $employeeId => $dates) {
            foreach ($dates as $logDate => $times) {
                $existing = AttendanceLog::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('employee_id', $employeeId)
                    ->where('log_date', $logDate)
                    ->first();

                if ($existing) {
                    $update = [];
                    foreach (['entry_time', 'exit_time'] as $field) {
                        $val = $times[$field];
                        if ($val !== null && ($duplicateMode === 'replace' || empty($existing->$field))) {
                            $update[$field] = $val;
                        }
                    }

                    if (! empty($update)) {
                        $existing->update($update);
                        $this->attendanceService->recalculateLog($existing);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $log = AttendanceLog::create([
                        'company_id' => $companyId,
                        'employee_id' => $employeeId,
                        'log_date' => $logDate,
                        'entry_time' => $times['entry_time'],
                        'exit_time' => $times['exit_time'],
                        'is_manual' => false,
                        'description' => 'سینک مستقیم از سخت‌افزار دستگاه حضور و غیاب',
                    ]);

                    $this->attendanceService->recalculateLog($log);
                    $imported++;
                }
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'unknown_pins' => $unknownPins,
            'total_punches' => count($punches),
            'message' => "تعداد {$imported} رکورد تردد با موفقیت همگام‌سازی و بازیافت شد.",
        ];
    }

    /**
     * Handle incoming real-time ADMS / Cloud Server text stream.
     */
    public function handleAdmsPush(string $rawBody, AttendanceDevice $device): array
    {
        $lines = array_filter(explode("\n", str_replace("\r", '', $rawBody)));
        $punches = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\t+/', trim($line));
            if (count($parts) >= 2) {
                $pin = trim($parts[0]);
                $datetime = trim($parts[1]);
                $status = isset($parts[2]) ? (int) trim($parts[2]) : 0;

                $punches[] = [
                    'device_id' => $pin,
                    'timestamp' => $datetime,
                    'log_type' => ($status === 0) ? 0 : 1,
                ];
            }
        }

        return $this->processRawPunches($punches, $device->company_id);
    }
}
