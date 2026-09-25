<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    protected $table = 'integration_events';

    protected $fillable = ['company_id', 'source_system', 'external_id', 'event_type', 'payload_hash', 'payload', 'status', 'message', 'attempts', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $row) {
            $row->company_id ??= getActiveCompany();
        });
    }
}
