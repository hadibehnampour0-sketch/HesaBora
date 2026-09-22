<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Enums\NumberingLeaseStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * D-075 stage 2.7C-A -- a disjoint, server-authoritative range of official
 * numbers leased to one device for one (company, invoice_type) series.
 * See the migration docblock for the full reservation-vs-consumption
 * contract.
 */
class NumberingLease extends Model
{
    protected $fillable = [
        'company_id',
        'invoice_type',
        'origin_device_id',
        'range_start',
        'range_end',
        'next_number',
        'status',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'status' => NumberingLeaseStatus::class,
    ];

    public function originDevice()
    {
        return $this->belongsTo(DeviceInstallation::class, 'origin_device_id');
    }

    public function isExhausted(): bool
    {
        return $this->status === NumberingLeaseStatus::EXHAUSTED || $this->next_number > $this->range_end;
    }
}
