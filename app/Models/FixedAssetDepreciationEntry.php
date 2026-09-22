<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No FiscalYearScope of its own -- only ever reachable through its parent
 * FixedAsset (which IS scoped), the same shape as SettlementInstallment
 * under SettlementObligation and PaymentTermLine under PaymentTerm.
 */
class FixedAssetDepreciationEntry extends Model
{
    protected $fillable = [
        'fixed_asset_id',
        'period_date',
        'amount',
        'document_id',
    ];

    protected $casts = [
        'period_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
