<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Attendance\AttendanceDeviceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AttendanceDeviceApiController extends Controller
{
    public function __construct(
        protected AttendanceDeviceSyncService $syncService
    ) {}

    /**
     * Standard ZKTeco / Biometric ADMS & IClock Push Protocol Handler.
     */
    public function handleIclock(Request $request): Response|JsonResponse
    {
        $sn = $request->query('SN');
        $token = $request->query('token');

        // Security (D4): Reject immediately if neither token nor SN is provided
        if (empty($token) && empty($sn)) {
            return response('UNAUTHORIZED_DEVICE', 401)->header('Content-Type', 'text/plain');
        }

        // Locate device by push token or serial number
        $device = AttendanceDevice::withoutGlobalScopes()
            ->when($token, fn ($q) => $q->where('cloud_push_token', $token))
            ->when(! $token && $sn, fn ($q) => $q->where('serial_number', $sn))
            ->first();

        // Security (D4): Never fall back to arbitrary active device when token/SN missing
        if (! $device) {
            return response('UNAUTHORIZED_DEVICE', 401)->header('Content-Type', 'text/plain');
        }

        // GET: Device Handshake / Heartbeat
        if ($request->isMethod('GET')) {
            $device->update([
                'last_status' => 'online',
                'last_status_message' => 'دستگاه به سرور متصل است (ارتباط زنده ADMS).',
            ]);

            return response("OK\nServerVersion=3.0.1\nServerName=Dakhl-Attendance-Server\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        // POST: Incoming Attendance Punch Records
        $rawBody = (string) $request->getContent();
        if (empty($rawBody)) {
            return response('OK: 0', 200)->header('Content-Type', 'text/plain');
        }

        $result = $this->syncService->handleAdmsPush($rawBody, $device);

        $device->update([
            'last_sync_at' => now(),
            'last_status' => 'synced',
            'last_status_message' => "دریافت خودکار از دستگاه (ADMS): {$result['imported']} رکورد ثبت شد.",
        ]);

        return response("OK: {$result['imported']}", 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * REST JSON Push endpoint for smart terminals or sync agents.
     */
    public function pushPunches(Request $request, AttendanceDevice $device): JsonResponse
    {
        // Authenticate device via push token (D4)
        $token = $request->header('X-Device-Token') ?? $request->input('token') ?? $request->bearerToken();
        if (empty($device->cloud_push_token) || ! $token || ! hash_equals($device->cloud_push_token, $token)) {
            return response()->json(['success' => false, 'message' => 'دستگاه احراز هویت نشد.'], 401);
        }

        $punches = $request->input('punches', []);
        if (! is_array($punches)) {
            return response()->json(['success' => false, 'message' => 'فرمت داده‌ها نامعتبر است.'], 422);
        }

        $result = $this->syncService->processRawPunches($punches, $device->company_id);

        $device->update([
            'last_sync_at' => now(),
            'last_status' => 'synced',
            'last_status_message' => "دریافت از طریق API: {$result['imported']} رکورد ثبت شد.",
        ]);

        return response()->json(array_merge(['success' => true], $result));
    }
}
