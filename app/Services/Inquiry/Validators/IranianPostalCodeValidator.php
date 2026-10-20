<?php

namespace App\Services\Inquiry\Validators;

class IranianPostalCodeValidator
{
    /**
     * Validates an Iranian 10-digit postal code.
     * Rule: 10 digits; neither the 1st nor the 5th digit can be '0' or '2'.
     */
    public static function validate(?string $postalCode): bool
    {
        if (empty($postalCode)) {
            return false;
        }

        $clean = preg_replace('/\D/', '', $postalCode);

        if (strlen($clean) !== 10) {
            return false;
        }

        if (in_array($clean[0], ['0', '2'], true)) {
            return false;
        }

        if (in_array($clean[4], ['0', '2'], true)) {
            return false;
        }

        return true;
    }
}
