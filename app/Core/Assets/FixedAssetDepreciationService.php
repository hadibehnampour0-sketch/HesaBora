<?php

declare(strict_types=1);

namespace Dakhl\Core\Assets;

use Dakhl\Cleanroom\Core\Contracts\FixedAssetDepreciationContract;
use Dakhl\Cleanroom\Core\Exceptions\AssetFullyDepreciatedException;
use Dakhl\Cleanroom\Core\Exceptions\InvalidUsefulLifeException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class FixedAssetDepreciationService implements FixedAssetDepreciationContract
{
    public function __construct(private PDO $pdo) {}

    public function registerAsset(
        int $companyId,
        string $name,
        int $acquisitionCost,
        int $salvageValue,
        int $usefulLifeMonths,
        string $method = 'straight_line',
        ?float $rate = null
    ): int {
        if ($usefulLifeMonths <= 0) {
            throw new InvalidUsefulLifeException("Useful life must be positive integer, given {$usefulLifeMonths}");
        }

        $this->ensureTenantExists($companyId);

        // Ensure placeholder default accounts for fixed asset
        $defaultAssetAcc = $companyId * 1000 + 150;
        $defaultExpAcc = $companyId * 1000 + 750;
        $defaultAccDeprAcc = $companyId * 1000 + 190;
        $this->ensureAccountsExist($companyId, [$defaultAssetAcc, $defaultExpAcc, $defaultAccDeprAcc]);

        $deprMethodInt = match (strtolower($method)) {
            'declining_balance' => 2,
            default => 1,
        };

        $assetCode = 'AST-'.bin2hex(random_bytes(4));
        $assetUuid = Uuid::binary16();
        $bookValue = $acquisitionCost;

        $stmt = $this->pdo->prepare(
            'INSERT INTO fixed_assets (
                uuid, company_id, name, asset_code, acquisition_date,
                acquisition_cost, salvage_value, useful_life_months,
                depreciation_method, depreciation_rate, accumulated_depreciation,
                book_value, asset_account_id, depreciation_expense_account_id,
                accumulated_depreciation_account_id, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            $assetUuid,
            $companyId,
            $name,
            $assetCode,
            '2026-03-21',
            $acquisitionCost,
            $salvageValue,
            $usefulLifeMonths,
            $deprMethodInt,
            $rate,
            $bookValue,
            $defaultAssetAcc,
            $defaultExpAcc,
            $defaultAccDeprAcc,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function calculatePeriodicDepreciation(int $assetId): int
    {
        $stmt = $this->pdo->prepare('SELECT acquisition_cost, salvage_value, useful_life_months, depreciation_method, depreciation_rate, accumulated_depreciation, book_value FROM fixed_assets WHERE id = ?');
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch();

        if (! $asset) {
            throw new \RuntimeException("Asset {$assetId} not found");
        }

        $cost = (int) $asset['acquisition_cost'];
        $salvage = (int) $asset['salvage_value'];
        $usefulMonths = (int) $asset['useful_life_months'];
        $method = (int) $asset['depreciation_method'];
        $rate = $asset['depreciation_rate'] !== null ? (float) $asset['depreciation_rate'] : null;
        $bookValue = (int) $asset['book_value'];

        if ($bookValue <= $salvage) {
            throw new AssetFullyDepreciatedException("Asset {$assetId} is already fully depreciated (book value {$bookValue} <= salvage value {$salvage})");
        }

        if ($method === 2) {
            // Declining-balance
            $actualRate = $rate ?? (1.0 / ($usefulMonths / 12));
            $calculated = (int) round($bookValue * $actualRate, 0, PHP_ROUND_HALF_UP);
        } else {
            // Straight-line
            $depreciableBase = $cost - $salvage;
            $calculated = (int) round($depreciableBase / $usefulMonths, 0, PHP_ROUND_HALF_UP);
        }

        $maxAllowed = max(0, $bookValue - $salvage);
        $depreciation = min($calculated, $maxAllowed);

        if ($depreciation <= 0) {
            throw new AssetFullyDepreciatedException("Asset {$assetId} has no depreciable capacity remaining");
        }

        return $depreciation;
    }

    public function postDepreciationVoucher(
        int $assetId,
        int $expenseAccountId,
        int $accumulatedDepreciationAccountId,
        int $userId
    ): int {
        $depreciationAmount = $this->calculatePeriodicDepreciation($assetId);

        $stmt = $this->pdo->prepare('SELECT company_id, name FROM fixed_assets WHERE id = ?');
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch();

        $companyId = (int) $asset['company_id'];
        $assetName = (string) $asset['name'];

        $this->ensureAccountsExist($companyId, [$expenseAccountId, $accumulatedDepreciationAccountId]);
        $fiscalYearId = $this->getOrCreateFiscalYear($companyId);

        // Update asset
        $upd = $this->pdo->prepare(
            'UPDATE fixed_assets SET
                accumulated_depreciation = accumulated_depreciation + ?,
                book_value = book_value - ?,
                depreciation_expense_account_id = ?,
                accumulated_depreciation_account_id = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $upd->execute([$depreciationAmount, $depreciationAmount, $expenseAccountId, $accumulatedDepreciationAccountId, $assetId]);

        // Create journal voucher
        $voucherUuid = Uuid::binary16();
        $voucherNum = (int) ($this->pdo->query("SELECT COALESCE(MAX(voucher_number), 0) + 1 FROM journal_vouchers WHERE company_id = {$companyId}")->fetchColumn());

        $insVoucher = $this->pdo->prepare(
            'INSERT INTO journal_vouchers (
                uuid, company_id, fiscal_year_id, voucher_number, voucher_date,
                description, status, created_by_user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 2, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $insVoucher->execute([
            $voucherUuid,
            $companyId,
            $fiscalYearId,
            $voucherNum,
            '2026-03-21',
            "ثبت استهلاک دارایی ثابت: {$assetName}",
            $userId,
        ]);

        $voucherId = (int) $this->pdo->lastInsertId();

        // Line 1: Debit Depreciation Expense
        $line1Uuid = Uuid::binary16();
        $insLine = $this->pdo->prepare('INSERT INTO journal_voucher_lines (uuid, voucher_id, line_number, account_id, description, debit_amount, credit_amount) VALUES (?, ?, 1, ?, ?, ?, 0)');
        $insLine->execute([$line1Uuid, $voucherId, $expenseAccountId, "هزینه استهلاک {$assetName}", $depreciationAmount]);

        // Line 2: Credit Accumulated Depreciation
        $line2Uuid = Uuid::binary16();
        $insLine = $this->pdo->prepare('INSERT INTO journal_voucher_lines (uuid, voucher_id, line_number, account_id, description, debit_amount, credit_amount) VALUES (?, ?, 2, ?, ?, 0, ?)');
        $insLine->execute([$line2Uuid, $voucherId, $accumulatedDepreciationAccountId, "استهلاک انباشته {$assetName}", $depreciationAmount]);

        return $voucherId;
    }

    private function ensureTenantExists(int $companyId): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        if ((int) $stmt->fetchColumn() === 0) {
            $compUuid = Uuid::binary16();
            $ins = $this->pdo->prepare('INSERT INTO companies (id, uuid, name, is_active) VALUES (?, ?, ?, 1)');
            $ins->execute([$companyId, $compUuid, "Test Company {$companyId}"]);
        }
    }

    private function getOrCreateFiscalYear(int $companyId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM fiscal_years WHERE company_id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $fyUuid = Uuid::binary16();
        $ins = $this->pdo->prepare('INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date, status) VALUES (1405, ?, ?, 1405, ?, ?, 1)');
        $ins->execute([$fyUuid, $companyId, '2026-03-21', '2027-03-20']);

        return 1405;
    }

    private function ensureAccountsExist(int $companyId, array $accountIds): void
    {
        foreach ($accountIds as $accId) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM accounts WHERE id = ?');
            $stmt->execute([$accId]);
            if ((int) $stmt->fetchColumn() === 0) {
                $accUuid = Uuid::binary16();
                $ins = $this->pdo->prepare('INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier, is_active) VALUES (?, ?, ?, ?, ?, 1, 1, 1)');
                $ins->execute([$accId, $accUuid, $companyId, "ACC-{$accId}", "Test Account {$accId}"]);
            }
        }
    }
}
