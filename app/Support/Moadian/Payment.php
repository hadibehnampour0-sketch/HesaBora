<?php

namespace App\Support\Moadian;

use JsonSerializable;

/**
 * Electronic Invoice Settlement Payment Record (INTA Schema v2.x).
 */
class Payment implements JsonSerializable
{
    public ?int $pmt = null;

    public int|float|null $pv = null;

    public ?string $iinn = null;

    public ?string $acn = null;

    public ?string $trmn = null;

    public ?string $trn = null;

    public ?int $pdt = null;

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
