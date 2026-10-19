<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'company_id',
        'invoice_id',
        'customer_id',
        'token',
        'amount',
        'gateway',
        'gateway_token',
        'gateway_reference',
        'card_pan',
        'status',
        'expires_at',
        'paid_at',
        'payment_id',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (PaymentLink $link) {
            $link->company_id ??= getActiveCompany();
            $link->uuid ??= (string) Str::uuid();
            $link->token ??= Str::random(64);
            $link->status ??= self::STATUS_PENDING;
            $link->expires_at ??= Carbon::now()->addDays(7);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isExpired(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('portal.invoice.show', ['token' => $this->token]);
    }

    public function getPaymentUrlAttribute(): string
    {
        return route('portal.invoice.pay', ['token' => $this->token]);
    }
}
