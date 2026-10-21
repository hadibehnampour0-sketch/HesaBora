<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'symbol',
        'title_fa',
        'title_en',
        'category',
        'price',
        'previous_price',
        'change_amount',
        'change_percent',
        'high_price',
        'low_price',
        'source',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'previous_price' => 'decimal:4',
        'change_amount' => 'decimal:4',
        'change_percent' => 'decimal:2',
        'high_price' => 'decimal:4',
        'low_price' => 'decimal:4',
    ];

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
