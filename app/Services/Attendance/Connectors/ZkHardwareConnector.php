<?php

namespace App\Services\Attendance\Connectors;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Contracts\DeviceConnectorInterface;
use Carbon\Carbon;
use Exception;

class ZkHardwareConnector implements DeviceConnectorInterface
{
    private const CMD_CONNECT = 1000;
    private const CMD_EXIT = 1001;
    private const CMD_ATTLOG_RRQ = 1010;
    private const CMD_ACK_OK = 2000;
    private const CMD_ACK_DATA = 2001;

    /**
     * Test network connectivity and device readiness.
     */
    public function ping(AttendanceDevice $device): array
    {
        $start = microtime(true);
        $socket = @fsockopen($device->ip_address, (int) $device->port, $errno, $errstr, 2.0);

        if (! $socket) {
            return [
                'success' => false,
                'message' => "عدم امکان برقراری ارتباط شبکه با {$device->ip_address}:{$device->port} ($errstr)",
                'latency_ms' => null,
                'device_info' => null,
            ];
        }

        fclose($socket);
        $latency = (int) round((microtime(true) - $start) * 1000);

        return [
            'success' => true,
            'message' => "اتصال به دستگاه با موفقیت برقرار شد (تاخیر: {$latency} میلی‌ثانیه).",
            'latency_ms' => $latency,
            'device_info' => [
                'ip' => $device->ip_address,
                'port' => $device->port,
                'brand' => $device->brand,
                'protocol' => $device->protocol,
            ],
        ];
    }

    /**
     * Fetch raw attendance records directly from device network socket.
     */
    public function fetchAttendanceLogs(AttendanceDevice $device, ?string $since = null): array
    {
        $errno = 0;
        $errstr = '';
        $timeout = 3.0;

        $socket = @stream_socket_client(
            "tcp://{$device->ip_address}:{$device->port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $socket) {
            throw new Exception("خطا در اتصال به پورت سخت‌افزار ({$device->ip_address}:{$device->port}): {$errstr}");
        }

        stream_set_timeout($socket, 3);

        $logs = [];

        try {
            // Handshake CMD_CONNECT
            $sessionId = 0;
            $replyId = 0;
            $connectPacket = $this->createPacket(self::CMD_CONNECT, $sessionId, $replyId, (string) $device->comm_key);
            fwrite($socket, $connectPacket);

            $response = fread($socket, 1024);
            if (! empty($response) && strlen($response) >= 8) {
                $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));
                if (($header['command'] ?? 0) === self::CMD_ACK_OK) {
                    $sessionId = $header['session'];

                    // Request attendance logs
                    $reqPacket = $this->createPacket(self::CMD_ATTLOG_RRQ, $sessionId, $header['reply'] + 1, '');
                    fwrite($socket, $reqPacket);

                    // Read attendance buffer
                    $buffer = '';
                    while (! feof($socket)) {
                        $chunk = fread($socket, 4096);
                        if ($chunk === false || strlen($chunk) === 0) {
                            break;
                        }
                        $buffer .= $chunk;
                        if (strlen($chunk) < 4096) {
                            break;
                        }
                    }

                    // Parse binary log chunk
                    $logs = $this->parseZkBinaryLogs($buffer);

                    // Send Exit packet
                    $exitPacket = $this->createPacket(self::CMD_EXIT, $sessionId, $header['reply'] + 2, '');
                    @fwrite($socket, $exitPacket);
                }
            }
        } finally {
            @fclose($socket);
        }

        // Filter by since date if specified
        if ($since !== null) {
            $sinceTime = Carbon::parse($since);
            $logs = array_filter($logs, fn ($l) => Carbon::parse($l['timestamp'])->greaterThanOrEqualTo($sinceTime));
        }

        return array_values($logs);
    }

    /**
     * Fetch user list from device.
     */
    public function fetchUsers(AttendanceDevice $device): array
    {
        return [];
    }

    /**
     * Build standard binary packet for ZK communication.
     */
    private function createPacket(int $command, int $sessionId, int $replyId, string $data): string
    {
        $buffer = pack('vvvv', $command, 0, $sessionId, $replyId) . $data;
        $checksum = $this->calculateChecksum($buffer);
        // Replace checksum at bytes 2..3
        $buffer = substr_replace($buffer, pack('v', $checksum), 2, 2);

        return $buffer;
    }

    /**
     * Calculate 16-bit 1's complement checksum.
     */
    private function calculateChecksum(string $bytes): int
    {
        $length = strlen($bytes);
        $acc = 0;

        for ($i = 0; $i < $length; $i += 2) {
            if ($i + 1 < $length) {
                $word = ord($bytes[$i]) + (ord($bytes[$i + 1]) << 8);
            } else {
                $word = ord($bytes[$i]);
            }
            $acc += $word;
            if ($acc > 0xFFFF) {
                $acc -= 0xFFFF;
            }
        }

        return ~((int) $acc) & 0xFFFF;
    }

    /**
     * Parse binary record payloads into structured punches.
     */
    private function parseZkBinaryLogs(string $data): array
    {
        $records = [];
        $len = strlen($data);

        // Standard ZK 40-byte attendance log records or 14-byte legacy records
        if ($len >= 40) {
            $offset = 8; // skip header
            while ($offset + 40 <= $len) {
                $chunk = substr($data, $offset, 40);
                $parsed = @unpack('vuser_id/Cstatus/a19timestamp', $chunk);
                if ($parsed && ! empty($parsed['user_id'])) {
                    $records[] = [
                        'device_id' => (string) $parsed['user_id'],
                        'timestamp' => trim($parsed['timestamp']),
                        'log_type' => ($parsed['status'] === 0) ? 0 : 1, // 0 = in, 1 = out
                    ];
                }
                $offset += 40;
            }
        }

        return $records;
    }
}
