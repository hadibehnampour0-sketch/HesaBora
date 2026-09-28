<?php

namespace App\Services\Integration;

use App\Models\ExternalReference;
use App\Models\IntegrationConnection;
use App\Models\WarehouseProductStock;
use App\Services\Integration\Adapters\WooCommerceAdapter;

/**
 * Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md §3, "outbound half": Dakhl's own
 * stock ledger is the aggregated number every channel's sale already subtracts from (inbound
 * orders go through the normal invoice/stock path -- see OrderImportHandler); this pushes that
 * current number out to every platform a product is mapped to. Run as a periodic reconciliation
 * pass (v1: manual "Sync now" button + an artisan command), not wired into every live stock
 * mutation call site yet -- see the design doc's own stated limitation.
 */
class IntegrationInventoryPushService
{
    /** @return array{pushed: int, failed: int, errors: array<int, string>} */
    public function pushAll(IntegrationConnection $connection): array
    {
        $adapter = match ($connection->source_system) {
            'woocommerce' => new WooCommerceAdapter($connection),
            default => throw new \InvalidArgumentException("No adapter for source system: {$connection->source_system}"),
        };

        $mappings = ExternalReference::where('source_system', $connection->source_system)
            ->where('entity_type', 'product')
            ->get();

        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach ($mappings as $mapping) {
            $quantity = (float) WarehouseProductStock::where('product_id', $mapping->entity_id)->sum('quantity');

            try {
                $adapter->pushInventory($mapping->external_id, $quantity);
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "product #{$mapping->entity_id} -> {$mapping->external_id}: {$e->getMessage()}";
            }
        }

        return ['pushed' => $pushed, 'failed' => $failed, 'errors' => $errors];
    }
}
