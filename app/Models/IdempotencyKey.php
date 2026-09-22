<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6 (API v1 Platform). One row per (Idempotency-Key header, user) pair
 * ever presented on a v1 write route -- see EnsureIdempotencyKey. Not
 * company-scoped by a global scope on purpose: a key is unique per user
 * regardless of which company path it was sent against, matching how a
 * Sanctum token itself is not company-bound.
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'user_id',
        'company_id',
        'route_signature',
        'request_fingerprint',
        'response_status',
        'response_body',
    ];
}
