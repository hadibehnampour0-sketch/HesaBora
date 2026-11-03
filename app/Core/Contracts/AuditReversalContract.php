<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Mirror Audit Reversals Engine Contract.
 *
 * Generates an exact inverted journal voucher and marks the original as reversed.
 */
interface AuditReversalContract
{
    /**
     * Generate an exact mirror audit reversal voucher.
     *
     * Reversal inverts debits and credits and updates original voucher status to 3 (Reversed).
     *
     * @param int $voucherId
     * @param string $reversalDate Y-m-d
     * @param string $reason
     * @param int $userId
     * @return int Reversal Voucher ID
     * @throws \Dakhl\Cleanroom\Core\Exceptions\VoucherAlreadyReversedException
     * @throws \Dakhl\Cleanroom\Core\Exceptions\InvalidVoucherStatusException
     */
    public function reverseVoucher(int $voucherId, string $reversalDate, string $reason, int $userId): int;
}
