<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException;
use Dakhl\Cleanroom\Core\Exceptions\ZeroAmountPaymentException;

/**
 * Treasury Payments & Receipts Contract.
 *
 * Enforces atomic journal voucher posting and immutability of posted payments.
 */
interface TreasuryPaymentContract
{
    /**
     * Record a treasury receipt and atomically post its journal voucher.
     *
     * @param  int  $amount  Whole Rials
     * @param  string  $date  Y-m-d
     * @param  string|null  $uuid  Canonical economic event UUIDv7
     * @return int Payment ID
     *
     * @throws ZeroAmountPaymentException
     */
    public function createReceipt(
        int $companyId,
        int $fiscalYearId,
        int $bankAccountId,
        int $customerAccountId,
        int $amount,
        string $date,
        string $description,
        int $userId,
        ?string $uuid = null
    ): int;

    /**
     * Record a treasury disbursement and atomically post its journal voucher.
     */
    public function createDisbursement(
        int $companyId,
        int $fiscalYearId,
        int $bankAccountId,
        int $vendorAccountId,
        int $amount,
        string $date,
        string $description,
        int $userId,
        ?string $uuid = null
    ): int;

    /**
     * Attempting to delete a posted payment is strictly rejected.
     *
     * @throws PostedFinancialMutationException
     */
    public function deletePayment(int $paymentId): void;
}
