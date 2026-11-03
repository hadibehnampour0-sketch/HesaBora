<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\CompanyDeletionForbiddenException;

/**
 * Financial Provenance Tracking & Company Pruning Guard Contract.
 */
interface ProvenanceTrackingContract
{
    /**
     * Assert that company can be safely deleted.
     *
     * @throws CompanyDeletionForbiddenException
     */
    public function assertCanDeleteCompany(int $companyId): void;

    /**
     * Check whether company has zero native accounting records and can be deleted.
     */
    public function canDeleteCompany(int $companyId): bool;
}
