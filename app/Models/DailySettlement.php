<?php

namespace App\Models;

use App\Enums\DailySettlementStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySettlement extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'settlement_date',
        'total_invoiced_amount',
        'cash_collected',
        'pos_collected',
        'cheque_collected',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_invoiced_amount' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'pos_collected' => 'decimal:2',
        'cheque_collected' => 'decimal:2',
        'status' => DailySettlementStatus::class,
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $settlement) {
            $settlement->company_id ??= getActiveCompany();
            $settlement->status ??= DailySettlementStatus::DRAFT;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Cash + POS + cheque collected minus what was invoiced -- positive
     * means more was collected than invoiced (e.g. prior debt collected
     * today), negative means a shortfall the reconciling cashier must
     * account for.
     */
    public function getVarianceAttribute(): float
    {
        return round(
            (float) $this->cash_collected + (float) $this->pos_collected + (float) $this->cheque_collected
                - (float) $this->total_invoiced_amount,
            2
        );
    }
}
