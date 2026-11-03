<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\ZeroExpenseException;

/**
 * Landed Acquisition Cost Allocation Contract.
 */
interface AncillaryCostContract
{
    /**
     * Distribute total ancillary landed cost across item lines.
     *
     * @param  array<int, array{id: int, item_code: string, subtotal: int, quantity: string}>  $items
     * @param  int  $totalExpense  Total expense in Rials
     * @param  string  $method  'by_value' or 'by_quantity'
     * @return array<int, int> Map of item ID => allocated landed cost in Rials
     *
     * @throws ZeroExpenseException
     */
    public function allocateLandedCost(array $items, int $totalExpense, string $method = 'by_value'): array;
}
