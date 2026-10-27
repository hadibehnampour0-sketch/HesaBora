<?php

namespace Tests\Feature;

use App\Enums\PeriodStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentReversalService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentReversalAndCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Subject $bankSubject;

    private Subject $customerSubject;

    private DocumentReversalService $reversalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
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

        $this->reversalService = new DocumentReversalService;

        // Create standard permissions and roles
        Permission::firstOrCreate(['name' => 'documents.show']);
        Permission::firstOrCreate(['name' => 'documents.edit']);
        Permission::firstOrCreate(['name' => 'documents.reverse']);
        Permission::firstOrCreate(['name' => 'documents.correct']);
        Permission::firstOrCreate(['name' => 'documents.reversal-preview']);
        Permission::firstOrCreate(['name' => 'fiscal-year.closed-period-override']);
    }

    private function createSampleApprovedDocument(float $amount = 500000.00): Document
    {
        $data = [
            'title' => 'سند واریز وجه مشتری',
            'date' => now()->format('Y-m-d'),
            'company_id' => $this->company->id,
            'approved_at' => now()->format('Y-m-d'),
            'approver_id' => $this->user->id,
        ];

        $transactions = [
            [
                'subject_id' => $this->bankSubject->id,
                'value' => $amount,
                'desc' => 'واریز به حساب جاری بانک',
                'user_id' => $this->user->id,
            ],
            [
                'subject_id' => $this->customerSubject->id,
                'value' => -$amount,
                'desc' => 'بستانکاری بابت دریافت وجه',
                'user_id' => $this->user->id,
            ],
        ];

        return DocumentService::createDocument($this->user, $data, $transactions);
    }

    public function test_reversal_creates_balanced_document_and_negates_original_ledger_effects(): void
    {
        $amount = 750000.00;
        $original = $this->createSampleApprovedDocument($amount);

        $this->assertTrue($original->canBeReversed());
        $this->assertFalse($original->isReversed());

        $reversalDoc = $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            'اشتباه در انتخاب حساب بانکی توسط کارمند'
        );

        // 1. Reversal Document Invariants
        $this->assertNotNull($reversalDoc);
        $this->assertEquals($original->id, $reversalDoc->reverses_document_id);
        $this->assertNotNull($reversalDoc->approved_at);
        $this->assertEquals($this->user->id, $reversalDoc->approver_id);
        $this->assertTrue($reversalDoc->isReversal());

        // 2. Strict Double-Entry Balance: SUM(transactions.value) == 0
        $this->assertEquals(0.00, (float) $original->transactions()->sum('value'));
        $this->assertEquals(0.00, (float) $reversalDoc->transactions()->sum('value'));

        // 3. Mathematical Negation: Net effect on every involved account is zero
        $bankOriginal = (float) $original->transactions()->where('subject_id', $this->bankSubject->id)->value('value');
        $bankReversal = (float) $reversalDoc->transactions()->where('subject_id', $this->bankSubject->id)->value('value');
        $this->assertEquals($amount, $bankOriginal);
        $this->assertEquals(-$amount, $bankReversal);
        $this->assertEquals(0.00, $bankOriginal + $bankReversal);

        $customerOriginal = (float) $original->transactions()->where('subject_id', $this->customerSubject->id)->value('value');
        $customerReversal = (float) $reversalDoc->transactions()->where('subject_id', $this->customerSubject->id)->value('value');
        $this->assertEquals(-$amount, $customerOriginal);
        $this->assertEquals($amount, $customerReversal);
        $this->assertEquals(0.00, $customerOriginal + $customerReversal);

        // 4. Original Document Audit Trail Updated
        $freshOriginal = $original->fresh();
        $this->assertTrue($freshOriginal->isReversed());
        $this->assertNotNull($freshOriginal->reversed_at);
        $this->assertEquals($this->user->id, $freshOriginal->reversed_by_id);
        $this->assertEquals(DocumentReversalService::REASON_CLERICAL_ERROR, $freshOriginal->reversal_reason_code);
        $this->assertEquals('اشتباه در انتخاب حساب بانکی توسط کارمند', $freshOriginal->reversal_reason);
        $this->assertFalse($freshOriginal->canBeReversed());
    }

    public function test_double_reversal_is_strictly_prevented(): void
    {
        $original = $this->createSampleApprovedDocument();

        // First reversal succeeds
        $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_DUPLICATE_ENTRY,
            'ثبت سند تکراری'
        );

        // Second reversal must throw validation exception
        $this->expectException(ValidationException::class);
        $this->reversalService->reverseDocument(
            $original->fresh(),
            $this->user,
            DocumentReversalService::REASON_DUPLICATE_ENTRY,
            'تلاش برای ابطال مجدد'
        );
    }

    public function test_reversal_of_draft_document_is_rejected(): void
    {
        // Create unapproved draft document
        $data = [
            'title' => 'پیش‌نویس سند',
            'date' => now()->format('Y-m-d'),
            'company_id' => $this->company->id,
            'approved_at' => null,
            'approver_id' => null,
        ];
        $transactions = [
            ['subject_id' => $this->bankSubject->id, 'value' => 10000.00, 'user_id' => $this->user->id],
            ['subject_id' => $this->customerSubject->id, 'value' => -10000.00, 'user_id' => $this->user->id],
        ];
        $draft = DocumentService::createDocument($this->user, $data, $transactions);

        $this->assertFalse($draft->canBeReversed());

        $this->expectException(ValidationException::class);
        $this->reversalService->reverseDocument(
            $draft,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            'ابطال پیش‌نویس'
        );
    }

    public function test_reversing_a_reversal_document_is_rejected(): void
    {
        $original = $this->createSampleApprovedDocument();
        $reversalDoc = $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_WRONG_ACCOUNT
        );

        $this->assertTrue($reversalDoc->isReversal());
        $this->assertFalse($reversalDoc->canBeReversed());

        $this->expectException(ValidationException::class);
        $this->reversalService->reverseDocument(
            $reversalDoc,
            $this->user,
            DocumentReversalService::REASON_OTHER,
            'ابطال سند معکوس'
        );
    }

    public function test_correct_document_flow_reverses_original_and_creates_unapproved_draft(): void
    {
        $original = $this->createSampleApprovedDocument(800000.00);

        $result = $this->reversalService->correctDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_INCORRECT_AMOUNT,
            'مبلغ اصلاحی باید 900 هزار تومان باشد'
        );

        $this->assertArrayHasKey('reversal', $result);
        $this->assertArrayHasKey('draft', $result);

        $reversal = $result['reversal'];
        $draft = $result['draft'];

        // 1. Original is reversed
        $this->assertTrue($original->fresh()->isReversed());
        $this->assertEquals($original->id, $reversal->reverses_document_id);

        // 2. Draft is created with matching lines but unapproved status for clerk editing
        $this->assertNull($draft->approved_at);
        $this->assertNull($draft->approver_id);
        $this->assertStringContainsString('اصلاحیه سند شماره', $draft->title);
        $this->assertEquals(2, $draft->transactions()->count());

        $draftBankVal = (float) $draft->transactions()->where('subject_id', $this->bankSubject->id)->value('value');
        $this->assertEquals(800000.00, $draftBankVal);
    }

    public function test_controller_reversal_requires_documents_reverse_permission(): void
    {
        $original = $this->createSampleApprovedDocument();

        // 1. User without documents.reverse gets 403 Forbidden
        $unauthorizedUser = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$unauthorizedUser->id]);
        $this->actingAs($unauthorizedUser);

        $response = $this->post(route('documents.reverse', $original), [
            'reversal_reason_code' => DocumentReversalService::REASON_CLERICAL_ERROR,
            'reversal_reason' => 'آزمون دسترسی',
        ]);
        $response->assertStatus(403);

        // 2. Grant permission and verify success
        $unauthorizedUser->givePermissionTo('documents.reverse');
        $response = $this->post(route('documents.reverse', $original), [
            'reversal_reason_code' => DocumentReversalService::REASON_CLERICAL_ERROR,
            'reversal_reason' => 'ابطال مجاز',
        ]);
        $response->assertRedirect(route('documents.show', $original));
        $this->assertTrue($original->fresh()->isReversed());
    }

    public function test_controller_correct_requires_documents_correct_permission(): void
    {
        $original = $this->createSampleApprovedDocument();

        $sellerUser = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$sellerUser->id]);
        $this->actingAs($sellerUser);

        // Without documents.correct -> 403
        $response = $this->post(route('documents.correct', $original), [
            'reversal_reason_code' => DocumentReversalService::REASON_INCORRECT_AMOUNT,
        ]);
        $response->assertStatus(403);

        // With documents.correct -> succeeds and redirects to draft edit page
        $sellerUser->givePermissionTo(['documents.reverse', 'documents.correct']);
        $response = $this->post(route('documents.correct', $original), [
            'reversal_reason_code' => DocumentReversalService::REASON_INCORRECT_AMOUNT,
            'reversal_reason' => 'اصلاح ارقام سند',
        ]);

        $this->assertTrue($original->fresh()->isReversed());
        $response->assertRedirect();
        $this->assertStringContainsString('/edit', $response->headers->get('Location'));
    }

    public function test_cross_company_reversal_attack_is_prevented(): void
    {
        $original = $this->createSampleApprovedDocument();

        // Company B and User B
        $companyB = Company::factory()->create();
        $userB = User::factory()->create();
        $companyB->users()->syncWithoutDetaching([$userB->id]);
        $userB->givePermissionTo(['documents.reverse']);

        $this->actingAs($userB);
        session(['active_company_id' => $companyB->id]);
        config(['active-company-id' => $companyB->id]);

        $response = $this->post(route('documents.reverse', $original), [
            'reversal_reason_code' => DocumentReversalService::REASON_CLERICAL_ERROR,
        ]);

        // Scoped out by FiscalYearScope/company scope -> 404
        $response->assertStatus(404);
        $this->assertFalse($original->fresh()->isReversed());
    }

    public function test_inspect_dependencies_reports_accurate_preconditions_and_preview(): void
    {
        $original = $this->createSampleApprovedDocument(320000.00);

        $inspection = $this->reversalService->inspectDependencies($original);

        $this->assertTrue($inspection['can_reverse']);
        $this->assertEmpty($inspection['reasons']);
        $this->assertEquals(2, $inspection['dependencies']['transaction_count']);
        $this->assertEquals(320000.00, $inspection['dependencies']['total_debit']);
        $this->assertEquals(320000.00, $inspection['dependencies']['total_credit']);
        $this->assertCount(2, $inspection['preview_transactions']);

        // First transaction preview should be -320000
        $this->assertEquals(-320000.00, $inspection['preview_transactions'][0]['reversal_value']);
        // Second transaction preview should be +320000
        $this->assertEquals(320000.00, $inspection['preview_transactions'][1]['reversal_value']);
    }

    public function test_reversal_in_closed_period_is_rejected_without_override_permission(): void
    {
        $original = $this->createSampleApprovedDocument(100000.00);

        // Close the period
        $this->company->update(['period_status' => PeriodStatus::CLOSED]);

        $this->expectException(ValidationException::class);
        $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            'دلیل اصلاح'
        );
    }

    public function test_reversal_in_closed_period_is_rejected_without_mandatory_reason(): void
    {
        $original = $this->createSampleApprovedDocument(100000.00);
        $this->company->update(['period_status' => PeriodStatus::CLOSED]);
        $this->user->givePermissionTo('fiscal-year.closed-period-override');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ابطال سند در دوره مالی بسته شده مستلزم درج توضیحات و دلیل تفصیلی اجباری است.');

        $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            '' // empty explanation
        );
    }

    public function test_reversal_in_closed_period_succeeds_with_override_and_mandatory_reason_and_logs_audit(): void
    {
        $original = $this->createSampleApprovedDocument(100000.00);
        $this->company->update(['period_status' => PeriodStatus::CLOSED]);
        $this->user->givePermissionTo('fiscal-year.closed-period-override');

        $reversal = $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            'اصلاح اشتباه ثبتی در دوره مالی با تایید مدیر مالی'
        );

        $this->assertNotNull($reversal);
        $this->assertTrue($original->fresh()->isReversed());
        $this->assertSame('اصلاح اشتباه ثبتی در دوره مالی با تایید مدیر مالی', $original->fresh()->reversal_reason);
    }

    public function test_reversal_in_locked_period_is_unconditionally_blocked_even_with_override(): void
    {
        $original = $this->createSampleApprovedDocument(100000.00);
        $this->company->update(['period_status' => PeriodStatus::LOCKED]);
        $this->user->givePermissionTo('fiscal-year.closed-period-override');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('قفل قطعی (Locked)');

        $this->reversalService->reverseDocument(
            $original,
            $this->user,
            DocumentReversalService::REASON_CLERICAL_ERROR,
            'توضیحات اضطراری'
        );
    }
}
