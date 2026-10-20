<?php

namespace App\Services\Inquiry\Validators;

class IranianNationalCodeValidator
{
    /**
     * Validates an Iranian National Code (کد ملی ۱۰ رقمی) using standard Modulo 11 checksum.
     */
    public static function validate(?string $code): bool
    {
        if (empty($code)) {
            return false;
        }

        $code = trim($code);

        // Must be exactly 10 numeric digits
        if (! preg_match('/^\d{10}$/', $code)) {
            return false;
        }

        // Rejection of known repetitive numbers like 0000000000, 1111111111, ..., 9999999999
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $checkDigit = (int) $code[9];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $code[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;

        if ($remainder < 2) {
            return $checkDigit === $remainder;
        }

        return $checkDigit === (11 - $remainder);
    }
}
