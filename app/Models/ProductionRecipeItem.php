<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No FiscalYearScope of its own -- company is only derivable via `recipe` (ProductionRecipe has
 * the scope), same pattern/gotcha as RouteCustomer (guardrail #32/#44/#46 class of defect): any
 * new query against this table directly needs an explicit company filter through that relation.
 */
class ProductionRecipeItem extends Model
{
    protected $fillable = [
        'recipe_id',
        'product_id',
        'quantity',
        'unit',
        'waste_percent',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percent' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'recipe_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
