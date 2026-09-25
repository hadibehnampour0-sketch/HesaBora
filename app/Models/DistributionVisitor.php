<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A field visitor (ویزیتور): a company user, optionally with a dedicated van warehouse. */
class DistributionVisitor extends Model
{
    protected $fillable = ['company_id', 'user_id', 'warehouse_id', 'is_active', 'phone', 'notes'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (DistributionVisitor $visitor) {
            $visitor->company_id ??= getActiveCompany();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
