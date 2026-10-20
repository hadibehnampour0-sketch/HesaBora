<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'calculation_method',
        'monthly_interest_rate',
        'annual_interest_rate',
        'default_tenor_months',
        'late_fee_rate_percent',
        'grace_period_days',
        'is_active',
    ];

    protected $casts = [
        'monthly_interest_rate' => 'float',
        'annual_interest_rate' => 'float',
        'default_tenor_months' => 'integer',
        'late_fee_rate_percent' => 'float',
        'grace_period_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(InstallmentContract::class, 'plan_id');
    }
}
