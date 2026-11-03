<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Fiscal Year Closing & Opening Voucher Transfer Contract.
 */
interface FiscalClosingContract
{
    /**
     * Execute closing voucher for temporary accounts into Retained Earnings.
     *
     * @throws \Dakhl\Cleanroom\Core\Exceptions\FiscalYearNotReconciledException
     */
    public function closeTemporaryAccounts(
        int $companyId,
        int $fiscalYearId,
        int $retainedEarningsAccountId,
        int $userId
    ): int;

    /**
     * Generate opening voucher carrying forward permanent balance sheet accounts.
     *
     * @throws \Dakhl\Cleanroom\Core\Exceptions\TargetYearAlreadyOpenedException
     */
    public function createOpeningVoucher(
        int $companyId,
        int $sourceFiscalYearId,
        int $targetFiscalYearId,
        int $userId
    ): int;
}
