<?php

namespace App\Support\Moadian;

use DateTimeInterface;

/**
 * Clean-Room Moadian Invoice Header DTO.
 *
 * Implements the official INTA electronic invoice header specification.
 *
 * License: MIT
 */
class InvoiceHeader
{
    public ?string $clientId = null;

    public ?string $taxid = null;

    public ?int $indatim = null;

    public ?int $indati2m = null;

    public ?int $inty = null;

    public ?string $inno = null;

    public ?string $irtaxid = null;

    public ?int $inp = null;

    public ?int $ins = null;

    public ?string $tins = null;

    public ?int $tob = null;

    public ?string $bid = null;

    public ?string $tinb = null;

    public ?string $bpc = null;

    public ?string $bbc = null;

    public ?int $ft = null;

    public ?string $bpn = null;

    public ?string $scc = null;

    public ?string $crn = null;

    public ?string $billid = null;

    public float|int|null $tprdis = null;

    public float|int|null $tdis = null;

    public float|int|null $tadis = null;

    public float|int|null $tvam = null;

    public float|int|null $todam = null;

    public float|int|null $tbill = null;

    public ?int $setm = null;

    public float|int|null $cap = null;

    public float|int|null $insp = null;

    public float|int|null $tvop = null;

    public float|int|null $tax17 = null;

    public ?string $cdcn = null;

    public ?int $cdcd = null;

    public ?int $tonw = null;

    public ?int $torv = null;

    public ?int $tocv = null;

    public function __construct(?string $clientId = null)
    {
        $this->clientId = $clientId;
    }

    public function setTaxID(DateTimeInterface $date, int $internalInvoiceId): string
    {
        $this->taxid = TaxIdGenerator::generate($this->clientId ?? '', $date, $internalInvoiceId);

        return $this->taxid;
    }

    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'clientId' || is_null($value)) {
                continue;
            }
            $data[$key] = $value;
        }

        return $data;
    }
}
