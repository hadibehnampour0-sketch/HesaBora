<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
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

class PaymentAllocationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9000;

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
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
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

    private function approvedSell(float $unit, int $qty): Invoice
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

    public function test_credit_payment_creates_balanced_document_and_is_fully_unallocated(): void
    {
        $payment = $this->creditPayment(1000);

        $this->assertNull($payment->invoice_id);
        $this->assertNotNull($payment->document_id);
        $this->assertEqualsWithDelta(0, $payment->document->transactions->sum('value'), 0.01);

        $customerTx = $payment->document->transactions->firstWhere('subject_id', $this->customer->subject->id);
        $this->assertGreaterThan(0, $customerTx->value);

        $this->assertEqualsWithDelta(1000, $this->paymentService->unallocatedAmount($payment), 0.01);
    }

    public function test_allocating_credit_payment_to_one_invoice_partially(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000 total
        $payment = $this->creditPayment(1000);

        $this->paymentService->allocatePayment($this->user, $payment, $sell, 800);

        $sell = $this->findInvoice($sell->id);
        $this->assertTrue($sell->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(800, $this->paymentService->paidAmount($sell), 0.01);
        $this->assertEqualsWithDelta(200, $this->paymentService->unallocatedAmount($payment->fresh()), 0.01);
    }

    public function test_one_payment_allocated_across_multiple_invoices(): void
    {
        $sellA = $this->approvedSell(1000, 1); // 1000
        $sellB = $this->approvedSell(500, 1); // 500
        $payment = $this->creditPayment(1500);

        $this->paymentService->allocatePayment($this->user, $payment, $sellA, 1000);
        $this->paymentService->allocatePayment($this->user, $payment, $sellB, 500);

        $this->assertTrue($this->findInvoice($sellA->id)->status->isPaid());
        $this->assertTrue($this->findInvoice($sellB->id)->status->isPaid());
        $this->assertEqualsWithDelta(0, $this->paymentService->unallocatedAmount($payment->fresh()), 0.01);
    }

    public function test_multiple_credit_payments_allocated_to_one_invoice(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000
        $first = $this->creditPayment(1200);
        $second = $this->creditPayment(800);

        $this->paymentService->allocatePayment($this->user, $first, $sell, 1200);
        $this->paymentService->allocatePayment($this->user, $second, $sell, 800);

        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
        $this->assertEqualsWithDelta(2000, $this->paymentService->paidAmount($this->findInvoice($sell->id)), 0.01);
    }

    public function test_allocation_cannot_exceed_payment_unallocated_balance(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(500);

        $this->expectException(ValidationException::class);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 600);
    }

    public function test_allocation_cannot_exceed_invoice_remaining_balance(): void
    {
        $sell = $this->approvedSell(1000, 1); // 1000
        $payment = $this->creditPayment(2000);

        $this->expectException(ValidationException::class);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1500);
    }

    public function test_a_singly_linked_payment_cannot_be_allocated(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $decision = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->assertFalse($decision->hasErrors());
        $linkedPayment = $sell->payments()->latest('id')->firstOrFail();

        $otherSell = $this->approvedSell(1000, 1);

        $this->expectException(ValidationException::class);
        $this->paymentService->allocatePayment($this->user, $linkedPayment, $otherSell, 100);
    }

    public function test_removing_a_posted_allocation_is_rejected_and_leaves_everything_intact(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(2000);

        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());

        $documentId = $payment->document_id;

        // D-084: a posted PaymentAllocation is a permanent financial record
        // -- removal through the service layer is rejected, and the
        // allocation/invoice/payment/document are left exactly as they were.
        // (Superseded the pre-D-084 assertion that removal succeeded and
        // reverted the invoice to Approved.)
        $this->expectException(ValidationException::class);

        try {
            $this->paymentService->removeAllocation($allocation);
        } finally {
            $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
            $this->assertEqualsWithDelta(0, $this->paymentService->unallocatedAmount($payment->fresh()), 0.01);
            $this->assertNotNull(Document::withoutGlobalScopes()->find($documentId));
            $this->assertEquals($documentId, $payment->fresh()->document_id);
        }
    }

    /**
     * D-084: deleting a Payment with multiple live allocations is rejected
     * the same as any other posted Payment -- every allocated invoice stays
     * exactly as settled as it was. (Superseded "...resyncs_every_allocated_
     * invoice", which asserted the pre-D-084 successful-delete behavior.)
     */
    public function test_deleting_a_credit_payment_with_multiple_allocations_is_rejected(): void
    {
        $sellA = $this->approvedSell(1000, 1);
        $sellB = $this->approvedSell(500, 1);
        $payment = $this->creditPayment(1500);

        $this->paymentService->allocatePayment($this->user, $payment, $sellA, 1000);
        $this->paymentService->allocatePayment($this->user, $payment, $sellB, 500);

        $this->expectException(ValidationException::class);

        try {
            $this->paymentService->deletePayment($payment);
        } finally {
            $this->assertTrue($this->findInvoice($sellA->id)->status->isPaid());
            $this->assertTrue($this->findInvoice($sellB->id)->status->isPaid());
            $this->assertNotNull(Payment::withoutGlobalScopes()->find($payment->id));
        }
    }

    public function test_allocation_rejected_for_unapproved_invoice(): void
    {
        $product = $this->createProduct();
        $draft = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice']->id);
        $payment = $this->creditPayment(1000);

        $this->expectException(ValidationException::class);
        $this->paymentService->allocatePayment($this->user, $payment, $draft, 500);
    }
}
