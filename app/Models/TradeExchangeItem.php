<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeExchangeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_exchange_id',
        'direction',
        'product_id',
        'item_name',
        'quantity',
        'unit',
        'unit_price',
        'returned_quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'returned_quantity' => 'float',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(TradeExchange::class, 'trade_exchange_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectiveQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }

    public function getEffectiveValueAttribute(): float
    {
        return $this->effective_quantity * $this->unit_price;
    }
}
