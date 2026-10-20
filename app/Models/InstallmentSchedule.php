<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'company_id',
        'installment_number',
        'due_date',
        'principal_portion',
        'interest_portion',
        'amount',
        'paid_amount',
        'late_fee_amount',
        'paid_at',
        'status',
        'sayad_cheque_number',
        'document_id',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date:Y-m-d',
        'principal_portion' => 'integer',
        'interest_portion' => 'integer',
        'amount' => 'integer',
        'paid_amount' => 'integer',
        'late_fee_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class, 'contract_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function remainingAmount(): int
    {
        return max(0, ($this->amount + $this->late_fee_amount) - $this->paid_amount);
    }
}
