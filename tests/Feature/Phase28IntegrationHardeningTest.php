<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
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
 * D-082 stage 2.8E -- Distributed Payment & Allocation Integration
 * Hardening. Adds NO new feature (decision: explicitly the same shape as
 * D-077/2.7E): this proves the whole 2.8A-2.8D chain --
 * Payment -> Financial Event -> Posting -> Allocation ->
 * Retry/Conflict/Recovery -> Final Convergence -- composes correctly,
 * without re-testing what each stage already proved individually.
 */
class Phase28IntegrationHardeningTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

    protected PaymentService $paymentService;

    protected DevicePairingService $pairingService;

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

    private function pushBatch(array $device, array $entries): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => $entries,
        ]);
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

    private function paymentEntry(float $amount, ?string $entityUuid = null, ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $entityUuid ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => [
                'payment_type' => 'credit',
                'customer_uuid' => $this->customer->uuid,
                'subject_uuid' => $this->cashSubject()->uuid,
                'amount' => $amount,
                'date' => now()->toDateString(),
            ],
            'base_sync_version' => null,
        ];
    }

    private function allocationEntry(string $paymentUuid, Invoice $invoice, float $amount, ?string $entityUuid = null, ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => PaymentAllocation::class,
            'entity_uuid' => $entityUuid ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => [
                'payment_uuid' => $paymentUuid,
                'allocatable_type' => 'invoice',
                'allocatable_uuid' => $invoice->uuid,
                'amount' => $amount,
            ],
            'base_sync_version' => null,
        ];
    }

    // === 2.8E-1: Payment-succeeds/Allocation-fails + hard-delete guards =====

    public function test_payment_succeeds_allocation_fails_leaves_payment_synced_and_untouched(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentUuid = (string) Str::uuid();

        $this->push($device, $this->paymentEntry(50, $paymentUuid))->assertJsonPath('results.0.status', 'accepted');
        $payment = Payment::where('uuid', $paymentUuid)->first();
        $syncStatusBefore = $payment->sync_status;
        $syncVersionBefore = $payment->sync_version;

        // Allocation asks for more than the invoice's remaining balance.
        $rejected = $this->push($device, $this->allocationEntry($paymentUuid, $sell, 500));
        $rejected->assertJsonPath('results.0.status', 'rejected');

        $fresh = $payment->fresh();
        $this->assertSame(SyncStatus::SYNCED, $fresh->sync_status);
        $this->assertSame($syncStatusBefore, $fresh->sync_status);
        $this->assertSame($syncVersionBefore, $fresh->sync_version);
        $this->assertSame(0, PaymentAllocation::where('payment_id', $payment->id)->count());

        // No blind rollback: the Payment (and its posting) still exist.
        $this->assertNotNull($fresh->document_id);

        // Hard delete on the (still perfectly valid) posted Payment stays rejected.
        $delete = $this->push($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $paymentUuid,
            'operation' => SyncOperation::DELETE->value,
            'payload' => ['uuid' => $paymentUuid],
            'base_sync_version' => 1,
        ]);
        $delete->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotNull(Payment::where('uuid', $paymentUuid)->first());
    }

    // === 2.8E-2: Chained retries + crash-between-Payment-and-Allocation =====

    public function test_crash_between_payment_and_allocation_resumes_only_the_allocation_on_reconnect(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentUuid = (string) Str::uuid();
        $paymentOpId = (string) Str::uuid();

        // Payment accepted; then a "crash" -- the allocation never went out.
        $this->push($device, $this->paymentEntry(100, $paymentUuid, $paymentOpId))->assertJsonPath('results.0.status', 'accepted');
        $documentCountAfterPayment = Document::withoutGlobalScopes()->count();

        // "Reconnect": retry the Payment's own operation_id (client is unsure
        // whether it landed) AND push the allocation for the first time.
        $paymentRetry = $this->push($device, $this->paymentEntry(100, $paymentUuid, $paymentOpId));
        $paymentRetry->assertJsonPath('results.0.status', 'accepted');

        $allocationUuid = (string) Str::uuid();
        $allocationOpId = (string) Str::uuid();
        $this->push($device, $this->allocationEntry($paymentUuid, $sell, 100, $allocationUuid, $allocationOpId))
            ->assertJsonPath('results.0.status', 'accepted');

        // Retry the allocation's own operation_id too (same "uncertain client" scenario).
        $allocationRetry = $this->push($device, $this->allocationEntry($paymentUuid, $sell, 100, $allocationUuid, $allocationOpId));
        $allocationRetry->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(1, Payment::where('uuid', $paymentUuid)->count());
        $this->assertSame(1, PaymentAllocation::where('uuid', $allocationUuid)->count());
        $this->assertSame($documentCountAfterPayment, Document::withoutGlobalScopes()->count()); // no second posting
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
    }

    // === 2.8E-3: Two devices, same Payment identity (D-080, chain-level) ====

    public function test_two_devices_same_payment_identity_distinction_holds_through_allocation(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentUuid = (string) Str::uuid();

        $this->push($deviceA, $this->paymentEntry(100, $paymentUuid))->assertJsonPath('results.0.status', 'accepted');

        // Device B: same identity, matching payload, different operation_id -> idempotent.
        $this->push($deviceB, $this->paymentEntry(100, $paymentUuid))->assertJsonPath('results.0.status', 'accepted');

        // Device B: same identity, DIFFERENT payload -> tamper conflict.
        $this->push($deviceB, $this->paymentEntry(999, $paymentUuid))->assertJsonPath('results.0.status', 'conflict');

        $this->assertSame(1, Payment::where('uuid', $paymentUuid)->count());
        $this->assertEqualsWithDelta(100, (float) Payment::where('uuid', $paymentUuid)->first()->amount, 0.01);

        // Device A allocates the (single, converged) Payment.
        $this->push($deviceA, $this->allocationEntry($paymentUuid, $sell, 100))->assertJsonPath('results.0.status', 'accepted');

        // Device B pulls and sees the converged Payment + Allocation state.
        $pullPayments = $this->withHeaders($this->deviceHeaders($deviceB))->getJson(
            route('api.sync.pull', ['entity_type' => Payment::class, 'after_id' => 0])
        );
        $pullAllocations = $this->withHeaders($this->deviceHeaders($deviceB))->getJson(
            route('api.sync.pull', ['entity_type' => PaymentAllocation::class, 'after_id' => 0])
        );

        $this->assertTrue(collect($pullPayments->json('rows'))->pluck('uuid')->contains($paymentUuid));
        $this->assertSame(1, collect($pullAllocations->json('rows'))->where('attributes.payment_id', Payment::where('uuid', $paymentUuid)->first()->id)->count());
    }

    // === 2.8E-4: Two devices, conflicting Allocations + pull ordering =======

    public function test_two_devices_conflicting_allocations_are_decided_by_existing_validation(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentA = (string) Str::uuid();
        $paymentB = (string) Str::uuid();

        $this->push($deviceA, $this->paymentEntry(70, $paymentA))->assertJsonPath('results.0.status', 'accepted');
        $this->push($deviceB, $this->paymentEntry(50, $paymentB))->assertJsonPath('results.0.status', 'accepted');

        // Device A allocates 70 to the invoice first.
        $this->push($deviceA, $this->allocationEntry($paymentA, $sell, 70))->assertJsonPath('results.0.status', 'accepted');

        // Device B, unaware, tries to allocate 50 more -- only 30 remains (100 - 70).
        $conflicting = $this->push($deviceB, $this->allocationEntry($paymentB, $sell, 50));
        $conflicting->assertJsonPath('results.0.status', 'rejected');

        $fresh = $this->findInvoice($sell->id);
        $this->assertEqualsWithDelta(70, $this->paymentService->paidAmount($fresh), 0.01);
        $this->assertSame(1, PaymentAllocation::whereIn('payment_id', [
            Payment::where('uuid', $paymentA)->value('id'),
            Payment::where('uuid', $paymentB)->value('id'),
        ])->count());
    }

    public function test_allocation_arriving_before_its_payment_converges_once_the_payment_arrives(): void
    {
        $device = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentUuid = (string) Str::uuid();
        $allocationOpId = (string) Str::uuid();

        // Worst-case ordering: the Allocation is listed BEFORE its Payment in
        // the same offline batch.
        $response = $this->pushBatch($device, [
            $this->allocationEntry($paymentUuid, $sell, 100, null, $allocationOpId),
            $this->paymentEntry(100, $paymentUuid),
        ]);

        $response->assertJsonPath('results.0.status', 'pending_dependency');
        $response->assertJsonPath('results.1.status', 'accepted');

        // Retry the allocation's own operation_id now that the Payment exists.
        $retry = $this->push($device, $this->allocationEntry($paymentUuid, $sell, 100, null, $allocationOpId));
        $retry->assertJsonPath('results.0.status', 'accepted');

        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
    }

    // === 2.8E-5: Capstone End-to-End Financial Invariant Test ================

    public function test_end_to_end_financial_invariant_holds_under_full_abuse(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();
        $sell = $this->approvedSell(50, 2); // 100
        $paymentUuid = (string) Str::uuid();
        $paymentOpId = (string) Str::uuid();
        $allocationAUuid = (string) Str::uuid();
        $allocationAOpId = (string) Str::uuid();
        $allocationBUuid = (string) Str::uuid();
        $allocationBOpId = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        // Payment 100, created on device A.
        $this->push($deviceA, $this->paymentEntry(100, $paymentUuid, $paymentOpId))->assertJsonPath('results.0.status', 'accepted');

        // Allocation A = 60, Allocation B = 40.
        $this->push($deviceA, $this->allocationEntry($paymentUuid, $sell, 60, $allocationAUuid, $allocationAOpId))
            ->assertJsonPath('results.0.status', 'accepted');
        $this->push($deviceA, $this->allocationEntry($paymentUuid, $sell, 40, $allocationBUuid, $allocationBOpId))
            ->assertJsonPath('results.0.status', 'accepted');

        // Abuse: duplicate pushes, retries, a concurrent device retrying the
        // SAME Payment identity, and pulls, all thrown at the converged state.
        $this->push($deviceA, $this->paymentEntry(100, $paymentUuid, $paymentOpId)); // retry
        $this->push($deviceA, $this->allocationEntry($paymentUuid, $sell, 60, $allocationAUuid, $allocationAOpId)); // retry
        $this->push($deviceB, $this->paymentEntry(100, $paymentUuid)); // different op_id, matching payload -> idempotent
        $this->withHeaders($this->deviceHeaders($deviceB))->getJson(route('api.sync.pull', ['entity_type' => Payment::class, 'after_id' => 0]));
        $this->withHeaders($this->deviceHeaders($deviceB))->getJson(route('api.sync.pull', ['entity_type' => PaymentAllocation::class, 'after_id' => 0]));
        $this->push($deviceA, $this->allocationEntry($paymentUuid, $sell, 40, $allocationBUuid, $allocationBOpId)); // retry

        $payment = Payment::where('uuid', $paymentUuid)->first();
        $allocationA = PaymentAllocation::where('uuid', $allocationAUuid)->first();
        $allocationB = PaymentAllocation::where('uuid', $allocationBUuid)->first();
        $invoice = $this->findInvoice($sell->id);

        $this->assertSame(1, Payment::where('uuid', $paymentUuid)->count());
        $this->assertEqualsWithDelta(100, (float) $payment->amount, 0.01);
        $this->assertEqualsWithDelta(60, (float) $allocationA->amount, 0.01);
        $this->assertEqualsWithDelta(40, (float) $allocationB->amount, 0.01);
        $this->assertEqualsWithDelta(100, $this->paymentService->paidAmount($invoice), 0.01);
        $this->assertEqualsWithDelta(0, $this->paymentService->unallocatedAmount($payment), 0.01);
        $this->assertTrue($invoice->status->isPaid());
        $this->assertSame(2, PaymentAllocation::where('payment_id', $payment->id)->count());
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count()); // exactly one posting
    }
}
