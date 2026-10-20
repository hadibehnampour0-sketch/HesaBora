<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'balance',
        'points',
        'tier',
    ];

    protected $casts = [
        'balance' => 'integer',
        'points' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class, 'wallet_id');
    }

    /**
     * Determine and update tier based on current points and loyalty settings.
     */
    public function recalculateTier(LoyaltySetting $settings): string
    {
        $points = $this->points;

        $tier = match (true) {
            $points >= $settings->vip_points_threshold => 'VIP',
            $points >= $settings->gold_points_threshold => 'GOLD',
            $points >= $settings->silver_points_threshold => 'SILVER',
            default => 'BRONZE',
        };

        if ($this->tier !== $tier) {
            $this->update(['tier' => $tier]);
        }

        return $tier;
    }
}
