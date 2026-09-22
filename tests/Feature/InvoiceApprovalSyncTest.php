<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Services\DevicePairingService;
use App\Services\Sync\NumberingLeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-076 stage 2.7D -- Approved Sales Invoice Sync. Covers the owner's exact
 * nine-scenario exit gate. FINALIZED (2.7C) and APPROVED/POSTED are two
 * separate operations; Approve is server-authoritative only and reuses
 * the EXISTING InvoiceService::getChangeStatusValidation()/
 * changeInvoiceStatus() -- never a second accounting engine.
 */
class InvoiceApprovalSyncTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected NumberingLeaseService $leaseService;

    private static int $nextTestInvoiceNumber = 700000;

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
            $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
            $customer = Customer::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId]);
            $defaults['customer_id'] = $customer->id;
        }

        return Invoice::create(array_merge($defaults, $overrides));
    }

    private function push(array $entry): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($entry['device']))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => $entry['operation_id'] ?? (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $entry['invoice']->uuid,
                'operation' => $entry['operation']->value,
                'payload' => $entry['payload'] ?? [],
                'base_sync_version' => $entry['base_sync_version'] ?? $entry['invoice']->fresh()->sync_version,
            ]],
        ]);
    }

    private function finalize(array $device, Invoice $invoice, ?string $operationId = null): TestResponse
    {
        return $this->push(['device' => $device, 'invoice' => $invoice, 'operation' => SyncOperation::FINALIZE, 'operation_id' => $operationId]);
    }

    private function approve(array $device, Invoice $invoice, ?string $operationId = null, ?int $baseSyncVersion = null): TestResponse
    {
        return $this->push([
            'device' => $device,
            'invoice' => $invoice,
            'operation' => SyncOperation::APPROVE,
            'operation_id' => $operationId,
            'base_sync_version' => $baseSyncVersion,
        ]);
    }

    /** Finalize + approve a fresh Sales Invoice end to end; returns the invoice. */
    private function finalizeAndApprove(array $device): Invoice
    {
        $invoice = $this->makeDraftInvoice();
        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');
        $this->approve($device, $invoice->fresh())->assertJsonPath('results.0.status', 'accepted');

        return $invoice->fresh();
    }

    // --- 1. Finalize -> approve -> exactly one posting -----------------------

    public function test_finalize_then_approve_produces_exactly_one_posting(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);

        $countBefore = Document::withoutGlobalScopes()->count();
        $invoice = $this->finalizeAndApprove($device);

        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNotNull($invoice->document_id);

        $document = Document::withoutGlobalScopes()->find($invoice->document_id);
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);
        $this->assertEqualsWithDelta(0.0, $document->transactions()->sum('value'), 0.01);

        $this->assertSame($countBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 2. Duplicate finalize/approve -> no duplicate posting ---------------

    public function test_approving_an_already_approved_invoice_with_a_new_operation_id_does_not_double_post(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->finalizeAndApprove($device);
        $documentId = $invoice->document_id;
        $documentCount = Document::withoutGlobalScopes()->count();

        // A different operation_id -- e.g. a second device, or a stale retry.
        $again = $this->approve($device, $invoice->fresh());

        $again->assertJsonPath('results.0.status', 'accepted');
        $fresh = $invoice->fresh();
        $this->assertSame($documentId, $fresh->document_id);
        $this->assertSame($documentCount, Document::withoutGlobalScopes()->count());
    }

    // --- 3. Offline-finalized invoice -> reconnect -> approval -> convergence -

    public function test_finalize_and_approve_can_happen_as_two_separate_round_trips(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();

        // Round trip 1: finalize while "connected" briefly.
        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');
        $finalizedNumber = $invoice->fresh()->number;

        // Simulated disconnect: nothing happens here. "Reconnect" is simply
        // the next independent HTTP round trip below.
        $response = $this->approve($device, $invoice->fresh());

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $invoice->fresh();
        $this->assertEquals($finalizedNumber, $fresh->number); // unchanged by approval
        $this->assertSame(InvoiceStatus::APPROVED, $fresh->status);
    }

    // --- 4. Number collision -> rejected -> no accounting posting ------------

    public function test_a_number_collision_at_finalize_is_rejected_with_no_posting(): void
    {
        $device = $this->makeDevice();
        // Occupies number "1" for SELL outside the lease system entirely --
        // e.g. a legacy/manually-created row -- BEFORE any lease exists.
        $this->makeDraftInvoice(['number' => '1']);

        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100); // 1-100, unaware of the row above
        $invoice = $this->makeDraftInvoice();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $response = $this->finalize($device, $invoice);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertFalse($invoice->fresh()->is_number_finalized);
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());
    }

    // --- 5. Invalid invoice -> rejected -> no accounting posting -------------

    public function test_approving_an_invoice_that_was_never_finalized_is_rejected_with_no_posting(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $response = $this->approve($device, $invoice);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNull($invoice->fresh()->document_id);
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());
    }

    // --- 6. Network timeout -> retry (same operation_id) -> exactly one posting

    public function test_resending_the_same_approve_operation_id_produces_exactly_one_posting(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice();
        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');

        $operationId = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $first = $this->approve($device, $invoice->fresh(), $operationId);
        $first->assertJsonPath('results.0.status', 'accepted');

        // Client never saw the response (simulated timeout) -- retries with
        // the SAME operation_id.
        $second = $this->approve($device, $invoice->fresh(), $operationId);
        $second->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 7 & 8. Posted invoice is immutable through sync ----------------------

    public function test_updating_a_posted_invoice_is_rejected(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->finalizeAndApprove($device);

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Trying to edit a posted invoice'],
                'base_sync_version' => $invoice->sync_version,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_deleting_a_posted_invoice_is_rejected(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->finalizeAndApprove($device);

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::DELETE->value,
                'payload' => ['uuid' => $invoice->uuid],
                'base_sync_version' => $invoice->sync_version,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    // --- 9. Two devices, conflicting approval -- only one wins ---------------

    public function test_two_devices_approving_the_same_invoice_only_one_wins(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();
        $this->leaseService->reserveRange($deviceA['installation'], InvoiceType::SELL, 100);

        $invoice = $this->makeDraftInvoice();
        $this->finalize($deviceA, $invoice)->assertJsonPath('results.0.status', 'accepted');

        // Both devices observed the same (finalized, not-yet-approved) version.
        $staleVersion = $invoice->fresh()->sync_version;

        $winner = $this->approve($deviceA, $invoice->fresh(), null, $staleVersion);
        $winner->assertJsonPath('results.0.status', 'accepted');

        // Device B still believes the stale version is current.
        $loser = $this->approve($deviceB, $invoice->fresh(), null, $staleVersion);
        $loser->assertJsonPath('results.0.status', 'conflict');

        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
    }

    // --- Scope boundary: Sales Invoice only -----------------------------------

    public function test_approving_a_buy_invoice_is_rejected_stage_scope(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::BUY, 100);
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::BUY]);
        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');

        $response = $this->approve($device, $invoice->fresh());

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNull($invoice->fresh()->document_id);
    }
}
