<?php

namespace App\Support\Moadian;

/**
 * Clean-Room Moadian Invoice Item (Body) DTO.
 *
 * Implements the official INTA electronic invoice body specification.
 *
 * License: MIT
 */
class InvoiceItem
{
    public ?string $sstid = null;

    public ?string $sstt = null;

    public float|int|null $am = null;

    public ?string $mu = null;

    public float|int|null $fee = null;

    public float|int|null $cfee = null;

    public ?string $cut = null;

    public float|int|null $exr = null;

    public float|int|null $prdis = null;

    public float|int|null $dis = null;

    public float|int|null $adis = null;

    public float|int|null $vra = null;

    public float|int|null $vam = null;

    public ?string $odt = null;

    public float|int|null $odr = null;

    public float|int|null $odam = null;

    public ?string $olt = null;

    public float|int|null $olr = null;

    public float|int|null $olam = null;

    public float|int|null $consfee = null;

    public float|int|null $spro = null;

    public float|int|null $bros = null;

    public float|int|null $tcpbs = null;

    public float|int|null $cop = null;

    public float|int|null $vop = null;

    public ?string $bsrn = null;

    public float|int|null $tsstam = null;

    public ?int $nw = null;

    public float|int|null $ssrv = null;

    public float|int|null $sscv = null;

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
