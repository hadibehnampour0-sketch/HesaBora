<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function settlementSubjectIds(): array
    {
        $bankRootId = (int) config('amir.bank');
        $cashBookId = (int) config('amir.cash_book');

        $bankSubjectIds = BankAccount::query()->whereNotNull('subject_id')->pluck('subject_id')->all();
        $cashSubtreeIds = $cashBookId ? (Subject::find($cashBookId)?->getAllDescendantIds() ?? []) : [];
        $ids = array_map('intval', array_merge($bankSubjectIds, $cashSubtreeIds));
        $ids = array_diff($ids, [$bankRootId, $cashBookId]);

        return array_values(array_unique($ids));
    }

    public function settlementSubjects(): Collection
    {
        $ids = $this->settlementSubjectIds();

        if (empty($ids)) {
            return new Collection;
        }

        return Subject::query()->with('parent')->whereIn('id', $ids)->orderBy('code')->get();
    }

    public function paidAmount(Invoice $invoice, ?Payment $except = null): float
    {
        $direct = (float) $invoice->payments()->whereNotNull('document_id')->when($except, fn ($query) => $query->where('id', '!=', $except->id))->sum('amount');
        $allocated = (float) $invoice->paymentAllocations()->when($except, fn ($query) => $query->where('payment_id', '!=', $except->id))->sum('amount');

        return $direct + $allocated;
    }

    public function payableAmount(Invoice $invoice): float
    {
        $ancillaryCostsAmount = $invoice->invoice_type === InvoiceType::BUY
            ? (float) $invoice->ancillaryCosts()->where('status', InvoiceStatus::APPROVED)->sum('amount')
            : 0.0;

        return (float) $invoice->amount + $ancillaryCostsAmount;
    }

    public function remainingAmount(Invoice $invoice, ?Payment $except = null): float
    {
        return max($this->payableAmount($invoice) - $this->paidAmount($invoice, $except), 0.0);
    }

    public function validateInvoicePayment(Invoice $invoice, array $data = [], ?Payment $except = null): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        $editingSettledPayment = $except && $invoice->status->isApprovedOrSettled();
        if (! $invoice->status->isApproved() && ! $invoice->status->isPartiallyPaid() && ! $editingSettledPayment) {
            $decision->addMessage('error', __('The invoice must be approved before recording a payment.'));
        }

        if ($this->remainingAmount($invoice, $except) <= 0) {
            $decision->addMessage('error', __('This invoice is fully paid.'));
        }

        if (isset($data['subject_id']) && ! in_array((int) $data['subject_id'], $this->settlementSubjectIds(), true)) {
            $decision->addMessage('error', __('The selected settlement account is not a valid bank or cash subject.'));
        }

        if (isset($data['amount']) && $data['amount'] > $this->remainingAmount($invoice, $except) + 0.001) {
            $decision->addMessage('error', __('Payment amount exceeds the remaining balance of the invoice.'));
        }

        if (! empty($data['date']) && $invoice->date
            && Carbon::parse($data['date'])->startOfDay()->lt($invoice->date->copy()->startOfDay())) {
            $decision->addMessage('error', __('The payment date cannot be earlier than the invoice date.'));
        }

        return $decision;
    }

    /**
     * Concurrency (Workstream S, Phase 1): two genuinely concurrent
     * requests recording a payment against the SAME invoice, each within
     * the invoice's remaining balance individually, could jointly overpay
     * it -- nothing here locked the invoice or otherwise serialized
     * against another payment/allocation landing on it while this
     * method's own balance check ran. Reproduced with two real, separate
     * MariaDB connections/transactions (SQLite `:memory:` cannot even
     * represent this race, since each connection is its own private
     * database) -- see tests/Feature/InvoicePaymentBalanceRaceTest.php.
     * Fixed with a row lock on the invoice, re-read into $invoice's OWN
     * attributes (setRawAttributes(), not a separate instance) so every
     * check below sees fresh, lock-protected state.
     */
    public function createPayment(User $user, Invoice $invoice, array $data): InvoiceStatusDecision
    {
        return DB::transaction(function () use ($user, $invoice, $data) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->setRawAttributes($locked->getAttributes(), true);

            $decision = $this->validateInvoicePayment($invoice, $data);
            if ($decision->hasErrors()) {
                return $decision;
            }

            $subjectId = (int) $data['subject_id'];
            $date = $data['date'] ?? now()->toDateString();
            $document = $this->paymentDocument($user, $invoice, $subjectId, $data['amount'], $date);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payer_id' => $invoice->customer_id,
                'amount' => $data['amount'],
                'date' => $date,
                'description' => $data['description'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'document_id' => $document->id,
                'settlement_subject_id' => $subjectId,
                'creator_id' => $user->id,
            ]);

            DocumentService::syncDocumentable($document, $payment);

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }

    /**
     * Concurrency (Workstream S, Phase 2): identical race to
     * createPayment()/allocatePayment() (CRITICAL finding #7) -- two
     * cheques registered against the SAME invoice by two genuinely
     * concurrent requests (via `ChequeService::register()`/`update()`,
     * both of which already run this inside their own `DB::transaction()`)
     * could each individually pass this balance check and jointly overpay
     * the invoice. Reproduced on real MariaDB with the already-fixed
     * Document-numbering lock (finding #8) temporarily disabled, to
     * isolate this specific gap from that unrelated lock's incidental
     * protection (both cheque registrations also create a Document, so
     * finding #8's fix alone happens to serialize this scenario too --
     * but only as a side effect, not a real guarantee, since it would
     * silently stop protecting this if document numbering ever changed
     * to not need a lock). Fixed the same way: lock the invoice, re-read
     * into $invoice's OWN attributes so the later `syncInvoiceStatus()`
     * call in this same method also sees fresh state.
     */
    public function saveChequePayment(User $user, ?Invoice $invoice, Cheque $cheque, Document $document, int $settlementSubjectId, ?Payment $payment = null): Payment
    {
        if ($invoice) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->setRawAttributes($locked->getAttributes(), true);

            $decision = $this->validateInvoicePayment($invoice, [
                'amount' => (float) $cheque->amount,
                'date' => $cheque->write_date->toDateString(),
            ], $payment);
            if ($decision->hasErrors()) {
                throw ValidationException::withMessages([
                    'invoice_id' => $decision->messages->pluck('text')->all(),
                ]);
            }
        }

        $attributes = [
            'invoice_id' => $invoice?->id,
            'cheque_id' => $cheque->id,
            'payer_id' => $cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null,
            'amount' => $cheque->amount,
            'date' => $cheque->write_date,
            'description' => $cheque->desc,
            'reference_number' => $cheque->sayad_number,
            'document_id' => $document->id,
            'settlement_subject_id' => $settlementSubjectId,
            'creator_id' => $user->id,
        ];

        if ($payment) {
            $payment->update($attributes);
        } else {
            $payment = Payment::create($attributes);
        }

        DocumentService::syncDocumentable($document, $payment);
        if ($invoice) {
            $this->syncInvoiceStatus($invoice);
        }

        return $payment->fresh();
    }

    public function syncInvoiceStatus(Invoice $invoice): void
    {
        if (! $invoice->status->isApprovedOrSettled()) {
            return;
        }

        $paid = $this->paidAmount($invoice);
        $amount = $this->payableAmount($invoice);

        $status = match (true) {
            $paid <= 0.0 => InvoiceStatus::APPROVED,
            $paid + 0.001 < $amount => InvoiceStatus::PARTIALLY_PAID,
            default => InvoiceStatus::PAID,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }

    public function createCreditPayment(User $user, Customer $customer, array $data): Payment
    {
        return DB::transaction(function () use ($user, $customer, $data) {
            $customerSubjectId = (int) ($customer->subject?->id ?? 0);
            if (! $customerSubjectId) {
                throw ValidationException::withMessages([
                    'customer_id' => [__('The selected customer has no linked accounting subject.')],
                ]);
            }

            $subjectId = (int) $data['subject_id'];
            if (! in_array($subjectId, $this->settlementSubjectIds(), true)) {
                throw ValidationException::withMessages([
                    'subject_id' => [__('The selected settlement account is not a valid bank or cash subject.')],
                ]);
            }

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => [__('Payment amount exceeds the remaining balance of the invoice.')],
                ]);
            }

            $date = $data['date'] ?? now()->toDateString();
            $desc = $data['description'] ?? __('Advance payment received from customer');

            $transactions = [
                ['subject_id' => $customerSubjectId, 'desc' => $desc, 'value' => $amount],
                ['subject_id' => $subjectId, 'desc' => $desc, 'value' => -$amount],
            ];

            $document = DocumentService::createDocument($user, [
                'date' => $date,
                'title' => $desc,
                'number' => null,
            ], $transactions);
            DocumentService::changeDocumentStatus($document, $user, 'approved');

            $payment = Payment::create([
                'invoice_id' => null,
                'payer_id' => $customer->id,
                'amount' => $amount,
                'date' => $date,
                'description' => $data['description'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'document_id' => $document->id,
                'settlement_subject_id' => $subjectId,
                'creator_id' => $user->id,
            ]);

            DocumentService::syncDocumentable($document, $payment);

            return $payment->fresh();
        });
    }

    public function unallocatedAmount(Payment $payment): float
    {
        if ($payment->invoice_id) {
            return 0.0;
        }

        return max((float) $payment->amount - (float) $payment->allocations()->sum('amount'), 0.0);
    }

    /**
     * Low-level allocation primitive (D-070): the single ledger both invoice
     * payments and CheckSettlement installments allocate through. Only
     * validates the PAYMENT side (not already singly-linked, amount > 0,
     * amount within the payment's own unallocated balance) — the caller is
     * responsible for target-side validation (e.g. an invoice's remaining
     * balance, or a settlement installment's remaining amount) and for
     * syncing the target's derived status after the allocation is created.
     */
    public function allocate(User $user, Payment $payment, $allocatable, float $amount, ?string $reference = null): PaymentAllocation
    {
        return DB::transaction(function () use ($user, $payment, $allocatable, $amount, $reference) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->invoice_id) {
                throw ValidationException::withMessages([
                    'payment' => [__('This payment is already linked to a single invoice and cannot be allocated separately.')],
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => [__('Allocation amount must be greater than zero.')],
                ]);
            }

            if ($amount > $this->unallocatedAmount($lockedPayment) + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => [__('Allocation amount exceeds the unallocated balance of this payment.')],
                ]);
            }

            return PaymentAllocation::create([
                'payment_id' => $lockedPayment->id,
                'allocatable_type' => $allocatable->getMorphClass(),
                'allocatable_id' => $allocatable->getKey(),
                'amount' => $amount,
                'allocated_at' => now(),
                'reference' => $reference,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Concurrency (Workstream S, Phase 1): identical race to
     * createPayment() -- the invoice-side validation used to run before
     * this method even entered a transaction, with no lock. Two
     * concurrent allocations from two DIFFERENT payments against the SAME
     * invoice could each individually pass and jointly overpay it (this
     * is a distinct hole from allocate()'s own PAYMENT-side lock, which
     * only prevents over-allocating a single payment's own balance).
     * Fixed the same way as createPayment(): lock the invoice, re-read
     * into $invoice's OWN attributes, and move the validation inside the
     * now-locked transaction.
     */
    public function allocatePayment(User $user, Payment $payment, Invoice $invoice, float $amount, ?string $reference = null): PaymentAllocation
    {
        return DB::transaction(function () use ($user, $payment, $invoice, $amount, $reference) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->setRawAttributes($locked->getAttributes(), true);

            if (! $invoice->status->isApproved() && ! $invoice->status->isPartiallyPaid()) {
                throw ValidationException::withMessages([
                    'invoice_id' => [__('The invoice must be approved before recording a payment.')],
                ]);
            }

            if ($amount > $this->remainingAmount($invoice) + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => [__('Payment amount exceeds the remaining balance of the invoice.')],
                ]);
            }

            $allocation = $this->allocate($user, $payment, $invoice, $amount, $reference);
            $this->syncInvoiceStatus($invoice);

            return $allocation;
        });
    }

    /**
     * D-084.3: a successfully allocated PaymentAllocation is a posted
     * financial record (D-081) and is immutable at the service layer --
     * this is never reachable for an existing allocation, regardless of
     * caller (Web, Offline, or Sync).
     */
    public function removeAllocation(PaymentAllocation $allocation): void
    {
        $this->rejectPostedFinancialMutation('allocation');
    }

    /**
     * D-084.3: the single enforcement point for "posted financial record
     * immutability." Deliberately a hard rejection, not a silent no-op --
     * D-084.5: the caller must see a clear error, never an invented
     * reversal. `never` matches this method's only use (always throws).
     */
    private function rejectPostedFinancialMutation(string $field): never
    {
        throw ValidationException::withMessages([
            $field => [__('This is a posted financial record and is immutable; it cannot be deleted or destructively modified. A correction/reversal workflow will be introduced by a future Decision.')],
        ]);
    }

    private function paymentDocument(User $user, Invoice $invoice, int $settlementSubjectId, float $amount, ?string $date = null)
    {
        $customerSubjectId = $invoice->customer->subject?->id ?? $invoice->customer->subject_id;
        $customerSign = in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY]) ? 1 : -1;
        $desc = __('Payment of').' '.__('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.localizeNumber($invoice->number ?? $invoice->id);

        $transactions = [
            [
                'subject_id' => $customerSubjectId,
                'desc' => $desc,
                'value' => $customerSign * $amount,
            ],
            [
                'subject_id' => $settlementSubjectId,
                'desc' => $desc,
                'value' => -$customerSign * $amount,
            ],
        ];

        $documentData = [
            'date' => $date ?? now()->toDateString(),
            'title' => __('Payment of').' '.(__('Invoice #').localizeNumber($invoice->number ?? $invoice->id)),
            'number' => null,
        ];
        $document = DocumentService::createDocument($user, $documentData, $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    /**
     * D-084.1/D-078: a non-cheque Payment (Credit or Invoice-linked -- the
     * "financial event" D-078 defines and Payment Sync already governs) has
     * no draft state; creation is posting, so every existing row is already
     * posted. Hard delete of that Payment is never reachable through the
     * service layer, regardless of caller (Web, Offline, or Sync already
     * rejected this per D-079 decision 7). Correction of a mistaken payment
     * is deliberately deferred to a future Reversal/Correction Decision
     * (D-084.5), not implemented here.
     *
     * A cheque-linked Payment is explicitly OUT of this boundary, exactly as
     * it is already excluded from Payment Sync (D-079: "Cheque-linked
     * payments are excluded"). Removing a mistakenly-registered Cheque
     * (and its Payment/Documents/history as one unit) is existing,
     * independent Cheque lifecycle behavior (D-070) -- not the generic
     * financial-event immutability this Decision closes -- and D-070/
     * AGENTS.md guardrail 3 forbid redesigning that lifecycle here.
     */
    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->cheque_id) {
                $this->rejectPostedFinancialMutation('payment');
            }

            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($lockedPayment->cheque_id);

            $invoices = Invoice::whereIn('id', $lockedCheque->payments()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())->get();

            $documentIds = $lockedCheque->histories()->pluck('document_id')->merge($lockedCheque->payments()->pluck('document_id'))->merge(
                Document::withoutGlobalScopes()->where('documentable_type', $lockedCheque->getMorphClass())->where('documentable_id', $lockedCheque->id)->pluck('id')
            )->filter()->unique();

            app(ActivityLogService::class)->deleteModels($lockedCheque->payments()->getQuery());
            $lockedCheque->delete();

            foreach ($documentIds as $documentId) {
                DocumentService::deleteDocument((int) $documentId);
            }

            foreach ($invoices as $invoice) {
                $this->syncInvoiceStatus($invoice);
            }
        });
    }

    public function removePaymentDocument(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            if ($payment->cheque_id) {
                throw ValidationException::withMessages([
                    'payment' => __('A cheque payment document is managed through the cheque lifecycle.'),
                ]);
            }

            $invoice = $payment->invoice;
            if ($payment->document) {
                DocumentService::deleteDocument($payment->document_id);
                $payment->document_id = null;
                $payment->save();
            }

            if ($invoice) {
                $this->syncInvoiceStatus($invoice);
            }
        });
    }

    public function createPaymentDocument(User $user, Payment $payment): InvoiceStatusDecision
    {
        return DB::transaction(function () use ($user, $payment) {
            $decision = new InvoiceStatusDecision;

            if ($payment->document_id) {
                $decision->addMessage('error', __('This payment already has an accounting document.'));

                return $decision;
            }

            if ($payment->cheque_id) {
                $decision->addMessage('error', __('A cheque payment document is managed through the cheque lifecycle.'));

                return $decision;
            }

            $invoice = $payment->invoice;
            $settlementSubjectId = (int) $payment->settlement_subject_id;

            if (! $invoice || ! $settlementSubjectId) {
                $decision->addMessage('error', __('Cannot create a payment document without a settlement account.'));

                return $decision;
            }

            $document = $this->paymentDocument($user, $invoice, $settlementSubjectId, (float) $payment->amount, $payment->date?->toDateString());

            $payment->document_id = $document->id;
            $payment->save();

            DocumentService::syncDocumentable($document, $payment);

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }
}
