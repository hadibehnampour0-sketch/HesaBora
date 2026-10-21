<?php

namespace App\Services\Attendance\Connectors;

use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Services\Attendance\Contracts\DeviceConnectorInterface;
use Carbon\Carbon;

class MockHardwareConnector implements DeviceConnectorInterface
{
    /**
     * Test connection simulation.
     */
    public function ping(AttendanceDevice $device): array
    {
        return [
            'success' => true,
            'message' => "شبیه‌ساز سخت‌افزار: ارتباط با دستگاه «{$device->name}» در وضعیت ایده‌آل است.",
            'latency_ms' => 8,
            'device_info' => [
                'model' => 'ZKTeco uFace 800 (Simulated)',
                'firmware' => 'Ver 8.0.4.4_2026',
                'user_count' => 45,
                'log_count' => 1250,
            ],
        ];
    }

    /**
     * Generate realistic punch logs for testing/demo.
     */
    public function fetchAttendanceLogs(AttendanceDevice $device, ?string $since = null): array
    {
        $employees = Employee::where('company_id', $device->company_id)
            ->whereNotNull('device_id')
            ->get();

        if ($employees->isEmpty()) {
            // Fallback mock PINs if no employee has device_id yet
            $pins = ['101', '102', '103'];
        } else {
            $pins = $employees->pluck('device_id')->take(5)->toArray();
        }

        $logs = [];
        $targetDate = now()->toDateString();

        foreach ($pins as $idx => $pin) {
            // Check-in punch (entry around 07:50 - 08:15)
            $entryMinute = 50 + ($idx * 5);
            $entryHour = ($entryMinute >= 60) ? 8 : 7;
            $entryMinute = $entryMinute % 60;
            $entryTime = sprintf('%02d:%02d:12', $entryHour, $entryMinute);

            $logs[] = [
                'device_id' => (string) $pin,
                'timestamp' => "{$targetDate} {$entryTime}",
                'log_type' => 0, // In
            ];

            // Check-out punch (exit around 16:45 - 17:30)
            $exitMinute = 45 + ($idx * 10);
            $exitHour = 16 + (int) floor($exitMinute / 60);
            $exitMinute = $exitMinute % 60;
            $exitTime = sprintf('%02d:%02d:45', $exitHour, $exitMinute);

            $logs[] = [
                'device_id' => (string) $pin,
                'timestamp' => "{$targetDate} {$exitTime}",
                'log_type' => 1, // Out
            ];
        }

        return $logs;
    }

    /**
     * Fetch user list simulation.
     */
    public function fetchUsers(AttendanceDevice $device): array
    {
        return [
            ['device_id' => '101', 'name' => 'کاربر آزمایشی ۱'],
            ['device_id' => '102', 'name' => 'کاربر آزمایشی ۲'],
        ];
    }
}
