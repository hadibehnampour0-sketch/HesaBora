<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\InvoiceMutationForbiddenException;

/**
 * Commercial Invoice Mutation Boundary Guard Contract.
 */
interface CommercialInvoiceLifecycleContract
{
    /**
     * Assert that invoice can undergo the specified lifecycle mutation.
     *
     * @throws InvoiceMutationForbiddenException
     */
    public function assertCanMutate(int $invoiceId, string $action): void;

    /**
     * Check whether invoice can undergo mutation.
     */
    public function canMutate(int $invoiceId, string $action): bool;

    /**
     * Check if invoice has effective financial activity (payments or allocations).
     */
    public function hasEffectiveFinancialActivity(int $invoiceId): bool;
}
