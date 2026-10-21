<?php

namespace App\Services\Attendance\Contracts;

use App\Models\AttendanceDevice;

interface DeviceConnectorInterface
{
    /**
     * Check network connectivity and device responsiveness.
     *
     * @return array{success: bool, message: string, latency_ms: ?int, device_info: ?array}
     */
    public function ping(AttendanceDevice $device): array;

    /**
     * Fetch attendance punch logs from the device.
     *
     * @param  AttendanceDevice  $device
     * @param  string|null  $since  ISO date/time or date string
     * @return array<int, array{device_id: string, timestamp: string, log_type: int}>
     */
    public function fetchAttendanceLogs(AttendanceDevice $device, ?string $since = null): array;

    /**
     * Fetch registered user PINs / names from the device.
     *
     * @param  AttendanceDevice  $device
     * @return array<int, array{device_id: string, name: string}>
     */
    public function fetchUsers(AttendanceDevice $device): array;
}
