<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P9.2 (Tax Rules engine). System-wide, not company-scoped -- see the
 * migration's own docblock. Managed by whoever administers the platform's
 * shared tax configuration (Super-Admin), not by individual companies.
 */
class TaxRule extends Model
{
    protected $fillable = [
        'sstid',
        'rate',
        'is_exempt',
        'effective_from',
        'effective_to',
        'description',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_exempt' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
