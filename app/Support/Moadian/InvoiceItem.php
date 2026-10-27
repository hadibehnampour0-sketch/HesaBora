<?php

namespace App\Support\Moadian;

use JsonSerializable;

/**
 * Electronic Invoice Line Item Data Transfer Object (INTA Schema v2.x).
 */
class InvoiceItem implements JsonSerializable
{
    public ?string $sstid = null;

    public ?string $sstt = null;

    public int|float|null $am = null;

    public int|float|null $fee = null;

    public int|float|null $prdis = null;

    public int|float|null $dis = null;

    public int|float|null $adis = null;

    public int|float|null $vra = null;

    public int|float|null $vam = null;

    public int|float|null $tsstam = null;

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
