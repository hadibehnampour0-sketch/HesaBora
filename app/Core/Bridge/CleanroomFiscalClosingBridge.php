<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use Dakhl\Cleanroom\Core\Contracts\FiscalClosingContract;
use Dakhl\Cleanroom\Core\Exceptions\FiscalYearNotReconciledException;
use Dakhl\Cleanroom\Core\Exceptions\TargetYearAlreadyOpenedException;
use Illuminate\Validation\ValidationException;

final class CleanroomFiscalClosingBridge
{
    public function __construct(private FiscalClosingContract $closingContract) {}

    /**
     * Close temporary revenue and expense accounts into Retained Earnings.
     *
     * @throws ValidationException
     */
    public function closeTemporaryAccounts(
        int $companyId,
        int $fiscalYearId,
        int $retainedEarningsAccountId,
        int $userId
    ): int {
        try {
            return $this->closingContract->closeTemporaryAccounts(
                companyId: $companyId,
                fiscalYearId: $fiscalYearId,
                retainedEarningsAccountId: $retainedEarningsAccountId,
                userId: $userId
            );
        } catch (FiscalYearNotReconciledException $e) {
            throw ValidationException::withMessages([
                'fiscal_year' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Create opening voucher carrying forward permanent accounts into next fiscal year.
     *
     * @throws ValidationException
     */
    public function createOpeningVoucher(
        int $companyId,
        int $sourceFiscalYearId,
        int $targetFiscalYearId,
        int $userId
    ): int {
        try {
            return $this->closingContract->createOpeningVoucher(
                companyId: $companyId,
                sourceFiscalYearId: $sourceFiscalYearId,
                targetFiscalYearId: $targetFiscalYearId,
                userId: $userId
            );
        } catch (TargetYearAlreadyOpenedException $e) {
            throw ValidationException::withMessages([
                'target_fiscal_year' => [$e->getMessage()],
            ]);
        }
    }
}
