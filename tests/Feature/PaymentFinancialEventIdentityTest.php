<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\Sync\SyncOutboxService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-078 stage 2.8A -- Payment Financial Event Identity. Payment already
 * carries a `uuid` via HasSyncEnvelope (stage 2.3); this stage's only job
 * is establishing that this identity IS the `financial_event_uuid` the
 * Decision Log names, that it is stable for the life of the record, and
 * that it is a genuinely different concept from a sync `operation_id`
 * (which identifies one sync attempt, not the economic event itself).
 * No CRUD-sync wiring for Payment is built here -- that is 2.8B.
 */
class PaymentFinancialEventIdentityTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9500;

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

    private function creditPayment(float $amount): Payment
    {
        return $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => $amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
    }

    // --- 1. A financial_event_uuid is assigned at creation, every path -----

    public function test_a_credit_payment_gets_a_valid_financial_event_uuid(): void
    {
        $payment = $this->creditPayment(1000);

        $this->assertNotNull($payment->financial_event_uuid);
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/i', $payment->financial_event_uuid));
        $this->assertSame($payment->uuid, $payment->financial_event_uuid);
    }

    public function test_an_invoice_linked_payment_gets_a_valid_financial_event_uuid(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $decision = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->assertFalse($decision->hasErrors());

        $payment = $sell->payments()->latest('id')->firstOrFail();
        $this->assertNotNull($payment->financial_event_uuid);
    }

    // --- 2. Two distinct payments never share an identity --------------------

    public function test_two_payments_get_distinct_financial_event_uuids(): void
    {
        $first = $this->creditPayment(500);
        $second = $this->creditPayment(300);

        $this->assertNotSame($first->financial_event_uuid, $second->financial_event_uuid);
    }

    // --- 3. The identity is stable -- never changes after allocation/updates -

    public function test_financial_event_uuid_never_changes_across_allocation_or_update(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(2000);
        $originalUuid = $payment->financial_event_uuid;

        $this->paymentService->allocatePayment($this->user, $payment, $sell, 2000);

        $fresh = $payment->fresh();
        $this->assertSame($originalUuid, $fresh->financial_event_uuid);

        $fresh->update(['description' => 'A later note']);
        $this->assertSame($originalUuid, $fresh->fresh()->financial_event_uuid);
    }

    // --- 4. financial_event_uuid is a different concept from operation_id ---

    public function test_financial_event_uuid_is_distinct_from_a_sync_operation_ids(): void
    {
        $payment = $this->creditPayment(1000);

        $firstPush = app(SyncOutboxService::class)->enqueue($payment, SyncOperation::CREATE, $this->companyId);
        $secondPush = app(SyncOutboxService::class)->enqueue($payment, SyncOperation::UPDATE, $this->companyId, $payment->sync_version);

        // Two DIFFERENT sync attempts (operation_id) about the exact SAME
        // economic event (entity_uuid == financial_event_uuid).
        $this->assertNotSame($firstPush->operation_id, $secondPush->operation_id);
        $this->assertSame($payment->financial_event_uuid, $firstPush->entity_uuid);
        $this->assertSame($payment->financial_event_uuid, $secondPush->entity_uuid);
        $this->assertSame($firstPush->entity_uuid, $secondPush->entity_uuid);
    }
}
