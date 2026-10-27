<?php

namespace Tests\Feature;

use App\Enums\PeriodStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentReversalService;
use App\Services\DocumentService;
use App\Services\PaymentService;
use App\Services\PeriodChecklistService;
use App\Services\PeriodLockGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PeriodCloseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Subject $bankSubject;

    private Subject $customerSubject;

    private PeriodChecklistService $checklistService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'period_status' => PeriodStatus::OPEN,
        ]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$this->user->id]);

        $this->actingAs($this->user);
        session(['active_company_id' => $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->bankSubject = Subject::create([
            'name' => 'بانک ملت',
            'code' => '101',
            'company_id' => $this->company->id,
            'parent_id' => null,
            'type' => 1,
        ]);

        $this->customerSubject = Subject::create([
            'name' => 'حساب دریافتنی مشتری',
            'code' => '102',
            'company_id' => $this->company->id,
            'parent_id' => null,
            'type' => 1,
        ]);

        $this->checklistService = new PeriodChecklistService;

        // Permissions
        Permission::firstOrCreate(['name' => 'fiscal-year.period-status']);
        Permission::firstOrCreate(['name' => 'fiscal-year.reopen']);
        Permission::firstOrCreate(['name' => 'fiscal-year.closed-period-override']);
        Permission::firstOrCreate(['name' => 'companies.period-status']);
        Permission::firstOrCreate(['name' => 'companies.period-checklist']);
        Permission::firstOrCreate(['name' => 'documents.reverse']);
        Permission::firstOrCreate(['name' => 'documents.show']);
    }

    private function createSampleApprovedDocument(): Document
    {
        $data = [
            'title' => 'سند نمونه تایید شده',
            'date' => now()->format('Y-m-d'),
            'company_id' => $this->company->id,
            'approved_at' => now()->format('Y-m-d'),
            'approver_id' => $this->user->id,
        ];

        $transactions = [
            [
                'subject_id' => $this->bankSubject->id,
                'value' => 200000.00,
                'user_id' => $this->user->id,
            ],
            [
                'subject_id' => $this->customerSubject->id,
                'value' => -200000.00,
                'user_id' => $this->user->id,
            ],
        ];

        return DocumentService::createDocument($this->user, $data, $transactions);
    }

    public function test_company_period_defaults_to_open(): void
    {
        $this->assertEquals(PeriodStatus::OPEN, $this->company->period_status);
        $this->assertTrue($this->company->isMutable());
        $this->assertFalse($this->company->isLocked());
        $this->assertFalse($this->company->isClosed());
        $this->assertTrue(PeriodLockGuard::isMutable($this->company));
    }

    public function test_period_state_machine_transitions(): void
    {
        $this->user->givePermissionTo(['fiscal-year.period-status', 'companies.period-status']);

        // Transition to Review
        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'review',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(PeriodStatus::REVIEW, $this->company->fresh()->period_status);
        $this->assertTrue($this->company->fresh()->isMutable());

        // Transition to Reconciled
        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'reconciled',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(PeriodStatus::RECONCILED, $this->company->fresh()->period_status);
        $this->assertTrue($this->company->fresh()->isMutable());
    }

    public function test_pre_close_checklist_identifies_draft_documents_and_blocks_closing(): void
    {
        $this->user->givePermissionTo(['fiscal-year.period-status', 'companies.period-status']);

        // Create an unapproved draft document
        $data = [
            'title' => 'سند پیش‌نویس',
            'date' => now()->format('Y-m-d'),
            'company_id' => $this->company->id,
            'approved_at' => null,
            'approver_id' => null,
        ];
        $transactions = [
            ['subject_id' => $this->bankSubject->id, 'value' => 50000.00, 'user_id' => $this->user->id],
            ['subject_id' => $this->customerSubject->id, 'value' => -50000.00, 'user_id' => $this->user->id],
        ];
        DocumentService::createDocument($this->user, $data, $transactions);

        // Checklist should catch draft
        $checklist = $this->checklistService->getPreCloseChecklist($this->company);
        $this->assertFalse($checklist['can_close']);
        $this->assertGreaterThan(0, $checklist['blockers_count']);
        $this->assertEquals('blocker', $checklist['items']['draft_documents']['status']);

        // Attempting to close or lock via endpoint must throw ValidationException
        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'locked',
        ]);
        $response->assertSessionHasErrors('period_status');
        $this->assertNotEquals(PeriodStatus::LOCKED, $this->company->fresh()->period_status);
    }

    public function test_advancing_to_locked_sets_locked_at_and_locked_by(): void
    {
        $this->user->givePermissionTo(['fiscal-year.period-status', 'companies.period-status']);

        // Clean state with approved documents only
        $this->createSampleApprovedDocument();

        $checklist = $this->checklistService->getPreCloseChecklist($this->company);
        $this->assertTrue($checklist['can_close']);

        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'locked',
        ]);
        $response->assertSessionHasNoErrors();

        $fresh = $this->company->fresh();
        $this->assertEquals(PeriodStatus::LOCKED, $fresh->period_status);
        $this->assertTrue($fresh->isLocked());
        $this->assertFalse($fresh->isMutable());
        $this->assertNotNull($fresh->locked_at);
        $this->assertEquals($this->user->id, $fresh->locked_by);
    }

    public function test_period_lock_guard_strictly_rejects_document_creation_when_locked(): void
    {
        $this->company->update([
            'period_status' => PeriodStatus::LOCKED,
            'locked_at' => now(),
            'locked_by' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);

        DocumentService::createDocument($this->user, [
            'title' => 'تلاش برای ثبت سند در دوره قفل شده',
            'date' => now()->format('Y-m-d'),
            'company_id' => $this->company->id,
        ], [
            ['subject_id' => $this->bankSubject->id, 'value' => 1000.00, 'user_id' => $this->user->id],
            ['subject_id' => $this->customerSubject->id, 'value' => -1000.00, 'user_id' => $this->user->id],
        ]);
    }

    public function test_period_lock_guard_strictly_rejects_document_update_delete_and_reversal_when_locked(): void
    {
        $doc = $this->createSampleApprovedDocument();

        // Lock period
        $this->company->update(['period_status' => PeriodStatus::LOCKED]);

        // 1. Update must fail
        try {
            DocumentService::updateDocument($doc, ['title' => 'عنوان جدید']);
            $this->fail('Expected ValidationException on updateDocument');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('قفل قطعی', $e->getMessage());
        }

        // 2. Change status must fail
        try {
            DocumentService::changeDocumentStatus($doc, $this->user, 'unapproved');
            $this->fail('Expected ValidationException on changeDocumentStatus');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('قفل قطعی', $e->getMessage());
        }

        // 3. Reversal must fail
        try {
            app(DocumentReversalService::class)->reverseDocument($doc, $this->user, DocumentReversalService::REASON_OTHER, 'ابطال');
            $this->fail('Expected ValidationException on reverseDocument');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('قفل قطعی', $e->getMessage());
        }

        // 4. Delete must fail
        try {
            DocumentService::deleteDocument($doc->id);
            $this->fail('Expected ValidationException on deleteDocument');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('قفل قطعی', $e->getMessage());
        }
    }

    public function test_period_lock_guard_strictly_rejects_allocation_when_locked(): void
    {
        $doc = $this->createSampleApprovedDocument();

        $payment = Payment::create([
            'amount' => 50000.00,
            'date' => now()->toDateString(),
            'company_id' => $this->company->id,
            'subject_id' => $this->bankSubject->id,
            'document_id' => $doc->id,
            'type' => 1,
        ]);

        $this->company->update(['period_status' => PeriodStatus::LOCKED]);

        $paymentService = new PaymentService;

        $this->expectException(ValidationException::class);
        $paymentService->allocate($this->user, $payment, $doc, 10000.00);
    }

    public function test_reopening_locked_period_requires_elevated_permission_and_records_audit_trail(): void
    {
        $this->company->update([
            'period_status' => PeriodStatus::LOCKED,
            'locked_at' => now(),
            'locked_by' => $this->user->id,
        ]);

        // 1. User without fiscal-year.reopen gets 403 Forbidden
        $this->user->givePermissionTo('companies.period-status');
        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'reopen',
            'reopen_reason' => 'بازگشایی جهت اصلاح اسناد پایان سال',
        ]);
        $response->assertStatus(403);

        // 2. Grant permission and verify successful reopening
        $this->user->givePermissionTo(['fiscal-year.reopen', 'companies.period-status']);
        $response = $this->post(route('companies.period-status', $this->company), [
            'status' => 'reopen',
            'reopen_reason' => 'بازگشایی جهت اعمال تعدیلات مالیاتی مصوب هیات مدیره',
        ]);
        $response->assertSessionHasNoErrors();

        $fresh = $this->company->fresh();
        $this->assertEquals(PeriodStatus::OPEN, $fresh->period_status);
        $this->assertTrue($fresh->isMutable());
        $this->assertNotNull($fresh->reopened_at);
        $this->assertEquals($this->user->id, $fresh->reopened_by);
        $this->assertEquals('بازگشایی جهت اعمال تعدیلات مالیاتی مصوب هیات مدیره', $fresh->reopen_reason);
    }

    public function test_period_checklist_endpoint_returns_json_checklist(): void
    {
        $this->user->givePermissionTo('companies.period-checklist');
        $this->createSampleApprovedDocument();

        $response = $this->getJson(route('companies.period-checklist', $this->company));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'company_id',
            'period_status',
            'checklist' => [
                'can_close',
                'blockers_count',
                'warnings_count',
                'tax_readiness_score',
                'items',
            ],
        ]);
        $this->assertTrue($response->json('checklist.can_close'));
    }
}
