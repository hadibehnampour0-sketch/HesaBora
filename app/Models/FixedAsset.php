<?php

namespace App\Models;

use App\Enums\FixedAssetStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'asset_subject_id',
        'depreciation_expense_subject_id',
        'accumulated_depreciation_subject_id',
        'status',
        'disposed_at',
        'created_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'status' => FixedAssetStatus::class,
        'disposed_at' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $asset) {
            $asset->company_id ??= getActiveCompany();
            $asset->status ??= FixedAssetStatus::ACTIVE;
        });
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class)->orderBy('period_date');
    }

    public function depreciableAmount(): float
    {
        return max((float) $this->acquisition_cost - (float) $this->salvage_value, 0.0);
    }

    public function monthlyDepreciation(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableAmount() / $this->useful_life_months, 2);
    }
}
