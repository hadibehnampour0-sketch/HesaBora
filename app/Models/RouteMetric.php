<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteMetric extends Model
{
    protected $fillable = [
        'company_id',
        'route_id',
        'period_start',
        'period_end',
        'visit_completion_rate',
        'average_invoice_value',
        'cash_collection_ratio',
        'calculated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'visit_completion_rate' => 'decimal:2',
        'average_invoice_value' => 'decimal:2',
        'cash_collection_ratio' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $metric) {
            $metric->company_id ??= getActiveCompany();
        });
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }
}
