<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\EmptyInvoiceException;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceAlreadyPostedException;

/**
 * Commercial Invoice Journal Builder & Posting Contract.
 */
interface CommercialInvoicePostingContract
{
    /**
     * Create a draft commercial invoice.
     *
     * @param  int  $invoiceType  1=Buy, 2=Sell, 3=Return Buy, 4=Return Sell
     * @param  array<int, array{description: string, quantity: string, unit_price: int, discount_amount: int, tax_rate: float}>  $items
     * @return int Invoice ID
     *
     * @throws EmptyInvoiceException
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
     * @return int Created Journal Voucher ID
     *
     * @throws InvoiceAlreadyPostedException
     * @throws EmptyInvoiceException
     */
    public function postInvoice(int $invoiceId, int $revenueAccountId, int $vatPayableAccountId, int $userId): int;
}
