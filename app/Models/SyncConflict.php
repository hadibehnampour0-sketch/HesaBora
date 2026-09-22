<?php

namespace App\Models;

use App\Enums\SyncConflictResolution;
use App\Enums\SyncConflictStatus;
use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    protected $fillable = [
        'inbox_entry_id',
        'entity_type',
        'entity_uuid',
        'local_snapshot',
        'server_snapshot',
        'base_version',
        'server_version',
        'origin_device_id',
        'company_id',
        'status',
        'resolution',
        'resolved_by',
        'resolved_at',
        'resulting_operation_id',
    ];

    protected $casts = [
        'local_snapshot' => 'array',
        'server_snapshot' => 'array',
        'status' => SyncConflictStatus::class,
        'resolution' => SyncConflictResolution::class,
        'resolved_at' => 'datetime',
    ];

    public function inboxEntry()
    {
        return $this->belongsTo(SyncInboxEntry::class, 'inbox_entry_id');
    }

    public function originDevice()
    {
        return $this->belongsTo(DeviceInstallation::class, 'origin_device_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === SyncConflictStatus::OPEN;
    }
}
