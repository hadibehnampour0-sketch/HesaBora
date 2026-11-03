<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\InvalidVoucherStatusException;
use Dakhl\Cleanroom\Core\Exceptions\VoucherAlreadyReversedException;

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
     * @param  string  $reversalDate  Y-m-d
     * @return int Reversal Voucher ID
     *
     * @throws VoucherAlreadyReversedException
     * @throws InvalidVoucherStatusException
     */
    public function reverseVoucher(int $voucherId, string $reversalDate, string $reason, int $userId): int;
}
