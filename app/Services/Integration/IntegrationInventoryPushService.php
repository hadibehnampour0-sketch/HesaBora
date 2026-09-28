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
 * current number out to every platform a product is mapped to.
 *
 * Two ways this runs, both using the same per-mapping pusher below:
 *  - `pushAll()`: the periodic/manual reconciliation pass (every mapped product for one connection).
 *  - `pushProduct()`: the live path, fired by WarehouseProductStockObserver -> PushProductStockJob
 *    whenever that product's on-hand quantity actually changes, for every connection it is
 *    mapped to. Neither InvoiceService, WarehouseService nor any other stock-mutating service
 *    was changed to add this -- the Observer is the only new listener.
 */
class IntegrationInventoryPushService
{
    /** @return array{pushed: int, failed: int, errors: array<int, string>} */
    public function pushAll(IntegrationConnection $connection): array
    {
        $mappings = ExternalReference::where('source_system', $connection->source_system)
            ->where('entity_type', 'product')
            ->get();

        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach ($mappings as $mapping) {
            try {
                $this->pushMapping($connection, $mapping->entity_id, $mapping->external_id);
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "product #{$mapping->entity_id} -> {$mapping->external_id}: {$e->getMessage()}";
            }
        }

        return ['pushed' => $pushed, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Push one product's current quantity to every active connection it is mapped to
     * (regardless of source system). Used by the live-change path.
     *
     * @return array{pushed: int, failed: int, errors: array<int, string>}
     */
    public function pushProduct(int $productId): array
    {
        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach (IntegrationConnection::where('is_active', true)->get() as $connection) {
            $mapping = ExternalReference::where('source_system', $connection->source_system)
                ->where('entity_type', 'product')
                ->where('entity_id', $productId)
                ->first();

            if (! $mapping) {
                continue;
            }

            try {
                $this->pushMapping($connection, $productId, $mapping->external_id);
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "connection #{$connection->id}: {$e->getMessage()}";
            }
        }

        return ['pushed' => $pushed, 'failed' => $failed, 'errors' => $errors];
    }

    private function pushMapping(IntegrationConnection $connection, int $productId, string $externalId): void
    {
        $quantity = (float) WarehouseProductStock::where('product_id', $productId)->sum('quantity');
        $this->adapterFor($connection)->pushInventory($externalId, $quantity);
    }

    private function adapterFor(IntegrationConnection $connection): IntegrationAdapter
    {
        return match ($connection->source_system) {
            'woocommerce' => new WooCommerceAdapter($connection),
            default => throw new \InvalidArgumentException("No adapter for source system: {$connection->source_system}"),
        };
    }
}
