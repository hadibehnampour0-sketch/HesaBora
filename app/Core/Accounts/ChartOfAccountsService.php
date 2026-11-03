<?php

declare(strict_types=1);

namespace Dakhl\Core\Accounts;

use Dakhl\Cleanroom\Core\Contracts\SubjectHierarchyContract;
use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\DuplicateAccountCodeException;
use Dakhl\Cleanroom\Core\Exceptions\ValidationException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class ChartOfAccountsService implements SubjectHierarchyContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function createAccount(
        int $companyId,
        string $code,
        string $title,
        int $accountTier,
        int $accountNature,
        ?int $parentId = null,
        ?string $description = null
    ): int {
        if ($accountTier < 1 || $accountTier > 3) {
            throw new ValidationException("Invalid account tier: {$accountTier}. Tier must be 1, 2, or 3.");
        }

        // Tier 1 must have no parent
        if ($accountTier === 1 && $parentId !== null) {
            throw new ValidationException('Tier 1 root account cannot have a parent.');
        }

        // Tier 2 & 3 must have a parent
        if ($accountTier > 1 && $parentId === null) {
            throw new ValidationException("Tier {$accountTier} account must specify a valid parent.");
        }

        $effectiveNature = $accountNature;

        if ($parentId !== null) {
            $stmt = $this->pdo->prepare('SELECT id, company_id, account_tier, account_nature FROM accounts WHERE id = ?');
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $parent) {
                throw new DomainException("Parent account ID {$parentId} does not exist.");
            }

            if ((int) $parent['company_id'] !== $companyId) {
                throw new DomainException('Cannot attach account to parent of different company.');
            }

            $parentTier = (int) $parent['account_tier'];
            if ($parentTier !== $accountTier - 1) {
                throw new ValidationException("Invalid hierarchy tier skipping: Tier {$accountTier} cannot have Tier {$parentTier} as parent.");
            }

            // Inherit account nature from parent node
            $effectiveNature = (int) $parent['account_nature'];
        }

        // Ensure company exists
        $cStmt = $this->pdo->prepare('SELECT id FROM companies WHERE id = ?');
        $cStmt->execute([$companyId]);
        if ($cStmt->fetchColumn() === false) {
            $cInsert = $this->pdo->prepare('INSERT INTO companies (id, uuid, name) VALUES (?, ?, ?)');
            $cInsert->execute([$companyId, Uuid::binary16(), "Company {$companyId}"]);
        }

        // Check unique code within company
        $checkStmt = $this->pdo->prepare('SELECT id FROM accounts WHERE company_id = ? AND code = ?');
        $checkStmt->execute([$companyId, $code]);
        if ($checkStmt->fetchColumn() !== false) {
            throw new DuplicateAccountCodeException("Account code '{$code}' already exists for company {$companyId}.");
        }

        $uuid = Uuid::binary16();
        $insert = $this->pdo->prepare('
            INSERT INTO accounts (uuid, company_id, code, title, account_nature, account_tier, parent_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ');
        $insert->execute([$uuid, $companyId, $code, $title, $effectiveNature, $accountTier, $parentId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function wouldCauseCycle(int $accountId, int $targetParentId): bool
    {
        if ($accountId === $targetParentId) {
            return true;
        }

        $currentParentId = $targetParentId;
        while ($currentParentId !== null) {
            if ($currentParentId === $accountId) {
                return true;
            }

            $stmt = $this->pdo->prepare('SELECT parent_id FROM accounts WHERE id = ?');
            $stmt->execute([$currentParentId]);
            $res = $stmt->fetchColumn();

            if ($res === false || $res === null) {
                break;
            }
            $currentParentId = (int) $res;
        }

        return false;
    }

    public function updateParent(int $accountId, ?int $newParentId): void
    {
        $stmt = $this->pdo->prepare('SELECT id, company_id, account_tier FROM accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $account) {
            throw new DomainException("Account ID {$accountId} does not exist.");
        }

        if ($newParentId !== null) {
            if ($this->wouldCauseCycle($accountId, $newParentId)) {
                throw new DomainException("Attaching account {$accountId} to {$newParentId} would cause a circular dependency cycle.");
            }

            $pStmt = $this->pdo->prepare('SELECT id, company_id, account_tier, account_nature FROM accounts WHERE id = ?');
            $pStmt->execute([$newParentId]);
            $parent = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (! $parent) {
                throw new DomainException("Target parent account ID {$newParentId} does not exist.");
            }

            if ((int) $parent['company_id'] !== (int) $account['company_id']) {
                throw new DomainException('Cannot attach account to parent of different company.');
            }

            $parentTier = (int) $parent['account_tier'];
            $accountTier = (int) $account['account_tier'];
            if ($parentTier !== $accountTier - 1) {
                throw new DomainException("Tier skipping forbidden: Tier {$accountTier} cannot be attached to Tier {$parentTier}.");
            }

            $update = $this->pdo->prepare('UPDATE accounts SET parent_id = ?, account_nature = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $update->execute([$newParentId, (int) $parent['account_nature'], $accountId]);
        } else {
            if ((int) $account['account_tier'] !== 1) {
                throw new DomainException('Only Tier 1 accounts can have null parent.');
            }

            $update = $this->pdo->prepare('UPDATE accounts SET parent_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $update->execute([$accountId]);
        }
    }

    public function calculateBalance(int $accountId, int $fiscalYearId, bool $includeDescendants = true): int
    {
        $accountIds = [$accountId];

        if ($includeDescendants) {
            $queue = [$accountId];
            while (! empty($queue)) {
                $curr = array_shift($queue);
                $stmt = $this->pdo->prepare('SELECT id FROM accounts WHERE parent_id = ?');
                $stmt->execute([$curr]);
                $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($children as $childId) {
                    $childIdInt = (int) $childId;
                    $accountIds[] = $childIdInt;
                    $queue[] = $childIdInt;
                }
            }
        }

        $accountIds = array_unique($accountIds);
        if (empty($accountIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $sql = "
            SELECT 
                COALESCE(SUM(l.debit_amount), 0) AS total_debit,
                COALESCE(SUM(l.credit_amount), 0) AS total_credit
            FROM journal_voucher_lines l
            INNER JOIN journal_vouchers v ON v.id = l.voucher_id
            WHERE v.fiscal_year_id = ?
              AND l.account_id IN ({$placeholders})
        ";

        $params = array_merge([$fiscalYearId], $accountIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return 0;
        }

        $debit = (int) $row['total_debit'];
        $credit = (int) $row['total_credit'];

        return $debit - $credit;
    }
}
