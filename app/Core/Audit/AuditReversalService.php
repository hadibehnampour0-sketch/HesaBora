<?php

declare(strict_types=1);

namespace Dakhl\Core\Audit;

use Dakhl\Cleanroom\Core\Contracts\AuditReversalContract;
use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\InvalidVoucherStatusException;
use Dakhl\Cleanroom\Core\Exceptions\VoucherAlreadyReversedException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class AuditReversalService implements AuditReversalContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function reverseVoucher(int $voucherId, string $reversalDate, string $reason, int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT id, company_id, fiscal_year_id, voucher_number, status FROM journal_vouchers WHERE id = ?');
        $stmt->execute([$voucherId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $voucher) {
            throw new DomainException("Journal voucher ID {$voucherId} not found.");
        }

        $currentStatus = (int) $voucher['status'];
        if ($currentStatus === 3) {
            throw new VoucherAlreadyReversedException("Voucher ID {$voucherId} is already reversed and cannot be reversed again.");
        }

        if ($currentStatus !== 2) {
            throw new InvalidVoucherStatusException("Only posted vouchers (status 2) can be reversed. Current status: {$currentStatus}.");
        }

        $companyId = (int) $voucher['company_id'];
        $fiscalYearId = (int) $voucher['fiscal_year_id'];
        $origNumber = (int) $voucher['voucher_number'];

        // Fetch original lines
        $lStmt = $this->pdo->prepare('SELECT * FROM journal_voucher_lines WHERE voucher_id = ? ORDER BY line_number ASC');
        $lStmt->execute([$voucherId]);
        $lines = $lStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lines)) {
            throw new DomainException("Cannot reverse voucher {$voucherId} with no lines.");
        }

        // Allocate next sequential voucher number
        $numStmt = $this->pdo->prepare('
            SELECT COALESCE(MAX(voucher_number), 0) + 1
            FROM journal_vouchers
            WHERE company_id = ? AND fiscal_year_id = ? AND status >= 2
        ');
        $numStmt->execute([$companyId, $fiscalYearId]);
        $reversalNumber = (int) $numStmt->fetchColumn();

        $revDesc = "سند برگشت سند شماره {$origNumber} - {$reason}";
        $revUuid = Uuid::binary16();

        // Insert reversal voucher as posted (status 2)
        $insVoucher = $this->pdo->prepare('
            INSERT INTO journal_vouchers (uuid, company_id, fiscal_year_id, voucher_number, voucher_date, description, status, is_reversal, reversed_voucher_id, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, 2, 1, ?, ?)
        ');
        $insVoucher->execute([$revUuid, $companyId, $fiscalYearId, $reversalNumber, $reversalDate, $revDesc, $voucherId, $userId]);
        $reversalId = (int) $this->pdo->lastInsertId();

        // Insert inverted lines
        $insLine = $this->pdo->prepare('
            INSERT INTO journal_voucher_lines (uuid, voucher_id, line_number, account_id, description, debit_amount, credit_amount, currency_code, foreign_amount, exchange_rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($lines as $idx => $line) {
            $insLine->execute([
                Uuid::binary16(),
                $reversalId,
                $idx + 1,
                (int) $line['account_id'],
                $line['description'] ? "برگشت: {$line['description']}" : null,
                (int) $line['credit_amount'], // Invert: Debit becomes Credit
                (int) $line['debit_amount'],  // Invert: Credit becomes Debit
                $line['currency_code'] ?? 'IRR',
                $line['foreign_amount'] ?? null,
                $line['exchange_rate'] ?? null,
            ]);
        }

        // Update original voucher to status 3 (Reversed)
        $upStmt = $this->pdo->prepare('UPDATE journal_vouchers SET status = 3 WHERE id = ?');
        $upStmt->execute([$voucherId]);

        return $reversalId;
    }
}
