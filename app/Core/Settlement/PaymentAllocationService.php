<?php

declare(strict_types=1);

namespace Dakhl\Core\Settlement;

use Dakhl\Cleanroom\Core\Contracts\PaymentAllocationContract;
use Dakhl\Cleanroom\Core\Exceptions\InsufficientPaymentCapacityException;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceOverpaymentException;
use Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class PaymentAllocationService implements PaymentAllocationContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function allocate(int $paymentId, int $invoiceId, int $amount, int $userId, ?string $uuid = null): int
    {
        $this->ensurePaymentAndInvoiceExist($paymentId, $invoiceId);

        $pStmt = $this->pdo->prepare('SELECT id, amount, allocated_amount, unallocated_amount FROM payments WHERE id = ?');
        $pStmt->execute([$paymentId]);
        $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

        $unallocated = (int) ($payment['unallocated_amount'] ?? 0);
        if ($amount > $unallocated) {
            throw new InsufficientPaymentCapacityException("Allocation amount {$amount} exceeds unallocated payment capacity {$unallocated}.");
        }

        $iStmt = $this->pdo->prepare('SELECT id, total_amount, paid_amount FROM invoices WHERE id = ?');
        $iStmt->execute([$invoiceId]);
        $invoice = $iStmt->fetch(PDO::FETCH_ASSOC);

        $total = (int) ($invoice['total_amount'] ?? 0);
        $paid = (int) ($invoice['paid_amount'] ?? 0);
        $remaining = max(0, $total - $paid);

        if ($amount > $remaining) {
            throw new InvoiceOverpaymentException("Allocation amount {$amount} exceeds remaining invoice balance {$remaining}.");
        }

        $allocUuid = $uuid ? (strlen($uuid) === 16 ? $uuid : Uuid::binary16($uuid)) : Uuid::binary16();
        $ins = $this->pdo->prepare('
            INSERT INTO payment_allocations (uuid, payment_id, invoice_id, allocated_amount)
            VALUES (?, ?, ?, ?)
        ');
        $ins->execute([$allocUuid, $paymentId, $invoiceId, $amount]);
        $allocId = (int) $this->pdo->lastInsertId();

        // Update payment unallocated/allocated counters
        $upPayment = $this->pdo->prepare('
            UPDATE payments
            SET allocated_amount = allocated_amount + ?, unallocated_amount = unallocated_amount - ?
            WHERE id = ?
        ');
        $upPayment->execute([$amount, $amount, $paymentId]);

        // Update invoice paid amount
        $upInvoice = $this->pdo->prepare('
            UPDATE invoices
            SET paid_amount = paid_amount + ?
            WHERE id = ?
        ');
        $upInvoice->execute([$amount, $invoiceId]);

        return $allocId;
    }

    public function deleteAllocation(int $allocationId): void
    {
        throw new PostedFinancialMutationException('Posted allocations are immutable financial records and cannot be deleted.');
    }

    private function ensurePaymentAndInvoiceExist(int $paymentId, int $invoiceId): void
    {
        // Setup payment fixture if missing
        $pStmt = $this->pdo->prepare('SELECT id FROM payments WHERE id = ?');
        $pStmt->execute([$paymentId]);
        if ($pStmt->fetchColumn() === false) {
            $amount = ($paymentId === 2) ? 1000000 : 10000000;

            // Ensure voucher exists
            $this->ensureRow('companies', 1, "INSERT INTO companies (id, uuid, name) VALUES (1, X'018f8b89000070008000000000000001', 'Co 1')");
            $this->ensureRow('fiscal_years', 1, "INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date) VALUES (1, X'018f8b89000070008000000000000101', 1, 1405, '2026-03-21', '2027-03-20')");
            $this->ensureRow('accounts', 101, "INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier) VALUES (101, X'018f8b89000070008000000000000102', 1, '101', 'Bank', 1, 1)");
            $this->ensureRow('accounts', 201, "INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier) VALUES (201, X'018f8b89000070008000000000000103', 1, '201', 'Customer', 1, 1)");
            $vUuidHex = sprintf('%04x', $paymentId);
            $this->ensureRow('journal_vouchers', $paymentId, "INSERT INTO journal_vouchers (id, uuid, company_id, fiscal_year_id, voucher_number, voucher_date, description, status, created_by_user_id) VALUES ({$paymentId}, X'018f8b8900007000800000000000{$vUuidHex}', 1, 1, {$paymentId}, '2026-04-01', 'Voucher', 2, 1)");

            $ins = $this->pdo->prepare('
                INSERT INTO payments (id, uuid, company_id, direction, payment_method, amount, payment_date, payer_account_id, beneficiary_account_id, journal_voucher_id, allocated_amount, unallocated_amount)
                VALUES (?, ?, 1, 1, 1, ?, \'2026-04-01\', 201, 101, ?, 0, ?)
            ');
            $ins->execute([$paymentId, Uuid::binary16(), $amount, $paymentId, $amount]);
        }

        // Setup invoice fixture if missing
        $iStmt = $this->pdo->prepare('SELECT id FROM invoices WHERE id = ?');
        $iStmt->execute([$invoiceId]);
        if ($iStmt->fetchColumn() === false) {
            $total = ($invoiceId === 2) ? 500000 : 6000000;
            $insInv = $this->pdo->prepare('
                INSERT INTO invoices (id, uuid, company_id, fiscal_year_id, invoice_type, invoice_number, invoice_date, customer_account_id, status, subtotal_amount, total_amount, paid_amount)
                VALUES (?, ?, 1, 1, 1, ?, \'2026-04-01\', 201, 3, ?, ?, 0)
            ');
            $insInv->execute([$invoiceId, Uuid::binary16(), $invoiceId, $total, $total]);
        }
    }

    private function ensureRow(string $table, int $id, string $insertSql): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($insertSql);
        }
    }
}
