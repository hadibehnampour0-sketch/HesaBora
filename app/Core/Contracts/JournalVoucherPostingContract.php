<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Universal Double-Entry Journal Voucher Posting Contract.
 *
 * Invariants:
 * 1. SUM(lines.debit_amount) == SUM(lines.credit_amount)
 * 2. Every line must have (debit_amount > 0 XOR credit_amount > 0)
 * 3. Zero-amount lines are strictly prohibited
 * 4. Posted vouchers are immutable
 */
interface JournalVoucherPostingContract
{
    /**
     * Create and validate an unposted draft journal voucher.
     *
     * @param int $companyId
     * @param int $fiscalYearId
     * @param string $voucherDate Y-m-d format
     * @param string $description
     * @param array<int, array{account_id: int, description?: string, debit_amount: int, credit_amount: int, currency_code?: string, foreign_amount?: float, exchange_rate?: float}> $lines
     * @param int $userId
     * @param string|null $uuid Canonical UUIDv7
     * @return int Voucher ID
     * @throws \Dakhl\Cleanroom\Core\Exceptions\UnbalancedJournalVoucherException
     * @throws \Dakhl\Cleanroom\Core\Exceptions\InvalidLineAmountException
     */
    public function createDraft(
        int $companyId,
        int $fiscalYearId,
        string $voucherDate,
        string $description,
        array $lines,
        int $userId,
        ?string $uuid = null
    ): int;

    /**
     * Post an existing voucher, assigning an official sequence number and making it immutable.
     *
     * @throws \Dakhl\Cleanroom\Core\Exceptions\DomainException If unbalanced or period locked
     */
    public function postVoucher(int $voucherId, int $userId): void;

    /**
     * Verify whether a set of proposed lines satisfies double-entry mathematical balance.
     *
     * @param array<int, array{debit_amount: int, credit_amount: int}> $lines
     */
    public function verifyBalance(array $lines): bool;
}
