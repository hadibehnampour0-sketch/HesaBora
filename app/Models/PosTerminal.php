<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    use HasFactory;

    protected $table = 'pos_terminals';

    protected $fillable = [
        'company_id',
        'name',
        'psp',
        'connection_type',
        'ip_address',
        'port',
        'serial_port',
        'baud_rate',
        'terminal_id',
        'merchant_id',
        'bank_account_subject_id',
        'is_active',
        'is_default',
        'last_status',
        'last_ping_at',
        'metadata',
    ];

    protected $casts = [
        'port' => 'integer',
        'baud_rate' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_ping_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (PosTerminal $terminal) {
            $terminal->company_id ??= getActiveCompany();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccountSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'bank_account_subject_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PosTransaction::class, 'pos_terminal_id');
    }

    public function getPspLabelAttribute(): string
    {
        return match ($this->psp) {
            'behpardakht' => __('به‌پرداخت ملت'),
            'saman_kish' => __('سامان‌کیش (SEP)'),
            'sadad' => __('سداد بانک ملی'),
            'iran_kish' => __('ایران‌کیش'),
            'asan_pardakht' => __('آسان‌پرداخت (آپ)'),
            'pasargad' => __('فناپ / پاسارگاد'),
            'simulator' => __('شبیه‌ساز تستی کارتخوان'),
            default => $this->psp,
        };
    }

    public function getConnectionTypeLabelAttribute(): string
    {
        return match ($this->connection_type) {
            'lan' => __('شبکه محلی (LAN / IP)'),
            'serial' => __('کابل سریال / USB (RS-232)'),
            'bridge' => __('ایجنت سخت‌افزاری محلی (Hardware Bridge)'),
            default => $this->connection_type,
        };
    }

    public function isOnline(): bool
    {
        return $this->last_status === 'online' && $this->last_ping_at && $this->last_ping_at->gt(now()->subMinutes(10));
    }
}
