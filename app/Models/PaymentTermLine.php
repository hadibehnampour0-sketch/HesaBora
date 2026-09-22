<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No FiscalYearScope of its own -- only ever reachable through its parent
 * PaymentTerm (which IS scoped), the same shape as SettlementInstallment
 * under SettlementObligation.
 */
class PaymentTermLine extends Model
{
    protected $fillable = [
        'payment_term_id',
        'days_offset',
        'percentage',
        'sort_order',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }
}
