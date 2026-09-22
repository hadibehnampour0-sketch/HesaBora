<?php

namespace App\Models;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;

class SyncInboxEntry extends Model
{
    protected $table = 'sync_inbox';

    protected $fillable = [
        'operation_id',
        'entity_type',
        'entity_uuid',
        'operation',
        'payload',
        'base_sync_version',
        'origin_device_id',
        'company_id',
        'status',
        'applied_at',
        'rejection_reason',
        'server_version',
        'local_version',
    ];

    protected $casts = [
        'operation' => SyncOperation::class,
        'status' => SyncStatus::class,
        'payload' => 'array',
        'applied_at' => 'datetime',
    ];

    public function originDevice()
    {
        return $this->belongsTo(DeviceInstallation::class, 'origin_device_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
