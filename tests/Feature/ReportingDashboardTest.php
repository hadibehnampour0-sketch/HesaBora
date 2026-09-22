<?php

namespace Tests\Feature;

use App\Enums\AnalyticDimensionType;
use App\Enums\ChequeType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\AnalyticDimensionService;
use App\Services\CashFlowReportService;
use App\Services\ChequeRiskDashboardService;
use App\Services\ChequeService;
use App\Services\PaymentService;
use App\Services\ProfitabilityByDimensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P5.1/P5.2/P5.4 (Reporting 2.0), Master Phase 2. P5.1's CashFlowReportService
 * and P5.4's ProfitabilityByDimensionService both proactively apply
 * guardrail #36's lesson (Transaction/WarehouseProductStock have no
 * FiscalYearScope) before it could ever surface as a bug -- these tests
 * verify that discipline held.
 */
class ReportingDashboardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected CashFlowReportService $cashFlowService;

    protected ChequeRiskDashboardService $chequeRiskService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $this->seedChequeConfigs($this->company->id);

        $this->cashFlowService = app(CashFlowReportService::class);
        $this->chequeRiskService = app(ChequeRiskDashboardService::class);
    }

    private function seedChequeConfigs(int $companyId): void
    {
        foreach (['cheque_documents_receivable', 'cheque_documents_in_collection', 'cheque_documents_payable'] as $key) {
            $subject = \App\Models\Subject::factory()->create(['company_id' => $companyId]);
            config(['amir.'.$key => (string) $subject->id]);
        }
    }

    private function cashSubjectId(): int
    {
        $cashBook = \App\Models\Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));

        return \App\Models\Subject::factory()->withParent($cashBook)->create(['name' => 'Cash Box', 'company_id' => $this->company->id])->id;
    }

    // --- P5.1 Cash Flow ---------------------------------------------------

    public function test_a_cash_receipt_appears_as_inflow_for_its_period(): void
    {
        $payment = app(PaymentService::class)->createCreditPayment($this->user, $this->customer, [
            'amount' => 5000,
            'subject_id' => $this->cashSubjectId(),
            'date' => '2026-06-15',
        ]);

        $report = $this->cashFlowService->report('2026-06-01', '2026-06-30');

        $this->assertEqualsWithDelta(5000.0, $report['total_inflow'], 0.01);
        $this->assertEqualsWithDelta(0.0, $report['total_outflow'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $report['net'], 0.01);
        $this->assertCount(1, $report['periods']);
    }

    public function test_cash_flow_report_only_includes_transactions_within_the_requested_date_range(): void
    {
        app(PaymentService::class)->createCreditPayment($this->user, $this->customer, [
            'amount' => 1000, 'subject_id' => $this->cashSubjectId(), 'date' => '2026-01-01',
        ]);

        $report = $this->cashFlowService->report('2026-06-01', '2026-06-30');

        $this->assertEqualsWithDelta(0.0, $report['total_inflow'], 0.01);
    }

    public function test_cash_flow_drill_down_returns_the_underlying_transactions(): void
    {
        app(PaymentService::class)->createCreditPayment($this->user, $this->customer, [
            'amount' => 2500, 'subject_id' => $this->cashSubjectId(), 'date' => '2026-06-15',
        ]);

        $transactions = $this->cashFlowService->transactionsForPeriod('2026-06-01', '2026-06-30');

        $this->assertGreaterThanOrEqual(1, $transactions->count());
    }

    public function test_cash_flow_report_never_includes_another_companys_transactions(): void
    {
        // SeederHelper::importSubjects()/importConfigs() upsert by a FIXED
        // subject id -- calling them for a second company would silently
        // STEAL company A's own subject/config rows (their company_id
        // would be overwritten). Build company B's minimal cash subject
        // by hand instead of re-calling those helpers.
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $previous = config('active-company-id');
        $previousCashBook = config('amir.cash_book');
        config(['active-company-id' => $companyB->id]);
        $groupB = CustomerGroup::factory()->withSubject()->create(['company_id' => $companyB->id]);
        $customerB = Customer::factory()->withGroup($groupB)->withSubject()->create(['company_id' => $companyB->id]);
        $cashRootB = \App\Models\Subject::create(['company_id' => $companyB->id, 'name' => 'Cash Root B', 'code' => 'CASH-ROOT-B', 'parent_id' => null]);
        $cashSubjectB = \App\Models\Subject::create(['company_id' => $companyB->id, 'name' => 'Cash B', 'code' => 'CASH-B', 'parent_id' => $cashRootB->id])->id;
        config(['amir.cash_book' => $cashRootB->id]);
        app(PaymentService::class)->createCreditPayment($this->user, $customerB, [
            'amount' => 999999, 'subject_id' => $cashSubjectB, 'date' => '2026-06-15',
        ]);
        config(['active-company-id' => $previous, 'amir.cash_book' => $previousCashBook]);

        app(PaymentService::class)->createCreditPayment($this->user, $this->customer, [
            'amount' => 1000, 'subject_id' => $this->cashSubjectId(), 'date' => '2026-06-15',
        ]);

        $report = $this->cashFlowService->report('2026-06-01', '2026-06-30');

        $this->assertEqualsWithDelta(1000.0, $report['total_inflow'], 0.01);
    }

    // --- P5.2 Cheque Risk ---------------------------------------------------

    private function registerCheque(string $dueDate, ChequeType $direction = ChequeType::RECEIVABLE): \App\Models\Cheque
    {
        $data = [
            'direction' => $direction->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 1000,
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
        ];

        if ($direction === ChequeType::PAYABLE) {
            $bank = \App\Models\Bank::create(['name' => 'Test Bank', 'company_id' => $this->company->id]);
            $subjectId = \App\Models\Subject::create(['company_id' => $this->company->id, 'name' => 'Bank Account Subject', 'code' => 'BANK-'.random_int(1000, 9999), 'parent_id' => null])->id;
            $account = \App\Models\BankAccount::create(['bank_id' => $bank->id, 'name' => 'Main', 'number' => (string) random_int(1000, 9999), 'type' => 1, 'subject_id' => $subjectId]);
            $data['bank_account_id'] = $account->id;
        }

        return app(ChequeService::class)->register($this->user, $data);
    }

    public function test_a_cheque_due_within_the_window_is_bucketed_as_due_soon(): void
    {
        $this->registerCheque(now()->addDays(3)->toDateString());

        $report = $this->chequeRiskService->report(7);

        $this->assertCount(1, $report['receivable']['due_soon']);
        $this->assertCount(0, $report['receivable']['overdue']);
    }

    public function test_a_cheque_past_its_due_date_and_not_yet_cleared_is_bucketed_as_overdue(): void
    {
        $this->registerCheque(now()->subDays(3)->toDateString());

        $report = $this->chequeRiskService->report(7);

        $this->assertCount(1, $report['receivable']['overdue']);
        $this->assertCount(0, $report['receivable']['due_soon']);
    }

    public function test_a_cheque_far_in_the_future_appears_in_neither_bucket(): void
    {
        $this->registerCheque(now()->addDays(60)->toDateString());

        $report = $this->chequeRiskService->report(7);

        $this->assertCount(0, $report['receivable']['due_soon']);
        $this->assertCount(0, $report['receivable']['overdue']);
    }

    public function test_payable_and_receivable_cheques_are_reported_separately(): void
    {
        $this->registerCheque(now()->addDays(3)->toDateString(), ChequeType::RECEIVABLE);
        $this->registerCheque(now()->addDays(3)->toDateString(), ChequeType::PAYABLE);

        $report = $this->chequeRiskService->report(7);

        $this->assertCount(1, $report['receivable']['due_soon']);
        $this->assertCount(1, $report['payable']['due_soon']);
    }

    public function test_cheque_risk_report_never_includes_another_companys_cheques(): void
    {
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $actorB = User::factory()->create();
        $actorB->companies()->syncWithoutDetaching([$companyB->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $actorB->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $this->importSubjects($companyB->id);
        $this->importConfigs($companyB->id);
        $this->seedChequeConfigs($companyB->id);
        $groupB = CustomerGroup::factory()->withSubject()->create(['company_id' => $companyB->id]);
        $customerB = Customer::factory()->withGroup($groupB)->withSubject()->create(['company_id' => $companyB->id]);
        app(ChequeService::class)->register($actorB, [
            'direction' => ChequeType::RECEIVABLE->value, 'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 999999, 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(3)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999), 'customer_id' => $customerB->id,
        ]);
        config(['active-company-id' => $previous]);
        // The 'amir.cheque_documents_*' config keys are global runtime
        // state, not company-namespaced -- re-seed company A's own values
        // after registering companyB's cheque overwrote them.
        $this->seedChequeConfigs($this->company->id);

        $this->registerCheque(now()->addDays(3)->toDateString());

        $report = $this->chequeRiskService->report(7);

        $this->assertCount(1, $report['receivable']['due_soon']);
        $this->assertEqualsWithDelta(1000.0, $report['receivable']['total_outstanding'], 0.01);
    }

    // --- P5.4 Profitability by Dimension -------------------------------------

    public function test_profitability_by_dimension_computes_revenue_minus_expense(): void
    {
        $dimensionService = app(AnalyticDimensionService::class);
        $dimension = $dimensionService->create(['type' => AnalyticDimensionType::PROJECT->value, 'code' => 'P-01', 'name' => 'Project X']);

        $document = \App\Models\Document::factory()->create();
        $revenueSubjectId = (int) config('amir.sales_revenue');
        $expenseSubjectId = (int) config('amir.cost');

        $revenueTxn = \App\Models\Transaction::create(['document_id' => $document->id, 'subject_id' => $revenueSubjectId, 'value' => 1000, 'desc' => 'revenue']);
        $expenseTxn = \App\Models\Transaction::create(['document_id' => $document->id, 'subject_id' => $expenseSubjectId, 'value' => -400, 'desc' => 'expense']);
        $dimensionService->syncOnTransaction($revenueTxn, [$dimension->id]);
        $dimensionService->syncOnTransaction($expenseTxn, [$dimension->id]);

        $profitabilityService = app(ProfitabilityByDimensionService::class);
        $figures = $profitabilityService->forDimension($dimension);

        $this->assertEqualsWithDelta(1000.0, $figures['revenue'], 0.01);
        $this->assertEqualsWithDelta(400.0, $figures['expense'], 0.01);
        $this->assertEqualsWithDelta(600.0, $figures['profit'], 0.01);
    }
}
