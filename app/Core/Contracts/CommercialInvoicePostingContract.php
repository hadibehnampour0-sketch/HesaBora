<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Commercial Invoice Journal Builder & Posting Contract.
 */
interface CommercialInvoicePostingContract
{
    /**
     * Create a draft commercial invoice.
     *
     * @param int $companyId
     * @param int $fiscalYearId
     * @param int $invoiceType 1=Buy, 2=Sell, 3=Return Buy, 4=Return Sell
     * @param int $customerAccountId
     * @param array<int, array{description: string, quantity: string, unit_price: int, discount_amount: int, tax_rate: float}> $items
     * @param int $userId
     * @param string|null $uuid
     * @return int Invoice ID
     * @throws \Dakhl\Cleanroom\Core\Exceptions\EmptyInvoiceException
     */
    public function createDraftInvoice(
        int $companyId,
        int $fiscalYearId,
        int $invoiceType,
        int $customerAccountId,
        array $items,
        int $userId,
        ?string $uuid = null
    ): int;

    /**
     * Post commercial invoice, creating a balanced journal voucher.
     *
     * @param int $invoiceId
     * @param int $revenueAccountId
     * @param int $vatPayableAccountId
     * @param int $userId
     * @return int Created Journal Voucher ID
     * @throws \Dakhl\Cleanroom\Core\Exceptions\InvoiceAlreadyPostedException
     * @throws \Dakhl\Cleanroom\Core\Exceptions\EmptyInvoiceException
     */
    public function postInvoice(int $invoiceId, int $revenueAccountId, int $vatPayableAccountId, int $userId): int;
}
