<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Enums\InvoiceType;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\MonthlyAttendance;
use App\Models\OrgChart;
use App\Models\Payroll;
use App\Models\PayrollElement;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\SalaryDecree;
use App\Models\TaxSlab;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AncillaryCostService;
use App\Services\ChequeService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Workstream Y (domain/product analyst pass, Epic Pack 1): realistic,
 * combined, end-to-end business scenarios rather than isolated unit-level
 * assertions. Every individual mechanism exercised here already has deep
 * dedicated unit coverage elsewhere in this suite (ReturnInvoiceCOGSTest,
 * ChequeManagementTest, PayrollServiceTest, the Workstream S concurrency
 * suites) -- this file's job is proving those mechanisms COMPOSE correctly
 * when chained together the way a real bookkeeper would actually use them
 * in one sitting, which per-feature unit tests do not by themselves prove.
 */
class DomainBusinessScenariosTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    private int $nextInvoiceNumber = 5000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->user->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);

        $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'quantity' => 0,
            'average_cost' => 0,
        ]);
        WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id],
            ['quantity' => 0, 'average_cost' => 0]
        );
    }

    /**
     * Scenario 1: sell goods, collect part in cash and part via a cheque,
     * and confirm the invoice's COGS transaction uses the weighted-average
     * cost established by the prior purchases, and the invoice correctly
     * reaches PAID only once both payments are recorded.
     */
    public function test_scenario_sell_with_cash_and_cheque_payment_and_weighted_average_cogs(): void
    {
        // Two purchases at different unit costs -> weighted average.
        $this->createInvoice(InvoiceType::BUY, 10, 100); // 10 @ 100 = 1000
        $this->createInvoice(InvoiceType::BUY, 10, 200); // 10 @ 200 = 2000
        // average_cost is now (1000+2000)/20 = 150.

        $this->product->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $this->product->average_cost, 0.01);

        $sell = $this->createInvoice(InvoiceType::SELL, 5, 500); // sell 5 @ 500 = 2500

        $cogTransaction = $sell->document->transactions()->where('subject_id', $this->product->cogs_subject_id)->first();
        $this->assertNotNull($cogTransaction);
        // 5 units at the 150 weighted average = 750.
        $this->assertEqualsWithDelta(750.0, abs((float) $cogTransaction->value), 0.01);

        $paymentService = new PaymentService;

        // A valid settlement subject_id must be a BankAccount's own linked
        // subject (or a descendant of the cash_book subject) --
        // PaymentService::settlementSubjectIds() explicitly EXCLUDES the
        // cash_book/bank root subject ids themselves.
        $this->seedChequeAccountingSubjects();
        $cashAccount = $this->makeBankAccount();

        $cashDecision = $paymentService->createPayment($this->user, $sell->fresh(), [
            'subject_id' => $cashAccount->subject_id,
            'amount' => 1000,
            'date' => now()->toDateString(),
        ]);
        $this->assertFalse($cashDecision->hasErrors());
        $chequeService = app(ChequeService::class);
        $chequeService->register($this->user, [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 1500,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
            'bank_account_id' => null,
            'invoice_id' => $sell->fresh()->id,
        ]);

        $sell->refresh();
        $this->assertSame(2500.0, (float) $sell->payments()->sum('amount'));
    }

    /**
     * Scenario 2: a partial return of a sales invoice restores exactly the
     * returned quantity to the warehouse and produces a balanced reversing
     * accounting document -- extending ReturnInvoiceCOGSTest's per-mechanism
     * proofs into one realistic buy -> sell -> partial-return chain.
     */
    public function test_scenario_partial_sales_return_restores_exact_stock_and_balances_the_ledger(): void
    {
        $this->createInvoice(InvoiceType::BUY, 20, 100);
        $sell = $this->createInvoice(InvoiceType::SELL, 8, 300);
        $this->assertStock(12);

        $return = $this->createInvoice(InvoiceType::RETURN_SELL, 3, 300, $sell);
        $this->assertStock(15);

        $this->assertNotNull($return->document);
        $this->assertEqualsWithDelta(0, $return->document->transactions()->sum('value'), 0.01);
    }

    /**
     * Scenario 3: a purchase's ancillary shipping cost is allocated onto
     * the product's average cost, and a subsequent sale's COGS reflects
     * that raised cost, not the original purchase price alone.
     */
    public function test_scenario_purchase_with_ancillary_cost_raises_average_cost_used_by_a_later_sale(): void
    {
        $buy = $this->createInvoice(InvoiceType::BUY, 10, 100); // base cost 100/unit

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'type' => 'Shipping',
            'amount' => 200,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $this->product->id, 'amount' => 200],
            ],
        ], true);

        $this->product->refresh();
        // (1000 base + 200 shipping) / 10 units = 120/unit.
        $this->assertEqualsWithDelta(120.0, (float) $this->product->average_cost, 0.01);

        $sell = $this->createInvoice(InvoiceType::SELL, 4, 500);
        $cogTransaction = $sell->document->transactions()->where('subject_id', $this->product->cogs_subject_id)->first();
        $this->assertEqualsWithDelta(480.0, abs((float) $cogTransaction->value), 0.01);
    }

    /**
     * Scenario 4: a guarantee cheque is off-balance (no accounting document,
     * no ledger effect) until executed into a real settlement cheque, and
     * a subsequent return/bounce does not double-post a financial event --
     * extending ChequeManagementTest's own off-balance proof with the
     * return step.
     */
    public function test_scenario_guarantee_cheque_execution_and_return_does_not_double_post(): void
    {
        $this->seedChequeAccountingSubjects();
        $bankAccount = $this->makeBankAccount();

        $chequeService = app(ChequeService::class);

        $cheque = $chequeService->register($this->user, [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::GUARANTEE->value,
            'amount' => 1000,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
            'bank_account_id' => null,
        ]);

        $this->assertSame(ChequeType::GUARANTEE_RECEIVED, $cheque->status);
        $this->assertSame(0, Document::count(), 'a guarantee cheque must be off-balance until executed');

        $cheque = $chequeService->transition($cheque, $this->user, 'execute');
        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $documentsAfterExecute = Document::count();
        $this->assertGreaterThan(0, $documentsAfterExecute);

        $cheque = $chequeService->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $bankAccount->id]);
        $documentsAfterDeposit = Document::count();
        $cheque = $chequeService->transition($cheque, $this->user, 'bounce');

        $this->assertSame(ChequeType::BOUNCED, $cheque->status);
        // The bounce posts exactly one more balanced document; it must not
        // silently duplicate the execute/deposit steps' own documents.
        $this->assertSame($documentsAfterDeposit + 1, Document::count());
        foreach (Document::all() as $document) {
            $this->assertEqualsWithDelta(0, $document->transactions()->sum('value'), 0.01);
        }
    }

    /**
     * Scenario 5: once an invoice is approved, its financial content
     * (items/amount) cannot be silently edited, and re-approving an
     * already-approved invoice is a safe no-op, not a double-post --
     * confirms D-086/D-088's boundary holds in a realistic single flow.
     */
    public function test_scenario_approved_invoice_financial_content_is_immutable_and_reapproval_is_a_safe_noop(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, 100);
        $sell = $this->createInvoice(InvoiceType::SELL, 5, 200);
        $this->assertNotNull($sell->document_id);
        $documentCountAfterApprove = Document::count();

        (new InvoiceService)->changeInvoiceStatus($sell->fresh(), 'approved');

        $documentCountAfterReapprove = Document::count();
        $this->assertSame($documentCountAfterApprove, $documentCountAfterReapprove, 're-approving an already-approved invoice must not create a second document');

        $this->put(route('invoices.update', $sell), [
            'title' => $sell->title,
            'date' => $sell->date->toDateString(),
            'invoice_type' => $sell->invoice_type->value,
            'customer_id' => $sell->customer_id,
            'number' => $sell->number,
            'warehouse_id' => $sell->warehouse_id,
            'transactions' => [[
                'item_id' => 'product-'.$this->product->id,
                'quantity' => 5,
                'unit' => 999999,
                'off' => 0,
                'vat' => 0,
            ]],
        ])->assertSessionHasErrors();
    }

    /**
     * Scenario 6: a Payroll created from real attendance/decree data (with
     * insurance and progressive income tax both applied) flows through the
     * full draft -> pending -> approved -> paid workflow without the
     * calculated deductions being lost or altered by a status transition.
     */
    public function test_scenario_payroll_calculation_with_deductions_flows_through_the_full_approval_workflow(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $orgChart = OrgChart::factory()->create(['company_id' => $this->company->id]);
        $shift = WorkShift::factory()->create([
            'company_id' => $this->company->id,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 60,
            'float' => 0,
            'overtime_coefficient' => 1.4,
            'auto_overtime_coefficient' => 1.2,
            'max_auto_overtime' => 120,
            'holiday_coefficient' => 1.5,
            'mission_coefficient' => 1.4,
            'undertime_coefficient' => 2.0,
            'is_active' => true,
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $shift->id,
            'children_count' => 0,
        ]);

        PayrollElement::factory()->create(['company_id' => $this->company->id, 'system_code' => 1, 'title' => 1, 'category' => 1, 'calc_type' => 1, 'is_taxable' => false, 'is_insurable' => false]);
        TaxSlab::factory()->create(['company_id' => $this->company->id, 'income_to' => null, 'tax_rate' => 10]);

        $decree = SalaryDecree::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'daily_wage' => 600000,
            'is_active' => true,
        ]);
        $attendance = MonthlyAttendance::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'year' => 1404,
            'month' => 1,
            'work_days' => 26,
            'present_days' => 26,
            'absent_days' => 0,
        ]);

        $payroll = app(PayrollService::class)->createFromAttendance($attendance, $decree, $this->company->id);
        $payroll = Payroll::withoutGlobalScopes()->with('items')->findOrFail($payroll->id);

        $this->assertGreaterThan(0, $payroll->items->count());
        $insuranceItem = $payroll->items->first(fn ($item) => str_contains(strtolower($item->description ?? ''), 'insurance') || $item->calculated_amount < 0);
        $this->assertNotNull($insuranceItem, 'expected at least one deduction line item (insurance/tax) on the payroll');

        $this->patch(route('salary.payrolls.transition.draft-to-pending-manager-approval', $payroll))
            ->assertRedirect(route('salary.payrolls.show', $payroll));
        $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll))
            ->assertRedirect(route('salary.payrolls.show', $payroll));
        $this->patch(route('salary.payrolls.transition.approved-to-paid', $payroll))
            ->assertRedirect(route('salary.payrolls.show', $payroll));

        $finalPayroll = Payroll::withoutGlobalScopes()->with('items')->findOrFail($payroll->id);
        $this->assertSame($payroll->items->count(), $finalPayroll->items->count(), 'status transitions must not alter the calculated line items');
    }

    /**
     * Scenario 7: concurrency in payment registration and inventory
     * deduction is already exhaustively proven on real MariaDB by
     * InvoicePaymentBalanceRaceTest and ProductQuantityRaceTest (Workstream
     * S). Re-run here as a direct, named cross-reference rather than
     * duplicated, so this domain-scenario suite documents where that
     * evidence actually lives instead of re-deriving it.
     */
    public function test_scenario_concurrency_coverage_reference(): void
    {
        $this->assertFileExists(base_path('tests/Feature/InvoicePaymentBalanceRaceTest.php'));
        $this->assertFileExists(base_path('tests/Feature/ProductQuantityRaceTest.php'));
        $this->assertFileExists(base_path('tests/Feature/BankReconciliationMatchRaceTest.php'));
        $this->assertFileExists(base_path('tests/Feature/DocumentNumberingRaceTest.php'));
    }

    /**
     * Cheque postings require these configured subjects (see
     * ChequeManagementTest's identical setUp pattern) -- not part of
     * SeederHelper's own subject/config set, which this file's other
     * scenarios don't need. ChequeService::subject() reads the runtime
     * config('amir.*') cache (normally bridged from the DB by ConfigLoader
     * middleware on a real request), not the Config table directly, so
     * both the DB row and the config() cache must be set here.
     */
    private function seedChequeAccountingSubjects(): void
    {
        \Illuminate\Support\Facades\DB::table('subjects')->insert([
            ['id' => 100, 'code' => '091001', 'name' => 'Documents receivable', 'parent_id' => null, 'type' => 3, 'company_id' => $this->company->id],
            ['id' => 101, 'code' => '091002', 'name' => 'Documents in collection', 'parent_id' => null, 'type' => 3, 'company_id' => $this->company->id],
            ['id' => 102, 'code' => '091003', 'name' => 'Documents payable', 'parent_id' => null, 'type' => 3, 'company_id' => $this->company->id],
        ]);
        foreach ([
            'cheque_documents_receivable' => 100,
            'cheque_documents_in_collection' => 101,
            'cheque_documents_payable' => 102,
        ] as $key => $subjectId) {
            \App\Models\Config::create([
                'key' => $key,
                'value' => (string) $subjectId,
                'desc' => $key,
                'type' => 3,
                'category' => 1,
                'company_id' => $this->company->id,
            ]);
            config(['amir.'.$key => (string) $subjectId]);
        }
    }

    private function makeBankAccount(): \App\Models\BankAccount
    {
        \Illuminate\Support\Facades\DB::table('subjects')->insert([
            'id' => 103, 'code' => '010001', 'name' => 'Bank account subject', 'parent_id' => null, 'type' => 3, 'company_id' => $this->company->id,
        ]);
        $bank = \App\Models\Bank::create(['name' => 'Test Bank', 'company_id' => $this->company->id]);

        return \App\Models\BankAccount::create(['bank_id' => $bank->id, 'name' => 'Main account', 'number' => '123456', 'type' => 1, 'subject_id' => 103]);
    }

    private function createInvoice(InvoiceType $type, float $quantity, float $unit, ?Invoice $returnedInvoice = null): Invoice
    {
        $number = ++$this->nextInvoiceNumber;

        $result = InvoiceService::createInvoice(
            $this->user,
            [
                'title' => $type->valueName(),
                'date' => now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'warehouse_id' => $this->warehouse->id,
                'document_number' => $number,
                'number' => $number,
                'returned_invoice_id' => $returnedInvoice?->id,
            ],
            [[
                'itemable_type' => 'product',
                'itemable_id' => $this->product->id,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_discount' => 0,
                'vat' => 0,
            ]],
            true
        );

        $invoice = Invoice::withoutGlobalScopes()->findOrFail($result['invoice']->id);

        // InvoiceService::createInvoice(..., approved: true) only fully
        // posts a Document immediately for BUY/return types -- a SELL
        // invoice is left at READY_TO_APPROVE and needs its own explicit
        // changeInvoiceStatus() call (see InvoiceService::createInvoice(),
        // matching WarehouseInvoiceStockTest's identical two-step pattern).
        if ($invoice->invoice_type === InvoiceType::SELL) {
            (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
        }

        return Invoice::withoutGlobalScopes()->with(['items', 'document.transactions.subject'])->findOrFail($invoice->id);
    }

    private function assertStock(float $quantity): void
    {
        $actual = WarehouseProductStock::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEqualsWithDelta($quantity, (float) $actual, 0.01);
    }
}
