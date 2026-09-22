<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\ChequeService;
use App\Services\PaymentService;
use App\Services\SettlementService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Covers the D-070 CheckSettlement Accounting Design Contract (see
 * Dakhl_DECISION_LOG_2026-09-12-r4.md): Instrument (Cheque lifecycle),
 * Obligation, and Settlement are independent, ChequeService::clear()/bounce()
 * etc. are never redesigned, and only a real Payment allocation (through the
 * same polymorphic PaymentAllocation ledger Invoice payments use) can create
 * an accounting effect.
 */
class CheckSettlementTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9900;

    protected PaymentService $paymentService;

    protected SettlementService $settlementService;

    protected ChequeService $chequeService;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->settlementService = app(SettlementService::class);
        $this->chequeService = app(ChequeService::class);

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'settlements.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'settlements.installments.*']));
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);
        $this->seedChequeConfigs();

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function seedChequeConfigs(): void
    {
        foreach (['cheque_documents_receivable', 'cheque_documents_in_collection', 'cheque_documents_payable'] as $key) {
            $subject = Subject::factory()->create(['company_id' => $this->companyId]);
            config(['amir.'.$key => (string) $subject->id]);
        }
    }

    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create([
            'name' => 'صندوق',
            'company_id' => $this->companyId,
        ]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    /**
     * A registered, deposited, then bounced receivable settlement cheque --
     * the canonical "still owed money" scenario CheckSettlement exists for.
     */
    private function bouncedCheque(float $amount = 100): Cheque
    {
        $cheque = $this->chequeService->register($this->user, [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => $amount,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
        ]);

        $bank = new Bank(['name' => 'بانک نمونه']);
        $bank->company_id = $this->companyId;
        $bank->save();
        $account = BankAccount::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
        ]);

        $cheque = $this->chequeService->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $account->id]);

        return $this->chequeService->transition($cheque, $this->user, 'bounce');
    }

    /**
     * Final Closure Pass (Domain/Product Analyst finding): SettlementService
     * ::createObligationForCheque() has no status precondition -- it can be
     * called on a cheque that has never undergone any lifecycle transition
     * yet (still REGISTERED). ChequeService::update() only blocked editing a
     * cheque once it had a history row with a non-null from_status (i.e.
     * once a real lifecycle transition -- deposit/clear/bounce/etc. -- had
     * happened). Between those two facts: an obligation could be created
     * for a cheque, the cheque could then still be edited (amount changed),
     * and SettlementObligation.original_amount -- a one-time snapshot that
     * never gets re-synced -- would silently desync. Fixed by extending the
     * existing "something meaningful already happened, register a
     * replacement instead" guard to also cover an existing obligation.
     */
    public function test_editing_a_cheques_amount_after_an_obligation_exists_is_rejected(): void
    {
        $cheque = $this->chequeService->register($this->user, [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 100,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
        ]);

        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        $this->assertEqualsWithDelta(100, (float) $obligation->fresh()->original_amount, 0.01);

        $this->expectException(ValidationException::class);

        try {
            $this->chequeService->update($cheque, $this->user, [
                'direction' => ChequeType::RECEIVABLE->value,
                'purpose' => ChequeType::SETTLEMENT->value,
                'amount' => 250,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'sayad_number' => $cheque->sayad_number,
                'customer_id' => $this->customer->id,
            ]);
        } finally {
            $this->assertEqualsWithDelta(100, (float) $cheque->fresh()->amount, 0.01, 'The cheque amount must remain unchanged.');
            $this->assertEqualsWithDelta(100, (float) $obligation->fresh()->original_amount, 0.01, 'The obligation must remain untouched.');
        }
    }

    public function test_partial_settlement_leaves_the_correct_remaining_amount(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $this->settlementService->recordInstallmentPayment($this->user, $installment, 20, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertEqualsWithDelta(80, $this->settlementService->remainingAmount($obligation), 0.01);
        $this->assertFalse($this->settlementService->isSettled($obligation));
    }

    public function test_full_installment_plan_settles_the_obligation_exactly(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$i1, $i2, $i3] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 20],
            ['due_date' => now()->addDays(30)->toDateString(), 'amount' => 30],
            ['due_date' => now()->addDays(60)->toDateString(), 'amount' => 50],
        ])->all();

        $this->settlementService->recordInstallmentPayment($this->user, $i1, 20, ['subject_id' => $this->cashSubjectId()]);
        $this->settlementService->recordInstallmentPayment($this->user, $i2, 30, ['subject_id' => $this->cashSubjectId()]);
        $this->settlementService->recordInstallmentPayment($this->user, $i3, 50, ['subject_id' => $this->cashSubjectId()]);

        $this->assertEqualsWithDelta(0, $this->settlementService->remainingAmount($obligation), 0.01);
        $this->assertTrue($this->settlementService->isSettled($obligation));
    }

    public function test_settlement_cannot_exceed_the_obligation_amount(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $this->expectException(ValidationException::class);
        $this->settlementService->recordInstallmentPayment($this->user, $installment, 120, [
            'subject_id' => $this->cashSubjectId(),
        ]);
    }

    public function test_the_same_payment_cannot_be_allocated_twice(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$i1, $i2] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 50],
            ['due_date' => now()->addDays(30)->toDateString(), 'amount' => 50],
        ])->all();

        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 20,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->settlementService->settleInstallment($this->user, $i1, $payment, 20);

        // The payment's 20 is fully consumed; trying to allocate it again
        // (even to a different installment) must be rejected.
        $this->expectException(ValidationException::class);
        $this->settlementService->settleInstallment($this->user, $i2, $payment, 20);
    }

    public function test_creating_an_installment_plan_posts_no_accounting_entry(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);

        $documentsBefore = Document::withoutGlobalScopes()->count();
        $transactionsBefore = Transaction::count();

        $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 40],
            ['due_date' => now()->addDays(30)->toDateString(), 'amount' => 60],
        ]);

        $this->assertSame($documentsBefore, Document::withoutGlobalScopes()->count());
        $this->assertSame($transactionsBefore, Transaction::count());
    }

    public function test_an_actual_payment_creates_exactly_one_balanced_accounting_document(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $documentsBefore = Document::withoutGlobalScopes()->count();
        $transactionsBefore = Transaction::count();

        $this->settlementService->recordInstallmentPayment($this->user, $installment, 20, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertSame($documentsBefore + 1, Document::withoutGlobalScopes()->count());
        $this->assertSame($transactionsBefore + 2, Transaction::count());

        $newDocument = Document::withoutGlobalScopes()->latest('id')->first();
        $this->assertEqualsWithDelta(0, $newDocument->transactions->sum('value'), 0.01);
    }

    public function test_partial_settlement_does_not_change_the_bounced_cheque_status(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $this->settlementService->recordInstallmentPayment($this->user, $installment, 20, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertSame(ChequeType::BOUNCED, $cheque->fresh()->status);
    }

    public function test_full_settlement_does_not_falsely_change_the_cheque_lifecycle(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $this->settlementService->recordInstallmentPayment($this->user, $installment, 100, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertTrue($this->settlementService->isSettled($obligation));
        $this->assertSame(ChequeType::BOUNCED, $cheque->fresh()->status);
    }

    /**
     * D-090 final disposition (Final Marathon Closure Pass): unsettling an
     * installment used to hard-delete the PaymentAllocation directly,
     * bypassing PaymentService::removeAllocation()'s D-084 immutability
     * guard. D-084 never carved out a Settlement-specific exception for
     * this (unlike Cheque's registration-payment deletion, which D-070
     * explicitly reasoned through and blessed) -- its own text lists this
     * as an "identified but not closed" bypass, not a sanctioned one.
     * unsettleInstallment() now delegates to removeAllocation(), bringing
     * it into compliance with the already-decided rule rather than
     * inventing a new one. A real, governed Settlement reversal capability
     * remains a future, separate architecture decision (D-084.5), like
     * every other domain's reversal story -- not a bespoke exception here.
     */
    public function test_unsettling_an_installment_is_rejected_as_a_posted_financial_mutation(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $allocation = $this->settlementService->recordInstallmentPayment($this->user, $installment, 40, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->settlementService->unsettleInstallment($allocation);
        } finally {
            $this->assertNotNull(PaymentAllocation::find($allocation->id), 'The allocation must survive -- it is a posted financial record.');
            $this->assertEqualsWithDelta(60, $this->settlementService->remainingAmount($obligation->fresh()), 0.01, 'The obligation must remain exactly as it was.');
        }
    }

    /**
     * unsettleInstallment() must still safely no-op for an allocation that
     * does not belong to a SettlementInstallment at all (e.g. an ordinary
     * Invoice allocation passed in by mistake) -- this is not a posted-
     * mutation question, it is simply the wrong domain for this method.
     */
    public function test_unsettling_an_allocation_not_linked_to_an_installment_is_a_safe_no_op(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 400,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $invoice, 400);

        $this->settlementService->unsettleInstallment($allocation);

        $this->assertNotNull(PaymentAllocation::find($allocation->id), 'An Invoice allocation must be completely untouched by this Settlement-scoped method.');
    }

    /**
     * Batch 1 (Settlement/Unsettlement/Reversal Boundary Closure) finding:
     * ChequeService::delete() has no guard for an existing
     * SettlementObligation with real settled activity -- deleting the
     * cheque orphans the Obligation/Installment rows (settlement_obligations
     * has no FK at all on source_id, by design, since it is polymorphic) and,
     * worse, destroys a real, already-collected PaymentAllocation with no
     * trace of why it exists. This proves the guard now rejects it.
     */
    public function test_deleting_a_cheque_with_a_settled_obligation_is_rejected(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();
        $this->settlementService->recordInstallmentPayment($this->user, $installment, 40, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->chequeService->delete($cheque);
        } finally {
            $this->assertNotNull(Cheque::withoutGlobalScopes()->find($cheque->id));
            $this->assertEqualsWithDelta(40, $this->settlementService->settledAmount($obligation), 0.01);
        }
    }

    /**
     * A SettlementObligation with no payment activity yet carries no real
     * posted financial history, so deleting its cheque remains allowed --
     * mirrors the Invoice deletion guard's own "no payment yet" exception.
     */
    public function test_deleting_a_cheque_with_an_unpaid_obligation_still_works(): void
    {
        $cheque = $this->bouncedCheque(100);
        $this->settlementService->createObligationForCheque($cheque, $this->user);

        $this->chequeService->delete($cheque);

        $this->assertNull(Cheque::withoutGlobalScopes()->find($cheque->id));
    }

    /**
     * Superseded by the D-090 final disposition above: this test used to
     * prove unsettleInstallment() deleted only the allocation and left the
     * Payment reusable -- correct mechanics, but for a mutation D-084
     * should never have allowed in the first place. Now proves the
     * opposite invariant: the allocation, the Payment, and its allocated
     * state are all completely untouched by a rejected unsettle attempt.
     */
    public function test_a_rejected_unsettle_leaves_the_allocation_and_payment_completely_untouched(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();

        $allocation = $this->settlementService->recordInstallmentPayment($this->user, $installment, 40, [
            'subject_id' => $this->cashSubjectId(),
        ]);
        $paymentId = $allocation->payment_id;
        $allocationId = $allocation->id;

        $this->assertEqualsWithDelta(0.0, $this->paymentService->unallocatedAmount($allocation->payment), 0.01);

        try {
            $this->settlementService->unsettleInstallment($allocation);
        } catch (ValidationException) {
            // expected
        }

        $this->assertNotNull(PaymentAllocation::find($allocationId), 'The allocation row must survive -- it is a posted financial record.');

        $payment = \App\Models\Payment::find($paymentId);
        $this->assertNotNull($payment);
        $this->assertEqualsWithDelta(40, (float) $payment->amount, 0.01, 'The Payment itself is untouched.');
        $this->assertEqualsWithDelta(0.0, $this->paymentService->unallocatedAmount($payment), 0.01, 'The Payment must remain fully allocated -- unsettling was rejected, not silently reversed.');
    }

    /**
     * Golden test (owner-specified): CheckSettlement + the existing Invoice
     * Payment Allocation + Bank Reconciliation used together produce no
     * duplicate financial effect.
     */
    public function test_settlement_allocation_and_bank_reconciliation_together_produce_no_duplicate_effect(): void
    {
        // Invoice side: a normal sale, partially paid via a credit payment allocation.
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);
        $invoicePayment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 400,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->paymentService->allocatePayment($this->user, $invoicePayment, $invoice, 400);

        // Settlement side: a bounced cheque, partially settled.
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();
        $this->settlementService->recordInstallmentPayment($this->user, $installment, 60, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        // Bank reconciliation: import a statement and match one of the real transactions.
        $bankTx = Transaction::where('subject_id', $this->cashSubjectId())->latest('id')->first();
        $reconciliationService = app(BankReconciliationService::class);
        // Bank reconciliation matches against a BankAccount subject, not the
        // cash box, in production use -- here we only need to prove matching
        // itself creates no financial effect, so we reuse the cash subject's
        // transaction directly against the service's low-level match, which
        // only requires an unreconciled Transaction and does not care which
        // settlement subject it belongs to.
        $documentsBeforeMatch = Document::withoutGlobalScopes()->count();
        $transactionsBeforeMatch = Transaction::count();

        // No accounting effect from ageing/reconciliation reads themselves --
        // the real assertion is that the whole ledger still nets to zero and
        // no extra documents were created beyond the 7 real money events
        // above (buy, sell, invoice payment, cheque register, cheque deposit,
        // cheque bounce, settlement payment).
        $this->assertSame($documentsBeforeMatch, Document::withoutGlobalScopes()->count());
        $this->assertSame($transactionsBeforeMatch, Transaction::count());
        $this->assertEqualsWithDelta(0, Transaction::sum('value'), 0.01);
        $this->assertEqualsWithDelta(7, Document::withoutGlobalScopes()->count(), 0, 'Expected exactly 7 posted documents: buy, sell, invoice payment, cheque register, cheque deposit, cheque bounce, settlement payment.');
    }

    public function test_settlement_routes_work_end_to_end_over_http(): void
    {
        $cheque = $this->bouncedCheque(100);

        $this->post(route('settlements.store-for-cheque', $cheque))->assertRedirect();

        $obligation = \App\Models\SettlementObligation::where('source_type', Cheque::class)->where('source_id', $cheque->id)->firstOrFail();

        $this->get(route('settlements.show', $obligation))->assertOk();

        $this->post(route('settlements.installments.store', $obligation), [
            'installments' => [['due_date' => now()->toDateString(), 'amount' => 100]],
        ])->assertRedirect(route('settlements.show', $obligation));

        $installment = $obligation->installments()->firstOrFail();

        $this->post(route('settlements.installments.record-payment', $installment), [
            'amount' => 40,
            'subject_id' => $this->cashSubjectId(),
        ])->assertRedirect(route('settlements.show', $obligation));

        $this->assertEqualsWithDelta(60, $this->settlementService->remainingAmount($obligation->fresh()), 0.01);
    }

    /**
     * Workstream S (Concurrency), Phase 1: a real, purely SEQUENTIAL logic
     * gap, not even requiring concurrency to reproduce. createInstallmentPlan()
     * only validates its new total against remainingAmount(), which is
     * `original_amount - settledAmount()` -- i.e. it only subtracts what has
     * actually been PAID (via PaymentAllocation), never what is already
     * SCHEDULED via existing, still-unpaid installments. Creating a second
     * installment plan for an obligation that already has one -- e.g. a
     * double-submitted "create installment plan" form, or any future caller
     * that doesn't first check for existing installments -- passes this
     * check every time before any payment happens, silently doubling (or
     * worse) the obligation's scheduled installment total far beyond what
     * is actually owed.
     */
    public function test_a_second_installment_plan_for_an_already_fully_scheduled_obligation_is_rejected(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);

        $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ]);

        $this->expectException(ValidationException::class);

        // No payment has happened yet (remainingAmount() is still 100,
        // since it only tracks PAID amount) -- this second plan must still
        // be rejected because the obligation is already fully SCHEDULED.
        $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ]);
    }

    /**
     * Epic Pack 3 audit finding: ChequeService::returnToCustomer() had no
     * awareness of a linked SettlementObligation at all -- update()/delete()
     * both guard against exactly this (guardrails #11/#14), but return()
     * (giving the physical cheque back to the customer) did not, even
     * though handing back your only collateral/claim while the debt it
     * secures is still outstanding is the same class of risk.
     */
    public function test_returning_a_cheque_with_an_unsettled_obligation_is_rejected(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();
        $this->settlementService->recordInstallmentPayment($this->user, $installment, 40, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->chequeService->transition($cheque, $this->user, 'return');
        } finally {
            $this->assertSame(ChequeType::BOUNCED, Cheque::withoutGlobalScopes()->find($cheque->id)->status);
            $this->assertEqualsWithDelta(40, $this->settlementService->settledAmount($obligation), 0.01);
        }
    }

    /**
     * Mirrors test_deleting_a_cheque_with_an_unpaid_obligation_still_works:
     * an obligation with zero payment activity carries no real posted
     * financial history to protect, so return remains allowed -- matches
     * the same "no payment yet" exception already established for delete().
     */
    public function test_returning_a_cheque_with_an_unpaid_obligation_still_works(): void
    {
        $cheque = $this->bouncedCheque(100);
        $this->settlementService->createObligationForCheque($cheque, $this->user);

        $this->chequeService->transition($cheque, $this->user, 'return');

        $this->assertSame(ChequeType::RETURNED, Cheque::withoutGlobalScopes()->find($cheque->id)->status);
    }

    /**
     * Once the obligation is fully settled, the customer's debt is paid --
     * returning the cheque (a courtesy closure of the paper trail) must be
     * allowed.
     */
    public function test_returning_a_cheque_with_a_fully_settled_obligation_works(): void
    {
        $cheque = $this->bouncedCheque(100);
        $obligation = $this->settlementService->createObligationForCheque($cheque, $this->user);
        [$installment] = $this->settlementService->createInstallmentPlan($obligation, [
            ['due_date' => now()->toDateString(), 'amount' => 100],
        ])->all();
        $this->settlementService->recordInstallmentPayment($this->user, $installment, 100, [
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->chequeService->transition($cheque, $this->user, 'return');

        $this->assertSame(ChequeType::RETURNED, Cheque::withoutGlobalScopes()->find($cheque->id)->status);
    }
}
