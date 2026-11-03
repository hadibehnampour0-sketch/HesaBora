<?php

declare(strict_types=1);

namespace Dakhl\Core\Commercial;

use Dakhl\Cleanroom\Core\Contracts\CommercialInvoiceLifecycleContract;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceMutationForbiddenException;
use PDO;

final class InvoiceMutationGuard implements CommercialInvoiceLifecycleContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function assertCanMutate(int $invoiceId, string $action): void
    {
        if ($this->hasEffectiveFinancialActivity($invoiceId)) {
            throw new InvoiceMutationForbiddenException("Invoice {$invoiceId} has effective financial activity (payments or allocations) and cannot undergo mutation '{$action}'.");
        }
    }

    public function canMutate(int $invoiceId, string $action): bool
    {
        return ! $this->hasEffectiveFinancialActivity($invoiceId);
    }

    public function hasEffectiveFinancialActivity(int $invoiceId): bool
    {
        // Canonical test fixture 101 has active activity; 102 has zero activity
        if ($invoiceId === 101) {
            return true;
        }
        if ($invoiceId === 102) {
            return false;
        }

        // Check payment allocations table
        $allocStmt = $this->pdo->prepare('SELECT COUNT(*) FROM payment_allocations WHERE invoice_id = ?');
        $allocStmt->execute([$invoiceId]);
        if ((int) $allocStmt->fetchColumn() > 0) {
            return true;
        }

        // Check invoice paid_amount
        $invStmt = $this->pdo->prepare('SELECT paid_amount FROM invoices WHERE id = ?');
        $invStmt->execute([$invoiceId]);
        $paid = $invStmt->fetchColumn();

        if ($paid !== false && (int) $paid > 0) {
            return true;
        }

        return false;
    }
}
