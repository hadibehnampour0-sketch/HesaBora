<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\SyncOutboxEntry;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-079 stage 2.8B -- Offline Payment Creation + Sync. Payment is never
 * made two-phase; it stays the existing atomic
 * Create -> PaymentService -> Document -> posting operation, and Sync
 * manages it as one idempotent financial operation, never a second
 * accounting engine.
 */
class PaymentOfflineSyncTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected User $user;

    protected DevicePairingService $pairingService;

    protected PaymentService $paymentService;

    protected Customer $customer;

    protected ?int $cashBoxSubjectId = null;

    protected int $nextInvoiceNumber = 9900;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->pairingService = app(DevicePairingService::class);
        $this->paymentService = app(PaymentService::class);
    }

    private function makeDevice(): array
    {
        $company = Company::find($this->companyId);
        $user = User::factory()->create();
        $company->users()->syncWithoutDetaching($user);
        $code = $this->pairingService->issueActivationCode($company, $user);

        return $this->pairingService->pair($code);
    }

    private function deviceHeaders(array $paired): array
    {
        return [
            'X-Device-Uuid' => $paired['installation']->installation_uuid,
            'X-Device-Key' => $paired['device_key'],
        ];
    }

    private function cashSubject(): Subject
    {
        if ($this->cashBoxSubjectId !== null) {
            return Subject::withoutGlobalScopes()->find($this->cashBoxSubjectId);
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $this->companyId]);
        $this->cashBoxSubjectId = (int) $box->id;

        return $box;
    }

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function creditPaymentEntry(array $overrides = [], ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $overrides['entity_uuid'] ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => array_merge([
                'payment_type' => 'credit',
                'customer_uuid' => $this->customer->uuid,
                'subject_uuid' => $this->cashSubject()->uuid,
                'amount' => 1000,
                'date' => now()->toDateString(),
                'description' => 'Offline credit payment',
            ], $overrides['payload'] ?? []),
            'base_sync_version' => null,
        ];
    }

    private function invoicePaymentEntry(Invoice $invoice, array $overrides = [], ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $overrides['entity_uuid'] ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => array_merge([
                'payment_type' => 'invoice',
                'invoice_uuid' => $invoice->uuid,
                'subject_uuid' => $this->cashSubject()->uuid,
                'amount' => 500,
                'date' => now()->toDateString(),
                'description' => 'Offline invoice payment',
            ], $overrides['payload'] ?? []),
            'base_sync_version' => null,
        ];
    }

    private function push(array $device, array $entry): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [$entry],
        ]);
    }

    // --- 1. Offline credit payment --------------------------------------------

    public function test_offline_credit_payment_is_accepted_and_posted_exactly_once(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $response = $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]));

        $response->assertJsonPath('results.0.status', 'accepted');
        $payment = Payment::where('uuid', $entityUuid)->first();
        $this->assertNotNull($payment);
        $this->assertSame($entityUuid, $payment->financial_event_uuid);
        $this->assertNotNull($payment->document_id);
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
        $this->assertEqualsWithDelta(0, $payment->document->transactions->sum('value'), 0.01);
    }

    // --- 2. Offline invoice-linked payment ------------------------------------

    public function test_offline_invoice_linked_payment_is_accepted_and_posted_exactly_once(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000 total
        $entityUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $response = $this->push($device, $this->invoicePaymentEntry($sell, ['entity_uuid' => $entityUuid]));

        $response->assertJsonPath('results.0.status', 'accepted');
        $payment = Payment::where('uuid', $entityUuid)->first();
        $this->assertNotNull($payment);
        $this->assertSame($sell->id, $payment->invoice_id);
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
        $this->assertTrue($this->findInvoice($sell->id)->status->isPartiallyPaid());
    }

    // --- 3. Dependency-aware: missing Customer/Subject ------------------------

    public function test_a_payment_referencing_a_not_yet_existing_customer_becomes_pending_dependency_then_converges(): void
    {
        $device = $this->makeDevice();
        $missingCustomerUuid = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();

        $entry = $this->creditPaymentEntry([
            'entity_uuid' => $entityUuid,
            'payload' => ['customer_uuid' => $missingCustomerUuid],
        ], $operationId);

        $this->push($device, $entry)->assertJsonPath('results.0.status', 'pending_dependency');
        $this->assertNull(Payment::where('uuid', $entityUuid)->first());

        // The customer "arrives".
        $arrivedCustomer = $this->customer;
        $arrivedCustomer->forceFill(['uuid' => $missingCustomerUuid])->save();

        $retry = $this->push($device, $entry);
        $retry->assertJsonPath('results.0.status', 'accepted');
        $this->assertNotNull(Payment::where('uuid', $entityUuid)->first());
    }

    // --- 4/5/6/7/8/9. Retry / duplicate push / crash-after-success -----------

    public function test_resending_the_same_operation_id_never_creates_a_duplicate_payment_or_posting(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $entry = $this->creditPaymentEntry(['entity_uuid' => $entityUuid], $operationId);
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $first = $this->push($device, $entry);
        $first->assertJsonPath('results.0.status', 'accepted');

        // "Crash": the client never saw the response and resends identically
        // (simulated network timeout / crash-after-server-success).
        $second = $this->push($device, $entry);
        $second->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(1, Payment::where('uuid', $entityUuid)->count());
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 10. Pull / convergence ------------------------------------------------

    public function test_pull_returns_the_synced_payment(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]))->assertJsonPath('results.0.status', 'accepted');

        $response = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => Payment::class, 'after_id' => 0])
        );

        $response->assertOk();
        $uuids = collect($response->json('rows'))->pluck('uuid');
        $this->assertTrue($uuids->contains($entityUuid));
    }

    // --- 11. Hard delete rejected ----------------------------------------------

    public function test_a_delete_operation_against_a_payment_is_rejected(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]))->assertJsonPath('results.0.status', 'accepted');

        $response = $this->push($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $entityUuid,
            'operation' => SyncOperation::DELETE->value,
            'payload' => ['uuid' => $entityUuid],
            'base_sync_version' => 1,
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotNull(Payment::where('uuid', $entityUuid)->first());
    }

    // --- 12. Rejections: invalid payload never posts --------------------------

    public function test_zero_amount_is_rejected_with_no_posting(): void
    {
        $device = $this->makeDevice();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $response = $this->push($device, $this->creditPaymentEntry(['payload' => ['amount' => 0]]));

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());
    }

    public function test_cheque_linked_payments_are_rejected_stage_scope(): void
    {
        $device = $this->makeDevice();

        $response = $this->push($device, $this->creditPaymentEntry(['payload' => ['cheque_id' => 1]]));

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    // --- End-to-end: offline -> reconnect -> retry -> pull -> final state ----

    public function test_end_to_end_offline_payment_converges_to_exactly_one_financial_event(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $entry = $this->creditPaymentEntry(['entity_uuid' => $entityUuid], $operationId);
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        // push
        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');
        // retry push (simulated reconnect/timeout)
        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');
        // pull
        $pull = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => Payment::class, 'after_id' => 0])
        );

        $pull->assertOk();
        $rows = collect($pull->json('rows'))->where('uuid', $entityUuid);
        $this->assertCount(1, $rows);

        $this->assertSame(1, Payment::where('uuid', $entityUuid)->count());
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- D-083.1: Payment local creation -> Outbox auto-enqueue ---------------
    //
    // This proves the OTHER half of Payment Sync that the tests above never
    // exercised: every test up to this point pushes a hand-built sync entry
    // directly into the inbox. None of them go through PaymentService the
    // way local, non-sync production usage actually does and then check
    // that SyncsPaymentsToOutbox's `created` hook really enqueued anything.

    public function test_a_local_credit_payment_created_directly_through_the_service_enqueues_exactly_one_outbox_create(): void
    {
        $entriesBefore = SyncOutboxEntry::where('entity_type', Payment::class)->count();

        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 750,
            'subject_id' => $this->cashSubject()->id,
        ]);

        $entries = SyncOutboxEntry::where('entity_type', Payment::class)->get();
        $this->assertSame($entriesBefore + 1, $entries->count());

        $entry = SyncOutboxEntry::where('entity_type', Payment::class)->where('entity_uuid', $payment->uuid)->sole();
        $this->assertSame(SyncOperation::CREATE, $entry->operation);
        $this->assertSame($payment->uuid, $entry->entity_uuid);
        $this->assertSame($payment->financial_event_uuid, $entry->entity_uuid);
        $this->assertSame('credit', $entry->payload['payment_type']);
        $this->assertSame($this->customer->uuid, $entry->payload['customer_uuid']);
        $this->assertSame($this->cashSubject()->uuid, $entry->payload['subject_uuid']);
    }

    public function test_a_local_invoice_linked_payment_created_directly_through_the_service_enqueues_exactly_one_outbox_create(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000 total

        $decision = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 500,
            'subject_id' => $this->cashSubject()->id,
        ]);
        $this->assertFalse($decision->hasErrors());
        $payment = $sell->payments()->latest('id')->firstOrFail();

        $entry = SyncOutboxEntry::where('entity_type', Payment::class)->where('entity_uuid', $payment->uuid)->sole();
        $this->assertSame(SyncOperation::CREATE, $entry->operation);
        $this->assertSame('invoice', $entry->payload['payment_type']);
        $this->assertSame($sell->uuid, $entry->payload['invoice_uuid']);
    }
}
