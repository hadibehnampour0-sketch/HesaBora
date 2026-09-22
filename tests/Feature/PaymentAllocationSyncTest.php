<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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
 * D-081 stage 2.8D-1 -- Allocation UUID + Sync Envelope. PaymentAllocation
 * becomes its own independent Sync Entity, identity-only for this
 * micro-stage (no sync wiring yet -- that is 2.8D-2).
 */
class PaymentAllocationSyncTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9600;

    protected PaymentService $paymentService;

    protected ?int $cashBoxSubjectId = null;

    protected DevicePairingService $pairingService;

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

        $this->pairingService = app(DevicePairingService::class);
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

    private function push(array $device, array $entry): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [$entry],
        ]);
    }

    private function allocationEntry(Payment $payment, Invoice $invoice, float $amount, ?string $entityUuid = null, ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => PaymentAllocation::class,
            'entity_uuid' => $entityUuid ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => [
                'payment_uuid' => $payment->uuid,
                'allocatable_type' => 'invoice',
                'allocatable_uuid' => $invoice->uuid,
                'amount' => $amount,
            ],
            'base_sync_version' => null,
        ];
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

    // --- 2.8D-1: identity ------------------------------------------------------

    public function test_allocating_a_payment_assigns_a_stable_uuid(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(1000);

        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 800);

        $this->assertNotNull($allocation->uuid);
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/i', $allocation->uuid));

        $originalUuid = $allocation->uuid;
        $allocation->refresh();
        $this->assertSame($originalUuid, $allocation->uuid);
    }

    public function test_two_allocations_get_distinct_uuids(): void
    {
        $sellA = $this->approvedSell(1000, 1);
        $sellB = $this->approvedSell(500, 1);
        $payment = $this->creditPayment(1500);

        $allocationA = $this->paymentService->allocatePayment($this->user, $payment, $sellA, 1000);
        $allocationB = $this->paymentService->allocatePayment($this->user, $payment, $sellB, 500);

        $this->assertNotSame($allocationA->uuid, $allocationB->uuid);
    }

    // --- 2.8D-2: PaymentAllocation CREATE sync --------------------------------

    public function test_creating_an_allocation_via_sync_calls_the_existing_payment_service_exactly_once(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(800);
        $entityUuid = (string) Str::uuid();

        $response = $this->push($device, $this->allocationEntry($payment, $sell, 800, $entityUuid));

        $response->assertJsonPath('results.0.status', 'accepted');
        $allocation = PaymentAllocation::where('uuid', $entityUuid)->first();
        $this->assertNotNull($allocation);
        $this->assertSame($payment->id, $allocation->payment_id);
        $this->assertSame(Invoice::class, $allocation->allocatable_type);
        $this->assertSame($sell->id, $allocation->allocatable_id);
        $this->assertEqualsWithDelta(800, (float) $allocation->amount, 0.01);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPartiallyPaid());
    }

    public function test_duplicate_create_with_the_same_operation_id_does_not_double_allocate(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(800);
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $entry = $this->allocationEntry($payment, $sell, 800, $entityUuid, $operationId);

        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');
        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(1, PaymentAllocation::where('uuid', $entityUuid)->count());
        $this->assertEqualsWithDelta(800, $this->paymentService->paidAmount($this->findInvoice($sell->id)), 0.01);
    }

    // --- D-080-equivalent for Allocation: same identity, different operation_id
    //
    // paymentAllocationMatchesExisting() has implemented this distinction
    // since D-081, but no test ever exercised it directly (a genuine
    // coverage gap found during the post-Phase-2 hardening audit, Batch C).

    public function test_a_different_operation_id_for_the_same_allocation_identity_with_matching_payload_converges(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(800);
        $entityUuid = (string) Str::uuid();

        $this->push($device, $this->allocationEntry($payment, $sell, 800, $entityUuid))->assertJsonPath('results.0.status', 'accepted');

        // A DIFFERENT operation_id (device lost track of its own first
        // attempt) claiming the SAME allocation identity, same details.
        $again = $this->push($device, $this->allocationEntry($payment, $sell, 800, $entityUuid));

        $again->assertJsonPath('results.0.status', 'accepted');
        $this->assertSame(1, PaymentAllocation::where('uuid', $entityUuid)->count());
    }

    public function test_a_different_operation_id_for_the_same_allocation_identity_with_a_different_payload_is_a_conflict(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(800);
        $entityUuid = (string) Str::uuid();

        $this->push($device, $this->allocationEntry($payment, $sell, 800, $entityUuid))->assertJsonPath('results.0.status', 'accepted');

        $tampered = $this->push($device, $this->allocationEntry($payment, $sell, 500, $entityUuid));

        $tampered->assertJsonPath('results.0.status', 'conflict');
        $allocation = PaymentAllocation::where('uuid', $entityUuid)->first();
        $this->assertEqualsWithDelta(800, (float) $allocation->amount, 0.01);
        $this->assertSame(1, PaymentAllocation::where('uuid', $entityUuid)->count());
    }

    // --- 2.8D-3: Partial + multi-invoice allocations --------------------------

    public function test_multiple_allocations_against_one_payment_split_across_invoices(): void
    {
        $device = $this->makeDevice();
        $sellA = $this->approvedSell(1000, 1); // 1000
        $sellB = $this->approvedSell(500, 1); // 500
        $payment = $this->creditPayment(1500);

        $this->push($device, $this->allocationEntry($payment, $sellA, 1000))->assertJsonPath('results.0.status', 'accepted');
        $this->push($device, $this->allocationEntry($payment, $sellB, 500))->assertJsonPath('results.0.status', 'accepted');

        $this->assertTrue($this->findInvoice($sellA->id)->status->isPaid());
        $this->assertTrue($this->findInvoice($sellB->id)->status->isPaid());
        $this->assertEqualsWithDelta(0, $this->paymentService->unallocatedAmount($payment->fresh()), 0.01);
        $this->assertSame(2, PaymentAllocation::where('payment_id', $payment->id)->count());
    }

    public function test_a_partial_allocation_leaves_the_invoice_partially_paid(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(1200);

        $this->push($device, $this->allocationEntry($payment, $sell, 800))->assertJsonPath('results.0.status', 'accepted');

        $fresh = $this->findInvoice($sell->id);
        $this->assertTrue($fresh->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(1200, $this->paymentService->remainingAmount($fresh), 0.01);
    }

    // --- 2.8D-4: Idempotency + dependency + business rejection ---------------

    public function test_an_allocation_referencing_a_not_yet_synced_payment_becomes_pending_dependency_then_converges(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->creditPayment(1000);
        $missingPaymentUuid = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();

        $entry = $this->allocationEntry($payment, $sell, 1000, $entityUuid, $operationId);
        $entry['payload']['payment_uuid'] = $missingPaymentUuid;

        $this->push($device, $entry)->assertJsonPath('results.0.status', 'pending_dependency');
        $this->assertNull(PaymentAllocation::where('uuid', $entityUuid)->first());

        // The payment "arrives" under the uuid the allocation was waiting for.
        $payment->forceFill(['uuid' => $missingPaymentUuid])->save();

        $retry = $this->push($device, $entry);
        $retry->assertJsonPath('results.0.status', 'accepted');
        $this->assertNotNull(PaymentAllocation::where('uuid', $entityUuid)->first());
    }

    public function test_over_allocation_is_a_business_rejection_via_existing_validation(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 1); // 1000
        $payment = $this->creditPayment(2000);

        $response = $this->push($device, $this->allocationEntry($payment, $sell, 1500));

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, PaymentAllocation::where('payment_id', $payment->id)->count());
    }

    public function test_destination_already_settled_by_another_device_is_rejected(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();
        $sell = $this->approvedSell(1000, 1); // 1000
        $paymentA = $this->creditPayment(1000);
        $paymentB = $this->creditPayment(1000);

        // Device A settles the invoice first.
        $this->push($deviceA, $this->allocationEntry($paymentA, $sell, 1000))->assertJsonPath('results.0.status', 'accepted');
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());

        // Device B, unaware, tries to allocate its own payment to the same
        // now-fully-settled invoice.
        $response = $this->push($deviceB, $this->allocationEntry($paymentB, $sell, 500));

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, PaymentAllocation::where('payment_id', $paymentB->id)->count());
    }

    public function test_allocation_rejection_never_changes_the_payments_own_sync_status(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->creditPayment(2000);
        $syncStatusBefore = $payment->fresh()->sync_status;
        $syncVersionBefore = $payment->fresh()->sync_version;

        $this->push($device, $this->allocationEntry($payment, $sell, 1500))->assertJsonPath('results.0.status', 'rejected');

        $fresh = $payment->fresh();
        $this->assertSame($syncStatusBefore, $fresh->sync_status);
        $this->assertSame($syncVersionBefore, $fresh->sync_version);
    }

    // --- 2.8D-5: Integration / convergence hardening --------------------------

    public function test_offline_allocation_reconnect_retry_pull_converges_to_exactly_one_allocation(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(800);
        $entityUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $entry = $this->allocationEntry($payment, $sell, 800, $entityUuid, $operationId);

        // push
        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');
        // retry push (simulated reconnect/timeout)
        $this->push($device, $entry)->assertJsonPath('results.0.status', 'accepted');
        // pull
        $pull = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => PaymentAllocation::class, 'after_id' => 0])
        );

        $pull->assertOk();
        $rows = collect($pull->json('rows'))->where('uuid', $entityUuid);
        $this->assertCount(1, $rows);
        $this->assertSame(1, PaymentAllocation::where('uuid', $entityUuid)->count());
        $this->assertEqualsWithDelta(800, $this->paymentService->paidAmount($this->findInvoice($sell->id)), 0.01);
    }

    public function test_a_delete_or_update_of_an_allocation_is_rejected_out_of_scope(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->creditPayment(1000);
        $entityUuid = (string) Str::uuid();
        $this->push($device, $this->allocationEntry($payment, $sell, 1000, $entityUuid))->assertJsonPath('results.0.status', 'accepted');

        $delete = $this->push($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => PaymentAllocation::class,
            'entity_uuid' => $entityUuid,
            'operation' => SyncOperation::DELETE->value,
            'payload' => ['uuid' => $entityUuid],
            'base_sync_version' => 1,
        ]);
        $delete->assertJsonPath('results.0.status', 'rejected');

        $update = $this->push($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => PaymentAllocation::class,
            'entity_uuid' => $entityUuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['amount' => 999],
            'base_sync_version' => 1,
        ]);
        $update->assertJsonPath('results.0.status', 'rejected');

        $this->assertNotNull(PaymentAllocation::where('uuid', $entityUuid)->first());
        $this->assertEqualsWithDelta(1000, (float) PaymentAllocation::where('uuid', $entityUuid)->first()->amount, 0.01);
    }

    // --- D-083.2: PaymentAllocation local creation -> Outbox auto-enqueue -----
    //
    // Every test above pushes a hand-built sync entry straight into the
    // inbox (the server-apply direction, proven since 2.8D). None of them
    // exercise the OTHER half: a locally created allocation (the existing,
    // non-sync `allocatePayment()` call every web request already makes)
    // must itself land in the Outbox. Before this trait existed, it did not.

    public function test_a_local_allocation_created_directly_through_the_service_enqueues_exactly_one_outbox_create(): void
    {
        $sell = $this->approvedSell(1000, 2); // 2000
        $payment = $this->creditPayment(800);
        $entriesBefore = SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->count();

        $allocation = $this->paymentService->allocatePayment($this->user, $payment, $sell, 800);

        $this->assertSame($entriesBefore + 1, SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->count());

        $entry = SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->where('entity_uuid', $allocation->uuid)->sole();
        $this->assertSame(SyncOperation::CREATE, $entry->operation);
        $this->assertSame($payment->uuid, $entry->payload['payment_uuid']);
        $this->assertSame('invoice', $entry->payload['allocatable_type']);
        $this->assertSame($sell->uuid, $entry->payload['allocatable_uuid']);
        $this->assertEqualsWithDelta(800, (float) $entry->payload['amount'], 0.01);
    }

    public function test_a_rejected_local_allocation_attempt_never_creates_an_outbox_entry(): void
    {
        $sell = $this->approvedSell(1000, 1); // 1000
        $payment = $this->creditPayment(500);
        $entriesBefore = SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->count();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $this->paymentService->allocatePayment($this->user, $payment, $sell, 999);
        } finally {
            $this->assertSame($entriesBefore, SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->count());
        }
    }

    public function test_a_synced_allocation_applied_on_the_server_does_not_re_enqueue_itself(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->creditPayment(800);

        $this->push($device, $this->allocationEntry($payment, $sell, 800))->assertJsonPath('results.0.status', 'accepted');

        // The allocation SyncPilotService::applyPaymentAllocationCreate()
        // just materialized via the domain service must not itself enqueue
        // a second, redundant Outbox CREATE for the same event it just
        // received (D-081 decision 2/D-083.2: no local+remote double loop).
        $this->assertSame(0, SyncOutboxEntry::where('entity_type', PaymentAllocation::class)->count());
    }
}
