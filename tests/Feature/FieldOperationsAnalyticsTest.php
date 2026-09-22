<?php

namespace Tests\Feature;

use App\Enums\DailySettlementStatus;
use App\Enums\VisitPlanStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DailySettlement;
use App\Models\Payment;
use App\Models\ProductGroup;
use App\Models\RepCommission;
use App\Models\RouteMetric;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\VisitPlan;
use App\Services\RepCommissionEngineService;
use App\Services\RouteAnalyticsService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine).
 */
class FieldOperationsAnalyticsTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'field-operations-analytics.index']),
            Permission::firstOrCreate(['name' => 'field-operations-analytics.recalculate']),
        );
        $this->actingAs($this->user);
    }

    // -----------------------------------------------------------------
    // RepCommissionEngineService
    // -----------------------------------------------------------------

    public function test_an_approved_settlement_earns_the_correct_commission_tier(): void
    {
        $rep = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($rep);
        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-06-05',
            'total_invoiced_amount' => 100_000_000,
            'cash_collected' => 100_000_000,
            'status' => DailySettlementStatus::APPROVED,
        ]);

        $results = app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $this->assertCount(1, $results);
        $commission = $results->first();
        $this->assertSame(100_000_000.0, (float) $commission->total_settled_amount);
        $this->assertSame('Silver', $commission->tier_label);
        $this->assertSame(2.0, (float) $commission->commission_rate);
        $this->assertSame(2_000_000.0, (float) $commission->commission_amount);
    }

    public function test_a_submitted_but_unapproved_settlement_earns_no_commission(): void
    {
        $rep = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($rep);
        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-06-05',
            'total_invoiced_amount' => 500_000_000,
            'status' => DailySettlementStatus::SUBMITTED,
        ]);

        $results = app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $this->assertCount(0, $results);
    }

    public function test_a_settlement_outside_the_period_is_excluded(): void
    {
        $rep = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($rep);
        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-07-05',
            'total_invoiced_amount' => 100_000_000,
            'status' => DailySettlementStatus::APPROVED,
        ]);

        $results = app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $this->assertCount(0, $results);
    }

    public function test_recalculating_the_same_period_updates_instead_of_duplicating(): void
    {
        $rep = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($rep);
        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-06-05',
            'total_invoiced_amount' => 10_000_000,
            'status' => DailySettlementStatus::APPROVED,
        ]);

        app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-06-06',
            'total_invoiced_amount' => 60_000_000,
            'status' => DailySettlementStatus::APPROVED,
        ]);

        app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $this->assertSame(1, RepCommission::count());
        $this->assertSame(70_000_000.0, (float) RepCommission::first()->total_settled_amount);
    }

    public function test_a_user_cannot_read_another_companys_rep_commissions(): void
    {
        $rep = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($rep);
        DailySettlement::create([
            'user_id' => $rep->id,
            'settlement_date' => '2026-06-05',
            'total_invoiced_amount' => 100_000_000,
            'status' => DailySettlementStatus::APPROVED,
        ]);
        app(RepCommissionEngineService::class)->calculateForPeriod($this->companyId, '2026-06-01', '2026-06-30');
        $this->assertSame(1, RepCommission::count());

        // Positive control: confirm the SAME query the cross-company check
        // below relies on actually surfaces the row for its own company
        // first -- otherwise a broken query silently "passing" the
        // isolation check by returning nothing to ANYONE proves nothing.
        // Asserts on the raw (untranslated, un-localized) tier_label
        // rather than a formatted number, since localizeNumber() renders
        // digits in Persian numerals under this app's default 'fa' locale.
        $this->get(route('field-operations-analytics.index', ['period_start' => '2026-06-01', 'period_end' => '2026-06-30']))
            ->assertOk()
            ->assertSee('Silver');

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);

        config(['active-company-id' => $otherCompany->id]);
        $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => 'field-operations-analytics.index']));
        $this->actingAs($otherUser);

        $response = $this->get(route('field-operations-analytics.index', ['period_start' => '2026-06-01', 'period_end' => '2026-06-30']));

        $response->assertOk();
        $response->assertDontSee('Silver');
        $this->assertSame(0, RepCommission::count());
    }

    // -----------------------------------------------------------------
    // RouteAnalyticsService
    // -----------------------------------------------------------------

    public function test_visit_completion_rate_is_computed_from_visit_plan_statuses(): void
    {
        $route = SalesRoute::create(['title' => 'North', 'code' => 'RT-N']);
        VisitPlan::create(['user_id' => $this->user->id, 'route_id' => $route->id, 'plan_date' => '2026-06-01', 'status' => VisitPlanStatus::COMPLETED]);
        VisitPlan::create(['user_id' => $this->user->id, 'route_id' => $route->id, 'plan_date' => '2026-06-02', 'status' => VisitPlanStatus::PLANNED]);

        $results = app(RouteAnalyticsService::class)->analyzeForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $metric = $results->firstWhere('route_id', $route->id);
        $this->assertSame(50.0, (float) $metric->visit_completion_rate);
    }

    public function test_a_route_with_no_visit_plans_has_a_zero_completion_rate(): void
    {
        SalesRoute::create(['title' => 'Empty', 'code' => 'RT-E']);

        $results = app(RouteAnalyticsService::class)->analyzeForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $this->assertSame(0.0, (float) $results->first()->visit_completion_rate);
    }

    public function test_average_invoice_value_and_cash_collection_ratio_for_a_routes_customers(): void
    {
        $route = SalesRoute::create(['title' => 'South', 'code' => 'RT-S']);
        $route->customers()->attach($this->customer->id, ['visit_priority' => 1]);

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, '2026-06-05');
        $sold = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, '2026-06-05');

        Payment::create([
            'invoice_id' => $sold['invoice']->id,
            'amount' => 500,
            'date' => '2026-06-06',
        ]);

        $results = app(RouteAnalyticsService::class)->analyzeForPeriod($this->companyId, '2026-06-01', '2026-06-30');

        $metric = $results->firstWhere('route_id', $route->id);
        $this->assertSame(1000.0, (float) $metric->average_invoice_value);
        $this->assertSame(50.0, (float) $metric->cash_collection_ratio);
    }

    public function test_a_user_cannot_read_another_companys_route_metrics(): void
    {
        $route = SalesRoute::create(['title' => 'Confidential', 'code' => 'RT-C']);
        VisitPlan::create(['user_id' => $this->user->id, 'route_id' => $route->id, 'plan_date' => '2026-06-01', 'status' => VisitPlanStatus::COMPLETED]);
        app(RouteAnalyticsService::class)->analyzeForPeriod($this->companyId, '2026-06-01', '2026-06-30');
        $this->assertSame(1, RouteMetric::count());

        // Positive control -- see the identical rationale in
        // test_a_user_cannot_read_another_companys_rep_commissions().
        $this->get(route('field-operations-analytics.index', ['period_start' => '2026-06-01', 'period_end' => '2026-06-30']))
            ->assertOk()
            ->assertSee('Confidential');

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);

        config(['active-company-id' => $otherCompany->id]);
        $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => 'field-operations-analytics.index']));
        $this->actingAs($otherUser);

        $response = $this->get(route('field-operations-analytics.index', ['period_start' => '2026-06-01', 'period_end' => '2026-06-30']));

        $response->assertOk();
        $response->assertDontSee('Confidential');
        $this->assertSame(0, RouteMetric::count());
    }
}
