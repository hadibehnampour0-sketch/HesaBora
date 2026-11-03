<?php

declare(strict_types=1);

namespace Dakhl\Core\Support;

final class Uuid
{
    /**
     * Generate a 16-byte binary UUID (UUIDv7-compatible or random 16-byte blob).
     */
    public static function binary16(?string $customUuid = null): string
    {
        if ($customUuid !== null) {
            $cleaned = str_replace('-', '', $customUuid);
            if (strlen($cleaned) === 32 && ctype_xdigit($cleaned)) {
                return (string) hex2bin($cleaned);
            }
            if (strlen($customUuid) === 16) {
                return $customUuid;
            }
        }

        // Generate 16 bytes: 48-bit millisecond timestamp + 12 bits version 7 + 62 bits random
        $timeMs = (int) (microtime(true) * 1000);
        $timeBytes = pack('J', $timeMs); // 8 bytes big-endian
        $timePart = substr($timeBytes, 2); // 6 bytes

        $random = random_bytes(10);
        // set version 7 (0111) in byte 6
        $random[0] = chr((ord($random[0]) & 0x0f) | 0x70);
        // set variant 1 (10xx) in byte 8
        $random[2] = chr((ord($random[2]) & 0x3f) | 0x80);

        return $timePart . $random;
    }

    /**
     * Generate standard RFC 9562 UUIDv7 string.
     */
    public static function v7(): string
    {
        $bin = self::binary16();
        $hex = bin2hex($bin);
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
