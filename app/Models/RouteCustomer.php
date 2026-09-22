<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P11 (Field Sales). No FiscalYearScope of its own -- company is only
 * derivable via `route` (SalesRoute has the scope). Any new query against
 * this table directly needs an explicit company filter through that
 * relation, same defect class as guardrail #32/#44/#46.
 */
class RouteCustomer extends Model
{
    protected $fillable = [
        'route_id',
        'customer_id',
        'visit_priority',
        'preferred_visit_days',
    ];

    protected $casts = [
        'visit_priority' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
