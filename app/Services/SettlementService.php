<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SettlementInstallment;
use App\Models\SettlementObligation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CheckSettlement domain (D-070, locked before implementation).
 *
 * Three independent lifecycles that must never collapse into one:
 *  - Instrument: the Cheque's own status machine (ChequeService) -- NEVER
 *    touched here.
 *  - Obligation: the outstanding debt (SettlementObligation), which may
 *    originate from a cheque but is conceptually independent of it.
 *  - Settlement: how the obligation actually gets paid down
 *    (SettlementInstallment schedule + real Payment allocations).
 *
 * Creating an obligation or an installment schedule NEVER posts an
 * accounting entry -- only a real Payment, allocated through the same
 * PaymentAllocation ledger Invoice payments use (PaymentService::allocate()),
 * can create a financial effect. Paid/settled amounts are always computed
 * live from that ledger, never stored/denormalized, so there is nothing to
 * fall out of sync.
 */
class SettlementService
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function createObligationForCheque(Cheque $cheque, ?User $user = null): SettlementObligation
    {
        return $this->createObligation($cheque, (float) $cheque->amount, $user, 'cheque_id', __('A settlement obligation already exists for this cheque.'));
    }

    /**
     * P3.2 (Payment Terms): the invoice-sourced counterpart to
     * createObligationForCheque() -- reuses the exact same generic
     * SettlementObligation/SettlementInstallment ledger D-070 designed for
     * this ("a future release can attach an obligation to a returned
     * cheque, a manual customer-debt agreement, etc. without a schema
     * change"), rather than a second, parallel installment system.
     */
    public function createObligationForInvoice(Invoice $invoice, ?User $user = null): SettlementObligation
    {
        return $this->createObligation($invoice, $this->paymentService->payableAmount($invoice), $user, 'invoice_id', __('A settlement obligation already exists for this invoice.'));
    }

    /**
     * Concurrency: two genuinely concurrent calls for the SAME source could
     * both pass a plain exists() check before either commits, silently
     * doubling the obligation. Locks the source row itself (it always
     * already exists -- Cheque/Invoice creation precedes obligation
     * creation) so a second concurrent call is forced to wait behind the
     * first rather than racing; the migration's own unique constraint on
     * [source_type, source_id] is the final DB-level collision guard.
     */
    private function createObligation(Cheque|Invoice $source, float $amount, ?User $user, string $errorField, string $errorMessage): SettlementObligation
    {
        return DB::transaction(function () use ($source, $amount, $user, $errorField, $errorMessage) {
            $source::query()->lockForUpdate()->findOrFail($source->getKey());

            $exists = SettlementObligation::where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([$errorField => [$errorMessage]]);
            }

            return SettlementObligation::create([
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'original_amount' => $amount,
                'company_id' => getActiveCompany(),
                'created_by' => $user?->id,
            ]);
        });
    }

    /**
     * Workstream S (Concurrency), Phase 1 finding: this used to validate
     * the new plan's total against remainingAmount() (original_amount
     * minus what has actually been PAID), never against what is already
     * SCHEDULED via existing, still-unpaid installments -- so a second
     * installment plan for an obligation that already has one (a
     * double-submitted form, or any future caller that doesn't first
     * check for existing installments) passed every time before any
     * payment happened, doubling the obligation's scheduled total far
     * beyond what is actually owed. This was reproducible with a plain
     * sequential call, no concurrency required (see
     * tests/Feature/CheckSettlementTest.php).
     *
     * Fixed by validating against the obligation's remaining UNSCHEDULED
     * headroom (original_amount minus the sum of ALL existing
     * installments, paid or not) instead, and by moving the whole
     * validate-then-write sequence inside a locked transaction so two
     * genuinely concurrent calls can no longer race here either.
     *
     * @param  array<int, array{due_date: string, amount: float}>  $installments
     * @return Collection<int, SettlementInstallment>
     */
    public function createInstallmentPlan(SettlementObligation $obligation, array $installments): Collection
    {
        return DB::transaction(function () use ($obligation, $installments) {
            $lockedObligation = SettlementObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            $total = array_sum(array_map(fn ($row) => (float) $row['amount'], $installments));
            $alreadyScheduled = (float) $lockedObligation->installments()->sum('amount');
            $unscheduledHeadroom = (float) $lockedObligation->original_amount - $alreadyScheduled;

            if ($total > $unscheduledHeadroom + 0.001) {
                throw ValidationException::withMessages([
                    'installments' => [__('The installment plan total exceeds the remaining amount of this obligation.')],
                ]);
            }

            return collect($installments)->map(fn ($row) => SettlementInstallment::create([
                'obligation_id' => $lockedObligation->id,
                'due_date' => $row['due_date'],
                'amount' => $row['amount'],
            ]));
        });
    }

    public function settledAmount(SettlementObligation $obligation): float
    {
        $installmentIds = $obligation->installments()->pluck('id');
        if ($installmentIds->isEmpty()) {
            return 0.0;
        }

        return (float) PaymentAllocation::where('allocatable_type', SettlementInstallment::class)
            ->whereIn('allocatable_id', $installmentIds)
            ->sum('amount');
    }

    public function remainingAmount(SettlementObligation $obligation): float
    {
        return max((float) $obligation->original_amount - $this->settledAmount($obligation), 0.0);
    }

    public function isSettled(SettlementObligation $obligation): bool
    {
        return $this->remainingAmount($obligation) <= 0.005;
    }

    public function installmentPaidAmount(SettlementInstallment $installment): float
    {
        return (float) $installment->allocations()->sum('amount');
    }

    public function installmentRemainingAmount(SettlementInstallment $installment): float
    {
        return max((float) $installment->amount - $this->installmentPaidAmount($installment), 0.0);
    }

    public function installmentStatus(SettlementInstallment $installment): string
    {
        $paid = $this->installmentPaidAmount($installment);
        $amount = (float) $installment->amount;

        return match (true) {
            $paid <= 0.0 => 'pending',
            $paid + 0.001 < $amount => 'partial',
            default => 'paid',
        };
    }

    /**
     * Record a real payment and allocate it to one installment in a single
     * step: createCreditPayment() (the one Payment Engine, same as invoice
     * advance payments) followed by allocate() against this installment.
     */
    public function recordInstallmentPayment(User $user, SettlementInstallment $installment, float $amount, array $paymentData): PaymentAllocation
    {
        return DB::transaction(function () use ($user, $installment, $amount, $paymentData) {
            $customer = $this->resolveCustomer($installment->obligation);

            $payment = $this->paymentService->createCreditPayment($user, $customer, [
                'amount' => $amount,
                'subject_id' => $paymentData['subject_id'],
                'date' => $paymentData['date'] ?? null,
                'description' => $paymentData['description'] ?? __('Settlement payment'),
                'reference_number' => $paymentData['reference_number'] ?? null,
            ]);

            return $this->settleInstallment($user, $installment, $payment, $amount, $paymentData['reference'] ?? null);
        });
    }

    /**
     * Allocate an existing (already-posted) Payment to one installment.
     * Kept separate from recordInstallmentPayment() so a credit payment
     * already on hand (e.g. from Payment Allocation's own credit-payment
     * flow) can be applied here too, through the exact same ledger.
     */
    public function settleInstallment(User $user, SettlementInstallment $installment, Payment $payment, float $amount, ?string $reference = null): PaymentAllocation
    {
        return DB::transaction(function () use ($user, $installment, $payment, $amount, $reference) {
            $lockedInstallment = SettlementInstallment::query()->lockForUpdate()->findOrFail($installment->id);

            if ($amount > $this->installmentRemainingAmount($lockedInstallment) + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => [__('Allocation amount exceeds the remaining amount of this installment.')],
                ]);
            }

            $obligation = $lockedInstallment->obligation;
            if ($amount > $this->remainingAmount($obligation) + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => [__('Allocation amount exceeds the remaining amount of the settlement obligation.')],
                ]);
            }

            return $this->paymentService->allocate($user, $payment, $lockedInstallment, $amount, $reference);
        });
    }

    /**
     * D-090 final disposition (Final Marathon Closure Pass): this method
     * used to hard-delete the PaymentAllocation directly, bypassing
     * PaymentService::removeAllocation()'s D-084 immutability guard. D-084
     * never carved out a Settlement-specific exception for this the way it
     * explicitly did for Cheque's registration-payment deletion (D-070) --
     * its own text lists this as an "identified but not closed" bypass, not
     * a sanctioned one. D-084.2 ("PaymentAllocation becomes financially
     * effective at successful allocate(). Same no-new-state rule") and
     * D-084.3 ("a posted Payment/PaymentAllocation must not be deleted...
     * regardless of caller") apply to a SettlementInstallment allocation
     * exactly as much as an Invoice one -- there was never a reasoned basis
     * for Settlement being different, only a scope-limiting note that
     * nothing controller-reachable called this at the time D-084 was
     * locked. Bringing this into compliance with the already-decided rule
     * -- not inventing a new one -- resolves D-090 conclusively: a real,
     * governed Settlement reversal/correction capability remains a future,
     * separate architecture decision (D-084.5), exactly like every other
     * domain's reversal story, not a bespoke exception for this one.
     */
    public function unsettleInstallment(PaymentAllocation $allocation): void
    {
        if ($allocation->allocatable_type !== SettlementInstallment::class) {
            return;
        }

        $this->paymentService->removeAllocation($allocation);
    }

    /**
     * No-op hook: paid/settled amounts are always computed live from the
     * allocation ledger, so there is nothing to write back here. Exists so
     * PaymentService::deletePayment() has a safe place to notify this domain
     * after removing allocations, without depending on Settlement internals.
     */
    public function resyncInstallment(int $installmentId): void
    {
        // Intentionally empty -- see docblock.
    }

    private function resolveCustomer(SettlementObligation $obligation): Customer
    {
        $source = $obligation->source;

        if ($source instanceof Cheque && $source->customer) {
            return $source->customer;
        }

        if ($source instanceof Invoice && $source->customer) {
            return $source->customer;
        }

        throw ValidationException::withMessages([
            'source' => [__('Cannot determine the customer for this settlement obligation.')],
        ]);
    }
}
