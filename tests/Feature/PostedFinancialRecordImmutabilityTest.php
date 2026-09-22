<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-084 -- Posted Financial Record Immutability at the Service Layer.
 * Payment has no draft state (D-078) and PaymentAllocation has no draft
 * state (D-081): every existing row is already a posted financial event the
 * moment the domain service creates it, so these tests exercise the domain
 * service directly (never HTTP/UI), the same way the Phase 2 audit found the
 * gap -- the Sync layer already rejected these mutations; the service layer
 * did not, until this Decision.
 */
class PostedFinancialRecordImmutabilityTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

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

    private function approvedSell(float $unit, int $qty)
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function creditPayment(float $amount): Payment
    {
        return $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => $amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
    }

    // --- Payment -----------------------------------------------------------

    public function test_a_freshly_created_payment_is_immediately_immutable(): void
    {
        $payment = $this->creditPayment(500);

        $this->expectException(ValidationException::class);
        $this->paymentService->deletePayment($payment);
    }

    public function test_deleting_a_posted_payment_is_rejected(): void
    {
        $payment = $this->creditPayment(500);

        $this->expectException(ValidationException::class);
        $this->paymentService->deletePayment($payment);
    }

    public function test_a_rejected_payment_delete_does_not_partially_delete_the_payment(): void
    {
        $payment = $this->creditPayment(500);
        $documentId = $payment->document_id;

        try {
            $this->paymentService->deletePayment($payment);
        } catch (ValidationException) {
            // expected
        }

        $fresh = Payment::withoutGlobalScopes()->find($payment->id);
        $this->assertNotNull($fresh);
        $this->assertSame($documentId, $fresh->document_id);
        $this->assertEqualsWithDelta(500, (float) $fresh->amount, 0.01);
    }

    public function test_a_rejected_payment_delete_leaves_the_historical_document_intact(): void
    {
        $payment = $this->creditPayment(500);
        $documentId = $payment->document_id;

        try {
            $this->paymentService->deletePayment($payment);
        } catch (ValidationException) {
            // expected
        }

        $document = Document::withoutGlobalScopes()->find($documentId);
        $this->assertNotNull($document);
        $this->assertEqualsWithDelta(0, $document->transactions->sum('value'), 0.01);
    }

    public function test_an_invoice_linked_payment_is_also_immutable(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 1000,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $payment = $sell->payments()->latest('id')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->paymentService->deletePayment($payment);
    }

    // --- PaymentAllocation ---------------------------------------------------

    public function test_a_successfully_allocated_payment_allocation_is_immutable(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(2000);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);

        $this->expectException(ValidationException::class);
        $this->paymentService->removeAllocation($allocation);
    }

    public function test_removing_an_allocation_is_rejected(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(2000);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);

        $this->expectException(ValidationException::class);
        $this->paymentService->removeAllocation($allocation);
    }

    public function test_a_rejected_allocation_removal_does_not_modify_the_payment(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(2000);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);
        $syncVersionBefore = $payment->fresh()->sync_version;

        try {
            $this->paymentService->removeAllocation($allocation);
        } catch (ValidationException) {
            // expected
        }

        $freshPayment = $payment->fresh();
        $this->assertSame($syncVersionBefore, $freshPayment->sync_version);
        $this->assertEqualsWithDelta(0, $this->paymentService->unallocatedAmount($freshPayment), 0.01);
    }

    public function test_a_rejected_allocation_removal_leaves_associated_financial_records_intact(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(2000);
        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);

        try {
            $this->paymentService->removeAllocation($allocation);
        } catch (ValidationException) {
            // expected
        }

        $this->assertNotNull(PaymentAllocation::find($allocation->id));
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
        $this->assertNotNull(Document::withoutGlobalScopes()->find($payment->fresh()->document_id));
    }
}
