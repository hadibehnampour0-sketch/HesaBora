<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecipe extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'name',
        'output_quantity',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $recipe) {
            $recipe->company_id ??= getActiveCompany();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionRecipeItem::class, 'recipe_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
