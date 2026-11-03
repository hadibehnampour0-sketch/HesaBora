<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use App\Models\Invoice;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoiceLifecycleContract;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoicePostingContract;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceMutationForbiddenException;
use Illuminate\Validation\ValidationException;

final class CleanroomInvoiceBridge
{
    public function __construct(
        private CommercialInvoicePostingContract $postingContract,
        private CommercialInvoiceLifecycleContract $lifecycleContract
    ) {}

    /**
     * Enforce commercial invoice mutation boundary.
     *
     * @throws ValidationException
     */
    public function assertCanMutate(Invoice $invoice, string $action = 'edit'): void
    {
        try {
            $this->lifecycleContract->assertCanMutate((int) $invoice->id, $action);
        } catch (InvoiceMutationForbiddenException $e) {
            throw ValidationException::withMessages([
                'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
            ]);
        }
    }

    /**
     * Check whether invoice can be safely mutated.
     */
    public function canMutate(Invoice $invoice, string $action = 'edit'): bool
    {
        return $this->lifecycleContract->canMutate((int) $invoice->id, $action);
    }

    /**
     * Check whether invoice has active financial activity.
     */
    public function hasFinancialActivity(Invoice $invoice): bool
    {
        return $this->lifecycleContract->hasEffectiveFinancialActivity((int) $invoice->id);
    }

    /**
     * Post commercial invoice through the clean-room double-entry engine.
     *
     * @return int Created Journal Voucher ID
     */
    public function postInvoice(Invoice $invoice, int $userId): int
    {
        return $this->postingContract->postSalesInvoice((int) $invoice->id, $userId);
    }
}
