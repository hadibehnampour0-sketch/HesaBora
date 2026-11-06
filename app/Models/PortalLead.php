<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'phone',
        'business_type',
        'status',
        'ip_address',
        'notes',
        'trial_license_key',
        'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'new' => 'درخواست جدید',
            'contacted' => 'تماس گرفته شد',
            'converted' => 'تبدیل به مشتری',
            'cancelled' => 'انصراف',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'new' => 'badge-info',
            'contacted' => 'badge-warning',
            'converted' => 'badge-success',
            'cancelled' => 'badge-ghost',
            default => 'badge-ghost',
        };
    }
}
