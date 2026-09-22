<?php

namespace App\Models;

use App\Enums\SerialNumberStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class ProductSerialNumber extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'serial_number',
        'status',
        'invoice_id',
    ];

    protected $casts = [
        'status' => SerialNumberStatus::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $serial) {
            $serial->company_id ??= getActiveCompany();
            $serial->status ??= SerialNumberStatus::IN_STOCK;
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
