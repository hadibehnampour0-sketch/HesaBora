<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\InsufficientPaymentCapacityException;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceOverpaymentException;
use Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException;

/**
 * Payment to Invoice Settlement Allocation Contract.
 */
interface PaymentAllocationContract
{
    /**
     * Allocate unallocated payment capacity against an open invoice balance.
     *
     * @param  int  $amount  Whole Rials
     * @return int Allocation ID
     *
     * @throws InsufficientPaymentCapacityException
     * @throws InvoiceOverpaymentException
     */
    public function allocate(int $paymentId, int $invoiceId, int $amount, int $userId, ?string $uuid = null): int;

    /**
     * Deletion of a posted allocation is strictly rejected.
     *
     * @throws PostedFinancialMutationException
     * @throws DomainException
     */
    public function deleteAllocation(int $allocationId): void;
}
