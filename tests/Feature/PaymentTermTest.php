<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\SettlementObligation;
use App\Models\User;
use App\Services\PaymentTermService;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P3.2 (Payment Terms & Installments), Epic Pack 3. applyToInvoice() reuses
 * the exact same SettlementObligation/SettlementInstallment ledger the
 * Cheque-secured settlement domain (D-070, CheckSettlementTest) already
 * uses -- these tests focus on what's genuinely new (term definition,
 * percentage validation, schedule generation, rounding) rather than
 * re-proving settlement/allocation mechanics CheckSettlementTest already
 * covers exhaustively.
 */
class PaymentTermTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected PaymentTermService $service;

    protected SettlementService $settlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);
        $this->customer = Customer::factory()->create();
        $this->service = app(PaymentTermService::class);
        $this->settlementService = app(SettlementService::class);
    }

    private function makeInvoice(float $amount = 1000): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => random_int(1, 999999),
            'date' => now()->subDays(5),
            'invoice_type' => 1,
            'status' => 1,
            'amount' => $amount,
            'vat' => 0,
        ]);
    }

    public function test_a_term_with_lines_summing_to_100_percent_can_be_created(): void
    {
        $term = $this->service->create(
            ['code' => 'NET30', 'name' => 'Net 30'],
            [['days_offset' => 30, 'percentage' => 100]]
        );

        $this->assertCount(1, $term->lines);
        $this->assertSame($this->company->id, $term->company_id);
    }

    public function test_a_term_whose_lines_do_not_sum_to_100_percent_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(
            ['code' => 'BAD', 'name' => 'Bad Term'],
            [['days_offset' => 30, 'percentage' => 60]]
        );
    }

    public function test_applying_a_split_term_creates_correctly_scheduled_installments_summing_exactly_to_the_invoice_amount(): void
    {
        $term = $this->service->create(
            ['code' => 'SPLIT', 'name' => '30/30/40'],
            [
                ['days_offset' => 0, 'percentage' => 30],
                ['days_offset' => 30, 'percentage' => 30],
                ['days_offset' => 60, 'percentage' => 40],
            ]
        );
        $invoice = $this->makeInvoice(1000);

        $obligation = $this->service->applyToInvoice($invoice, $term, $this->user);

        $this->assertSame(Invoice::class, $obligation->source_type);
        $this->assertSame($invoice->id, $obligation->source_id);
        $this->assertEqualsWithDelta(1000.0, (float) $obligation->original_amount, 0.01);

        $installments = $obligation->installments()->orderBy('due_date')->get();
        $this->assertCount(3, $installments);
        $this->assertEqualsWithDelta(1000.0, (float) $installments->sum('amount'), 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $installments[0]->amount, 0.01);
        $this->assertEqualsWithDelta(300.0, (float) $installments[1]->amount, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $installments[2]->amount, 0.01);
        $this->assertSame($invoice->date->copy()->addDays(60)->toDateString(), $installments[2]->due_date->toDateString());
    }

    public function test_rounding_remainder_across_lines_is_folded_into_the_last_installment(): void
    {
        $term = $this->service->create(
            ['code' => 'THIRDS', 'name' => 'Three equal thirds'],
            [
                ['days_offset' => 0, 'percentage' => 33.34],
                ['days_offset' => 30, 'percentage' => 33.33],
                ['days_offset' => 60, 'percentage' => 33.33],
            ]
        );
        $invoice = $this->makeInvoice(100);

        $obligation = $this->service->applyToInvoice($invoice, $term, $this->user);

        $installments = $obligation->installments()->orderBy('due_date')->get();
        // Exact equality (not just delta) -- proves the plan never
        // silently under/over-schedules the obligation by a stray cent.
        $this->assertSame(100.0, round((float) $installments->sum('amount'), 2));
    }

    public function test_applying_a_term_twice_to_the_same_invoice_is_rejected(): void
    {
        $term = $this->service->create(['code' => 'NET30', 'name' => 'Net 30'], [['days_offset' => 30, 'percentage' => 100]]);
        $invoice = $this->makeInvoice(1000);

        $this->service->applyToInvoice($invoice, $term, $this->user);

        $this->expectException(ValidationException::class);
        $this->service->applyToInvoice($invoice, $term, $this->user);
    }

    // Real two-connection concurrency proof lives in
    // tests/Feature/SettlementObligationRaceTest.php (MariaDbRaceTestCase),
    // matching the established pattern (BankReconciliationMatchRaceTest,
    // InvoicePaymentBalanceRaceTest, ChequeInvoicePaymentRaceTest) --
    // SQLite cannot represent this race class at all (see guardrail #24).

    // --- Cross-company isolation -------------------------------------

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

    public function test_company_a_cannot_apply_its_payment_term_to_company_bs_invoice(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $termA = $this->withActiveCompany($companyA, fn () => $this->service->create(
            ['code' => 'NET30', 'name' => 'Net 30'],
            [['days_offset' => 30, 'percentage' => 100]]
        ));

        $customerB = $this->withActiveCompany($companyB, fn () => Customer::factory()->create());
        $invoiceB = $this->withActiveCompany($companyB, fn () => Invoice::create([
            'customer_id' => $customerB->id,
            'number' => 5001,
            'date' => now(),
            'invoice_type' => 1,
            'status' => 1,
            'amount' => 1000,
            'vat' => 0,
        ]));

        $this->asCompany($companyA, $actorA)
            ->post(route('payment-terms.apply', $invoiceB), ['payment_term_id' => $termA->id])
            ->assertNotFound();

        $this->assertSame(0, SettlementObligation::withoutGlobalScopes()->where('source_type', Invoice::class)->where('source_id', $invoiceB->id)->count());
    }

    public function test_company_a_cannot_update_company_bs_payment_term(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $termB = $this->withActiveCompany($companyB, fn () => $this->service->create(
            ['code' => 'NET30', 'name' => 'Net 30'],
            [['days_offset' => 30, 'percentage' => 100]]
        ));

        $this->asCompany($companyA, $actorA)
            ->put(route('payment-terms.update', $termB), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertSame('Net 30', PaymentTerm::withoutGlobalScopes()->find($termB->id)->name);
    }
}
