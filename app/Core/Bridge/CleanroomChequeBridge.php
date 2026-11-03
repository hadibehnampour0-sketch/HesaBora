<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use Dakhl\Cleanroom\Core\Contracts\ChequeInstrumentContract;
use Dakhl\Cleanroom\Core\Exceptions\DuplicateChequeException;
use Dakhl\Cleanroom\Core\Exceptions\InvalidChequeStateTransitionException;
use Illuminate\Validation\ValidationException;

final class CleanroomChequeBridge
{
    public function __construct(private ChequeInstrumentContract $chequeContract) {}

    /**
     * Register a new promissory cheque instrument.
     *
     * @throws ValidationException
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
    ): int {
        try {
            return $this->chequeContract->registerReceivedCheque(
                companyId: $companyId,
                fiscalYearId: $fiscalYearId,
                chequeNumber: $chequeNumber,
                sayadNumber: $sayadNumber,
                bankName: $bankName,
                dueDate: $dueDate,
                amount: $amount,
                customerAccountId: $customerAccountId,
                notesReceivableAccountId: $notesReceivableAccountId,
                userId: $userId,
                uuid: $uuid
            );
        } catch (DuplicateChequeException $e) {
            throw ValidationException::withMessages([
                'cheque_number' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Deposit cheque for bank collection.
     *
     * @throws ValidationException
     */
    public function depositCheque(int $chequeId, int $collectionAccountId, int $userId): void
    {
        try {
            $this->chequeContract->depositCheque($chequeId, $collectionAccountId, $userId);
        } catch (InvalidChequeStateTransitionException $e) {
            throw ValidationException::withMessages([
                'cheque' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Clear cheque upon bank clearance.
     *
     * @throws ValidationException
     */
    public function clearCheque(int $chequeId, int $bankAccountId, int $userId): int
    {
        try {
            return $this->chequeContract->clearCheque($chequeId, $bankAccountId, $userId);
        } catch (InvalidChequeStateTransitionException $e) {
            throw ValidationException::withMessages([
                'cheque' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Mark cheque as bounced.
     *
     * @throws ValidationException
     */
    public function bounceCheque(int $chequeId, string $reason, int $userId): int
    {
        try {
            return $this->chequeContract->bounceCheque($chequeId, $reason, $userId);
        } catch (InvalidChequeStateTransitionException $e) {
            throw ValidationException::withMessages([
                'cheque' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Cancel cheque.
     *
     * @throws ValidationException
     */
    public function cancelCheque(int $chequeId, string $reason, int $userId): void
    {
        try {
            $this->chequeContract->cancelCheque($chequeId, $reason, $userId);
        } catch (InvalidChequeStateTransitionException $e) {
            throw ValidationException::withMessages([
                'cheque' => [$e->getMessage()],
            ]);
        }
    }
}
