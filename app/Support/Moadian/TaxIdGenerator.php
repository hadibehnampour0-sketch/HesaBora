<?php

namespace App\Support\Moadian;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Clean-room unique tax identifier generator for the Iranian Tax Authority (INTA).
 * Generates official 22-character Tax IDs conforming to statutory e-invoicing standards.
 */
class TaxIdGenerator
{
    /**
     * Generate an official 22-character unique Tax ID.
     *
     * @param  string  $memoryId  6-character unique fiscal memory ID
     * @param  DateTimeInterface|string  $date  Invoice issuance date
     * @param  int|string  $serial  Sequential invoice number
     */
    public static function generate(string $memoryId, DateTimeInterface|string $date, int|string $serial): string
    {
        $cleanMemoryId = strtoupper(trim($memoryId));
        if (strlen($cleanMemoryId) !== 6) {
            $cleanMemoryId = str_pad(substr($cleanMemoryId, 0, 6), 6, '0', STR_PAD_RIGHT);
        }

        $invoiceDate = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        $daysPastEpoch = (int) floor($invoiceDate->getTimestamp() / 86400);
        $hexDays = strtoupper(str_pad(dechex($daysPastEpoch), 5, '0', STR_PAD_LEFT));

        $hexSerial = strtoupper(str_pad(dechex((int) $serial), 10, '0', STR_PAD_LEFT));

        $body21 = $cleanMemoryId.$hexDays.$hexSerial;
        $checkDigit = Verhoeff::checkDigit($body21);

        return $body21.$checkDigit;
    }
}
