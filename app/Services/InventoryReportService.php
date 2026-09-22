<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P4.7/P4.8 (Inventory Valuation, Slow-Moving Stock). Pure read reports --
 * no new tables, computed entirely from WarehouseProductStock (the
 * existing single source of truth for on-hand quantity/average cost) and
 * InvoiceItem (for sales-recency).
 *
 * `WarehouseProductStock` carries NO `FiscalYearScope` of its own (found
 * while building this report's own cross-company test, before this ever
 * reached a real user) -- both methods here must filter explicitly via
 * `whereHas('warehouse')`, since `Warehouse` IS scoped and is the only
 * company-deriving relation this table has.
 */
class InventoryReportService
{
    /**
     * @return array{lines: Collection, total_value: float}
     */
    public function valuationReport(?Warehouse $warehouse = null): array
    {
        $query = WarehouseProductStock::query()
            ->whereHas('warehouse')
            ->with('product:id,name,code,average_cost')
            ->where('quantity', '>', 0);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $lines = $query->get()->map(function (WarehouseProductStock $stock) {
            $value = round((float) $stock->quantity * (float) $stock->average_cost, 2);

            return [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name,
                'warehouse_id' => $stock->warehouse_id,
                'quantity' => (float) $stock->quantity,
                'average_cost' => (float) $stock->average_cost,
                'value' => $value,
            ];
        });

        return [
            'lines' => $lines,
            'total_value' => round((float) $lines->sum('value'), 2),
        ];
    }

    /**
     * Products with on-hand stock (quantity > 0, any warehouse) whose most
     * recent SELL invoice item is older than $daysThreshold days -- or
     * that have never been sold at all.
     *
     * @return Collection<int, array{product_id: int, product_name: string, quantity: float, last_sold_at: ?string, days_since_last_sale: ?int}>
     */
    public function slowMovingReport(int $daysThreshold = 90): Collection
    {
        $today = Carbon::today();

        $stockByProduct = WarehouseProductStock::query()
            ->whereHas('warehouse')
            ->where('quantity', '>', 0)
            ->with('product:id,name,code')
            ->get()
            ->groupBy('product_id');

        return $stockByProduct->map(function (Collection $stocks, $productId) use ($today, $daysThreshold) {
            $product = $stocks->first()->product;
            $totalQuantity = (float) $stocks->sum('quantity');

            // Plain query-builder join bypasses Invoice's own FiscalYearScope
            // -- filter company_id explicitly rather than relying on it.
            $lastSaleDate = InvoiceItem::query()
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->where('invoice_items.itemable_type', Product::class)
                ->where('invoice_items.itemable_id', $productId)
                ->where('invoices.company_id', getActiveCompany())
                ->where('invoices.invoice_type', InvoiceType::SELL->value)
                ->whereIn('invoices.status', array_map(fn ($s) => $s->value, InvoiceStatus::approvedOrSettled()))
                ->max('invoices.date');

            // Carbon::diffInDays() returns a SIGNED difference (negative
            // when the argument is in the past relative to the caller) --
            // force absolute, since this is always meant to be an "age in
            // days" regardless of which side is earlier.
            $daysSince = $lastSaleDate ? $today->diffInDays(Carbon::parse($lastSaleDate), absolute: true) : null;

            return [
                'product_id' => (int) $productId,
                'product_name' => $product?->name,
                'quantity' => $totalQuantity,
                'last_sold_at' => $lastSaleDate,
                'days_since_last_sale' => $daysSince,
            ];
        })
            ->filter(fn ($row) => $row['days_since_last_sale'] === null || $row['days_since_last_sale'] >= $daysThreshold)
            ->sortByDesc(fn ($row) => $row['days_since_last_sale'] ?? PHP_INT_MAX)
            ->values();
    }
}
