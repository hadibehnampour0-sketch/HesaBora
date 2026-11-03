<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Payment to Invoice Settlement Allocation Contract.
 */
interface PaymentAllocationContract
{
    /**
     * Allocate unallocated payment capacity against an open invoice balance.
     *
     * @param int $paymentId
     * @param int $invoiceId
     * @param int $amount Whole Rials
     * @param int $userId
     * @param string|null $uuid
     * @return int Allocation ID
     * @throws \Dakhl\Cleanroom\Core\Exceptions\InsufficientPaymentCapacityException
     * @throws \Dakhl\Cleanroom\Core\Exceptions\InvoiceOverpaymentException
     */
    public function allocate(int $paymentId, int $invoiceId, int $amount, int $userId, ?string $uuid = null): int;

    /**
     * Deletion of a posted allocation is strictly rejected.
     *
     * @throws \Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException
     * @throws \Dakhl\Cleanroom\Core\Exceptions\DomainException
     */
    public function deleteAllocation(int $allocationId): void;
}
