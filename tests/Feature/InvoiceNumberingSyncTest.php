<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NumberingLeaseStatus;
use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\NumberingLease;
use App\Services\DevicePairingService;
use App\Services\Sync\NumberingLeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-075 stage 2.7C -- Offline Numbering & Reservation Contract, Invoice
 * only. Covers the owner's exact exit-gate: reservation, exhaustion,
 * retry, and collision, plus Finalize's immutability and idempotency.
 * 2.7D (Approved Document Sync) does not start until this suite is green.
 */
class InvoiceNumberingSyncTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected NumberingLeaseService $leaseService;

    private static int $nextTestInvoiceNumber = 500000;

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

    private function finalize(array $device, Invoice $invoice, ?string $operationId = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => $operationId ?? (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::FINALIZE->value,
                'payload' => [],
                'base_sync_version' => $invoice->fresh()->sync_version,
            ]],
        ]);
    }

    // --- 1. Reservation ------------------------------------------------------

    public function test_reserving_a_range_returns_a_disjoint_server_authoritative_block(): void
    {
        $device = $this->makeDevice();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.numbering.reserve-range'), [
            'invoice_type' => 'sell',
            'size' => 500,
        ]);

        $response->assertOk();
        $response->assertJsonPath('range_start', 1);
        $response->assertJsonPath('range_end', 500);
        $response->assertJsonPath('status', 'ACTIVE');

        $lease = NumberingLease::findOrFail($response->json('lease_id'));
        $this->assertSame($this->companyId, $lease->company_id);
        $this->assertSame($device['installation']->id, $lease->origin_device_id);
    }

    public function test_two_devices_reserving_concurrently_get_disjoint_ranges(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();

        $leaseA = $this->leaseService->reserveRange($deviceA['installation'], InvoiceType::SELL, 500);
        $leaseB = $this->leaseService->reserveRange($deviceB['installation'], InvoiceType::SELL, 500);

        $this->assertSame(1, $leaseA->range_start);
        $this->assertSame(500, $leaseA->range_end);
        $this->assertSame(501, $leaseB->range_start);
        $this->assertSame(1000, $leaseB->range_end);
    }

    public function test_ranges_for_different_invoice_types_do_not_collide(): void
    {
        $device = $this->makeDevice();

        $sellLease = $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $buyLease = $this->leaseService->reserveRange($device['installation'], InvoiceType::BUY, 100);

        $this->assertSame(1, $sellLease->range_start);
        $this->assertSame(1, $buyLease->range_start);
    }

    // --- 2. Local range consumption / Finalize -------------------------------

    public function test_finalize_consumes_the_next_number_from_the_devices_own_lease(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);

        $response = $this->finalize($device, $invoice);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $invoice->fresh();
        $this->assertEquals(1, $fresh->number);
        $this->assertTrue($fresh->is_number_finalized);
        $this->assertNotNull($fresh->number_finalized_at);
        $this->assertSame(2, $fresh->sync_version);
    }

    public function test_finalize_without_any_reserved_range_reports_numbering_unavailable(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);

        $response = $this->finalize($device, $invoice);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertFalse($invoice->fresh()->is_number_finalized);
    }

    // --- 3. Exhaustion --------------------------------------------------------

    public function test_exhausting_a_range_blocks_further_offline_finalization_without_a_provisional_number(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 2);

        $first = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);
        $second = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);
        $third = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);

        $this->finalize($device, $first)->assertJsonPath('results.0.status', 'accepted');
        $this->finalize($device, $second)->assertJsonPath('results.0.status', 'accepted');

        // Range (size 2) is now exhausted -- the third finalize must not
        // invent a provisional number.
        $response = $this->finalize($device, $third);
        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertFalse($third->fresh()->is_number_finalized);

        $lease = NumberingLease::where('origin_device_id', $device['installation']->id)->first();
        $this->assertSame(NumberingLeaseStatus::EXHAUSTED, $lease->status);

        // Reserve a new range (reconnect) -- the third invoice can now finalize.
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 2);
        $retry = $this->finalize($device, $third->fresh());
        $retry->assertJsonPath('results.0.status', 'accepted');
        $this->assertEquals(3, $third->fresh()->number);
    }

    // --- 4. Unused numbers are never recycled --------------------------------

    public function test_unused_numbers_in_an_exhausted_or_underused_lease_are_never_recycled(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100); // 1-100

        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);
        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');
        $this->assertEquals(1, $invoice->fresh()->number);

        // A brand new reservation -- even for the SAME device -- must never
        // reuse 2-100 from the first lease; it starts strictly after it.
        $secondLease = $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 50);
        $this->assertSame(101, $secondLease->range_start);
    }

    // --- 5. Retry-safety / idempotency ---------------------------------------

    public function test_resending_the_same_finalize_operation_id_does_not_double_consume(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);
        $operationId = (string) Str::uuid();

        $first = $this->finalize($device, $invoice, $operationId);
        $first->assertJsonPath('results.0.status', 'accepted');
        $firstNumber = $invoice->fresh()->number;

        // Simulate a network failure after the server applied it -- the
        // client retries with the SAME operation_id.
        $second = $this->finalize($device, $invoice->fresh(), $operationId);
        $second->assertJsonPath('results.0.status', 'accepted');

        $fresh = $invoice->fresh();
        $this->assertEquals($firstNumber, $fresh->number);
        $this->assertSame(2, $fresh->sync_version); // not 3 -- no double consumption

        $lease = NumberingLease::where('origin_device_id', $device['installation']->id)->first();
        $this->assertSame($lease->range_start + 1, $lease->next_number); // exactly one number consumed
    }

    public function test_finalizing_an_already_finalized_invoice_is_a_safe_noop(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);

        $this->finalize($device, $invoice)->assertJsonPath('results.0.status', 'accepted');
        $finalNumber = $invoice->fresh()->number;

        // A DIFFERENT operation_id, but the invoice is already finalized --
        // must not consume a second number.
        $again = $this->finalize($device, $invoice->fresh());
        $again->assertJsonPath('results.0.status', 'accepted');
        $this->assertEquals($finalNumber, $invoice->fresh()->number);

        $lease = NumberingLease::where('origin_device_id', $device['installation']->id)->first();
        $this->assertSame($lease->range_start + 1, $lease->next_number);
    }

    // --- 6. Collision defense-in-depth (DB uniqueness) -----------------------

    public function test_database_unique_constraint_is_the_final_collision_guard_on_invoices(): void
    {
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->makeDraftInvoice(['number' => '424242', 'invoice_type' => InvoiceType::SELL, 'customer_id' => $customer->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeDraftInvoice(['number' => '424242', 'invoice_type' => InvoiceType::SELL, 'customer_id' => $customer->id]);
    }

    public function test_numbering_lease_unique_constraint_guards_against_overlapping_ranges(): void
    {
        $device = $this->makeDevice();

        NumberingLease::create([
            'company_id' => $this->companyId,
            'invoice_type' => InvoiceType::SELL,
            'origin_device_id' => $device['installation']->id,
            'range_start' => 1,
            'range_end' => 100,
            'next_number' => 1,
            'status' => NumberingLeaseStatus::ACTIVE,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        NumberingLease::create([
            'company_id' => $this->companyId,
            'invoice_type' => InvoiceType::SELL,
            'origin_device_id' => $device['installation']->id,
            'range_start' => 1,
            'range_end' => 50,
            'next_number' => 1,
            'status' => NumberingLeaseStatus::ACTIVE,
        ]);
    }

    // --- 7. Version conflict still applies to Finalize -----------------------

    public function test_finalize_respects_the_normal_version_conflict_check(): void
    {
        $device = $this->makeDevice();
        $this->leaseService->reserveRange($device['installation'], InvoiceType::SELL, 100);
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::SELL]);

        // Someone else updates the title first, bumping sync_version to 2.
        $invoice->update(['title' => 'Updated elsewhere']);

        // finalize() would read the fresh (valid) sync_version, so build the
        // request manually with a deliberately stale base_sync_version.
        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::FINALIZE->value,
                'payload' => [],
                'base_sync_version' => 1, // stale -- server is already at 2
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'conflict');
        $this->assertFalse($invoice->fresh()->is_number_finalized);
    }
}
