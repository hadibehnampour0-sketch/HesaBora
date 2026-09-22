<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\SyncInboxEntry;
use App\Services\DevicePairingService;
use App\Services\Sync\NumberingLeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-077 stage 2.7E -- Document/Invoice Sync Integration & Hardening Gate.
 * Adds NO new feature (decision 1): this proves 2.7A-2.7D compose
 * correctly as one chain -- Customer -> Invoice Draft -> Dependency ->
 * Finalize+Number -> Approve/Post -> Retry/Conflict/Recovery -> Converged
 * state -- with no duplicate posting, orphan document, lost operation,
 * silent overwrite, or wrong numbering anywhere in the chain.
 */
class Phase2IntegrationHardeningTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected NumberingLeaseService $leaseService;

    private static int $nextTestInvoiceNumber = 800000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->pairingService = app(DevicePairingService::class);
        $this->leaseService = app(NumberingLeaseService::class);
    }

    private function makeDevice(): array
    {
        $company = Company::find($this->companyId);
        $user = \App\Models\User::factory()->create();
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

    private function pushBatch(array $device, array $entries): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => $entries,
        ]);
    }

    private function pushOne(array $device, array $entry): TestResponse
    {
        return $this->pushBatch($device, [$entry]);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        return Customer::factory()->withGroup($group)->withSubject()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    /**
     * A bare sync-created Customer has no accounting `Subject` -- that
     * setup (CustomerGroup -> Subject -> Customer.subject_id) is a
     * pre-existing Customer/accounting domain requirement, entirely
     * separate from and pre-dating Document/Invoice sync, so 2.7E does not
     * touch it (D-077 decision 1: no accounting-semantics fixes here).
     * This helper only satisfies that unrelated requirement so a
     * dependency-chain test can proceed all the way to a real posting.
     */
    private function attachAccountingSubjectTo(Customer $customer): void
    {
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $subject = \App\Models\Subject::factory()
            ->withParent(\App\Models\Subject::withoutGlobalScopes()->find($group->subject_id))
            ->for($customer, 'subjectable')
            ->create(['name' => $customer->name, 'company_id' => $this->companyId]);

        $customer->subject_id = $subject->id;
        $customer->saveQuietly();
    }

    private function makeDraftInvoice(array $overrides = []): Invoice
    {
        $defaults = [
            'number' => (string) (++self::$nextTestInvoiceNumber),
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PENDING,
            'date' => now()->toDateString(),
            'title' => 'Draft invoice',
            'vat' => 0,
        ];

        if (! isset($overrides['customer_id'])) {
            $defaults['customer_id'] = $this->makeCustomer()->id;
        }

        return Invoice::create(array_merge($defaults, $overrides));
    }

    private function customerCreateEntry(string $uuid, array $payload = [], ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Customer::class,
            'entity_uuid' => $uuid,
            'operation' => SyncOperation::CREATE->value,
            'payload' => array_merge(['name' => 'Offline Customer', 'company_id' => $this->companyId], $payload),
            'base_sync_version' => null,
        ];
    }

    private function invoiceCreateEntry(string $uuid, string $customerUuid, array $payload = [], ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $uuid,
            'operation' => SyncOperation::CREATE->value,
            'payload' => array_merge([
                'customer_uuid' => $customerUuid,
                'invoice_type' => 'sell',
                'status' => 'pending',
                'date' => now()->toDateString(),
                'title' => 'Offline invoice',
            ], $payload),
            'base_sync_version' => null,
        ];
    }

    private function finalizeEntry(Invoice $invoice, ?string $operationId = null, ?int $baseSyncVersion = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::FINALIZE->value,
            'payload' => [],
            'base_sync_version' => $baseSyncVersion ?? $invoice->fresh()->sync_version,
        ];
    }

    private function approveEntry(Invoice $invoice, ?string $operationId = null, ?int $baseSyncVersion = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::APPROVE->value,
            'payload' => [],
            'base_sync_version' => $baseSyncVersion ?? $invoice->fresh()->sync_version,
        ];
    }

    // --- 1. Dependency chain, end to end -------------------------------------

    public function test_full_dependency_chain_converges_from_offline_creation_to_approval(): void
    {
        $device = $this->makeDevice();
        $customerUuid = (string) Str::uuid();
        $invoiceUuid = (string) Str::uuid();
        $invoiceOpId = (string) Str::uuid();

        // Worst-case ordering: the Invoice entry is listed BEFORE its
        // Customer in the same offline batch.
        $response = $this->pushBatch($device, [
            $this->invoiceCreateEntry($invoiceUuid, $customerUuid, [], $invoiceOpId),
            $this->customerCreateEntry($customerUuid),
        ]);

        $response->assertJsonPath('results.0.status', 'pending_dependency');
        $response->assertJsonPath('results.1.status', 'accepted');
        $this->assertNull(Invoice::where('uuid', $invoiceUuid)->first());

        // Retry the SAME Invoice operation_id -- the customer now exists.
        $retry = $this->pushOne($device, $this->invoiceCreateEntry($invoiceUuid, $customerUuid, [], $invoiceOpId));
        $retry->assertJsonPath('results.0.status', 'accepted');

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertNotNull($invoice);
        $customer = Customer::where('uuid', $customerUuid)->first();
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->attachAccountingSubjectTo($customer);

        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');
        $this->pushOne($device, $this->approveEntry($invoice->fresh()))->assertJsonPath('results.0.status', 'accepted');

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::APPROVED, $fresh->status);
        $this->assertNotNull($fresh->document_id);
    }

    // --- 2. Partial batch failure --------------------------------------------

    public function test_partial_batch_failure_yields_independent_per_operation_outcomes(): void
    {
        $device = $this->makeDevice();
        $customerUuid = (string) Str::uuid();
        $invoiceAUuid = (string) Str::uuid();

        // A pre-existing invoice, staged to receive a stale UPDATE (conflict).
        $invoiceB = $this->makeDraftInvoice(['title' => 'Original B']);
        $invoiceB->update(['title' => 'Changed elsewhere']); // bumps sync_version to 2

        $invoiceCCustomerUuid = (string) Str::uuid(); // never created -- dependency never resolves

        $response = $this->pushBatch($device, [
            $this->customerCreateEntry($customerUuid),                              // 0: ok
            $this->invoiceCreateEntry($invoiceAUuid, $customerUuid),                // 1: ok (depends on entry 0, same batch)
            [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoiceB->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Stale write'],
                'base_sync_version' => 1, // stale -- server is already at 2
            ],                                                                       // 2: conflict
            $this->invoiceCreateEntry((string) Str::uuid(), $invoiceCCustomerUuid),  // 3: pending_dependency
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $response->assertJsonPath('results.1.status', 'accepted');
        $response->assertJsonPath('results.2.status', 'conflict');
        $response->assertJsonPath('results.3.status', 'pending_dependency');

        // The failures of entries 2/3 must not have rolled back entries 0/1.
        $this->assertNotNull(Customer::where('uuid', $customerUuid)->first());
        $this->assertNotNull(Invoice::where('uuid', $invoiceAUuid)->first());
        $this->assertSame('Changed elsewhere', $invoiceB->fresh()->title); // untouched by the stale write
    }

    // --- 3. Retry preserves operation_id / crash-after-server-success -------

    public function test_retrying_create_with_the_same_operation_id_after_a_simulated_crash_does_not_duplicate(): void
    {
        $device = $this->makeDevice();
        $customerUuid = (string) Str::uuid();
        $this->pushOne($device, $this->customerCreateEntry($customerUuid))->assertJsonPath('results.0.status', 'accepted');

        $invoiceUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $entry = $this->invoiceCreateEntry($invoiceUuid, $customerUuid, [], $operationId);

        $first = $this->pushOne($device, $entry);
        $first->assertJsonPath('results.0.status', 'accepted');

        // "Crash": the client never saw the response and resends the exact
        // same batch (same operation_id) believing it may have failed.
        $retry = $this->pushOne($device, $entry);
        $retry->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(1, Invoice::where('uuid', $invoiceUuid)->count());
        $this->assertSame(1, SyncInboxEntry::where('operation_id', $operationId)->count());
    }

    public function test_retrying_finalize_after_a_simulated_crash_does_not_consume_a_second_number(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $operationId = (string) Str::uuid();

        $this->pushOne($device, $this->finalizeEntry($invoice, $operationId))->assertJsonPath('results.0.status', 'accepted');
        $numberAfterFirst = $invoice->fresh()->number;

        $retry = $this->pushOne($device, $this->finalizeEntry($invoice->fresh(), $operationId, $invoice->fresh()->sync_version));
        $retry->assertJsonPath('results.0.status', 'accepted');

        $this->assertEquals($numberAfterFirst, $invoice->fresh()->number);
    }

    public function test_retrying_approve_after_a_simulated_crash_does_not_create_a_second_posting(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');

        $operationId = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $this->pushOne($device, $this->approveEntry($invoice->fresh(), $operationId))->assertJsonPath('results.0.status', 'accepted');

        // The client never received this response and resends identically.
        $retry = $this->pushOne($device, $this->approveEntry($invoice->fresh(), $operationId, $invoice->fresh()->sync_version));
        $retry->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 4. Stale-version conflict vs idempotent no-op, for BOTH operations -

    public function test_a_stale_finalize_on_an_already_finalized_invoice_is_a_conflict_not_a_silent_accept(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $staleVersion = $invoice->fresh()->sync_version;

        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');

        // A second finalize attempt, still carrying the PRE-finalize version.
        $stale = $this->pushOne($device, $this->finalizeEntry($invoice->fresh(), null, $staleVersion));

        $stale->assertJsonPath('results.0.status', 'conflict');
    }

    public function test_a_stale_approve_on_an_already_approved_invoice_is_a_conflict_not_a_silent_accept(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');
        $staleVersion = $invoice->fresh()->sync_version;

        $this->pushOne($device, $this->approveEntry($invoice->fresh()))->assertJsonPath('results.0.status', 'accepted');

        $stale = $this->pushOne($device, $this->approveEntry($invoice->fresh(), null, $staleVersion));

        $stale->assertJsonPath('results.0.status', 'conflict');
    }

    // --- 5. Lifecycle enforcement across Draft / Finalized / Approved -------

    public function test_draft_invoice_can_be_edited_via_sync(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice(['title' => 'Original']);

        $response = $this->pushOne($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['title' => 'Edited while draft'],
            'base_sync_version' => $invoice->sync_version,
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $this->assertSame('Edited while draft', $invoice->fresh()->title);
    }

    public function test_finalized_but_not_approved_invoice_can_still_have_non_number_fields_edited(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice(['title' => 'Before finalize']);
        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');
        $numberAfterFinalize = $invoice->fresh()->number;

        $response = $this->pushOne($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['title' => 'Edited after finalize', 'number' => '999999'],
            'base_sync_version' => $invoice->fresh()->sync_version,
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $invoice->fresh();
        $this->assertSame('Edited after finalize', $fresh->title);
        $this->assertEquals($numberAfterFinalize, $fresh->number); // number stays locked regardless
    }

    public function test_approved_invoice_cannot_be_edited_or_deleted_via_sync(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $this->pushOne($device, $this->finalizeEntry($invoice))->assertJsonPath('results.0.status', 'accepted');
        $this->pushOne($device, $this->approveEntry($invoice->fresh()))->assertJsonPath('results.0.status', 'accepted');

        $update = $this->pushOne($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['title' => 'Trying to edit posted'],
            'base_sync_version' => $invoice->fresh()->sync_version,
        ]);
        $update->assertJsonPath('results.0.status', 'rejected');

        $delete = $this->pushOne($device, [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Invoice::class,
            'entity_uuid' => $invoice->uuid,
            'operation' => SyncOperation::DELETE->value,
            'payload' => ['uuid' => $invoice->uuid],
            'base_sync_version' => $invoice->fresh()->sync_version,
        ]);
        $delete->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_hard_delete_is_rejected_at_every_lifecycle_stage(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);

        $draft = $this->makeDraftInvoice();
        $finalized = $this->makeDraftInvoice();
        $this->pushOne($device, $this->finalizeEntry($finalized))->assertJsonPath('results.0.status', 'accepted');

        foreach ([$draft, $finalized->fresh()] as $invoice) {
            $response = $this->pushOne($device, [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::DELETE->value,
                'payload' => ['uuid' => $invoice->uuid],
                'base_sync_version' => $invoice->sync_version,
            ]);
            $response->assertJsonPath('results.0.status', 'rejected');
            $this->assertNotNull(Invoice::where('uuid', $invoice->uuid)->first());
        }
    }

    // --- 6. Multi-device convergence -----------------------------------------

    public function test_two_devices_converge_to_the_same_state_after_interleaved_offline_activity(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();

        // A creates a customer while B is offline and knows nothing about it.
        $customerUuid = (string) Str::uuid();
        $this->pushOne($deviceA, $this->customerCreateEntry($customerUuid, ['name' => 'Shared Customer']))
            ->assertJsonPath('results.0.status', 'accepted');

        // B "reconnects" and pulls it.
        $pull = $this->withHeaders($this->deviceHeaders($deviceB))->getJson(
            route('api.sync.pull', ['entity_type' => Customer::class, 'after_id' => 0])
        );
        $pulledUuids = collect($pull->json('rows'))->pluck('uuid');
        $this->assertTrue($pulledUuids->contains($customerUuid));

        // B now creates an Invoice referencing that same customer.
        $invoiceUuid = (string) Str::uuid();
        $this->pushOne($deviceB, $this->invoiceCreateEntry($invoiceUuid, $customerUuid))
            ->assertJsonPath('results.0.status', 'accepted');

        // A pulls Invoices and sees B's invoice.
        $invoicePull = $this->withHeaders($this->deviceHeaders($deviceA))->getJson(
            route('api.sync.pull', ['entity_type' => Invoice::class, 'after_id' => 0])
        );
        $invoiceUuids = collect($invoicePull->json('rows'))->pluck('uuid');
        $this->assertTrue($invoiceUuids->contains($invoiceUuid));

        // Server-side truth: exactly one Customer, one Invoice, correctly linked.
        $customer = Customer::where('uuid', $customerUuid)->first();
        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame(1, Customer::where('uuid', $customerUuid)->count());
        $this->assertSame(1, Invoice::where('uuid', $invoiceUuid)->count());
    }

    // --- 7. Exactly-once accounting posting, under repeated abuse -----------

    public function test_exactly_one_posting_survives_finalize_never_posts_plus_repeated_retries_and_pulls(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();

        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $finalizeOpId = (string) Str::uuid();
        $this->pushOne($device, $this->finalizeEntry($invoice, $finalizeOpId))->assertJsonPath('results.0.status', 'accepted');

        // Finalize alone must never create a posting.
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());

        $approveOpId = (string) Str::uuid();
        $this->pushOne($device, $this->approveEntry($invoice->fresh(), $approveOpId))->assertJsonPath('results.0.status', 'accepted');
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());

        // Barrage: retry finalize, retry approve, pull, duplicate-push approve
        // again, all after the fact.
        $this->pushOne($device, $this->finalizeEntry($invoice->fresh(), $finalizeOpId, $invoice->fresh()->sync_version));
        $this->pushOne($device, $this->approveEntry($invoice->fresh(), $approveOpId, $invoice->fresh()->sync_version));
        $this->withHeaders($this->deviceHeaders($device))->getJson(route('api.sync.pull', ['entity_type' => Invoice::class, 'after_id' => 0]));
        $this->pushOne($device, $this->approveEntry($invoice->fresh())); // a different operation_id, matching version -> safe no-op

        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
    }
}
