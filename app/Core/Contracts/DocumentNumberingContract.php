<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\InvalidBlockSizeException;

/**
 * Concurrency-Safe Sequential Document Numbering Contract.
 *
 * Invariants:
 * 1. Sequential, gap-free numbers within (company_id, fiscal_year_id)
 * 2. Atomic reservation with pessimistic locking
 * 3. Leased numbers cannot be recycled or decremented
 */
interface DocumentNumberingContract
{
    /**
     * Atomically lease the next sequential number for an official document/voucher.
     *
     * @param  string  $entityType  e.g., 'journal_voucher', 'invoice_sell', 'invoice_buy'
     * @return int Next allocated sequential number
     */
    public function allocateNextNumber(int $companyId, int $fiscalYearId, string $entityType): int;

    /**
     * Reserve a block range of sequential numbers for offline/distributed synchronization.
     *
     * @param  int  $blockSize  Number of sequential IDs to reserve (1 to 500)
     * @return array{0: int, 1: int} Reserved bounds [start, end]
     *
     * @throws InvalidBlockSizeException
     */
    public function leaseNumberRange(int $companyId, int $fiscalYearId, string $entityType, string $deviceId, int $blockSize): array;

    /**
     * Explicitly record a voided sequential number in the audit ledger without recycling.
     */
    public function recordVoidNumber(int $companyId, int $fiscalYearId, string $entityType, int $number, string $reason): void;
}
