<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $table = 'attendance_devices';

    protected $fillable = [
        'company_id',
        'name',
        'brand',
        'protocol',
        'ip_address',
        'port',
        'comm_key',
        'serial_number',
        'location',
        'is_active',
        'auto_sync',
        'cloud_push_token',
        'last_sync_at',
        'last_status',
        'last_status_message',
        'last_log_count',
        'metadata',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_log_count' => 'integer',
        'metadata' => 'array',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (AttendanceDevice $device) {
            if (empty($device->cloud_push_token)) {
                $device->cloud_push_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Return full URL for device's ADMS / Cloud Server configuration.
     */
    public function getPushEndpointUrlAttribute(): string
    {
        return url("/api/attendance/iclock?token={$this->cloud_push_token}");
    }

    /**
     * Persian brand display name.
     */
    public function getBrandLabelAttribute(): string
    {
        return match ($this->brand) {
            'zkteco' => 'زد‌کی‌تکو (ZKTeco)',
            'kara2000' => 'کارا ۲۰۰۰ (Kara2000)',
            'palizafzar' => 'پالیز افزار (Palizafzar)',
            'virdi' => 'ویردی (Virdi)',
            'suprema' => 'سوپریما (Suprema)',
            'hikvision' => 'هایک ویژن (Hikvision)',
            default => 'استاندارد TCP/IP',
        };
    }

    /**
     * Status label in Persian.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->last_status) {
            'online' => 'متصل و آنلاین',
            'offline' => 'آفلاین / خارج از دسترس',
            'synced' => 'همگام‌سازی شده',
            'error' => 'خطای ارتباطی',
            default => 'در انتظار اتصال',
        };
    }

    /**
     * Badge CSS class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->last_status) {
            'online' => 'badge-success text-white font-bold',
            'synced' => 'badge-primary text-white font-bold',
            'offline' => 'badge-error text-white font-bold',
            'error' => 'badge-warning text-slate-900 font-bold',
            default => 'badge-ghost text-base-content/60',
        };
    }
}
