<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\AssetFullyDepreciatedException;

/**
 * Fixed Assets Acquisition & Depreciation Contract.
 */
interface FixedAssetDepreciationContract
{
    /**
     * Register tangible fixed asset.
     */
    public function registerAsset(
        int $companyId,
        string $name,
        int $acquisitionCost,
        int $salvageValue,
        int $usefulLifeMonths,
        string $method = 'straight_line',
        ?float $rate = null
    ): int;

    /**
     * Compute periodic depreciation for an asset.
     *
     * @throws AssetFullyDepreciatedException
     */
    public function calculatePeriodicDepreciation(int $assetId): int;

    /**
     * Post periodic depreciation journal voucher.
     *
     * @return int Created Journal Voucher ID
     */
    public function postDepreciationVoucher(
        int $assetId,
        int $expenseAccountId,
        int $accumulatedDepreciationAccountId,
        int $userId
    ): int;
}
