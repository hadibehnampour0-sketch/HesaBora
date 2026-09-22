<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-086 -- Approved Invoice Financial Immutability. Once an Invoice has real
 * financially effective activity against it (a Payment or a
 * PaymentAllocation), InvoiceService::updateInvoice() rejects any change to
 * its financial content (items, quantities, prices, totals, customer,
 * warehouse, or its Document relationship) -- mirroring D-084's Payment/
 * Allocation immutability principle one level up, at the Invoice's own
 * effective amount. An Approved invoice with no payment/allocation yet is
 * completely unaffected -- editing/correcting it before any money has moved
 * remains the existing, tested bookkeeping behavior (COGSCalculationTest).
 *
 * Deliberately scoped to updateInvoice() only, NOT unapproveInvoice()/
 * changeInvoiceStatus('unapproved') itself -- that method is a shared
 * low-level primitive GroupActionService::inactivateDependentInvoices()/
 * approveInactiveInvoices() also uses internally to cycle a downstream
 * invoice's status for COG-chain consistency without ever changing its
 * amount/customer. Guarding it there broke that real, tested mechanism; see
 * the Decision Log's D-086 entry for the open question this leaves.
 */
class ApprovedInvoiceFinancialImmutabilityTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9900;

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
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.update']));
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
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

    private function approvedSellWithPayment(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $this->paymentService->createPayment($this->user, $sell, [
            'amount' => (float) $sell->amount,
            'subject_id' => $this->cashSubjectId(),
        ]);

        return $this->findInvoice($sell->id);
    }

    private function updatePayload(Invoice $invoice, array $overrides = []): array
    {
        return array_merge([
            'title' => $invoice->title,
            'date' => $invoice->date->toDateString(),
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'document_number' => $invoice->document?->number,
            'number' => $invoice->number,
            'description' => $invoice->description,
            'subtraction' => $invoice->subtraction ?? 0,
            'warehouse_id' => $invoice->warehouse_id,
        ], $overrides);
    }

    private function sameItems(Invoice $invoice): array
    {
        return $invoice->items->map(fn ($item) => [
            'itemable_type' => 'product',
            'itemable_id' => $item->itemable_id,
            'quantity' => (float) $item->quantity,
            'unit' => (float) $item->unit_price,
            'unit_discount' => (float) $item->unit_discount,
            'vat' => (float) $item->vat,
        ])->all();
    }

    // --- 1/2/4/7: financial lines/totals cannot be edited (direct service call) --

    public function test_an_invoice_with_a_payment_rejects_a_financial_line_edit(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);
        $product = $this->findProduct((int) $invoice->items->first()->itemable_id);

        $this->expectException(ValidationException::class);
        try {
            InvoiceService::updateInvoice(
                $invoice->id,
                $this->updatePayload($invoice),
                [$this->productItem($product, 1, 1500)], // quantity/price both changed
                false
            );
        } finally {
            $this->assertEqualsWithDelta(2000, (float) $this->findInvoice($invoice->id)->amount, 0.01);
        }
    }

    public function test_an_invoice_with_a_payment_rejects_the_approved_rebuild_edit_even_with_identical_items(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);

        $this->expectException(ValidationException::class);
        InvoiceService::updateInvoice(
            $invoice->id,
            $this->updatePayload($invoice),
            $this->sameItems($invoice),
            true // requests the approved-edit rebuild branch explicitly
        );
    }

    // --- 5: PaymentAllocation alone also blocks financial mutation -----------

    public function test_an_invoice_with_only_an_allocation_rejects_a_financial_line_edit(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 2, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 2000,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);
        $invoice = $this->findInvoice($sell->id);

        $this->expectException(ValidationException::class);
        try {
            InvoiceService::updateInvoice(
                $invoice->id,
                $this->updatePayload($invoice),
                [$this->productItem($product, 1, 1500)],
                false
            );
        } finally {
            $this->assertEqualsWithDelta(2000, (float) $this->findInvoice($invoice->id)->amount, 0.01);
        }
    }

    // --- 6: controller cannot bypass the service-layer guard ------------------

    public function test_the_update_controller_cannot_bypass_the_service_layer_guard(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);
        $product = $this->findProduct((int) $invoice->items->first()->itemable_id);

        $response = $this->put(route('invoices.update', $invoice), [
            'title' => $invoice->title,
            'date' => toEnglish(formatDate($invoice->date)),
            'invoice_type' => $invoice->invoice_type->value,
            'customer_id' => $invoice->customer_id,
            'number' => $invoice->number,
            'warehouse_id' => $invoice->warehouse_id,
            'transactions' => [[
                'item_id' => 'product-'.$product->id,
                'quantity' => 1,
                'unit' => 1500,
                'off' => 0,
                'vat' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors();
        $this->assertEqualsWithDelta(2000, (float) $this->findInvoice($invoice->id)->amount, 0.01);
    }

    // --- 8/10: no payment yet + non-financial metadata remain unaffected -----

    public function test_a_draft_invoice_remains_fully_editable(): void
    {
        $product = $this->createProduct();
        $draft = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice']->id);

        $result = InvoiceService::updateInvoice(
            $draft->id,
            $this->updatePayload($draft, ['title' => 'edited draft']),
            [$this->productItem($product, 2, 1200)],
            false
        );

        $this->assertSame('edited draft', $result['invoice']->title);
        $this->assertEqualsWithDelta(2400, (float) $result['invoice']->amount, 0.01);
    }

    public function test_an_approved_invoice_with_no_payment_yet_remains_editable(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        // The real approved-edit rebuild path (the one InvoiceController
        // actually drives for an already-approved invoice) properly
        // reverses/reapplies warehouse stock; this proves D-086's guard
        // does not fire at all when no payment/allocation exists yet.
        $result = InvoiceService::updateInvoice(
            $sell->id,
            $this->updatePayload($sell),
            [$this->productItem($product, 1, 1500)],
            true
        );

        $this->assertEqualsWithDelta(1500, (float) $result['invoice']->amount, 0.01);
    }

    public function test_non_financial_metadata_remains_editable_even_with_a_payment_attached(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);

        $result = InvoiceService::updateInvoice(
            $invoice->id,
            $this->updatePayload($invoice, ['description' => 'corrected note']),
            $this->sameItems($invoice),
            false
        );

        $this->assertSame('corrected note', $result['invoice']->description);
        $this->assertEqualsWithDelta(2000, (float) $result['invoice']->amount, 0.01);
    }

    // --- Boundary hardening: re-approve re-entry, direct service call --------
    //
    // InvoiceController::changeStatus() and SyncPilotService::applyApprove()
    // both already prevent reaching this today via their own upstream
    // checks, but the service layer must not depend on that -- this proves
    // changeInvoiceStatus($invoice, 'approved') itself rejects re-approving
    // an already-settled invoice, with no Document rebuild or double
    // quantity/COG mutation as a side effect.

    public function test_reapproving_a_paid_invoice_directly_through_the_service_is_rejected(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);
        $documentId = $invoice->document_id;
        $product = $this->findProduct((int) $invoice->items->first()->itemable_id);
        $quantityBefore = $product->fresh()->quantity;

        $this->expectException(ValidationException::class);

        try {
            app(InvoiceService::class)->changeInvoiceStatus($invoice, 'approved');
        } finally {
            $fresh = $this->findInvoice($invoice->id);
            $this->assertSame($documentId, $fresh->document_id);
            $this->assertEqualsWithDelta(2000, (float) $fresh->amount, 0.01);
            $this->assertEqualsWithDelta($quantityBefore, $product->fresh()->quantity, 0.01);
        }
    }

    // --- Phase 5 cross-module finding: 'rejected' is the one status-
    // transition arm of changeInvoiceStatus() that previously had no
    // service-layer guard at all -- mirrors D-086's own reasoning: marking
    // a financially-active invoice REJECTED is a reversal by another name,
    // leaving its Document/Payment/PaymentAllocation exactly as they are
    // while detaching the status label from them. --

    public function test_rejecting_a_paid_invoice_directly_through_the_service_is_rejected(): void
    {
        $invoice = $this->approvedSellWithPayment(1000, 2);
        $documentId = $invoice->document_id;
        $statusBefore = $invoice->status;

        $this->expectException(ValidationException::class);

        try {
            app(InvoiceService::class)->changeInvoiceStatus($invoice, 'rejected');
        } finally {
            $fresh = $this->findInvoice($invoice->id);
            $this->assertSame($documentId, $fresh->document_id);
            $this->assertEquals($statusBefore, $fresh->status);
        }
    }

    public function test_rejecting_an_approved_invoice_with_no_payment_yet_still_works(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 2, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        app(InvoiceService::class)->changeInvoiceStatus($sell, 'rejected');

        $this->assertTrue($this->findInvoice($sell->id)->status->isRejected());
    }

    // --- Final Marathon Closure Pass: re-approving an already-approved,
    // financially-inactive invoice must be a safe no-op, never a second
    // posting. No concurrency needed -- a plain resubmit of the same
    // request was enough to silently duplicate the Document and double-add
    // warehouse quantity before this fix. ---

    public function test_reapproving_an_already_approved_invoice_with_no_payment_is_a_safe_no_op(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 1, 200)], true, ++$this->nextInvoiceNumber)['invoice']->id);
        $documentIdAfterFirstApprove = $sell->document_id;
        $quantityAfterFirstApprove = $this->findProduct((int) $sell->items->first()->itemable_id)->quantity;

        app(InvoiceService::class)->changeInvoiceStatus($sell->fresh(), 'approved');

        $fresh = $this->findInvoice($sell->id);
        $this->assertSame($documentIdAfterFirstApprove, $fresh->document_id, 'Re-approving must not create a second Document.');
        $this->assertEqualsWithDelta(
            $quantityAfterFirstApprove,
            $this->findProduct((int) $sell->items->first()->itemable_id)->quantity,
            0.01,
            'Re-approving must not double-add warehouse quantity.'
        );
        $this->assertNull(Document::withoutGlobalScopes()->where('id', '!=', $documentIdAfterFirstApprove)
            ->where('documentable_type', Invoice::class)->where('documentable_id', $sell->id)->first(), 'No orphaned second Document may exist.');
    }
}
