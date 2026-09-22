<?php

namespace Tests\Feature;

use App\Enums\SyncOperation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\SyncInboxEntry;
use App\Models\User;
use App\Services\DevicePairingService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P10 hardening (found during the pre-P8/P9/P10 audit, not previously
 * tested anywhere): Payment/PaymentAllocation have no FiscalYearScope of
 * their own (no `company_id` column at all -- company is only derivable
 * via `resolvedCompanyId()`, which chains through document/invoice/
 * cheque/payer). SyncPilotService::apply()'s `$modelClass::where('uuid',
 * ...)->first()` lookup, run before the Payment/PaymentAllocation-specific
 * branches, was therefore NOT company-filtered -- a uuid collision with
 * another company's row (astronomically unlikely in practice, since uuids
 * are random, but the same defect class as guardrail #32's
 * BankStatementLine/Transaction findings) would surface that other
 * company's payment's existence and sync_version in the conflict response
 * returned to the pushing device. Fixed by treating a cross-company match
 * as no match at all.
 */
class PaymentSyncCrossCompanyTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected int $companyId;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function cashSubject(int $companyId): Subject
    {
        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $companyId]);

        return $box;
    }

    private function pairDeviceToOtherCompany(int $otherCompanyId): array
    {
        $otherCompany = Company::withoutGlobalScopes()->find($otherCompanyId);
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);

        $pairingService = app(DevicePairingService::class);
        $code = $pairingService->issueActivationCode($otherCompany, $otherUser);

        return $pairingService->pair($code);
    }

    public function test_a_uuid_collision_with_another_companys_payment_is_not_treated_as_that_payment(): void
    {
        // Company A already has a real payment.
        $companyASubject = $this->cashSubject($this->companyId);
        $sharedUuid = (string) Str::uuid();
        $existingPayment = Payment::withoutGlobalScopes()->create([
            'uuid' => $sharedUuid,
            'financial_event_uuid' => $sharedUuid,
            'sync_version' => 1,
            'sync_status' => \App\Enums\SyncStatus::SYNCED,
            'payer_type' => Customer::class,
            'payer_id' => $this->customer->id,
            'settlement_subject_id' => $companyASubject->id,
            'amount' => 12345,
            'date' => now()->toDateString(),
        ]);

        // Company B is a completely different tenant.
        config(['active-company-id' => $this->companyId]);
        $otherCompanyId = Company::factory()->create()->id;
        config(['active-company-id' => $otherCompanyId]);
        $otherCustomerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $otherCompanyId]);
        $otherCustomer = Customer::factory()->withGroup($otherCustomerGroup)->withSubject()->create(['company_id' => $otherCompanyId]);
        $otherSubject = $this->cashSubject($otherCompanyId);
        config(['active-company-id' => $this->companyId]);

        $device = $this->pairDeviceToOtherCompany($otherCompanyId);

        // Note: `config('amir.cash_book')` is a process-global runtime
        // value (guardrail #37) set for company A in setUp() -- company B's
        // subject here is not a fully valid settlement account for
        // PaymentService's own business validation, so this push will not
        // fully succeed. That's fine: this test isolates the ONE property
        // in question -- a uuid collision must never surface as a conflict
        // against company A's row -- independent of whether company B's
        // own payload is otherwise business-valid.
        //
        // Company B's device happens to push a brand-new payment that
        // collides on the SAME uuid as company A's existing payment --
        // this must be treated as a fresh create for company B, never as
        // "found" company A's row.
        $response = $this->withHeaders([
            'X-Device-Uuid' => $device['installation']->installation_uuid,
            'X-Device-Key' => $device['device_key'],
        ])->postJson(route('api.sync.push'), [
            'entries' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => Payment::class,
                'entity_uuid' => $sharedUuid,
                'operation' => SyncOperation::CREATE->value,
                'payload' => [
                    'payment_type' => 'credit',
                    'customer_uuid' => $otherCustomer->uuid,
                    'subject_uuid' => $otherSubject->uuid,
                    'amount' => 999,
                    'date' => now()->toDateString(),
                ],
                'base_sync_version' => null,
            ]],
        ]);

        // The bug this test guards against would surface here as status
        // "conflict" with a reason like "already exists with different
        // details" and company A's sync_version echoed back to company
        // B's device. Whatever the outcome of company B's own business
        // validation, it must never be a conflict against company A's row.
        $this->assertNotSame('conflict', $response->json('results.0.status'));
        $this->assertStringNotContainsString('already exists', (string) $response->json('results.0.reason'));

        // Company A's original payment is completely untouched.
        $existingPayment->refresh();
        $this->assertEqualsWithDelta(12345.0, (float) $existingPayment->amount, 0.01);
        $this->assertSame(1, $existingPayment->sync_version);
        $this->assertNotEquals((string) $existingPayment->sync_version, (string) $response->json('results.0.server_version'));
    }

    public function test_payment_allocation_resolved_company_id_derives_correctly_through_its_payment(): void
    {
        $otherCompanyId = Company::factory()->create()->id;

        $sharedUuid = (string) Str::uuid();
        $unrelatedPayment = Payment::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'financial_event_uuid' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_status' => \App\Enums\SyncStatus::SYNCED,
            'payer_type' => Customer::class,
            'payer_id' => $this->customer->id,
            'settlement_subject_id' => $this->cashSubject($this->companyId)->id,
            'amount' => 500,
            'date' => now()->toDateString(),
        ]);
        $existingAllocation = PaymentAllocation::withoutGlobalScopes()->create([
            'uuid' => $sharedUuid,
            'sync_version' => 1,
            'sync_status' => \App\Enums\SyncStatus::SYNCED,
            'payment_id' => $unrelatedPayment->id,
            'allocatable_type' => \App\Models\Invoice::class,
            'allocatable_id' => 0,
            'amount' => 500,
            'allocated_at' => now(),
        ]);

        $this->assertSame($this->companyId, $existingAllocation->resolvedCompanyId());
        $this->assertNotSame($otherCompanyId, $existingAllocation->resolvedCompanyId());
    }
}
