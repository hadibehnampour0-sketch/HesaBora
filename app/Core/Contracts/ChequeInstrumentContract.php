<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

use Dakhl\Cleanroom\Core\Exceptions\DuplicateChequeException;

/**
 * Cheque Commercial Instrument Lifecycle Contract.
 *
 * 8-State state machine independent of invoice allocations.
 */
interface ChequeInstrumentContract
{
    /**
     * Register a new promissory cheque instrument.
     *
     * @throws DuplicateChequeException
     */
    public function registerReceivedCheque(
        int $companyId,
        int $fiscalYearId,
        string $chequeNumber,
        string $sayadNumber,
        string $bankName,
        string $dueDate,
        int $amount,
        int $customerAccountId,
        int $notesReceivableAccountId,
        int $userId,
        ?string $uuid = null
    ): int;

    /**
     * Deposit cheque to bank account for collection.
     */
    public function depositCheque(int $chequeId, int $collectionAccountId, int $userId): void;

    /**
     * Clear cheque upon bank clearance.
     *
     * @return int Clearing Journal Voucher ID
     */
    public function clearCheque(int $chequeId, int $bankAccountId, int $userId): int;

    /**
     * Mark cheque as bounced / dishonored.
     *
     * @return int Dishonor Journal Voucher ID
     */
    public function bounceCheque(int $chequeId, string $reason, int $userId): int;

    /**
     * Cancel a registered or dishonored cheque.
     */
    public function cancelCheque(int $chequeId, string $reason, int $userId): void;
}
