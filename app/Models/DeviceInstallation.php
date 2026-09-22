<?php

namespace App\Models;

use App\Enums\DeviceInstallationStatus;
use Illuminate\Database\Eloquent\Model;

class DeviceInstallation extends Model
{
    protected $fillable = [
        'installation_uuid',
        'device_key_hash',
        'name',
        'company_id',
        'paired_by_user_id',
        'status',
        'paired_at',
        'last_seen_at',
    ];

    protected $casts = [
        'status' => DeviceInstallationStatus::class,
        'paired_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'device_key_hash',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * D-076: the user this device acts as when a sync operation (e.g.
     * Approve) needs a real acting user for attribution -- not a login,
     * just a durable copy of who paired this device.
     */
    public function pairedByUser()
    {
        return $this->belongsTo(User::class, 'paired_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === DeviceInstallationStatus::ACTIVE && $this->company_id !== null;
    }
}
