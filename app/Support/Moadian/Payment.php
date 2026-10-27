<?php

namespace App\Support\Moadian;

/**
 * Clean-Room Moadian Invoice Payment DTO.
 *
 * Implements the official INTA electronic invoice settlement / payment specification.
 *
 * License: MIT
 */
class Payment
{
    public ?string $iinn = null;

    public ?string $acn = null;

    public ?string $trmn = null;

    public ?string $trn = null;

    public ?int $pdt = null;

    public ?int $pmt = null;

    public float|int|null $pv = null;

    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if (! is_null($value)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
