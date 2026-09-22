<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\SyncInboxEntry;
use App\Models\SyncOutboxEntry;
use App\Services\DevicePairingService;
use App\Services\DocumentService;
use App\Services\Sync\SyncPilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-073 stage 2.7A -- Document Draft Sync, the first sync stage to touch a
 * financial entity. Locked boundary: Document only (Invoice is 2.7B), Draft
 * only (Approved/Posted is 2.7D), manual documents only (documentable-linked
 * automatic documents wait for later stages), create/update only (hard
 * delete is permanently out of scope per D-073 decision 5), and the
 * `number` field is never synced (finalization/numbering is 2.7C).
 */
class DocumentDraftSyncTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected SyncPilotService $pilot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->pairingService = app(DevicePairingService::class);
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

    private function makeDraftDocument(array $overrides = []): Document
    {
        return Document::factory()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    // --- 1. Auto-enqueue is wired for manual Draft documents only -----------

    public function test_creating_a_manual_draft_document_enqueues_exactly_one_create_entry(): void
    {
        $document = $this->makeDraftDocument();

        $entries = SyncOutboxEntry::where('entity_uuid', $document->uuid)->get();

        $this->assertCount(1, $entries);
        $this->assertSame(SyncOperation::CREATE, $entries->first()->operation);
    }

    public function test_updating_a_draft_document_enqueues_an_update(): void
    {
        $document = $this->makeDraftDocument();
        $document->update(['title' => 'Renamed draft']);

        $entry = SyncOutboxEntry::where('entity_uuid', $document->uuid)->where('operation', SyncOperation::UPDATE)->first();

        $this->assertNotNull($entry);
        $this->assertSame('Renamed draft', $entry->payload['title']);
    }

    public function test_approving_a_document_does_not_enqueue_a_sync_operation(): void
    {
        $document = $this->makeDraftDocument();
        $countBefore = SyncOutboxEntry::where('entity_uuid', $document->uuid)->count();

        $user = \App\Models\User::factory()->create();
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        $this->assertSame($countBefore, SyncOutboxEntry::where('entity_uuid', $document->uuid)->count());
    }

    public function test_deleting_a_draft_document_never_enqueues_anything(): void
    {
        $document = $this->makeDraftDocument();
        $uuid = $document->uuid;
        DocumentService::deleteDocument($document->id);

        $this->assertSame(0, SyncOutboxEntry::where('entity_uuid', $uuid)->where('operation', SyncOperation::DELETE)->count());
    }

    public function test_an_automatic_documentable_linked_document_never_enqueues(): void
    {
        $user = \App\Models\User::factory()->create();
        $other = $this->makeDraftDocument(); // stands in as a "documentable" polymorphic target

        $document = DocumentService::createDocument($user, [
            'title' => 'Automatic doc',
            'date' => now()->toDateString(),
            'company_id' => $this->companyId,
            'documentable' => $other,
        ], []);

        $this->assertSame(0, SyncOutboxEntry::where('entity_uuid', $document->uuid)->count());
    }

    // --- 2. Real push over the HTTP API: create + update --------------------

    public function test_push_creates_the_draft_document_server_side(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['title' => 'Pushed draft', 'date' => now()->toDateString(), 'company_id' => $this->companyId],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');

        $created = Document::where('uuid', $entityUuid)->first();
        $this->assertNotNull($created);
        $this->assertSame('Pushed draft', $created->title);
        $this->assertNull($created->approved_at);
        $this->assertSame(1, $created->sync_version);
    }

    public function test_pushing_an_update_bumps_the_server_version(): void
    {
        $device = $this->makeDevice();
        $document = $this->makeDraftDocument(['title' => 'Original']);

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Updated remotely'],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $document->fresh();
        $this->assertSame('Updated remotely', $fresh->title);
        $this->assertSame(2, $fresh->sync_version);
    }

    // --- 3. Locked boundary: no hard delete ----------------------------------

    public function test_a_delete_operation_is_rejected_outright(): void
    {
        $device = $this->makeDevice();
        $document = $this->makeDraftDocument();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::DELETE->value,
                'payload' => ['uuid' => $document->uuid],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotNull(Document::where('uuid', $document->uuid)->first());
    }

    // --- 4. Locked boundary: no approving via sync ---------------------------

    public function test_a_payload_that_sets_approved_at_is_rejected(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['title' => 'Sneaky approval', 'approved_at' => now()->toDateString(), 'company_id' => $this->companyId],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNull(Document::where('uuid', $entityUuid)->first());
    }

    public function test_updating_an_already_approved_document_via_sync_is_rejected(): void
    {
        $device = $this->makeDevice();
        $document = $this->makeDraftDocument();
        $document->forceFill(['approved_at' => now(), 'sync_status' => SyncStatus::SYNCED])->save();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Trying to edit an approved doc'],
                'base_sync_version' => $document->fresh()->sync_version,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame($document->title, $document->fresh()->title);
    }

    // --- 5. Locked boundary: no documentable-linked documents ---------------

    public function test_a_payload_with_a_documentable_link_is_rejected(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'title' => 'Linked doc',
                    'documentable_type' => 'App\\Models\\Cheque',
                    'documentable_id' => 1,
                    'company_id' => $this->companyId,
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNull(Document::where('uuid', $entityUuid)->first());
    }

    // --- 6. `number` is never applied from a remote payload ------------------

    public function test_the_official_number_is_never_overwritten_by_a_remote_payload(): void
    {
        $device = $this->makeDevice();
        $document = $this->makeDraftDocument(['number' => 5]);
        $localNumber = $document->number;

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Still local numbering', 'number' => 999],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $document->fresh();
        $this->assertSame('Still local numbering', $fresh->title);
        $this->assertEquals($localNumber, $fresh->number);
    }

    // --- 7. Version conflict still works, exactly like Customer/Product -----

    public function test_two_devices_updating_the_same_draft_document_produces_a_conflict(): void
    {
        $deviceA = $this->makeDevice();
        $document = $this->makeDraftDocument(['title' => 'Shared draft']);
        $deviceB = $this->makeDevice();

        $pushA = $this->withHeaders($this->deviceHeaders($deviceA))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Renamed by A'],
                'base_sync_version' => 1,
            ]],
        ]);
        $pushA->assertJsonPath('results.0.status', 'accepted');

        $pushB = $this->withHeaders($this->deviceHeaders($deviceB))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Document::class,
                'entity_uuid' => $document->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Renamed by B'],
                'base_sync_version' => 1, // stale -- server is already at 2
            ]],
        ]);

        $pushB->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame('Renamed by A', $document->fresh()->title);

        $conflict = \App\Models\SyncConflict::where('entity_uuid', $document->uuid)->first();
        $this->assertNotNull($conflict);
    }

    // --- 8. Pull excludes Approved and automatic documents -------------------

    public function test_pull_only_returns_draft_manual_documents(): void
    {
        $device = $this->makeDevice();
        $draft = $this->makeDraftDocument();
        $approved = $this->makeDraftDocument();
        $approved->forceFill(['approved_at' => now()])->save();

        $response = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => Document::class, 'after_id' => 0])
        );

        $response->assertOk();
        $uuids = collect($response->json('rows'))->pluck('uuid');
        $this->assertTrue($uuids->contains($draft->uuid));
        $this->assertFalse($uuids->contains($approved->uuid));
    }

    // --- 9. Idempotent retry, same guarantee as Customer/Product ------------

    public function test_resending_the_same_operation_id_does_not_create_a_duplicate_document(): void
    {
        $device = $this->makeDevice();
        $operationId = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();
        $payload = [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Document::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['title' => 'Once only', 'company_id' => $this->companyId],
                'base_sync_version' => null,
            ]],
        ];

        $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);
        $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);

        $this->assertSame(1, Document::where('uuid', $entityUuid)->count());
        $this->assertSame(1, SyncInboxEntry::where('operation_id', $operationId)->count());
    }
}
