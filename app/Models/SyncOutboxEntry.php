<?php

namespace App\Models;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;

class SyncOutboxEntry extends Model
{
    protected $table = 'sync_outbox';

    protected $fillable = [
        'operation_id',
        'entity_type',
        'entity_uuid',
        'operation',
        'payload',
        'base_sync_version',
        'company_id',
        'status',
        'attempts',
        'last_error',
        'sent_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'operation' => SyncOperation::class,
        'status' => SyncStatus::class,
        'payload' => 'array',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
