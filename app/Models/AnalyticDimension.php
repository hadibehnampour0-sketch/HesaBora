<?php

namespace App\Models;

use App\Enums\AnalyticDimensionType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnalyticDimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'type' => AnalyticDimensionType::class,
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $dimension) {
            $dimension->company_id ??= getActiveCompany();
        });
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'analytic_dimension_transaction');
    }
}
