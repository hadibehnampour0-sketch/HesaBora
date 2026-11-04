<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancingPlan;
use App\Models\InstallmentCollateral;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Models\User;
use App\Services\Financing\FinancingService;
use App\Services\Financing\InstallmentCalculator;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function test_create_contract_with_manual_rate_and_initial_collaterals(): void
    {
        $response = $this->actingAs($this->user)->post(route('financing.store'), [
            'customer_id' => $this->customer->id,
            'calculation_method' => 'MARKET_MONTHLY',
            'monthly_interest_rate' => 4.5,
            'total_price' => 50000000,
            'down_payment' => 10000000,
            'tenor_months' => 8,
            'start_date' => '2026-11-04',
            'notes' => 'قرارداد اقساطی با نرخ دستی ۴.۵٪ و وثایق اولیه',
            'collaterals' => [
                [
                    'type' => 'CHEQUE',
                    'title' => 'چک صیادی ضمانت',
                    'purpose' => 'GUARANTEE',
                    'cheque_sayad_number' => '1234567890123456',
                    'cheque_bank' => 'بانک تجارت',
                    'cheque_account_owner' => 'رضا حسینی',
                    'cheque_amount' => 60000000,
                    'estimated_value' => 60000000,
                ],
                [
                    'type' => 'GOLD',
                    'title' => 'النگو طلا ۱۸ عیار',
                    'gold_weight_grams' => 15.350,
                    'gold_karat' => 18,
                    'estimated_value' => 750000000,
                ],
            ],
        ]);

        $contract = InstallmentContract::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($contract);
        $response->assertRedirect(route('financing.show', $contract->id));

        $this->assertSame(4.5, (float) $contract->applied_interest_rate);
        $this->assertSame('MARKET_MONTHLY', $contract->calculation_method);
        $this->assertSame(40000000, $contract->principal_amount);

        // 2 initial collaterals created
        $this->assertCount(2, $contract->collaterals);
        $this->assertSame(810000000, $contract->totalCollateralsEstimatedValue());

        $goldCol = $contract->collaterals->where('type', 'GOLD')->first();
        $this->assertNotNull($goldCol);
        $this->assertEquals(15.350, (float) $goldCol->gold_weight_grams);
        $this->assertSame(18, $goldCol->gold_karat);
    }

    public function test_add_collateral_with_attachment_and_release_workflow(): void
    {
        Storage::fake('public');

        $contract = $this->service->createContract([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total_price' => 20000000,
            'tenor_months' => 4,
            'start_date' => '2026-11-04',
        ]);

        $fakeImage = UploadedFile::fake()->create('gold_ring.jpg', 100, 'image/jpeg');

        $res = $this->actingAs($this->user)->post(route('financing.contracts.collaterals.store', $contract->id), [
            'type' => 'GOLD',
            'title' => 'انگشتر طلای مردانه',
            'gold_weight_grams' => 8.240,
            'gold_karat' => 18,
            'estimated_value' => 400000000,
            'attachment' => $fakeImage,
            'notes' => 'نزد صندوقدار شرکت به امانت سپرده شد.',
        ]);

        $res->assertRedirect();

        $collateral = InstallmentCollateral::where('contract_id', $contract->id)->first();
        $this->assertNotNull($collateral);
        $this->assertSame('GOLD', $collateral->type);
        $this->assertSame('RECEIVED', $collateral->status);
        $this->assertNotNull($collateral->attachment_path);
        Storage::disk('public')->assertExists($collateral->attachment_path);

        // Release collateral back to customer
        $releaseRes = $this->actingAs($this->user)->patch(route('financing.collaterals.release', $collateral->id), [
            'release_notes' => 'با تسویه اقساط، طلا تحویل مشتری شد.',
        ]);

        $releaseRes->assertRedirect();
        $collateral->refresh();
        $this->assertSame('RELEASED', $collateral->status);
        $this->assertNotNull($collateral->released_at);
        $this->assertSame('با تسویه اقساط، طلا تحویل مشتری شد.', $collateral->release_notes);
    }

    public function test_property_and_vehicle_collaterals_registration(): void
    {
        $contract = $this->service->createContract([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total_price' => 100000000,
            'tenor_months' => 12,
            'start_date' => '2026-11-04',
        ]);

        // Register Property
        $this->actingAs($this->user)->post(route('financing.contracts.collaterals.store', $contract->id), [
            'type' => 'PROPERTY',
            'title' => 'سند تک‌برگ آپارتمان',
            'property_registry_code' => 'پلاک ۹۸۴ فرعی ۱۲',
            'property_area' => 120.5,
            'property_address' => 'تهران، سعادت‌آباد، خیابان سرو غربی',
            'estimated_value' => 5000000000,
        ])->assertRedirect();

        // Register Vehicle
        $this->actingAs($this->user)->post(route('financing.contracts.collaterals.store', $contract->id), [
            'type' => 'VEHICLE',
            'title' => 'خودرو تارا اتوماتیک V4',
            'vehicle_model' => 'تارا اتومات ۱۴۰۳',
            'vehicle_plate' => '۱۲ الف ۳۴۵ ایران ۲۲',
            'vehicle_chassis_number' => 'NAAL123456789',
            'estimated_value' => 1200000000,
        ])->assertRedirect();

        $contract->refresh();
        $this->assertCount(2, $contract->collaterals);

        $property = $contract->collaterals->where('type', 'PROPERTY')->first();
        $this->assertSame('پلاک ۹۸۴ فرعی ۱۲', $property->property_registry_code);
        $this->assertEquals(120.5, (float) $property->property_area);

        $vehicle = $contract->collaterals->where('type', 'VEHICLE')->first();
        $this->assertSame('تارا اتومات ۱۴۰۳', $vehicle->vehicle_model);
        $this->assertSame('۱۲ الف ۳۴۵ ایران ۲۲', $vehicle->vehicle_plate);
    }
}
