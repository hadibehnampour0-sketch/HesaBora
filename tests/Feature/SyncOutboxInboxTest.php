<?php

namespace Tests\Feature;

use App\Enums\DeviceInstallationStatus;
use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeviceInstallation;
use App\Services\Sync\SyncInboxService;
use App\Services\Sync\SyncOutboxService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-071 decision 7 (stage 2.4): the generic Outbox/Inbox queue/log
 * infrastructure, with no entity wired to enqueue automatically yet
 * (that starts at stage 2.5).
 */
class SyncOutboxInboxTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected SyncOutboxService $outbox;

    protected SyncInboxService $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->outbox = app(SyncOutboxService::class);
        $this->inbox = app(SyncInboxService::class);
    }

    private function makeCustomer(): Customer
    {
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        return Customer::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_enqueue_create_captures_a_full_payload_snapshot(): void
    {
        $customer = $this->makeCustomer();

        $entry = $this->outbox->enqueue($customer, SyncOperation::CREATE, $this->companyId);

        $this->assertSame(Customer::class, $entry->entity_type);
        $this->assertSame($customer->uuid, $entry->entity_uuid);
        $this->assertSame(SyncOperation::CREATE, $entry->operation);
        $this->assertSame(SyncStatus::PENDING, $entry->status);
        $this->assertSame($customer->name, $entry->payload['name']);
    }

    public function test_enqueue_delete_captures_only_the_uuid(): void
    {
        $customer = $this->makeCustomer();

        $entry = $this->outbox->enqueue($customer, SyncOperation::DELETE, $this->companyId);

        $this->assertSame(['uuid' => $customer->uuid], $entry->payload);
    }

    public function test_pending_returns_only_pending_entries_for_the_company(): void
    {
        $customer = $this->makeCustomer();
        // Creating the customer itself already auto-enqueued a CREATE entry
        // (stage 2.5 wiring) -- acknowledge that first so this test's own
        // manually-enqueued entries are the only ones left pending.
        \App\Models\SyncOutboxEntry::where('entity_uuid', $customer->uuid)->get()
            ->each(fn ($entry) => $this->outbox->markAcknowledged($entry));

        $entry1 = $this->outbox->enqueue($customer, SyncOperation::CREATE, $this->companyId);
        $entry2 = $this->outbox->enqueue($customer, SyncOperation::UPDATE, $this->companyId);
        $this->outbox->markAcknowledged($entry1);

        $pending = $this->outbox->pending($this->companyId);

        $this->assertCount(1, $pending);
        $this->assertSame($entry2->id, $pending->first()->id);
    }

    public function test_outbox_status_transitions(): void
    {
        $customer = $this->makeCustomer();
        $entry = $this->outbox->enqueue($customer, SyncOperation::CREATE, $this->companyId);

        $this->outbox->markSent($entry);
        $this->assertNotNull($entry->fresh()->sent_at);

        $this->outbox->markAcknowledged($entry);
        $this->assertSame(SyncStatus::SYNCED, $entry->fresh()->status);

        $entry2 = $this->outbox->enqueue($customer, SyncOperation::UPDATE, $this->companyId);
        $this->outbox->markFailed($entry2, 'connection timeout');
        $this->assertSame(SyncStatus::REJECTED, $entry2->fresh()->status);
        $this->assertSame(1, $entry2->fresh()->attempts);
        $this->assertSame('connection timeout', $entry2->fresh()->last_error);

        $entry3 = $this->outbox->enqueue($customer, SyncOperation::UPDATE, $this->companyId);
        $this->outbox->markConflict($entry3, 'stale version');
        $this->assertSame(SyncStatus::CONFLICT, $entry3->fresh()->status);
    }

    public function test_inbox_receive_stages_without_applying(): void
    {
        $device = $this->makeDevice();
        $customerUuid = (string) Str::uuid();

        $entry = $this->inbox->receive(Customer::class, $customerUuid, SyncOperation::CREATE, ['name' => 'Remote Customer'], $device);

        $this->assertSame(SyncStatus::PENDING, $entry->status);
        $this->assertSame($device->id, $entry->origin_device_id);
        $this->assertSame($this->companyId, $entry->company_id);
        $this->assertCount(0, Customer::where('name', 'Remote Customer')->get(), 'receive() must not touch the real domain table');
    }

    public function test_inbox_reject_records_a_visible_reason_not_a_silent_rollback(): void
    {
        $device = $this->makeDevice();
        $entry = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::UPDATE, [], $device);

        $this->inbox->reject($entry, 'duplicate sayad number');

        $fresh = $entry->fresh();
        $this->assertSame(SyncStatus::REJECTED, $fresh->status);
        $this->assertSame('duplicate sayad number', $fresh->rejection_reason);
        $this->assertTrue($fresh->status->needsReview());
    }

    public function test_inbox_conflict_records_both_versions(): void
    {
        $device = $this->makeDevice();
        $entry = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::UPDATE, [], $device);

        $this->inbox->markConflict($entry, 'version mismatch', 'v5', 'v3');

        $fresh = $entry->fresh();
        $this->assertSame(SyncStatus::CONFLICT, $fresh->status);
        $this->assertSame('v5', $fresh->server_version);
        $this->assertSame('v3', $fresh->local_version);
    }

    public function test_inbox_mark_applied(): void
    {
        $device = $this->makeDevice();
        $entry = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::CREATE, [], $device);

        $this->inbox->markApplied($entry);

        $this->assertSame(SyncStatus::SYNCED, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->applied_at);
    }

    public function test_needs_review_returns_only_conflict_and_rejected(): void
    {
        $device = $this->makeDevice();
        $applied = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::CREATE, [], $device);
        $this->inbox->markApplied($applied);

        $rejected = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::CREATE, [], $device);
        $this->inbox->reject($rejected, 'bad data');

        $conflict = $this->inbox->receive(Customer::class, (string) Str::uuid(), SyncOperation::CREATE, [], $device);
        $this->inbox->markConflict($conflict, 'mismatch');

        $needsReview = $this->inbox->needsReview($this->companyId);

        $this->assertCount(2, $needsReview);
        $this->assertTrue($needsReview->pluck('id')->contains($rejected->id));
        $this->assertTrue($needsReview->pluck('id')->contains($conflict->id));
        $this->assertFalse($needsReview->pluck('id')->contains($applied->id));
    }

    private function makeDevice(): DeviceInstallation
    {
        return DeviceInstallation::create([
            'installation_uuid' => (string) Str::uuid(),
            'device_key_hash' => bcrypt('secret'),
            'company_id' => $this->companyId,
            'status' => DeviceInstallationStatus::ACTIVE,
        ]);
    }
}
