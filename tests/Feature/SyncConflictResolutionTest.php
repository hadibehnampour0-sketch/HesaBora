<?php

namespace Tests\Feature;

use App\Enums\SyncConflictResolution;
use App\Enums\SyncConflictStatus;
use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\SyncConflict;
use App\Services\DevicePairingService;
use App\Services\Sync\SyncConflictService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-072: Conflict Resolution Workflow, covering the owner's exact
 * exit-gate scenarios -- keep server, keep local, manual edit, and
 * retry-safety on the resolve action itself.
 */
class SyncConflictResolutionTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected SyncConflictService $conflictService;

    protected \App\Models\User $user;

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
        $this->conflictService = app(SyncConflictService::class);

        $this->user = \App\Models\User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync-conflicts.*']));
        $this->actingAs($this->user);
    }

    private function makeDevice(): array
    {
        $company = Company::find($this->companyId);
        $company->users()->syncWithoutDetaching($this->user);
        $code = $this->pairingService->issueActivationCode($company, $this->user);

        return $this->pairingService->pair($code);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        return Customer::factory()->withGroup($group)->withSubject()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    /**
     * Reproduces the exact 2.5 conflict scenario (Device A wins, Device B
     * collides) and returns the resulting open SyncConflict.
     */
    private function makeConflict(): SyncConflict
    {
        $deviceA = $this->makeDevice();
        $customer = $this->makeCustomer(['name' => 'Shared Customer']);
        $deviceB = $this->makeDevice();

        $pilot = app(\App\Services\Sync\SyncPilotService::class);

        $pilot->pushEntry($deviceA['installation'], [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Customer::class,
            'entity_uuid' => $customer->uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['name' => 'Renamed by A (server)'],
            'base_sync_version' => 1,
        ]);

        $pilot->pushEntry($deviceB['installation'], [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => Customer::class,
            'entity_uuid' => $customer->uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => ['name' => 'Renamed by B (local)'],
            'base_sync_version' => 1, // stale
        ]);

        return SyncConflict::where('entity_uuid', $customer->uuid)->firstOrFail();
    }

    // --- 1. Conflict visible in the review queue --------------------------

    public function test_conflict_is_visible_in_the_review_queue(): void
    {
        $conflict = $this->makeConflict();

        $open = $this->conflictService->openConflicts($this->companyId);

        $this->assertTrue($open->pluck('id')->contains($conflict->id));
        $this->assertSame(SyncConflictStatus::OPEN, $conflict->status);
        $this->assertSame('Renamed by A (server)', $conflict->server_snapshot['name']);
        $this->assertSame('Renamed by B (local)', $conflict->local_snapshot['name']);
    }

    // --- 2. KEEP_SERVER -----------------------------------------------------

    public function test_keep_server_resolution_leaves_server_data_and_settles_the_conflict(): void
    {
        $conflict = $this->makeConflict();

        $result = $this->conflictService->resolve($this->user, $conflict, SyncConflictResolution::KEEP_SERVER);

        $this->assertSame('resolved', $result['status']);
        $fresh = $conflict->fresh();
        $this->assertSame(SyncConflictStatus::RESOLVED, $fresh->status);
        $this->assertSame(SyncConflictResolution::KEEP_SERVER, $fresh->resolution);
        $this->assertSame($this->user->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);

        $customer = Customer::where('uuid', $conflict->entity_uuid)->first();
        $this->assertSame('Renamed by A (server)', $customer->name);
        $this->assertSame(SyncStatus::SYNCED, $customer->sync_status);

        $this->assertFalse($this->conflictService->openConflicts($this->companyId)->pluck('id')->contains($conflict->id));
    }

    // --- 3. KEEP_LOCAL --------------------------------------------------------

    public function test_keep_local_resolution_pushes_a_new_operation_and_converges(): void
    {
        $conflict = $this->makeConflict();

        $result = $this->conflictService->resolve($this->user, $conflict, SyncConflictResolution::KEEP_LOCAL);

        $this->assertSame('accepted', $result['status']);
        $this->assertNotNull($result['operation_id']);

        $customer = Customer::where('uuid', $conflict->entity_uuid)->first();
        $this->assertSame('Renamed by B (local)', $customer->name);
        $this->assertSame(3, $customer->sync_version); // 1 (create) -> 2 (A's accepted update) -> 3 (resolution's own update)

        $fresh = $conflict->fresh();
        $this->assertSame(SyncConflictStatus::RESOLVED, $fresh->status);
        $this->assertSame(SyncConflictResolution::KEEP_LOCAL, $fresh->resolution);
        $this->assertSame($result['operation_id'], $fresh->resulting_operation_id);
    }

    // --- 4. MANUAL_EDIT ---------------------------------------------------

    public function test_manual_edit_resolution_applies_the_edited_payload_with_a_new_operation_id(): void
    {
        $conflict = $this->makeConflict();

        $result = $this->conflictService->resolve($this->user, $conflict, SyncConflictResolution::MANUAL_EDIT, [
            'name' => 'Manually Reconciled Name',
        ]);

        $this->assertSame('accepted', $result['status']);
        $customer = Customer::where('uuid', $conflict->entity_uuid)->first();
        $this->assertSame('Manually Reconciled Name', $customer->name);

        $fresh = $conflict->fresh();
        $this->assertSame(SyncConflictResolution::MANUAL_EDIT, $fresh->resolution);
        $this->assertSame($result['operation_id'], $fresh->resulting_operation_id);
    }

    public function test_manual_edit_without_a_payload_is_rejected(): void
    {
        $conflict = $this->makeConflict();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->conflictService->resolve($this->user, $conflict, SyncConflictResolution::MANUAL_EDIT, null);
    }

    // --- 5. Retry-safety: the whole point of D-072 rule 2 -------------------

    public function test_resolving_twice_reuses_the_same_operation_id_and_does_not_double_mutate(): void
    {
        $conflict = $this->makeConflict();

        $first = $this->conflictService->resolve($this->user, $conflict, SyncConflictResolution::KEEP_LOCAL);
        // Simulate the caller never seeing the response (network failure)
        // and retrying the identical resolve request against the same conflict.
        $second = $this->conflictService->resolve($this->user, $conflict->fresh(), SyncConflictResolution::KEEP_LOCAL);

        $this->assertSame($first['operation_id'], $second['operation_id']);

        $customer = Customer::where('uuid', $conflict->entity_uuid)->first();
        // Only ONE resolution mutation should have landed on top of the
        // original conflicting update (version 2) -- version 3, not 4.
        $this->assertSame(3, $customer->sync_version);
    }

    // --- 6. HTTP smoke tests --------------------------------------------------

    public function test_conflict_review_page_renders(): void
    {
        $this->makeConflict();

        $this->get(route('sync-conflicts.index'))->assertOk();
    }

    public function test_resolve_route_works_end_to_end_over_http(): void
    {
        $conflict = $this->makeConflict();

        $this->post(route('sync-conflicts.resolve', $conflict), ['resolution' => 'keep_server'])
            ->assertRedirect(route('sync-conflicts.index'));

        $this->assertSame(SyncConflictStatus::RESOLVED, $conflict->fresh()->status);
    }
}
