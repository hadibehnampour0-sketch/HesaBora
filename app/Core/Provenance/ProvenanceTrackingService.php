<?php

declare(strict_types=1);

namespace Dakhl\Core\Provenance;

use Dakhl\Cleanroom\Core\Contracts\ProvenanceTrackingContract;
use Dakhl\Cleanroom\Core\Exceptions\CompanyDeletionForbiddenException;
use PDO;

final class ProvenanceTrackingService implements ProvenanceTrackingContract
{
    public function __construct(private PDO $pdo) {}

    public function assertCanDeleteCompany(int $companyId): void
    {
        if (! $this->canDeleteCompany($companyId)) {
            throw new CompanyDeletionForbiddenException(
                "Company {$companyId} cannot be deleted because it contains native accounting activity or payment allocations."
            );
        }
    }

    public function canDeleteCompany(int $companyId): bool
    {
        // Check 1: Approved/posted journal vouchers with provenance = NATIVE (1)
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM journal_vouchers
            WHERE company_id = ? AND status = 2 AND provenance = 1
        ');
        $stmt->execute([$companyId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        // Check 2: Payment allocations belonging to this company
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM payment_allocations pa
            JOIN payments p ON pa.payment_id = p.id
            WHERE p.company_id = ?
        ');
        $stmt->execute([$companyId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        return true;
    }
}
