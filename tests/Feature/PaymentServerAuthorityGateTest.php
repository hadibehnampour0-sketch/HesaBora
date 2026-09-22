<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use App\Services\DevicePairingService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-080 stage 2.8C -- Server Financial Authority & Reconciliation Gate.
 * Deliberately does NOT re-test 2.8B's already-proven create/retry/
 * idempotency mechanics -- only the scenarios 2.8B left open: a
 * different operation_id claiming the same financial_event_uuid, a
 * genuinely different financial_event_uuid that merely looks similar,
 * and a server-side business-validation failure on a payment the device
 * already treats as posted locally.
 */
class PaymentServerAuthorityGateTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected DevicePairingService $pairingService;

    protected Customer $customer;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

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

    private function creditPaymentEntry(array $overrides = [], ?string $operationId = null): array
    {
        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => Payment::class,
            'entity_uuid' => $overrides['entity_uuid'] ?? (string) Str::uuid(),
            'operation' => SyncOperation::CREATE->value,
            'payload' => array_merge([
                'payment_type' => 'credit',
                'customer_uuid' => $this->customer->uuid,
                'subject_uuid' => $this->cashSubject()->uuid,
                'amount' => 1000,
                'date' => now()->toDateString(),
                'description' => 'Gate test payment',
            ], $overrides['payload'] ?? []),
            'base_sync_version' => null,
        ];
    }

    private function push(array $device, array $entry): TestResponse
    {
        return $this->withHeaders($this->deviceHeaders($device))->postJson(route('api.sync.push'), [
            'entries' => [$entry],
        ]);
    }

    // --- 1. Same financial_event_uuid, matching payload, DIFFERENT operation_id
    //        -> idempotent convergence, never a conflict ------------------------

    public function test_a_different_operation_id_for_the_same_financial_event_with_matching_payload_converges(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]))->assertJsonPath('results.0.status', 'accepted');

        // A DIFFERENT operation_id (e.g. the device lost track of its own
        // first attempt) claiming the SAME financial_event_uuid, with the
        // exact same details.
        $again = $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]));

        $again->assertJsonPath('results.0.status', 'accepted');
        $this->assertSame(1, Payment::where('uuid', $entityUuid)->count());
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 2. Same financial_event_uuid, DIFFERENT payload -> tamper conflict --

    public function test_a_different_operation_id_for_the_same_financial_event_with_a_different_payload_is_a_conflict(): void
    {
        $device = $this->makeDevice();
        $entityUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $entityUuid]))->assertJsonPath('results.0.status', 'accepted');

        $tampered = $this->push($device, $this->creditPaymentEntry([
            'entity_uuid' => $entityUuid,
            'payload' => ['amount' => 9999],
        ]));

        $tampered->assertJsonPath('results.0.status', 'conflict');
        // The original posting must be completely untouched.
        $payment = Payment::where('uuid', $entityUuid)->first();
        $this->assertEqualsWithDelta(1000, (float) $payment->amount, 0.01);
        $this->assertSame($documentCountBefore + 1, Document::withoutGlobalScopes()->count());
    }

    // --- 3. DIFFERENT financial_event_uuid, similar payload -> separate events

    public function test_two_different_financial_event_uuids_with_identical_looking_payloads_are_two_separate_events(): void
    {
        $device = $this->makeDevice();
        $firstUuid = (string) Str::uuid();
        $secondUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $firstUuid]))->assertJsonPath('results.0.status', 'accepted');
        $this->push($device, $this->creditPaymentEntry(['entity_uuid' => $secondUuid]))->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(2, Payment::whereIn('uuid', [$firstUuid, $secondUuid])->count());
        $this->assertSame($documentCountBefore + 2, Document::withoutGlobalScopes()->count());
    }

    // --- 4. Server business-validation failure on a "locally posted" payment -

    public function test_a_real_server_side_business_validation_failure_is_a_deterministic_rejection_with_no_posting(): void
    {
        $device = $this->makeDevice();
        // A customer that EXISTS (so dependency resolution succeeds) but has
        // no accounting Subject -- PaymentService's own internal validation
        // (not this Sync layer's pre-checks) must reject this.
        $customerWithoutSubject = Customer::factory()->create(['company_id' => $this->companyId, 'group_id' => null]);
        $entityUuid = (string) Str::uuid();
        $documentCountBefore = Document::withoutGlobalScopes()->count();
        $paymentCountBefore = Payment::count();

        $response = $this->push($device, $this->creditPaymentEntry([
            'entity_uuid' => $entityUuid,
            'payload' => ['customer_uuid' => $customerWithoutSubject->uuid],
        ]));

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertNull(Payment::where('uuid', $entityUuid)->first());
        // Deterministic rejection -- no silent local rollback, no partial
        // Payment/Document ever leaked.
        $this->assertSame($paymentCountBefore, Payment::count());
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());
    }

    // --- 5. Retry after a rejection is still safe (no phantom half-state) ----

    public function test_retrying_the_same_operation_id_after_a_rejection_stays_rejected_with_no_posting(): void
    {
        $device = $this->makeDevice();
        $customerWithoutSubject = Customer::factory()->create(['company_id' => $this->companyId, 'group_id' => null]);
        $operationId = (string) Str::uuid();
        $entry = $this->creditPaymentEntry([
            'payload' => ['customer_uuid' => $customerWithoutSubject->uuid],
        ], $operationId);
        $documentCountBefore = Document::withoutGlobalScopes()->count();

        $this->push($device, $entry)->assertJsonPath('results.0.status', 'rejected');
        // Same operation_id retried -- the idempotency shortcut returns the
        // SAME cached rejection, it does not re-attempt (and does not
        // magically succeed) posting.
        $retry = $this->push($device, $entry);

        $retry->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame($documentCountBefore, Document::withoutGlobalScopes()->count());
    }
}
