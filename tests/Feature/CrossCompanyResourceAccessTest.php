<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SettlementInstallment;
use App\Models\SettlementObligation;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase (continuation),
 * Workstream J (permission matrix) / U (cross-company adversarial attack).
 *
 * Found while building the permission matrix: a whole class of controllers
 * route-bind a child model (Payment, PaymentAllocation, SettlementObligation/
 * Installment, Comment, DocumentFile, Transaction, WorkSiteContract,
 * PayrollItem) that has no `company_id`/`FiscalYearScope` of its own, either
 * standalone or nested under an already-scoped parent WITHOUT cross-checking
 * that the child actually belongs to that parent. `RoleController` had
 * exactly this defect earlier in the phase (Workstream A-I) and was fixed;
 * this is the same defect class recurring across 7 more controllers,
 * discovered by deliberately building the resource/action/company-context
 * matrix rather than trusting that a generic permission was sufficient.
 *
 * Every test below proves a Company-A actor is rejected (404) when
 * targeting a Company-B resource by id.
 */
class CrossCompanyResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Creates a Company and runs $callback with it as the active company (many factories read getActiveCompany()). */
    private function withActiveCompany(Company $company, \Closure $callback)
    {
        $previous = config('active-company-id');
        config(['active-company-id' => $company->id]);

        try {
            return $callback();
        } finally {
            config(['active-company-id' => $previous]);
        }
    }

    private function actorInCompany(Company $company): User
    {
        $role = Role::firstOrCreate(['name' => 'Super-Admin']);

        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$company->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        return $user;
    }

    private function asCompany(Company $company, User $user)
    {
        return $this->actingAs($user)->withCookie('active-company-id', (string) $company->id);
    }

    public function test_payment_allocation_cannot_target_another_companys_payment(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $customerB = $this->withActiveCompany($companyB, fn () => Customer::factory()->create());
        $paymentB = Payment::create(['amount' => 1000, 'date' => now(), 'payer_id' => $customerB->id]);

        $customerA = $this->withActiveCompany($companyA, fn () => Customer::factory()->create());
        $invoiceA = Invoice::create([
            'company_id' => $companyA->id,
            'customer_id' => $customerA->id,
            'number' => 1001,
            'date' => now(),
            'invoice_type' => 1,
            'status' => 1,
            'amount' => 1000,
            'vat' => 0,
        ]);

        // Company A actor attempts to allocate Company B's payment to their
        // own invoice -- must be rejected before any allocation logic runs.
        $this->asCompany($companyA, $actorA)
            ->post(route('payments.allocations.store', $paymentB), [
                'invoice_id' => $invoiceA->id,
                'amount' => 500,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    /**
     * Distinct vector from the test above: this time the actor's OWN
     * payment is legitimately theirs, but the invoice_id in the request
     * BODY (not a route parameter) targets another company's invoice.
     * Invoice carries FiscalYearScope, so Invoice::findOrFail() in
     * PaymentAllocationController::allocate() should already reject this
     * structurally -- verified empirically per this mission's own rule of
     * never trusting a scope claim without a real HTTP-level test (the
     * getActiveCompany() cookie-spoofing finding is exactly why).
     */
    public function test_payment_allocation_cannot_target_another_companys_invoice(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $customerA = $this->withActiveCompany($companyA, fn () => Customer::factory()->create());
        $paymentA = $this->withActiveCompany($companyA, fn () => Payment::create(['amount' => 1000, 'date' => now(), 'payer_id' => $customerA->id]));

        $customerB = $this->withActiveCompany($companyB, fn () => Customer::factory()->create());
        $invoiceB = $this->withActiveCompany($companyB, fn () => Invoice::create([
            'customer_id' => $customerB->id,
            'number' => 2001,
            'date' => now(),
            'invoice_type' => 1,
            'status' => 1,
            'amount' => 1000,
            'vat' => 0,
        ]));

        $this->asCompany($companyA, $actorA)
            ->post(route('payments.allocations.store', $paymentA), [
                'invoice_id' => $invoiceB->id,
                'amount' => 500,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_payment_allocation_destroy_cannot_target_another_companys_payment(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $customerB = $this->withActiveCompany($companyB, fn () => Customer::factory()->create());
        $paymentB = Payment::create(['amount' => 1000, 'date' => now(), 'payer_id' => $customerB->id]);
        $allocationB = PaymentAllocation::create([
            'payment_id' => $paymentB->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => 0,
            'amount' => 100,
            'allocated_at' => now(),
        ]);

        $this->asCompany($companyA, $actorA)
            ->delete(route('payments.allocations.destroy', [$paymentB, $allocationB]))
            ->assertNotFound();
    }

    public function test_settlement_obligation_cannot_be_viewed_or_mutated_by_another_company(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $obligationB = SettlementObligation::create([
            'company_id' => $companyB->id,
            'source_type' => 'cheque',
            'source_id' => 1,
            'original_amount' => 1000,
        ]);

        $this->asCompany($companyA, $actorA)
            ->get(route('settlements.show', $obligationB))
            ->assertNotFound();

        $this->asCompany($companyA, $actorA)
            ->post(route('settlements.installments.store', $obligationB), [
                'installments' => [['due_date' => now()->addMonth()->toDateString(), 'amount' => 100]],
            ])
            ->assertNotFound();
    }

    public function test_settlement_installment_payment_cannot_target_another_companys_installment(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $obligationB = SettlementObligation::create([
            'company_id' => $companyB->id,
            'source_type' => 'cheque',
            'source_id' => 1,
            'original_amount' => 1000,
        ]);
        $installmentB = SettlementInstallment::create([
            'obligation_id' => $obligationB->id,
            'due_date' => now()->addMonth(),
            'amount' => 500,
        ]);

        $this->asCompany($companyA, $actorA)
            ->post(route('settlements.installments.record-payment', $installmentB), [
                'amount' => 100,
                'subject_id' => 1,
            ])
            ->assertNotFound();
    }

    public function test_comment_actions_cannot_target_another_companys_comment(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $customerA = $this->withActiveCompany($companyA, fn () => Customer::factory()->create());
        $customerB = $this->withActiveCompany($companyB, fn () => Customer::factory()->create());
        $commentB = Comment::create(['customer_id' => $customerB->id, 'content' => 'secret note', 'user_id' => $actorA->id]);

        $this->asCompany($companyA, $actorA)
            ->get(route('comments.edit', [$customerA, $commentB]))
            ->assertNotFound();

        $this->asCompany($companyA, $actorA)
            ->delete(route('comments.destroy', [$customerA, $commentB]))
            ->assertNotFound();

        $this->assertDatabaseHas('comments', ['id' => $commentB->id]);
    }

    public function test_document_file_actions_cannot_target_another_companys_file(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $documentA = $this->withActiveCompany($companyA, fn () => Document::factory()->create());
        $documentB = $this->withActiveCompany($companyB, fn () => Document::factory()->create());
        $fileB = DocumentFile::factory()->withDocument($documentB)->create();

        $this->asCompany($companyA, $actorA)
            ->get(route('documents.files.edit', [$documentA, $fileB]))
            ->assertNotFound();

        $this->asCompany($companyA, $actorA)
            ->delete(route('documents.files.destroy', [$documentA, $fileB]))
            ->assertNotFound();

        $this->assertDatabaseHas('document_files', ['id' => $fileB->id]);
    }

    public function test_transaction_cannot_be_viewed_by_another_company(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $documentB = $this->withActiveCompany($companyB, fn () => Document::factory()->create());
        $subjectB = Subject::create(['company_id' => $companyB->id, 'name' => 'Subject B', 'code' => '001', 'parent_id' => null]);
        $transactionB = Transaction::create([
            'document_id' => $documentB->id,
            'subject_id' => $subjectB->id,
            'value' => 1000,
            'desc' => 'secret transaction',
        ]);

        $this->asCompany($companyA, $actorA)
            ->get(route('transactions.show', $transactionB))
            ->assertNotFound();
    }

    /**
     * Found during the Epic Pack 2 audit of the (already-implemented, not
     * newly-built) payment-allocation/bank-reconciliation/ageing layer:
     * `BankStatementLine` has no `company_id`/`FiscalYearScope` of its own
     * (only reachable via `bank_account_id` -> `BankAccount.company_id`),
     * and `BankReconciliationController::match()`/`unmatch()` route-bind
     * `{line}` directly with no ownership check -- the same defect class as
     * the other 7 gaps this suite already covers, just not caught by the
     * original Workstream J pass since `transaction_id` here comes from the
     * REQUEST BODY, not a route parameter route-enumeration would flag.
     */
    public function test_bank_reconciliation_match_cannot_target_another_companys_line_or_transaction(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $bankB = \App\Models\Bank::create(['name' => 'Bank B', 'company_id' => $companyB->id]);
        $subjectB = Subject::create(['company_id' => $companyB->id, 'name' => 'Bank Subject B', 'code' => '900', 'parent_id' => null]);
        // BankAccount::$fillable does NOT include company_id -- passing it
        // via create() is silently dropped by mass-assignment protection,
        // and the model's creating() hook then falls back to
        // getActiveCompany() at whatever the ambient context happens to be.
        // Must wrap creation in the target company's active context instead.
        $accountB = $this->withActiveCompany($companyB, fn () => \App\Models\BankAccount::create(['bank_id' => $bankB->id, 'name' => 'Account B', 'number' => 'B-1', 'type' => 1, 'subject_id' => $subjectB->id]));
        $statementB = \App\Models\BankStatement::create(['bank_account_id' => $accountB->id, 'company_id' => $companyB->id, 'lines_count' => 1]);
        $lineB = \App\Models\BankStatementLine::create([
            'bank_statement_id' => $statementB->id,
            'bank_account_id' => $accountB->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'dedupe_hash' => hash('sha256', 'line-b'),
        ]);

        $documentA = $this->withActiveCompany($companyA, fn () => Document::factory()->create());
        $subjectA = Subject::create(['company_id' => $companyA->id, 'name' => 'Subject A', 'code' => '001', 'parent_id' => null]);
        $transactionA = Transaction::create([
            'document_id' => $documentA->id,
            'subject_id' => $subjectA->id,
            'value' => 1000,
            'desc' => 'Company A transaction',
        ]);

        // Company A actor tries to match Company B's statement line to
        // Company A's own transaction.
        $this->asCompany($companyA, $actorA)
            ->post(route('bank-reconciliation.match', $lineB), ['transaction_id' => $transactionA->id])
            ->assertNotFound();

        $this->assertDatabaseHas('bank_statement_lines', ['id' => $lineB->id, 'reconciled_transaction_id' => null]);

        // Also try the reverse: Company A's own (unreconciled) line matched
        // against Company B's transaction.
        $bankA = \App\Models\Bank::create(['name' => 'Bank A', 'company_id' => $companyA->id]);
        $subjectAForAccount = Subject::create(['company_id' => $companyA->id, 'name' => 'Bank Subject A', 'code' => '901', 'parent_id' => null]);
        $accountA = $this->withActiveCompany($companyA, fn () => \App\Models\BankAccount::create(['bank_id' => $bankA->id, 'name' => 'Account A', 'number' => 'A-1', 'type' => 1, 'subject_id' => $subjectAForAccount->id]));
        $statementA = \App\Models\BankStatement::create(['bank_account_id' => $accountA->id, 'company_id' => $companyA->id, 'lines_count' => 1]);
        $lineA = \App\Models\BankStatementLine::create([
            'bank_statement_id' => $statementA->id,
            'bank_account_id' => $accountA->id,
            'date' => now()->toDateString(),
            'amount' => 500,
            'dedupe_hash' => hash('sha256', 'line-a'),
        ]);
        $documentB = $this->withActiveCompany($companyB, fn () => Document::factory()->create());
        $transactionB = Transaction::create([
            'document_id' => $documentB->id,
            'subject_id' => $subjectB->id,
            'value' => 500,
            'desc' => 'Company B transaction',
        ]);

        $this->asCompany($companyA, $actorA)
            ->post(route('bank-reconciliation.match', $lineA), ['transaction_id' => $transactionB->id])
            ->assertNotFound();

        $this->assertDatabaseHas('bank_statement_lines', ['id' => $lineA->id, 'reconciled_transaction_id' => null]);
    }

    public function test_bank_reconciliation_unmatch_cannot_target_another_companys_line(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $bankB = \App\Models\Bank::create(['name' => 'Bank B', 'company_id' => $companyB->id]);
        $subjectB = Subject::create(['company_id' => $companyB->id, 'name' => 'Bank Subject B', 'code' => '900', 'parent_id' => null]);
        $accountB = $this->withActiveCompany($companyB, fn () => \App\Models\BankAccount::create(['bank_id' => $bankB->id, 'name' => 'Account B', 'number' => 'B-2', 'type' => 1, 'subject_id' => $subjectB->id]));
        $statementB = \App\Models\BankStatement::create(['bank_account_id' => $accountB->id, 'company_id' => $companyB->id, 'lines_count' => 1]);
        $documentB = $this->withActiveCompany($companyB, fn () => Document::factory()->create());
        $transactionB = Transaction::create(['document_id' => $documentB->id, 'subject_id' => $subjectB->id, 'value' => 1000, 'desc' => 'x']);
        $lineB = \App\Models\BankStatementLine::create([
            'bank_statement_id' => $statementB->id,
            'bank_account_id' => $accountB->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'dedupe_hash' => hash('sha256', 'line-b-2'),
            'reconciled_transaction_id' => $transactionB->id,
            'reconciled_at' => now(),
        ]);

        $this->asCompany($companyA, $actorA)
            ->post(route('bank-reconciliation.unmatch', $lineB))
            ->assertNotFound();

        $this->assertDatabaseHas('bank_statement_lines', ['id' => $lineB->id, 'reconciled_transaction_id' => $transactionB->id]);
    }

    public function test_work_site_contract_actions_cannot_target_another_companys_contract(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $workSiteB = WorkSite::create(['company_id' => $companyB->id, 'name' => 'Site B', 'code' => 'B1']);
        $contractB = WorkSiteContract::create(['work_site_id' => $workSiteB->id, 'name' => 'Contract B', 'code' => 'C1']);

        $this->asCompany($companyA, $actorA)
            ->get(route('salary.work-site-contracts.edit', $contractB))
            ->assertNotFound();

        $this->asCompany($companyA, $actorA)
            ->get(route('salary.work-site-contracts.index'))
            ->assertOk()
            ->assertDontSee('Contract B');

        $this->asCompany($companyA, $actorA)
            ->delete(route('salary.work-site-contracts.destroy', $contractB))
            ->assertNotFound();

        $this->assertDatabaseHas('work_site_contracts', ['id' => $contractB->id]);
    }

    public function test_payroll_item_actions_cannot_target_another_companys_item(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
        $payrollB = Payroll::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'year' => 1403,
            'month' => 1,
            'status' => 1,
        ]);
        $itemB = PayrollItem::create([
            'payroll_id' => $payrollB->id,
            'calculated_amount' => 1000,
        ]);

        $this->asCompany($companyA, $actorA)
            ->get(route('salary.payroll-items.edit', $itemB))
            ->assertNotFound();

        $this->asCompany($companyA, $actorA)
            ->put(route('salary.payroll-items.update', $itemB), ['calculated_amount' => 1])
            ->assertNotFound();

        $this->assertDatabaseHas('payroll_items', ['id' => $itemB->id, 'calculated_amount' => 1000]);
    }
}
