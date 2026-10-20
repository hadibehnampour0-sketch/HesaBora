<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancingPlan;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Models\User;
use App\Services\Financing\FinancingService;
use App\Services\Financing\InstallmentCalculator;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancingInstallmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Customer $customer;
    protected FinancingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Test Company',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'financing.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $group = CustomerGroup::factory()->withSubject()->create([
            'name' => 'General',
            'company_id' => $this->company->id,
        ]);

        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create([
            'company_id' => $this->company->id,
            'name' => 'مشتری خریدار اقساطی',
            'mobile' => '09129998877',
        ]);

        $this->service = app(FinancingService::class);
    }

    public function test_market_monthly_interest_calculator(): void
    {
        // 10,000,000 Rials principal, 3% monthly rate, 10 months
        $res = InstallmentCalculator::calculateMarketPlan(10000000, 3.0, 10);

        $this->assertSame(10000000, $res['principal']);
        $this->assertSame(3000000, $res['total_interest']);
        $this->assertSame(13000000, $res['total_amount']);
        $this->assertSame(1300000, $res['monthly_installment']);
        $this->assertCount(10, $res['schedule']);

        // Sum of all principal portions must equal exactly 10,000,000
        $sumP = array_sum(array_column($res['schedule'], 'principal_portion'));
        $this->assertSame(10000000, $sumP);

        // Sum of all interest portions must equal exactly 3,000,000
        $sumI = array_sum(array_column($res['schedule'], 'interest_portion'));
        $this->assertSame(3000000, $sumI);
    }

    public function test_banking_amortization_calculator(): void
    {
        // 20,000,000 Rials principal, 24% annual rate, 12 months
        $res = InstallmentCalculator::calculateBankingPlan(20000000, 24.0, 12);

        $this->assertSame(20000000, $res['principal']);
        $this->assertGreaterThan(0, $res['total_interest']);
        $this->assertGreaterThan(20000000, $res['total_amount']);
        $this->assertCount(12, $res['schedule']);

        $sumP = array_sum(array_column($res['schedule'], 'principal_portion'));
        $this->assertSame(20000000, $sumP);
    }

    public function test_late_fee_calculation(): void
    {
        // 1,000,000 Rials unpaid, 25 days overdue, 6% annual rate, 5 days grace period
        // Effective days = 20. Penalty = 1,000,000 * 0.06 * (20 / 365) = 3288 Rials
        $fee = InstallmentCalculator::calculateLateFee(1000000, 25, 6.0, 5);
        $this->assertSame(3288, $fee);

        // Within grace period -> 0 penalty
        $feeZero = InstallmentCalculator::calculateLateFee(1000000, 4, 6.0, 5);
        $this->assertSame(0, $feeZero);
    }

    public function test_create_contract_and_schedule_generation(): void
    {
        $plan = FinancingPlan::create([
            'company_id' => $this->company->id,
            'name' => 'طرح ۳ درصد بازاری',
            'calculation_method' => 'MARKET_MONTHLY',
            'monthly_interest_rate' => 3.0,
            'default_tenor_months' => 6,
        ]);

        $response = $this->actingAs($this->user)->post(route('financing.store'), [
            'customer_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'total_price' => 12000000,
            'down_payment' => 2000000,
            'tenor_months' => 5,
            'start_date' => '2026-10-20',
            'calculation_method' => 'MARKET_MONTHLY',
            'monthly_interest_rate' => 3.0,
        ]);

        $contract = InstallmentContract::where('company_id', $this->company->id)->first();
        $this->assertNotNull($contract);
        $response->assertRedirect(route('financing.show', $contract->id));

        $this->assertSame(10000000, $contract->principal_amount);
        $this->assertSame(1500000, $contract->total_interest); // 10M * 3% * 5 months
        $this->assertSame(11500000, $contract->total_amount);
        $this->assertSame(5, $contract->tenor_months);
        $this->assertSame('ACTIVE', $contract->status);

        // 5 schedule rows generated
        $this->assertCount(5, $contract->schedules);
        $this->assertSame(2300000, $contract->schedules->first()->amount);
    }

    public function test_pay_installment_schedule_and_settlement(): void
    {
        // Create contract with 2 installments
        $contract = $this->service->createContract([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total_price' => 2000000,
            'down_payment' => 0,
            'tenor_months' => 2,
            'calculation_method' => 'MARKET_MONTHLY',
            'monthly_interest_rate' => 0.0, // 0% interest for simple math
            'start_date' => '2026-10-20',
        ], $this->user);

        $schedules = $contract->schedules;
        $this->assertCount(2, $schedules);
        $this->assertSame(1000000, $schedules[0]->amount);
        $this->assertSame(1000000, $schedules[1]->amount);

        // Pay installment 1
        $res = $this->actingAs($this->user)->postJson(route('financing.schedules.pay', $schedules[0]->id), [
            'amount' => 1000000,
        ]);

        $res->assertOk();
        $schedules[0]->refresh();
        $this->assertSame('PAID', $schedules[0]->status);
        $this->assertNotNull($schedules[0]->document_id);

        $contract->refresh();
        $this->assertSame('ACTIVE', $contract->status);

        // Pay installment 2
        $res2 = $this->actingAs($this->user)->postJson(route('financing.schedules.pay', $schedules[1]->id), [
            'amount' => 1000000,
        ]);

        $res2->assertOk();
        $schedules[1]->refresh();
        $this->assertSame('PAID', $schedules[1]->status);

        // Entire contract is now settled!
        $contract->refresh();
        $this->assertSame('SETTLED', $contract->status);
    }

    public function test_overdue_penalty_application(): void
    {
        $plan = FinancingPlan::create([
            'company_id' => $this->company->id,
            'name' => 'طرح جریمه',
            'late_fee_rate_percent' => 12.0,
            'grace_period_days' => 2,
        ]);

        $contract = $this->service->createContract([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'total_price' => 1000000,
            'down_payment' => 0,
            'tenor_months' => 1,
            'calculation_method' => 'MARKET_MONTHLY',
            'monthly_interest_rate' => 0.0,
            'start_date' => '2026-08-01',
        ], $this->user);

        // Schedule was due 2026-09-01
        $schedule = $contract->schedules->first();
        $this->assertSame('UNPAID', $schedule->status);

        // Recalculate penalties as of 2026-09-21 (20 days overdue)
        $asOf = Carbon::parse('2026-09-21');
        $penalties = $this->service->calculateAndApplyOverduePenalties($contract, $asOf);

        $this->assertGreaterThan(0, $penalties);
        $schedule->refresh();
        $this->assertSame('OVERDUE', $schedule->status);
        $this->assertGreaterThan(0, $schedule->late_fee_amount);
    }

    public function test_cross_company_financing_isolation(): void
    {
        $contractA = $this->service->createContract([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'contract_number' => 'INS-COMPANY-A-001',
            'total_price' => 5000000,
            'tenor_months' => 3,
            'start_date' => '2026-10-20',
        ]);

        $companyB = Company::factory()->create(['fiscal_year' => 1405]);
        $groupB = CustomerGroup::factory()->withSubject()->create([
            'company_id' => $companyB->id,
        ]);
        $customerB = Customer::factory()->withGroup($groupB)->withSubject()->create([
            'company_id' => $companyB->id,
            'name' => 'مشتری شرکت دوم',
        ]);

        $contractB = $this->service->createContract([
            'company_id' => $companyB->id,
            'customer_id' => $customerB->id,
            'contract_number' => 'INS-COMPANY-B-999',
            'total_price' => 3000000,
            'tenor_months' => 2,
            'start_date' => '2026-10-20',
        ]);

        $response = $this->actingAs($this->user)->get(route('financing.index'));
        $response->assertOk();
        $response->assertSee('INS-COMPANY-A-001');
        $response->assertDontSee('INS-COMPANY-B-999');
    }
}
