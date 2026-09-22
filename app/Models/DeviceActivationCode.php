<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceActivationCode extends Model
{
    protected $fillable = [
        'code_hash',
        'company_id',
        'created_by',
        'expires_at',
        'used_at',
        'used_by_installation_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedByInstallation()
    {
        return $this->belongsTo(DeviceInstallation::class, 'used_by_installation_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
