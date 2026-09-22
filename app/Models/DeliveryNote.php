<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A purely informational confirmation log ("goods for invoice X physically
 * dispatched on date Y") -- NOT an authoritative stock-moving document.
 * Actual stock was already removed when the SELL invoice was approved
 * (ProductService::subProductsQuantities(), unchanged by this).
 */
class DeliveryNote extends Model
{
    protected $fillable = [
        'company_id',
        'invoice_id',
        'delivered_date',
        'reference',
        'note',
        'delivered_by',
    ];

    protected $casts = [
        'delivered_date' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $note) {
            $note->company_id ??= getActiveCompany();
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
