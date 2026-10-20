<?php

namespace App\Services\Hardware\DTO;

class BarcodeParsedResult
{
    public function __construct(
        public readonly string $rawBarcode,
        public readonly string $productCode,
        public readonly bool $isWeighted = false,
        public readonly float $weightInGrams = 0.0,
        public readonly ?float $embeddedPrice = null,
        public readonly float $quantity = 1.0
    ) {}

    public function getWeightInKilograms(): float
    {
        return $this->weightInGrams / 1000.0;
    }
}
