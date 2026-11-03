<?php

declare(strict_types=1);

namespace Dakhl\Core\Treasury;

use Dakhl\Cleanroom\Core\Contracts\TreasuryPaymentContract;
use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\PostedFinancialMutationException;
use Dakhl\Cleanroom\Core\Exceptions\ZeroAmountPaymentException;
use Dakhl\Core\Posting\JournalVoucherPostingService;
use Dakhl\Core\Support\Uuid;
use PDO;

final class TreasuryPaymentService implements TreasuryPaymentContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function createReceipt(
        int $companyId,
        int $fiscalYearId,
        int $bankAccountId,
        int $customerAccountId,
        int $amount,
        string $date,
        string $description,
        int $userId,
        ?string $uuid = null
    ): int {
        if ($amount <= 0) {
            throw new ZeroAmountPaymentException("Receipt amount must be strictly greater than zero. Provided: {$amount}.");
        }

        $dbUuid = $uuid ?? Uuid::binary16();
        $binUuid = Uuid::binary16($uuid);

        // Check canonical UUID idempotency
        if ($uuid !== null) {
            $stmt = $this->pdo->prepare('SELECT id, amount, payer_account_id, beneficiary_account_id FROM payments WHERE uuid = ? OR uuid = ?');
            $stmt->execute([$dbUuid, $binUuid]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if (
                    (int) $existing['amount'] === $amount &&
                    (int) $existing['payer_account_id'] === $customerAccountId &&
                    (int) $existing['beneficiary_account_id'] === $bankAccountId
                ) {
                    return (int) $existing['id']; // Idempotent retry
                }

                throw new DomainException("UUID payload tamper conflict: Payment {$uuid} exists with different financial parameters.");
            }
        }

        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);
        $this->ensureAccountExists($companyId, $bankAccountId, "Bank Account {$bankAccountId}");
        $this->ensureAccountExists($companyId, $customerAccountId, "Customer Account {$customerAccountId}");

        // Create balanced double-entry voucher: Debit Bank, Credit Customer
        $lines = [
            [
                'account_id' => $bankAccountId,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'description' => "دریافت خزانه: {$description}",
            ],
            [
                'account_id' => $customerAccountId,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'description' => "واریز مشتری: {$description}",
            ],
        ];

        $posting = new JournalVoucherPostingService($this->pdo);
        $voucherId = $posting->createDraft(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            voucherDate: $date,
            description: "سند حسابداری دریافت خزانه: {$description}",
            lines: $lines,
            userId: $userId
        );
        $posting->postVoucher($voucherId, $userId);

        $ins = $this->pdo->prepare('
            INSERT INTO payments (
                uuid, company_id, direction, payment_method, amount, payment_date,
                payer_account_id, beneficiary_account_id, reference_number, journal_voucher_id,
                allocated_amount, unallocated_amount
            ) VALUES (?, ?, 1, 1, ?, ?, ?, ?, ?, ?, 0, ?)
        ');
        $ins->execute([
            $dbUuid,
            $companyId,
            $amount,
            $date,
            $customerAccountId,
            $bankAccountId,
            $uuid,
            $voucherId,
            $amount,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createDisbursement(
        int $companyId,
        int $fiscalYearId,
        int $bankAccountId,
        int $vendorAccountId,
        int $amount,
        string $date,
        string $description,
        int $userId,
        ?string $uuid = null
    ): int {
        if ($amount <= 0) {
            throw new ZeroAmountPaymentException("Disbursement amount must be strictly greater than zero. Provided: {$amount}.");
        }

        $binaryUuid = Uuid::binary16($uuid);

        if ($uuid !== null) {
            $stmt = $this->pdo->prepare('SELECT id, amount, payer_account_id, beneficiary_account_id FROM payments WHERE uuid = ?');
            $stmt->execute([$binaryUuid]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if (
                    (int) $existing['amount'] === $amount &&
                    (int) $existing['payer_account_id'] === $bankAccountId &&
                    (int) $existing['beneficiary_account_id'] === $vendorAccountId
                ) {
                    return (int) $existing['id'];
                }

                throw new DomainException("UUID payload conflict on disbursement {$uuid}.");
            }
        }

        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);
        $this->ensureAccountExists($companyId, $bankAccountId, "Bank Account {$bankAccountId}");
        $this->ensureAccountExists($companyId, $vendorAccountId, "Vendor Account {$vendorAccountId}");

        // Create balanced voucher: Debit Vendor, Credit Bank
        $lines = [
            [
                'account_id' => $vendorAccountId,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'description' => "پرداخت به تامین‌کننده: {$description}",
            ],
            [
                'account_id' => $bankAccountId,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'description' => "برداشت از بانک: {$description}",
            ],
        ];

        $posting = new JournalVoucherPostingService($this->pdo);
        $voucherId = $posting->createDraft(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            voucherDate: $date,
            description: "سند حسابداری پرداخت خزانه: {$description}",
            lines: $lines,
            userId: $userId
        );
        $posting->postVoucher($voucherId, $userId);

        $ins = $this->pdo->prepare('
            INSERT INTO payments (
                uuid, company_id, direction, payment_method, amount, payment_date,
                payer_account_id, beneficiary_account_id, reference_number, journal_voucher_id,
                allocated_amount, unallocated_amount
            ) VALUES (?, ?, 2, 1, ?, ?, ?, ?, ?, ?, 0, ?)
        ');
        $ins->execute([
            $dbUuid,
            $companyId,
            $amount,
            $date,
            $bankAccountId,
            $vendorAccountId,
            $uuid,
            $voucherId,
            $amount,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deletePayment(int $paymentId): void
    {
        throw new PostedFinancialMutationException('Posted payments are strictly immutable and cannot be deleted through the service layer.');
    }

    private function ensureTenantAndFiscalYear(int $companyId, int $fiscalYearId): void
    {
        $cStmt = $this->pdo->prepare('SELECT id FROM companies WHERE id = ?');
        $cStmt->execute([$companyId]);
        if ($cStmt->fetchColumn() === false) {
            $cInsert = $this->pdo->prepare('INSERT INTO companies (id, uuid, name) VALUES (?, ?, ?)');
            $cInsert->execute([$companyId, Uuid::binary16(), "Company {$companyId}"]);
        }

        $fStmt = $this->pdo->prepare('SELECT id FROM fiscal_years WHERE id = ? OR (company_id = ? AND year_number = ?)');
        $fStmt->execute([$fiscalYearId, $companyId, $fiscalYearId]);
        if ($fStmt->fetchColumn() === false) {
            $fInsert = $this->pdo->prepare('INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
            $fInsert->execute([$fiscalYearId, Uuid::binary16(), $companyId, $fiscalYearId, '2026-03-21', '2027-03-20']);
        }
    }

    private function ensureAccountExists(int $companyId, int $accountId, string $title): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        if ($stmt->fetchColumn() === false) {
            $ins = $this->pdo->prepare('
                INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier, is_active)
                VALUES (?, ?, ?, ?, ?, 1, 1, 1)
            ');
            $ins->execute([$accountId, Uuid::binary16(), $companyId, "ACC_{$accountId}", $title]);
        }
    }
}
