<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'contract_number',
        'customer_id',
        'invoice_id',
        'plan_id',
        'total_price',
        'down_payment',
        'principal_amount',
        'total_interest',
        'total_amount',
        'tenor_months',
        'monthly_installment_amount',
        'start_date',
        'status',
        'interest_income_subject_id',
        'penalty_income_subject_id',
        'creator_id',
        'notes',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'down_payment' => 'integer',
        'principal_amount' => 'integer',
        'total_interest' => 'integer',
        'total_amount' => 'integer',
        'tenor_months' => 'integer',
        'monthly_installment_amount' => 'integer',
        'start_date' => 'date:Y-m-d',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinancingPlan::class, 'plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class, 'contract_id')->orderBy('installment_number');
    }
}
