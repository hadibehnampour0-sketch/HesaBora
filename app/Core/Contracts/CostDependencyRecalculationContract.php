<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Chronological Inventory Cost Dependency Recalculation Contract.
 */
interface CostDependencyRecalculationContract
{
    /**
     * Recalculate moving average costs from a historical date without altering posted sales vouchers.
     *
     * @return array{recalculation_succeeded: bool, variance_amount: int}
     * @throws \Dakhl\Cleanroom\Core\Exceptions\FiscalPeriodLockedException
     */
    public function recalculateCostChain(int $companyId, string $itemCode, string $effectiveStartDate): array;

    /**
     * Post variance adjustment voucher for cumulative COGS discrepancy.
     */
    public function generateVarianceAdjustmentVoucher(
        int $companyId,
        int $fiscalYearId,
        int $varianceAmount,
        int $cogsAccountId,
        int $inventoryAccountId,
        int $userId
    ): int;
}
