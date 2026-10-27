<?php

namespace App\Support\Moadian;

use DateTimeInterface;

/**
 * Clean-Room Iranian Tax Administration (INTA) Unique Tax ID (شناسه یکتای صورتحساب مالیاتی) Generator.
 *
 * Generates the official 22-character unique tax ID as specified in the INTA electronic invoice standard:
 *   [Memory ID (6 chars)] + [Hex Date (5 chars)] + [Hex Serial (10 chars)] + [Verhoeff Checksum (1 char)]
 *
 * License: MIT
 */
class TaxIdGenerator
{
    private const CHAR_TO_NUM = [
        'A' => 65, 'B' => 66, 'C' => 67, 'D' => 68, 'E' => 69, 'F' => 70, 'G' => 71, 'H' => 72, 'I' => 73,
        'J' => 74, 'K' => 75, 'L' => 76, 'M' => 77, 'N' => 78, 'O' => 79, 'P' => 80, 'Q' => 81, 'R' => 82,
        'S' => 83, 'T' => 84, 'U' => 85, 'V' => 86, 'W' => 87, 'X' => 88, 'Y' => 89, 'Z' => 90,
    ];

    public static function generate(string $clientId, DateTimeInterface $date, int $serialNumber): string
    {
        $clientId = trim($clientId);
        $daysPastEpoch = (int) floor($date->getTimestamp() / 86400);

        $daysPastEpochPadded = str_pad((string) $daysPastEpoch, 6, '0', STR_PAD_LEFT);
        $hexDaysPastEpochPadded = str_pad(dechex($daysPastEpoch), 5, '0', STR_PAD_LEFT);

        $numericClientId = self::clientIdToDigits($clientId);
        $serialPadded = str_pad((string) $serialNumber, 12, '0', STR_PAD_LEFT);
        $hexSerialPadded = str_pad(dechex($serialNumber), 10, '0', STR_PAD_LEFT);

        $decimalString = $numericClientId.$daysPastEpochPadded.$serialPadded;
        $checksum = Verhoeff::checkSum($decimalString);

        return strtoupper($clientId.$hexDaysPastEpochPadded.$hexSerialPadded.$checksum);
    }

    public static function clientIdToDigits(string $clientId): string
    {
        $digits = '';
        $upper = strtoupper($clientId);
        $len = strlen($upper);

        for ($i = 0; $i < $len; $i++) {
            $char = $upper[$i];
            if (is_numeric($char)) {
                $digits .= $char;
            } elseif (isset(self::CHAR_TO_NUM[$char])) {
                $digits .= self::CHAR_TO_NUM[$char];
            } else {
                $digits .= ord($char);
            }
        }

        return $digits;
    }
}
