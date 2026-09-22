<?php

namespace App\Models;

use App\Enums\VisitPlanStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitPlan extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'route_id',
        'plan_date',
        'status',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'status' => VisitPlanStatus::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $plan) {
            $plan->company_id ??= getActiveCompany();
            $plan->status ??= VisitPlanStatus::PLANNED;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }
}
