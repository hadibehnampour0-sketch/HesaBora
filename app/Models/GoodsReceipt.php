<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A purely informational confirmation log ("goods for invoice X physically
 * arrived on date Y") -- NOT an authoritative stock-moving document. Actual
 * stock was already added when the BUY invoice was approved
 * (ProductService::addProductsQuantities(), unchanged by this).
 */
class GoodsReceipt extends Model
{
    protected $fillable = [
        'company_id',
        'invoice_id',
        'received_date',
        'reference',
        'note',
        'received_by',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $receipt) {
            $receipt->company_id ??= getActiveCompany();
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
