<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\SyncInboxEntry;
use App\Models\SyncOutboxEntry;
use App\Services\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-073 stage 2.7B -- Invoice Draft Sync, the first real exercise of
 * decision 6 (dependency-aware sync): an Invoice's `customer_id` is a
 * required FK, so a CREATE cannot be attempted without resolving the
 * parent Customer by `customer_uuid` first. Locked boundary otherwise
 * mirrors 2.7A: Draft only (no document_id, no approved/settled status),
 * no hard delete, no return/void types (those reference another invoice),
 * no linking to a Document yet.
 */
class InvoiceDraftSyncTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    private static int $nextTestInvoiceNumber = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->pairingService = app(DevicePairingService::class);
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

    /**
     * A bare, minimal Draft invoice -- deliberately NOT using the heavy
     * InvoiceFactory (which builds items/inventory effects and can land on
     * either APPROVED or UNAPPROVED); Draft Sync only cares about the
     * header row and its sync-eligibility boundary.
     */
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

    // --- 1. Auto-enqueue wiring ----------------------------------------------

    public function test_creating_a_draft_invoice_enqueues_exactly_one_create_entry_with_customer_uuid(): void
    {
        $customer = $this->makeCustomer();
        $invoice = $this->makeDraftInvoice(['customer_id' => $customer->id]);

        $entries = SyncOutboxEntry::where('entity_uuid', $invoice->uuid)->get();

        $this->assertCount(1, $entries);
        $this->assertSame(SyncOperation::CREATE, $entries->first()->operation);
        $this->assertSame($customer->uuid, $entries->first()->payload['customer_uuid']);
    }

    public function test_updating_a_draft_invoice_enqueues_an_update(): void
    {
        $invoice = $this->makeDraftInvoice();
        $invoice->update(['title' => 'Renamed draft invoice']);

        $entry = SyncOutboxEntry::where('entity_uuid', $invoice->uuid)->where('operation', SyncOperation::UPDATE)->first();

        $this->assertNotNull($entry);
        $this->assertSame('Renamed draft invoice', $entry->payload['title']);
    }

    public function test_approving_an_invoice_does_not_enqueue_a_sync_operation(): void
    {
        $invoice = $this->makeDraftInvoice();
        $countBefore = SyncOutboxEntry::where('entity_uuid', $invoice->uuid)->count();

        $invoice->update(['status' => InvoiceStatus::APPROVED]);

        $this->assertSame($countBefore, SyncOutboxEntry::where('entity_uuid', $invoice->uuid)->count());
    }

    public function test_a_return_invoice_never_enqueues(): void
    {
        $invoice = $this->makeDraftInvoice(['invoice_type' => InvoiceType::RETURN_SELL]);

        $this->assertSame(0, SyncOutboxEntry::where('entity_uuid', $invoice->uuid)->count());
    }

    public function test_deleting_a_draft_invoice_never_enqueues_anything(): void
    {
        $invoice = $this->makeDraftInvoice();
        $uuid = $invoice->uuid;
        $invoice->delete();

        $this->assertSame(0, SyncOutboxEntry::where('entity_uuid', $uuid)->where('operation', SyncOperation::DELETE)->count());
    }

    // --- 2. Dependency-aware sync (D-073 decision 6) -------------------------

    public function test_pushing_an_invoice_before_its_customer_becomes_pending_dependency_then_converges_on_retry(): void
    {
        $device = $this->makeDevice();
        $missingCustomerUuid = (string) Str::uuid();
        $invoiceUuid = (string) Str::uuid();
        $operationId = (string) Str::uuid();

        $push = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoiceUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $missingCustomerUuid,
                    'invoice_type' => 'sell',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                    'title' => 'Waiting on customer',
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $push->assertJsonPath('results.0.status', 'pending_dependency');
        $this->assertNull(Invoice::where('uuid', $invoiceUuid)->first());

        $entry = SyncInboxEntry::where('operation_id', $operationId)->firstOrFail();
        $this->assertSame(SyncStatus::PENDING_DEPENDENCY, $entry->status);

        // The customer "arrives" -- created locally with the exact uuid the
        // pending invoice referenced (as if its own Customer sync entry had
        // just been accepted). `uuid` is not mass-assignable, so it is
        // forced onto the row directly after creation.
        $customer = $this->makeCustomer();
        $customer->forceFill(['uuid' => $missingCustomerUuid])->save();

        // Retry: same operation_id, same request.
        $retry = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoiceUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $missingCustomerUuid,
                    'invoice_type' => 'sell',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                    'title' => 'Waiting on customer',
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $retry->assertJsonPath('results.0.status', 'accepted');
        $created = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertNotNull($created);
        $this->assertSame($customer->id, $created->customer_id);
        $this->assertSame(1, $created->sync_version);
    }

    public function test_a_create_without_customer_uuid_is_rejected_not_pending(): void
    {
        $device = $this->makeDevice();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::CREATE->value,
                'payload' => ['invoice_type' => 'sell', 'status' => 'pending', 'date' => now()->toDateString()],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    // --- 3. Real push over HTTP: normal create/update ------------------------

    public function test_push_creates_the_draft_invoice_server_side(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer();
        $invoiceUuid = (string) Str::uuid();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoiceUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $customer->uuid,
                    'invoice_type' => 'buy',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                    'title' => 'Pushed draft invoice',
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $created = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertNotNull($created);
        $this->assertSame($customer->id, $created->customer_id);
        $this->assertSame('Pushed draft invoice', $created->title);
        $this->assertNull($created->document_id);
    }

    public function test_pushing_an_update_bumps_the_server_version(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice(['title' => 'Original']);

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Updated remotely'],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'accepted');
        $fresh = $invoice->fresh();
        $this->assertSame('Updated remotely', $fresh->title);
        $this->assertSame(2, $fresh->sync_version);
    }

    // --- 4. Locked boundary rejections ---------------------------------------

    public function test_a_delete_operation_is_rejected_outright(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::DELETE->value,
                'payload' => ['uuid' => $invoice->uuid],
                'base_sync_version' => 1,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotNull(Invoice::where('uuid', $invoice->uuid)->first());
    }

    public function test_a_payload_setting_an_approved_status_is_rejected(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $customer->uuid,
                    'invoice_type' => 'sell',
                    'status' => 'approved',
                    'date' => now()->toDateString(),
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_a_payload_with_a_document_link_is_rejected(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $customer->uuid,
                    'invoice_type' => 'sell',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                    'document_id' => 1,
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_a_return_or_void_invoice_type_is_rejected(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => (string) Str::uuid(),
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $customer->uuid,
                    'invoice_type' => 'void',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                ],
                'base_sync_version' => null,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_updating_an_invoice_already_linked_to_a_document_is_rejected(): void
    {
        $device = $this->makeDevice();
        $invoice = $this->makeDraftInvoice();
        $document = \App\Models\Document::factory()->create(['company_id' => $this->companyId]);
        $invoice->forceFill(['document_id' => $document->id])->save();

        $response = $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Trying to edit a linked invoice'],
                'base_sync_version' => $invoice->fresh()->sync_version,
            ]],
        ]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    // --- 5. Version conflict, same shape as Customer/Product/Document -------

    public function test_two_devices_updating_the_same_draft_invoice_produces_a_conflict(): void
    {
        $deviceA = $this->makeDevice();
        $invoice = $this->makeDraftInvoice(['title' => 'Shared invoice']);
        $deviceB = $this->makeDevice();

        $pushA = $this->withHeaders($this->deviceHeaders($deviceA))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Renamed by A'],
                'base_sync_version' => 1,
            ]],
        ]);
        $pushA->assertJsonPath('results.0.status', 'accepted');

        $pushB = $this->withHeaders($this->deviceHeaders($deviceB))->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Invoice::class,
                'entity_uuid' => $invoice->uuid,
                'operation' => SyncOperation::UPDATE->value,
                'payload' => ['title' => 'Renamed by B'],
                'base_sync_version' => 1, // stale -- server is already at 2
            ]],
        ]);

        $pushB->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame('Renamed by A', $invoice->fresh()->title);

        $conflict = \App\Models\SyncConflict::where('entity_uuid', $invoice->uuid)->first();
        $this->assertNotNull($conflict);
    }

    // --- 6. Pull filters ------------------------------------------------------

    public function test_pull_only_returns_draft_document_less_non_return_invoices(): void
    {
        $device = $this->makeDevice();
        $draft = $this->makeDraftInvoice();
        $approved = $this->makeDraftInvoice();
        $approved->forceFill(['status' => InvoiceStatus::APPROVED])->save();
        $returned = $this->makeDraftInvoice(['invoice_type' => InvoiceType::RETURN_SELL]);

        $response = $this->withHeaders($this->deviceHeaders($device))->getJson(
            route('api.sync.pull', ['entity_type' => Invoice::class, 'after_id' => 0])
        );

        $response->assertOk();
        $uuids = collect($response->json('rows'))->pluck('uuid');
        $this->assertTrue($uuids->contains($draft->uuid));
        $this->assertFalse($uuids->contains($approved->uuid));
        $this->assertFalse($uuids->contains($returned->uuid));
    }

    // --- 7. Idempotent retry ---------------------------------------------------

    public function test_resending_the_same_operation_id_does_not_create_a_duplicate_invoice(): void
    {
        $device = $this->makeDevice();
        $customer = $this->makeCustomer();
        $operationId = (string) Str::uuid();
        $entityUuid = (string) Str::uuid();
        $payload = [
            'entries' => [[
                'operation_id' => $operationId,
                'entity_type' => Invoice::class,
                'entity_uuid' => $entityUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'customer_uuid' => $customer->uuid,
                    'invoice_type' => 'sell',
                    'status' => 'pending',
                    'date' => now()->toDateString(),
                    'title' => 'Once only',
                ],
                'base_sync_version' => null,
            ]],
        ];

        $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);
        $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), $payload);

        $this->assertSame(1, Invoice::where('uuid', $entityUuid)->count());
        $this->assertSame(1, SyncInboxEntry::where('operation_id', $operationId)->count());
    }
}
