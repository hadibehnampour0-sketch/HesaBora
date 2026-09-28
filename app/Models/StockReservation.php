<?php

namespace App\Models;

use App\Enums\StockReservationStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'source_type',
        'source_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'status' => StockReservationStatus::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $reservation) {
            $reservation->company_id ??= getActiveCompany();
            $reservation->status ??= StockReservationStatus::ACTIVE;
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

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
