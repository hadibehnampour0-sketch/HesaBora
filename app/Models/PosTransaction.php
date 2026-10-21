<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransaction extends Model
{
    use HasFactory;

    protected $table = 'pos_transactions';

    protected $fillable = [
        'company_id',
        'pos_terminal_id',
        'invoice_id',
        'payment_id',
        'user_id',
        'trace_number',
        'amount',
        'status',
        'rrn',
        'terminal_trace_no',
        'card_number_masked',
        'card_holder_name',
        'response_code',
        'response_message',
        'raw_payload',
        'raw_response',
        'transaction_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'raw_payload' => 'array',
        'raw_response' => 'array',
        'transaction_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (PosTransaction $tx) {
            $tx->company_id ??= getActiveCompany();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success' && $this->response_code === '00';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => __('موفق'),
            'pending' => __('در انتظار کشیدن کارت'),
            'failed' => __('ناموفق / خطا'),
            'cancelled' => __('انصراف مشتری'),
            'timeout' => __('پایان زمان / تایم‌اوت'),
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'success' => 'badge-success text-white',
            'pending' => 'badge-warning',
            'failed' => 'badge-error text-white',
            'cancelled' => 'badge-ghost',
            'timeout' => 'badge-neutral',
            default => 'badge-outline',
        };
    }
}
