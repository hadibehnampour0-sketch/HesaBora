<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\ProductionRecipeItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manufacturing Phase 1 ("Production Lite"), per
 * Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md §4. Reuses the exact locking/oversell
 * discipline WarehouseService::transfer()/ProductService already use for every other stock
 * mutation -- no new stock-movement mechanism, no generic ledger (guardrail #53).
 */
class ManufacturingService
{
    public function createRecipe(int $productId, string $name, float $outputQuantity, array $items, User $user): ProductionRecipe
    {
        return DB::transaction(function () use ($productId, $name, $outputQuantity, $items, $user) {
            $recipe = ProductionRecipe::create([
                'product_id' => $productId,
                'name' => $name,
                'output_quantity' => $outputQuantity,
                'created_by' => $user->id,
            ]);

            $recipe->items()->createMany(array_map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'waste_percent' => $item['waste_percent'] ?? 0,
            ], $items));

            return $recipe->load('items');
        });
    }

    /**
     * @return array{materials: array<int, array{product: Product, needed: float, available: float, sufficient: bool}>, can_produce: bool}
     */
    public function estimate(ProductionRecipe $recipe, float $quantityToProduce, Warehouse $warehouse): array
    {
        $recipe->loadMissing('items.product');
        $scale = $this->scaleFor($recipe, $quantityToProduce);

        $materials = $recipe->items->map(function ($item) use ($scale, $warehouse) {
            $needed = $this->neededQuantity($item, $scale);
            $available = (float) (WarehouseProductStock::where('warehouse_id', $warehouse->id)->where('product_id', $item->product_id)->value('quantity') ?? 0);

            return [
                'product' => $item->product,
                'needed' => $needed,
                'available' => $available,
                'sufficient' => $item->product->oversell || $available >= $needed,
            ];
        })->values()->all();

        return [
            'materials' => $materials,
            'can_produce' => collect($materials)->every(fn ($row) => $row['sufficient']),
        ];
    }

    public function produce(ProductionRecipe $recipe, float $quantityToProduce, Warehouse $warehouse, User $user, float $laborCost = 0, float $overheadCost = 0): ProductionOrder
    {
        if ($quantityToProduce <= 0) {
            throw ValidationException::withMessages(['quantity' => [__('The quantity to produce must be greater than zero.')]]);
        }

        return DB::transaction(function () use ($recipe, $quantityToProduce, $warehouse, $user, $laborCost, $overheadCost) {
            $recipe = ProductionRecipe::with('items.product')->lockForUpdate()->findOrFail($recipe->id);
            $scale = $this->scaleFor($recipe, $quantityToProduce);

            // Lock every material row plus the output row in one consistent (sorted) order to
            // avoid a deadlock against a concurrent production run needing an overlapping set --
            // the exact reasoning WarehouseService::lockedStocks() already documents.
            $productIds = $recipe->items->pluck('product_id')->push($recipe->product_id)->unique()->sort()->values();
            foreach ($productIds as $productId) {
                WarehouseProductStock::firstOrCreate(
                    ['product_id' => $productId, 'warehouse_id' => $warehouse->id],
                    ['quantity' => 0, 'average_cost' => 0]
                );
            }
            $stocks = WarehouseProductStock::where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $materialCost = 0.0;
            $consumedRows = [];

            foreach ($recipe->items as $item) {
                $needed = $this->neededQuantity($item, $scale);
                $stock = $stocks[$item->product_id];

                if (! $item->product->oversell && (float) $stock->quantity < $needed) {
                    throw ValidationException::withMessages([
                        'materials' => [__('Not enough ":name" in this warehouse: need :need, have :have.', [
                            'name' => $item->product->name,
                            'need' => $needed,
                            'have' => (float) $stock->quantity,
                        ])],
                    ]);
                }

                $unitCost = (float) $stock->average_cost;
                $lineCost = round($needed * $unitCost, 2);
                $materialCost += $lineCost;

                $stock->quantity = (float) $stock->quantity - $needed;
                $stock->save();

                $consumedRows[] = [
                    'product_id' => $item->product_id,
                    'quantity_consumed' => $needed,
                    'unit_cost' => $unitCost,
                    'line_cost' => $lineCost,
                ];
            }

            $totalCost = round($materialCost + $laborCost + $overheadCost, 2);
            $unitCost = $quantityToProduce > 0 ? round($totalCost / $quantityToProduce, 4) : 0;

            $outputStock = $stocks[$recipe->product_id];
            $quantityBefore = (float) $outputStock->quantity;
            $newQuantity = $quantityBefore + $quantityToProduce;
            $outputStock->average_cost = $newQuantity > 0
                ? (($quantityBefore * (float) $outputStock->average_cost) + ($quantityToProduce * $unitCost)) / $newQuantity
                : 0;
            $outputStock->quantity = $newQuantity;
            $outputStock->save();

            $order = ProductionOrder::create([
                'recipe_id' => $recipe->id,
                'product_id' => $recipe->product_id,
                'warehouse_id' => $warehouse->id,
                'quantity_produced' => $quantityToProduce,
                'status' => 'produced',
                'material_cost' => round($materialCost, 2),
                'labor_cost' => round($laborCost, 2),
                'overhead_cost' => round($overheadCost, 2),
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'produced_at' => now(),
                'created_by' => $user->id,
            ]);

            $order->materials()->createMany($consumedRows);

            return $order->load('materials.product', 'product', 'warehouse');
        });
    }

    private function scaleFor(ProductionRecipe $recipe, float $quantityToProduce): float
    {
        return (float) $recipe->output_quantity > 0 ? $quantityToProduce / (float) $recipe->output_quantity : 0.0;
    }

    /** Waste % means "this much MORE is actually consumed" (spillage), not "this much less ends up in the product". */
    private function neededQuantity(ProductionRecipeItem $recipeItem, float $scale): float
    {
        $nominal = (float) $recipeItem->quantity * $scale;

        return round($nominal * (1 + ((float) $recipeItem->waste_percent / 100)), 4);
    }
}
