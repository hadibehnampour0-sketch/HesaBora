<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMarketPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'company_id',
        'market_price',
        'market_source_type',
        'market_source_url',
        'is_wholesale_broadcast',
        'wholesale_price',
        'wholesale_min_qty',
        'wholesale_note',
        'guild_category',
        'last_checked_at',
        'updated_by_user_id',
    ];

    protected $casts = [
        'market_price' => 'decimal:4',
        'wholesale_price' => 'decimal:4',
        'wholesale_min_qty' => 'decimal:2',
        'is_wholesale_broadcast' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
