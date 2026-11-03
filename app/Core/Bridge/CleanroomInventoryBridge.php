<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use Dakhl\Cleanroom\Core\Contracts\AncillaryCostContract;
use Dakhl\Cleanroom\Core\Contracts\InventoryValuationCogsContract;

final class CleanroomInventoryBridge
{
    public function __construct(
        private InventoryValuationCogsContract $inventoryContract,
        private AncillaryCostContract $ancillaryCostContract
    ) {}

    /**
     * Record inventory inbound reception.
     */
    public function recordInbound(int $companyId, string $itemCode, string $quantity, int $totalCost): void
    {
        $this->inventoryContract->recordInbound($companyId, $itemCode, $quantity, $totalCost);
    }

    /**
     * Calculate cost of goods sold using moving weighted average.
     */
    public function calculateCogs(int $companyId, string $itemCode, string $quantity): int
    {
        return $this->inventoryContract->calculateCogs($companyId, $itemCode, $quantity);
    }

    /**
     * Get current unit average cost for item.
     */
    public function getCurrentAverageUnitCost(int $companyId, string $itemCode): float
    {
        return $this->inventoryContract->getCurrentAverageUnitCost($companyId, $itemCode);
    }

    /**
     * Allocate ancillary landed costs across commercial lines.
     */
    public function allocateLandedCost(array $items, int $totalExpense, string $method = 'by_value'): array
    {
        return $this->ancillaryCostContract->allocateLandedCost($items, $totalExpense, $method);
    }
}
