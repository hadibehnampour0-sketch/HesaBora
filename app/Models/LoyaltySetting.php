<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'is_enabled',
        'spend_per_point',
        'point_value_rials',
        'silver_points_threshold',
        'gold_points_threshold',
        'vip_points_threshold',
        'silver_discount_percent',
        'gold_discount_percent',
        'vip_discount_percent',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'spend_per_point' => 'integer',
        'point_value_rials' => 'integer',
        'silver_points_threshold' => 'integer',
        'gold_points_threshold' => 'integer',
        'vip_points_threshold' => 'integer',
        'silver_discount_percent' => 'float',
        'gold_discount_percent' => 'float',
        'vip_discount_percent' => 'float',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
