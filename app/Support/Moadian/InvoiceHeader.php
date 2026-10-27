<?php

namespace App\Support\Moadian;

use DateTimeInterface;
use JsonSerializable;

/**
 * Electronic Invoice Header Data Transfer Object (INTA Schema v2.x).
 */
class InvoiceHeader implements JsonSerializable
{
    public ?string $taxid = null;

    public ?int $ins = null;

    public ?int $inty = null;

    public ?string $irtaxid = null;

    public ?int $indatim = null;

    public ?int $indati2m = null;

    public ?int $inp = null;

    public ?string $tins = null;

    public ?int $tob = null;

    public ?string $bid = null;

    public ?string $tinb = null;

    public ?string $bpc = null;

    public int|float|null $tprdis = null;

    public int|float|null $tdis = null;

    public int|float|null $tadis = null;

    public int|float|null $tvam = null;

    public int|float|null $todam = null;

    public int|float|null $tbill = null;

    public ?int $setm = null;

    public int|float|null $cap = null;

    public function __construct(public string $username = '') {}

    /**
     * Compute and assign the 22-character unique tax ID to the header.
     */
    public function setTaxID(DateTimeInterface|string $date, int|string $serial): self
    {
        $this->taxid = TaxIdGenerator::generate($this->username, $date, $serial);

        return $this;
    }

    /**
     * Export header properties to array, omitting null values.
     */
    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $val) {
            if ($key !== 'username' && $val !== null) {
                $data[$key] = $val;
            }
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
