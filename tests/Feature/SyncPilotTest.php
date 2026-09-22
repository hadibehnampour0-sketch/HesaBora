<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeviceInstallation;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\SyncInboxEntry;
use App\Models\SyncOutboxEntry;
use App\Services\DevicePairingService;
use App\Services\Sync\SyncInboxService;
use App\Services\Sync\SyncOutboxService;
use App\Services\Sync\SyncPilotService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-071 stage 2.5 -- the gated Customer/Product Sync Pilot. Covers the
 * owner's explicit exit-gate checklist: create/update/delete enqueue,
 * push, pull, retry/duplicate idempotency, conflict (not last-write-wins),
 * rejected, and offline-then-reconnect convergence. Document/Invoice/
 * Payment/Transaction are deliberately out of scope here (stages 2.7/2.8).
 */
class SyncPilotTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected SyncOutboxService $outbox;

    protected SyncInboxService $inbox;

    protected SyncPilotService $pilot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->pairingService = app(DevicePairingService::class);
        $this->outbox = app(SyncOutboxService::class);
        $this->inbox = app(SyncInboxService::class);
        $this->pilot = app(SyncPilotService::class);
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

    private function makeCustomer(array $overrides = []): Customer
    {
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        return Customer::factory()->withGroup($group)->withSubject()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    private function makeProductGroup(): ProductGroup
    {
        return ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
    }

    // --- 1. Enqueue is really wired to mutations ---------------------------

    public function test_creating_a_customer_enqueues_exactly_one_create_entry(): void
    {
        $countBefore = SyncOutboxEntry::count();
        $customer = $this->makeCustomer();

        $entries = SyncOutboxEntry::where('entity_uuid', $customer->uuid)->get();

        $this->assertCount(1, $entries);
        $this->assertSame(SyncOperation::CREATE, $entries->first()->operation);
        $this->assertSame($countBefore + 1, SyncOutboxEntry::count());
    }

    public function test_updating_a_customer_enqueues_an_update_with_the_correct_base_version(): void
    {
        $customer = $this->makeCustomer();
        $customer->update(['name' => 'Renamed']);

        $entry = SyncOutboxEntry::where('entity_uuid', $customer->uuid)->where('operation', SyncOperation::UPDATE)->firstOrFail();

        $this->assertSame(1, $entry->base_sync_version);
        $this->assertSame('Renamed', $entry->payload['name']);
    }

    public function test_deleting_a_customer_enqueues_a_delete_entry(): void
    {
        $customer = $this->makeCustomer();
        $uuid = $customer->uuid;
        $customer->delete();

        $entry = SyncOutboxEntry::where('entity_uuid', $uuid)->where('operation', SyncOperation::DELETE)->firstOrFail();
        $this->assertSame(['uuid' => $uuid], $entry->payload);
    }

    public function test_saving_with_no_dirty_attributes_does_not_enqueue_a_duplicate(): void
    {
        $customer = $this->makeCustomer();
        $countAfterCreate = SyncOutboxEntry::where('entity_uuid', $customer->uuid)->count();

        $customer->save(); // no changes

        $this->assertSame($countAfterCreate, SyncOutboxEntry::where('entity_uuid', $customer->uuid)->count());
    }

    public function test_creating_a_product_enqueues_exactly_one_create_entry(): void
    {
        $this->makeProductGroup();
        $product = $this->createProduct();

        $entries = SyncOutboxEntry::where('entity_uuid', $product->uuid)->get();
        $this->assertCount(1, $entries);
        $this->assertSame(SyncOperation::CREATE, $entries->first()->operation);
    }

    // --- 2. Real push over the real HTTP API --------------------------------

    public function test_push_over_http_creates_the_customer_server_side(): void
    {
        $device = $this->makeDevice();
        $operationId = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Customer::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['name' => 'Pushed Customer', 'company_id' => $this->companyId],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.status', 'accepted');

        $created = Customer::where('uuid', $entityUuid)->first();
        $this->assertNotNull($created);
        $this->assertSame('Pushed Customer', $created->name);
        $this->assertSame(1, $created->sync_version);
        $this->assertSame($device['installation']->id, $created->origin_device_id);
    }

    public function test_pushing_an_update_bumps_the_server_version(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer(['name' => 'Original']);

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Customer::class,
                'entity_uuid' => $customer->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['name' => 'Updated remotely'],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $customer->fresh();
        $this->assertSame('Updated remotely', $fresh->name);
        $this->assertSame(2, $fresh->sync_version);
    }

    // --- 3. Retry / duplicate idempotency ------------------------------------

    public function test_resending_the_same_operation_id_does_not_create_a_duplicate(): void
    {
        $device = $this->makeDevice();
        $operationId = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();
        $payload = [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Customer::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['name' => 'Once Only', 'company_id' => $this->companyId],
                'base_sync_version' => null,
            ]],
        ];

        $first = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);
        $second = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);

        $first->assertJsonPath('results.0.status', 'accepted');
        $second->assertJsonPath('results.0.status', 'accepted');
        $this->assertSame(1, Customer::where('uuid', $entityUuid)->count());
        $this->assertSame(1, SyncInboxEntry::where('operation_id', $operationId)->count());
    }

    // --- 4. Real conflict, not last-write-wins ------------------------------

    public function test_two_devices_updating_the_same_customer_produces_a_conflict_not_last_write_wins(): void
    {
        $deviceA = $this->makeDevice();
        $customer = $this->makeCustomer(['name' => 'Shared Customer']);
        // Device B pairs after the customer already exists at version 1 --
        // both devices "pulled" it at version 1 before either edits.
        $deviceB = $this->makeDevice();

        $pushA = $this->withHeaders($this->deviceHeaders($deviceA))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Customer::class,
                'entity_uuid' => $customer->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['name' => 'Renamed by A'],
                'base_sync_version' => 1,
            ]],
        ]);
        $pushA->assertJsonPath('results.0.status', 'accepted');

        $pushB = $this->withHeaders($this->deviceHeaders($deviceB))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Customer::class,
                'entity_uuid' => $customer->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['name' => 'Renamed by B'],
                'base_sync_version' => 1, // stale -- server is already at 2
            ]],
        ]);

        $pushB->assertJsonPath('results.0.status', 'conflict');
        $pushB->assertJsonPath('results.0.server_version', '2');
        $pushB->assertJsonPath('results.0.local_version', '1');

        // B's data must NOT have overwritten A's -- no silent last-write-wins.
        $this->assertSame('Renamed by A', $customer->fresh()->name);
        $this->assertSame(2, $customer->fresh()->sync_version);

        $conflictEntries = $this->inbox->needsReview($this->companyId);
        $this->assertCount(1, $conflictEntries);
        $this->assertSame(SyncStatus::CONFLICT, $conflictEntries->first()->status);
    }

    // --- 5. Rejected ---------------------------------------------------------

    public function test_update_targeting_a_nonexistent_entity_is_rejected(): void
    {
        $device = $this->makeDevice();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Customer::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['name' => 'Ghost'],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    // --- 6. Pull -------------------------------------------------------------

    public function test_pull_returns_rows_created_after_the_cursor(): void
    {
        $device = $this->makeDevice();
        $before = $this->makeCustomer();
        $after = $this->makeCustomer();

        $response = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => Customer::class, 'after_id' => $before->id])
        );

        $response->assertOk();
        $uuids = collect($response->json('rows'))->pluck('uuid');
        $this->assertTrue($uuids->contains($after->uuid));
        $this->assertFalse($uuids->contains($before->uuid));
    }

    // --- 7. Offline accumulation -> reconnect -> converge --------------------

    public function test_offline_changes_accumulate_then_converge_once_pushed_on_reconnect(): void
    {
        $device = $this->makeDevice();

        // "Offline": the device has NOT touched the server DB at all yet --
        // simulated here as fabricated payloads/uuids (a real device would
        // have these in its own separate local SQLite), not real rows in
        // this test's shared database. Two operations for the SAME entity
        // accumulate locally before any push: a create, then a local edit
        // on top of it.
        $entityUuid = (string) Str::uuid();
        $createEntry = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Customer::class,
            'entity_uuid' => $entityUuid,
            'operation' => SyncOperation::CREATE->value,
            'payload' => ['name' => 'Offline Created', 'company_id' => $this->companyId],
            'base_sync_version' => null,
        ];
        $updateEntry = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Customer::class,
            'entity_uuid' => $entityUuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['name' => 'Offline Then Renamed'],
            'base_sync_version' => 1, // the device's own local version after its own create
        ];

        // "Reconnect": push both accumulated operations, in order, as one batch.
        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [$createEntry, $updateEntry],
        ]);

        $response->assertOk();
        foreach ($response->json('results') as $result) {
            $this->assertSame('accepted', $result['status']);
        }

        $final = Customer::where('uuid', $entityUuid)->first();
        $this->assertSame('Offline Then Renamed', $final->name);
        $this->assertSame(2, $final->sync_version);
    }

    public function test_ping_and_sync_routes_reject_a_revoked_device(): void
    {
        $device = $this->makeDevice();
        $this->pairingService->revoke($device['installation']);

        $this->withHeaders($this->deviceHeaders($device))
            ->postJson(route('api.sync.push'), ['entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Customer::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['name' => 'Should Not Apply'],
                'base_sync_version' => null,
            ]]])
            ->assertUnauthorized();
    }
}
