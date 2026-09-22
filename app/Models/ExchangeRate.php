<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'company_id',
        'currency_id',
        'rate',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $rate) {
            $rate->company_id ??= getActiveCompany();
        });
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
