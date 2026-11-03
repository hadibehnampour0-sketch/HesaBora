<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\DomainException;

/**
 * Fiscal Period 5-State Lifecycle Guard Contract.
 *
 * States: 1=Open, 2=SoftLocked, 3=Frozen, 4=Closed, 5=Audited.
 */
interface PeriodFreezeGuardContract
{
    /**
     * Set the status of a fiscal period month.
     *
     * @throws DomainException If illegal backward transition
     */
    public function setPeriodStatus(int $companyId, int $fiscalYearId, int $periodNumber, int $status): void;

    /**
     * Check whether posting a financial transaction on the given date is permitted.
     *
     * @param  array<int, string>  $userPermissions
     */
    public function canPostTransaction(int $companyId, string $date, int $userId, array $userPermissions = []): bool;
}
