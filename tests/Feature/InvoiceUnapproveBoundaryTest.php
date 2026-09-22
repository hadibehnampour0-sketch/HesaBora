<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-087 -- explicit separation of the user-facing unapprove action from the
 * internal COG dependency-recalculation status-cycle. The two are now two
 * structurally distinct entry points on InvoiceService:
 *
 * - unapproveInvoice() (private, reached only via changeInvoiceStatus()) --
 *   guarded by D-086, the ONLY path InvoiceController::changeStatus() (and
 *   any future caller of the general-purpose changeInvoiceStatus()) can
 *   ever reach.
 * - recycleInvoiceStatusForDependencyRecalculation() (public) -- unguarded,
 *   reserved for GroupActionService's internal, trusted COG-chain
 *   recalculation, which never touches the invoice's own amount/vat/
 *   customer/items or any Payment/PaymentAllocation row.
 *
 * No boolean flag, magic parameter, or caller-name check exists anywhere in
 * this design -- the distinction is purely which method is called.
 */
class InvoiceUnapproveBoundaryTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9950;

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

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);

        return $this->findInvoice($this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice']->id);
    }

    // --- Test A: user unapprove with a Payment is rejected --------------------

    public function test_user_unapprove_of_an_invoice_with_a_payment_is_rejected(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->paymentService->createPayment($this->user, $sell, [
            'amount' => (float) $sell->amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $invoice = $this->findInvoice($sell->id);
        $documentId = $invoice->document_id;
        $paymentId = $invoice->payments()->firstOrFail()->id;

        $this->expectException(ValidationException::class);

        try {
            app(InvoiceService::class)->changeInvoiceStatus($invoice, 'unapproved');
        } finally {
            $fresh = $this->findInvoice($invoice->id);
            $this->assertTrue($fresh->status->isApprovedOrSettled());
            $this->assertSame($documentId, $fresh->document_id);
            $this->assertNotNull(\App\Models\Document::withoutGlobalScopes()->find($documentId));
            $this->assertNotNull(\App\Models\Payment::withoutGlobalScopes()->find($paymentId));
        }
    }

    // --- Test B: user unapprove with only a PaymentAllocation is rejected ----

    public function test_user_unapprove_of_an_invoice_with_only_an_allocation_is_rejected(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 2000,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);
        $invoice = $this->findInvoice($sell->id);
        $documentId = $invoice->document_id;

        $this->expectException(ValidationException::class);

        try {
            app(InvoiceService::class)->changeInvoiceStatus($invoice, 'unapproved');
        } finally {
            $fresh = $this->findInvoice($invoice->id);
            $this->assertTrue($fresh->status->isApprovedOrSettled());
            $this->assertSame($documentId, $fresh->document_id);
            $this->assertNotNull(\App\Models\PaymentAllocation::find($allocation->id));
        }
    }

    // --- Test C: user unapprove without financial settlement is unaffected --

    public function test_user_unapprove_of_an_invoice_without_financial_settlement_still_works(): void
    {
        $sell = $this->approvedSell(1000, 2);

        app(InvoiceService::class)->changeInvoiceStatus($sell, 'unapproved');

        $fresh = $this->findInvoice($sell->id);
        $this->assertSame(InvoiceStatus::UNAPPROVED, $fresh->status);
        $this->assertNull($fresh->document_id);
    }

    // --- Test D: the real internal COG status-cycle still works -------------

    public function test_the_internal_dependency_recalculation_cycle_still_works_and_never_touches_payment(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 100, 100)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 25, 120)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $this->paymentService->createPayment($this->user, $sell, [
            'amount' => (float) $sell->amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $sell = $this->findInvoice($sell->id);
        $paymentId = $sell->payments()->firstOrFail()->id;
        $originalAmount = (float) $sell->amount;
        $originalCustomerId = $sell->customer_id;

        // Exercises the exact GroupActionService primitive directly.
        app(InvoiceService::class)->recycleInvoiceStatusForDependencyRecalculation($sell, 'unapproved');
        $inactive = $this->findInvoice($sell->id);
        $this->assertNull($inactive->document_id);

        app(InvoiceService::class)->recycleInvoiceStatusForDependencyRecalculation($inactive, 'approved');
        $reapproved = $this->findInvoice($sell->id);

        $this->assertNotNull($reapproved->document_id);
        $this->assertEqualsWithDelta($originalAmount, (float) $reapproved->amount, 0.01);
        $this->assertSame($originalCustomerId, $reapproved->customer_id);
        $this->assertNotNull(\App\Models\Payment::withoutGlobalScopes()->find($paymentId));
        $this->assertEqualsWithDelta($originalAmount, (float) \App\Models\Payment::withoutGlobalScopes()->find($paymentId)->amount, 0.01);
    }

    public function test_the_real_group_action_dependency_recalculation_flow_remains_green(): void
    {
        $product = $this->createProduct(['quantity' => 500]);
        $baseDate = now()->startOfDay();

        $buyInv1 = $this->buy([$this->productItem($product, 100, 100)], true, ++$this->nextInvoiceNumber, $baseDate->toDateString())['invoice'];
        $buyInv2 = $this->buy([$this->productItem($product, 80, 100)], true, ++$this->nextInvoiceNumber, $baseDate->copy()->addDay()->toDateString())['invoice'];
        $sellInv = $this->sell([$this->productItem($product, 25, 120)], true, ++$this->nextInvoiceNumber, $baseDate->copy()->addDays(2)->toDateString())['invoice'];

        $this->paymentService->createPayment($this->user, $this->findInvoice($sellInv->id), [
            'amount' => (float) $this->findInvoice($sellInv->id)->amount,
            'subject_id' => $this->cashSubjectId(),
        ]);

        $groupActionService = app(\App\Services\GroupActionService::class);
        $groupActionService->inactivateDependentInvoices($this->findInvoice($buyInv2->id));

        $this->assertSame(\App\Enums\InvoiceStatus::APPROVED_INACTIVE, $this->findInvoice($sellInv->id)->status);

        $groupActionService->approveInactiveInvoices();

        $fresh = $this->findInvoice($sellInv->id);
        $this->assertSame(\App\Enums\InvoiceStatus::PAID, $fresh->status);
        $this->assertNotNull($fresh->payments()->first());
    }

    // --- Test E: structural guard -- no bypass flag exists on unapproveInvoice() --

    public function test_there_is_no_bypass_flag_on_the_user_facing_unapprove_path(): void
    {
        $changeInvoiceStatus = new ReflectionMethod(InvoiceService::class, 'changeInvoiceStatus');
        $this->assertCount(
            2,
            $changeInvoiceStatus->getParameters(),
            'changeInvoiceStatus() must take only (Invoice $invoice, string $status) -- no bypass/force/internal flag.'
        );

        $recycle = new ReflectionMethod(InvoiceService::class, 'recycleInvoiceStatusForDependencyRecalculation');
        $this->assertTrue($recycle->isPublic(), 'The internal recalculation primitive must be its own explicitly named public method, not a hidden flag on the guarded one.');
        $this->assertNotSame(
            'unapproveInvoice',
            $recycle->getName(),
            'The internal recalculation primitive must be a structurally separate method from unapproveInvoice().'
        );
    }

    /**
     * Final Marathon Closure Pass: the exact mirror image of the approve
     * side's double-post bug. cycleInvoiceStatusMechanics() unconditionally
     * called ProductService::subProductsQuantities() with no check that a
     * Document even existed to reverse -- a plain double-click/resubmit of
     * "unapprove" against an already-unapproved invoice silently subtracted
     * warehouse quantity a second time, manufacturing phantom inventory out
     * of nothing. No concurrency required to reproduce.
     */
    public function test_unapproving_an_already_unapproved_invoice_is_a_safe_no_op(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 1, 200)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        app(InvoiceService::class)->changeInvoiceStatus($sell->fresh(), 'unapproved');
        $quantityAfterFirstUnapprove = $this->findProduct((int) $sell->items->first()->itemable_id)->quantity;
        $this->assertEqualsWithDelta(10, $quantityAfterFirstUnapprove, 0.01);

        app(InvoiceService::class)->changeInvoiceStatus($sell->fresh(), 'unapproved');

        $this->assertEqualsWithDelta(
            $quantityAfterFirstUnapprove,
            $this->findProduct((int) $sell->items->first()->itemable_id)->quantity,
            0.01,
            'A second unapprove must not subtract warehouse quantity again -- no phantom inventory.'
        );
    }
}
