<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6 (API v1 Platform). No FiscalYearScope of its own -- company scoping
 * is always derived through `webhook` (guardrail #32/#36's own lesson:
 * scope every new query against a table that lacks the scope through an
 * already-scoped parent relation, never trust the table alone).
 */
class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'status',
        'attempt_count',
        'last_response_status',
        'last_response_body',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
