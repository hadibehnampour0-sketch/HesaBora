<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\DTO;

/**
 * Authoritative financial posting configuration DTO.
 * Resolves default accounting ledger accounts for automated transaction builders.
 */
final readonly class PostingConfiguration
{
    public function __construct(
        public int $salesRevenueAccountId,
        public int $vatPayableAccountId,
        public ?int $merchandiseInventoryAccountId = null,
        public ?int $vatReceivableAccountId = null,
        public ?int $cogsExpenseAccountId = null,
        public ?int $retainedEarningsAccountId = null,
        public ?int $depreciationExpenseAccountId = null,
        public ?int $accumulatedDepreciationAccountId = null
    ) {}
}
