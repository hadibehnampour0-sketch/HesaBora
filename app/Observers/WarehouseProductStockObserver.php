<?php

namespace App\Observers;

use App\Jobs\PushProductStockJob;
use App\Models\IntegrationConnection;
use App\Models\WarehouseProductStock;

/**
 * Live half of the two-way stock sync (Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md
 * §3). Every stock-mutating service (InvoiceService, WarehouseService, ProductService,
 * StockReservationService's consume path) already ends its mutation with `$stock->quantity = ...;
 * $stock->save();` on a loaded WarehouseProductStock row -- an Observer on that one model is a
 * single, zero-footprint hook point that catches all of them, instead of adding an integration
 * push call inside each of those already-tested services individually.
 */
class WarehouseProductStockObserver
{
    public function saved(WarehouseProductStock $stock): void
    {
        if (! $stock->wasChanged('quantity')) {
            return;
        }

        // Cheap early exit: the overwhelming common case (no integration set up at all) never
        // needs to queue a job just to find that out again inside it.
        if (! IntegrationConnection::where('is_active', true)->exists()) {
            return;
        }

        PushProductStockJob::dispatch($stock->product_id);
    }
}
