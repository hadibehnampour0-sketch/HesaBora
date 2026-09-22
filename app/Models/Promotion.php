<?php

namespace App\Models;

use App\Enums\PromotionType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'type',
        'customer_group_id',
        'rules',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'rules' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $promotion) {
            $promotion->company_id ??= getActiveCompany();
        });
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function isActiveOn(\Illuminate\Support\Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $date->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $date->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
