<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class TaxReadinessLog extends Model
{
    protected $fillable = [
        'company_id',
        'score',
        'status',
        'metrics_breakdown',
        'recommendations',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'metrics_breakdown' => 'array',
        'recommendations' => 'array',
        'calculated_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $log) {
            $log->company_id ??= getActiveCompany();
        });
    }
}
