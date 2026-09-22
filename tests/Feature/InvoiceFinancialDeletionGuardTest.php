<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Post-Phase-2 hardening, Batch A -- closes the "Invoice delete bypasses
 * D-084" gap the Final Audit found. Invoice::booted()'s `deleting` hook now
 * rejects deletion whenever the invoice has a non-cheque Payment or any
 * PaymentAllocation: neither has a draft state (D-078/D-081), so any
 * existing row is already a posted financial event, and deleting the parent
 * Invoice must not be a back door around PaymentService's own immutability
 * guard (D-084). Cheque-linked invoices are explicitly excluded -- see
 * ChequeManagementTest for that already-covered, unaffected behavior.
 */
class InvoiceFinancialDeletionGuardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9800;

    protected PaymentService $paymentService;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $this->companyId]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function draftSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], false, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function creditPayment(float $amount): Payment
    {
        return $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => $amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
    }

    private function recordPayment(Invoice $invoice, array $data): Payment
    {
        $this->paymentService->createPayment($this->user, $invoice, $data);

        return $invoice->payments()->latest('id')->firstOrFail();
    }

    // --- 1. Legitimate lifecycle is preserved for financially-clean invoices --

    public function test_a_draft_invoice_with_no_financial_records_can_still_be_deleted(): void
    {
        $draft = $this->draftSell(1000, 1);

        InvoiceService::deleteInvoice($draft->id);

        $this->assertNull(Invoice::withoutGlobalScopes()->find($draft->id));
    }

    public function test_an_approved_invoice_with_no_payment_or_allocation_can_still_be_deleted(): void
    {
        $sell = $this->approvedSell(1000, 1);

        InvoiceService::deleteInvoice($sell->id);

        $this->assertNull(Invoice::withoutGlobalScopes()->find($sell->id));
    }

    // --- 2. Invoice with a direct Payment cannot be deleted -------------------

    public function test_an_invoice_with_a_direct_payment_cannot_be_deleted_through_the_service(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->recordPayment($sell, ['amount' => 500, 'subject_id' => $this->cashSubjectId()]);

        $this->expectException(ValidationException::class);

        try {
            InvoiceService::deleteInvoice($sell->id);
        } finally {
            $this->assertNotNull(Invoice::withoutGlobalScopes()->find($sell->id));
        }
    }

    // --- 3. Invoice with a PaymentAllocation cannot be deleted -----------------

    public function test_an_invoice_with_an_allocation_cannot_be_deleted_through_the_service(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->creditPayment(1000);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);

        $this->expectException(ValidationException::class);

        try {
            InvoiceService::deleteInvoice($sell->id);
        } finally {
            $this->assertNotNull(Invoice::withoutGlobalScopes()->find($sell->id));
        }
    }

    // --- 4. Direct model deletion cannot bypass the invariant ------------------

    public function test_calling_delete_directly_on_the_model_is_also_rejected(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->recordPayment($sell, ['amount' => 500, 'subject_id' => $this->cashSubjectId()]);

        $this->expectException(ValidationException::class);
        Invoice::withoutGlobalScopes()->findOrFail($sell->id)->delete();
    }

    // --- 7. No Payment/Allocation disappears as a side effect of a rejection --

    public function test_a_rejected_invoice_deletion_leaves_the_payment_and_allocation_intact(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->creditPayment(1000);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);

        try {
            InvoiceService::deleteInvoice($sell->id);
        } catch (ValidationException) {
            // expected
        }

        $this->assertNotNull(Payment::find($payment->id));
        $this->assertNotNull(PaymentAllocation::find($allocation->id));
    }

    // --- 8. Error is deterministic and does not partially mutate data --------

    public function test_a_rejected_invoice_deletion_does_not_delete_invoice_items(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->recordPayment($sell, ['amount' => 500, 'subject_id' => $this->cashSubjectId()]);
        $itemIds = $sell->items()->pluck('id');
        $this->assertGreaterThan(0, $itemIds->count());

        try {
            InvoiceService::deleteInvoice($sell->id);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($itemIds->count(), InvoiceItem::whereIn('id', $itemIds)->count());
    }

    // --- 6. Cheque-linked invoices remain unaffected -------------------------
    //
    // Owned end-to-end by ChequeManagementTest::
    // test_deleting_invoice_removes_its_cheque_payment_histories_and_accounting_documents
    // (unchanged by this batch, re-verified green by the full suite) -- a
    // cheque-linked Payment is excluded from this guard's `whereNull
    // ('cheque_id')` condition by construction, deliberately not duplicated
    // here.
}
