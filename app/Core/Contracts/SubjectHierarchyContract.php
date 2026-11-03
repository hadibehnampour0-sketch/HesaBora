<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\DuplicateAccountCodeException;
use Dakhl\Cleanroom\Core\Exceptions\ValidationException;

/**
 * Chart of Accounts (COA) 3-Tier Hierarchy & Balance Contract.
 *
 * Tiers:
 * 1 = Group (کل)
 * 2 = General (معین)
 * 3 = Subsidiary (تفصیلی)
 */
interface SubjectHierarchyContract
{
    /**
     * Create a new chart of accounts node.
     *
     * @throws ValidationException
     * @throws DuplicateAccountCodeException
     */
    public function createAccount(
        int $companyId,
        string $code,
        string $title,
        int $accountTier,
        int $accountNature,
        ?int $parentId = null,
        ?string $description = null
    ): int;

    /**
     * Verify whether attaching node to target parent would cause a circular reference cycle.
     */
    public function wouldCauseCycle(int $accountId, int $targetParentId): bool;

    /**
     * Update parent of an account node, enforcing cycle prevention and company scoping.
     *
     * @throws DomainException If cycle detected or company mismatch
     */
    public function updateParent(int $accountId, ?int $newParentId): void;

    /**
     * Calculate net balance (sum of debits - sum of credits) for an account across a fiscal year.
     *
     * @param  bool  $includeDescendants  If true, recursively sums all child subsidiary accounts.
     * @return int Net balance in Rials (positive = Debit balance, negative = Credit balance)
     */
    public function calculateBalance(int $accountId, int $fiscalYearId, bool $includeDescendants = true): int;
}
