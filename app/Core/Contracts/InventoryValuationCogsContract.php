<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\InsufficientInventoryException;

/**
 * Perpetual Moving Weighted Average Inventory Valuation & COGS Contract.
 */
interface InventoryValuationCogsContract
{
    /**
     * Record inbound inventory purchase or receipt, updating moving weighted average unit cost.
     *
     * @param  string  $quantity  Decimal string with up to 6 decimal places
     * @param  int  $totalCost  Whole Rials
     * @return int Stock Movement ID
     */
    public function recordInbound(int $companyId, string $itemCode, string $quantity, int $totalCost, ?string $uuid = null): int;

    /**
     * Calculate and deduct COGS for outbound sales delivery.
     *
     * @return int COGS in whole Rials
     *
     * @throws InsufficientInventoryException
     */
    public function calculateCogs(int $companyId, string $itemCode, string $quantity, bool $allowNegativeStock = false): int;

    /**
     * Retrieve current moving weighted average unit cost in Rials.
     */
    public function getCurrentAverageUnitCost(int $companyId, string $itemCode): float;
}
