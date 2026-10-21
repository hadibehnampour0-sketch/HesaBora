<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    use HasFactory;

    protected $table = 'licenses';

    protected $fillable = [
        'license_key',
        'license_hash',
        'edition',
        'customer_name',
        'customer_national_id',
        'hardware_fingerprint',
        'max_companies',
        'max_users',
        'max_devices',
        'modules',
        'issued_at',
        'expires_at',
        'grace_period_days',
        'is_active',
        'signature',
        'company_id',
        'metadata',
    ];

    protected $casts = [
        'modules' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'max_companies' => 'integer',
        'max_users' => 'integer',
        'max_devices' => 'integer',
        'grace_period_days' => 'integer',
    ];

    /**
     * Optional company scoping if license is company-specific.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if the license is perpetual / lifetime.
     */
    public function isLifetime(): bool
    {
        return is_null($this->expires_at);
    }

    /**
     * Check if the license has passed its expiry date.
     */
    public function isExpired(): bool
    {
        if ($this->isLifetime()) {
            return false;
        }

        return now()->greaterThan($this->expires_at);
    }

    /**
     * Check if the license is expired but currently in the grace period.
     */
    public function isInGracePeriod(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        $graceDeadline = $this->expires_at->copy()->addDays($this->grace_period_days);

        return now()->lessThanOrEqualTo($graceDeadline);
    }

    /**
     * Check if the grace period has also expired.
     */
    public function isPastGracePeriod(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        $graceDeadline = $this->expires_at->copy()->addDays($this->grace_period_days);

        return now()->greaterThan($graceDeadline);
    }

    /**
     * Whether the license is operationally valid (active and not past grace period).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! $this->isPastGracePeriod();
    }

    /**
     * Returns remaining days until expiration, or null for lifetime.
     */
    public function getDaysRemaining(): ?int
    {
        if ($this->isLifetime()) {
            return null;
        }

        if ($this->isExpired()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($this->expires_at, false) / 86400);
    }

    /**
     * Check if a specific module is entitled under this license.
     */
    public function hasModule(string $moduleKey): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $modules = $this->modules ?? [];

        if (in_array('*', $modules, true)) {
            return true;
        }

        return in_array($moduleKey, $modules, true);
    }

    /**
     * Persian human-readable status.
     */
    public function getStatusLabel(): string
    {
        if (! $this->is_active) {
            return 'غیرفعال شده';
        }

        if ($this->isLifetime()) {
            return 'فعال (دائمی و مادام‌العمر)';
        }

        if ($this->isInGracePeriod()) {
            return 'مهلت تمدید (دوره مهلت)';
        }

        if ($this->isPastGracePeriod()) {
            return 'منقضی شده';
        }

        return 'فعال و معتبر';
    }

    /**
     * CSS badge class for status display.
     */
    public function getStatusBadgeClass(): string
    {
        if (! $this->is_active) {
            return 'badge-ghost text-base-content/60';
        }

        if ($this->isLifetime()) {
            return 'badge-success text-white font-bold';
        }

        if ($this->isInGracePeriod()) {
            return 'badge-warning text-slate-900 font-bold';
        }

        if ($this->isPastGracePeriod()) {
            return 'badge-error text-white font-bold';
        }

        return 'badge-success text-white font-bold';
    }

    /**
     * Persian title for license edition.
     */
    public function getEditionLabel(): string
    {
        return match ($this->edition) {
            'trial' => 'نسخه آزمایشی ۱۴ روزه (Trial)',
            'basic' => 'نسخه برنزی (پایه اصناف خرد)',
            'professional' => 'نسخه نقره‌ای (حرفه‌ای / رشد کسب‌وکار)',
            'enterprise' => 'نسخه طلایی (سازمانی / نامحدود)',
            'custom' => 'نسخه سفارشی (ماژولار انتخابی)',
            default => 'نامشخص',
        };
    }
}
