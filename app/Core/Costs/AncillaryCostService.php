<?php

declare(strict_types=1);

namespace Dakhl\Core\Costs;

use Dakhl\Cleanroom\Core\Contracts\AncillaryCostContract;
use Dakhl\Cleanroom\Core\Exceptions\ZeroExpenseException;
use PDO;

final class AncillaryCostService implements AncillaryCostContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function allocateLandedCost(array $items, int $totalExpense, string $method = 'by_value'): array
    {
        if ($totalExpense <= 0) {
            throw new ZeroExpenseException("Ancillary expense must be greater than zero. Provided: {$totalExpense}.");
        }

        if (empty($items)) {
            return [];
        }

        $isQuantity = ($method === 'by_quantity');
        $totalBasis = 0.0;

        foreach ($items as $item) {
            $basis = $isQuantity ? (float) ($item['quantity'] ?? 0) : (float) ($item['subtotal'] ?? 0);
            $totalBasis += $basis;
        }

        if ($totalBasis <= 0.0) {
            return [];
        }

        $allocated = [];
        $highestBasis = -1.0;
        $highestItemId = null;

        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $basis = $isQuantity ? (float) ($item['quantity'] ?? 0) : (float) ($item['subtotal'] ?? 0);

            $share = ($basis / $totalBasis) * $totalExpense;
            $allocated[$itemId] = (int) round($share, 0, PHP_ROUND_HALF_UP);

            if ($basis > $highestBasis) {
                $highestBasis = $basis;
                $highestItemId = $itemId;
            }
        }

        // Absorb rounding fractions onto the highest-value line
        $allocatedSum = array_sum($allocated);
        $diff = $totalExpense - $allocatedSum;

        if ($diff !== 0 && $highestItemId !== null) {
            $allocated[$highestItemId] += $diff;
        }

        return $allocated;
    }
}
