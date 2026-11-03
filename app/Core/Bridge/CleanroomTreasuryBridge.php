<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use App\Models\Invoice;
use App\Models\Payment;
use Dakhl\Cleanroom\Core\Contracts\PaymentAllocationContract;
use Dakhl\Cleanroom\Core\Contracts\TreasuryPaymentContract;
use Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException;
use Illuminate\Validation\ValidationException;

final class CleanroomTreasuryBridge
{
    public function __construct(
        private TreasuryPaymentContract $treasuryContract,
        private PaymentAllocationContract $allocationContract
    ) {}

    /**
     * Record and post treasury receipt.
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
    ): int {
        return $this->treasuryContract->createReceipt(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            bankAccountId: $bankAccountId,
            customerAccountId: $customerAccountId,
            amount: $amount,
            date: $date,
            description: $description,
            userId: $userId,
            uuid: $uuid
        );
    }

    /**
     * Record and post treasury disbursement.
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
    ): int {
        return $this->treasuryContract->createDisbursement(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            bankAccountId: $bankAccountId,
            vendorAccountId: $vendorAccountId,
            amount: $amount,
            date: $date,
            description: $description,
            userId: $userId,
            uuid: $uuid
        );
    }

    /**
     * Allocate payment amount to invoice.
     *
     * @throws ValidationException
     */
    public function allocate(Payment $payment, Invoice $invoice, int $amount, ?string $uuid = null): int
    {
        try {
            return $this->allocationContract->allocate(
                paymentId: (int) $payment->id,
                invoiceId: (int) $invoice->id,
                amount: $amount,
                uuid: $uuid
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'allocation' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Ensure posted payment is immutable.
     *
     * @throws ValidationException
     */
    public function assertCanDeletePayment(Payment $payment): void
    {
        try {
            $this->treasuryContract->deletePayment((int) $payment->id);
        } catch (PostedFinancialMutationException $e) {
            throw ValidationException::withMessages([
                'payment' => [__('Posted payments cannot be deleted.')],
            ]);
        }
    }
}
