<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'wallet_id',
        'customer_id',
        'type',
        'amount',
        'points_change',
        'balance_after',
        'points_after',
        'reference_id',
        'reference_type',
        'description',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'points_change' => 'integer',
        'balance_after' => 'integer',
        'points_after' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
