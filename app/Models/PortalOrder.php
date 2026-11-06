<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_reference',
        'plan',
        'customer_name',
        'phone',
        'company_name',
        'hardware_fingerprint',
        'extra_companies',
        'extra_users',
        'total_price',
        'status',
        'issued_license_key',
        'admin_notes',
        'payment_method',
        'payment_reference',
    ];

    protected $casts = [
        'extra_companies' => 'integer',
        'extra_users' => 'integer',
        'total_price' => 'integer',
    ];

    public function getPlanLabelAttribute(): string
    {
        return match ($this->plan) {
            'shop', 'basic' => 'پلن برنزی اصناف (۵ م.ت)',
            'commercial', 'professional' => 'پلن نقره‌ای تجاری (۱۵ م.ت)',
            'distribution' => 'پلن پخش مویرگی (۳۵ م.ت)',
            'manufacturing' => 'پلن تولیدی و بهای تمام‌شده (۵۰ م.ت)',
            'enterprise' => 'پلن سازمانی و هلدینگ (۸۰ م.ت)',
            default => $this->plan,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'در انتظار پرداخت و تایید',
            'paid' => 'پرداخت شده / صدور لایسنس',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'badge-warning',
            'paid' => 'badge-info',
            'completed' => 'badge-success',
            'cancelled' => 'badge-error',
            default => 'badge-ghost',
        };
    }
}
