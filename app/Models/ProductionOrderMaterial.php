<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** No FiscalYearScope of its own -- derivable via `productionOrder` (guardrail #32/#44/#46 class). */
class ProductionOrderMaterial extends Model
{
    protected $fillable = [
        'production_order_id',
        'product_id',
        'quantity_consumed',
        'unit_cost',
        'line_cost',
    ];

    protected $casts = [
        'quantity_consumed' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_cost' => 'decimal:2',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
