<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class ProductLot extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'lot_number',
        'quantity',
        'received_date',
        'expiry_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $lot) {
            $lot->company_id ??= getActiveCompany();
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
