<?php

namespace App\Services\Hardware;

use App\Services\Hardware\DTO\BarcodeParsedResult;

class BarcodeScannerParser
{
    /**
     * Parse raw barcode input from scanner.
     */
    public function parse(string $rawBarcode): BarcodeParsedResult
    {
        $clean = trim($rawBarcode);

        // Check for Iranian supermarket/deli scale barcode format (13 digits starting with 20 or 21)
        if (strlen($clean) === 13 && (str_starts_with($clean, '20') || str_starts_with($clean, '21')) && ctype_digit($clean)) {
            $prefix = substr($clean, 0, 2);
            $productCode = ltrim(substr($clean, 2, 5), '0');
            $variableDigits = (float) substr($clean, 7, 5);

            if ($prefix === '20') {
                // Prefix 20: Embedded weight in grams
                $weightGrams = $variableDigits;
                $weightKg = $weightGrams / 1000.0;

                return new BarcodeParsedResult(
                    rawBarcode: $clean,
                    productCode: $productCode ?: substr($clean, 2, 5),
                    isWeighted: true,
                    weightInGrams: $weightGrams,
                    embeddedPrice: null,
                    quantity: $weightKg > 0 ? $weightKg : 1.0
                );
            }

            if ($prefix === '21') {
                // Prefix 21: Embedded price (in thousands of Rials or Tomans)
                return new BarcodeParsedResult(
                    rawBarcode: $clean,
                    productCode: $productCode ?: substr($clean, 2, 5),
                    isWeighted: false,
                    weightInGrams: 0.0,
                    embeddedPrice: $variableDigits * 1000,
                    quantity: 1.0
                );
            }
        }

        // Standard piece item barcode
        return new BarcodeParsedResult(
            rawBarcode: $clean,
            productCode: $clean,
            isWeighted: false,
            weightInGrams: 0.0,
            embeddedPrice: null,
            quantity: 1.0
        );
    }
}
