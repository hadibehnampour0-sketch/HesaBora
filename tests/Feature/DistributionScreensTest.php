<?php

namespace Tests\Feature;

use App\Enums\DailySettlementStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\Promotion;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\Warehouse;
use App\Services\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** Capillary distribution screens: presentation over the P11 backend + the visitor/van-warehouse link. */
class DistributionScreensTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private User $visitorUser;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;
        $this->user = User::factory()->create();
        $this->visitorUser = User::factory()->create(['name' => 'Reza Visitor']);
        $company->users()->syncWithoutDetaching([$this->user->id, $this->visitorUser->id]);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);
        $this->actingAs($this->user);
    }

    private function grantAll(): void
    {
        $names = [
            'distribution.index',
            'distribution.routes.index', 'distribution.routes.store', 'distribution.routes.update', 'distribution.routes.destroy',
            'distribution.routes.customers.store', 'distribution.routes.customers.destroy',
            'distribution.visitors.index', 'distribution.visitors.store', 'distribution.visitors.update', 'distribution.visitors.destroy',
            'distribution.plans.index', 'distribution.plans.store', 'distribution.plans.update-status', 'distribution.plans.destroy',
            'distribution.promotions.index', 'distribution.promotions.store', 'distribution.promotions.update', 'distribution.promotions.destroy',
            'distribution.settlements.index', 'distribution.settlements.store', 'distribution.settlements.submit', 'distribution.settlements.approve',
        ];
        foreach ($names as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name]));
        }
    }

    private function addVisitor(bool $withVan = true): DistributionVisitor
    {
        $this->post(route('distribution.visitors.store'), [
            'user_id' => $this->visitorUser->id,
            'create_van_warehouse' => $withVan ? '1' : '0',
        ])->assertSessionHasNoErrors();

        return DistributionVisitor::firstOrFail();
    }

    public function test_every_screen_needs_its_own_permission(): void
    {
        foreach (['index', 'routes.index', 'visitors.index', 'plans.index', 'promotions.index', 'settlements.index'] as $suffix) {
            $name = $suffix === 'index' ? 'distribution.index' : 'distribution.'.$suffix;
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_the_screens_render_for_a_permitted_user(): void
    {
        $this->grantAll();

        foreach (['distribution.index', 'distribution.routes.index', 'distribution.visitors.index', 'distribution.plans.index', 'distribution.promotions.index', 'distribution.settlements.index'] as $name) {
            $this->get(route($name))->assertOk()->assertSee(__('Capillary distribution'));
        }
    }

    public function test_a_route_can_be_created_edited_given_customers_and_deleted(): void
    {
        $this->grantAll();
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Corner Shop']);

        $this->post(route('distribution.routes.store'), ['title' => 'North', 'code' => 'N1'])->assertSessionHasNoErrors();
        $route = SalesRoute::firstOrFail();

        $this->post(route('distribution.routes.store'), ['title' => 'Dup', 'code' => 'N1'])->assertSessionHasErrors('code');

        $this->put(route('distribution.routes.update', $route), ['title' => 'North 2', 'code' => 'N1', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('North 2', $route->fresh()->title);

        $this->post(route('distribution.routes.customers.store', $route), ['customer_id' => $customer->id, 'visit_priority' => 1])->assertSessionHasNoErrors();
        $this->get(route('distribution.routes.index'))->assertSee('Corner Shop');

        $this->delete(route('distribution.routes.customers.destroy', [$route, $customer->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, $route->fresh()->customers()->count());

        $this->delete(route('distribution.routes.destroy', $route))->assertSessionHasNoErrors();
        $this->assertSame(0, SalesRoute::count());
    }

    public function test_adding_a_visitor_can_create_a_dedicated_van_warehouse(): void
    {
        $this->grantAll();

        $visitor = $this->addVisitor();

        $this->assertNotNull($visitor->warehouse_id);
        $this->assertStringContainsString('Reza Visitor', Warehouse::findOrFail($visitor->warehouse_id)->name);
        $this->get(route('distribution.visitors.index'))->assertOk()->assertSee('Reza Visitor');
    }

    public function test_a_user_can_be_a_visitor_only_once_and_only_within_the_company(): void
    {
        $this->grantAll();
        $this->addVisitor(false);

        $this->post(route('distribution.visitors.store'), ['user_id' => $this->visitorUser->id])->assertSessionHasErrors('user_id');

        $outsider = User::factory()->create();
        $this->post(route('distribution.visitors.store'), ['user_id' => $outsider->id])->assertSessionHasErrors('user_id');
    }

    public function test_a_visitors_commission_rate_can_be_set_and_cleared(): void
    {
        $this->grantAll();
        $visitor = $this->addVisitor(false);

        $this->put(route('distribution.visitors.update', $visitor), ['commission_rate' => '5.5'])->assertSessionHasNoErrors();
        $this->assertSame(5.5, (float) $visitor->fresh()->commission_rate);

        $this->put(route('distribution.visitors.update', $visitor), ['commission_rate' => ''])->assertSessionHasNoErrors();
        $this->assertNull($visitor->fresh()->commission_rate);

        $this->put(route('distribution.visitors.update', $visitor), ['commission_rate' => '150'])->assertSessionHasErrors('commission_rate');
    }

    public function test_removing_a_visitor_keeps_the_van_warehouse(): void
    {
        $this->grantAll();
        $visitor = $this->addVisitor();
        $warehouseId = $visitor->warehouse_id;

        $this->delete(route('distribution.visitors.destroy', $visitor))->assertSessionHasNoErrors();

        $this->assertSame(0, DistributionVisitor::count());
        $this->assertNotNull(Warehouse::find($warehouseId));
    }

    public function test_a_visit_plan_needs_an_active_visitor_and_is_unique_per_day(): void
    {
        $this->grantAll();
        $date = now()->toDateString();

        $this->post(route('distribution.plans.store'), ['user_id' => $this->visitorUser->id, 'plan_date' => $date])->assertSessionHasErrors('user_id');

        $this->addVisitor(false);
        $this->post(route('distribution.plans.store'), ['user_id' => $this->visitorUser->id, 'plan_date' => $date])->assertSessionHasNoErrors();
        $this->post(route('distribution.plans.store'), ['user_id' => $this->visitorUser->id, 'plan_date' => $date])->assertSessionHasErrors('plan_date');

        $plan = VisitPlan::firstOrFail();
        $this->patch(route('distribution.plans.update-status', $plan), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $plan->fresh()->status->value);
    }

    public function test_the_route_day_calendar_shows_this_weeks_plans_and_navigates_by_week(): void
    {
        $this->grantAll();
        $visitor = $this->addVisitor(false);

        $thisWeek = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();

        VisitPlan::create(['user_id' => $visitor->user_id, 'plan_date' => $thisWeek]);
        VisitPlan::create(['user_id' => $visitor->user_id, 'plan_date' => $nextWeek]);

        // This week's calendar only carries this week's plan...
        $response = $this->get(route('distribution.plans.index'));
        $response->assertOk();
        $weekPlans = $response->viewData('weekPlans');
        $this->assertTrue($weekPlans->has($thisWeek));
        $this->assertFalse($weekPlans->has($nextWeek));

        // ...and navigating forward a week brings the other plan into view.
        $response = $this->get(route('distribution.plans.index', ['week' => $nextWeek]));
        $response->assertOk();
        $weekPlans = $response->viewData('weekPlans');
        $this->assertTrue($weekPlans->has($nextWeek));
        $this->assertFalse($weekPlans->has($thisWeek));
    }

    public function test_a_settlement_goes_from_draft_to_submitted_to_approved(): void
    {
        $this->grantAll();
        $this->addVisitor(false);

        $this->post(route('distribution.settlements.store'), [
            'user_id' => $this->visitorUser->id,
            'settlement_date' => now()->toDateString(),
            'cash_collected' => '1000',
            'pos_collected' => '500',
            'cheque_collected' => '0',
        ])->assertSessionHasNoErrors();

        $settlement = DailySettlement::firstOrFail();
        $this->assertSame(DailySettlementStatus::DRAFT, $settlement->status);

        $this->post(route('distribution.settlements.approve', $settlement))->assertSessionHasErrors('settlement'); // not submitted yet
        $this->post(route('distribution.settlements.submit', $settlement))->assertSessionHasNoErrors();
        $this->post(route('distribution.settlements.approve', $settlement))->assertSessionHasNoErrors();

        $this->assertSame(DailySettlementStatus::APPROVED, $settlement->fresh()->status);
        $this->assertSame($this->user->id, $settlement->fresh()->approved_by);
    }

    public function test_another_companys_routes_and_visitors_are_never_shown_or_editable(): void
    {
        $this->grantAll();
        SalesRoute::create(['title' => 'Mine', 'code' => 'M1']);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        $foreign = SalesRoute::create(['title' => 'Foreign', 'code' => 'F1']);
        config(['active-company-id' => $this->companyId]);

        $this->get(route('distribution.routes.index'))->assertSee('Mine')->assertDontSee('Foreign');
        $this->put(route('distribution.routes.update', $foreign), ['title' => 'Hacked', 'code' => 'F1'])->assertNotFound();
        $this->assertSame('Foreign', SalesRoute::withoutGlobalScopes()->find($foreign->id)->title);
    }

    public function test_the_menu_has_a_top_level_capillary_distribution_entry(): void
    {
        $this->grantAll();
        foreach (['home', 'home.summary'] as $extra) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $extra]));
        }
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.index']));

        $this->get(route('home'))->assertOk()->assertSee(route('distribution.visitors.index'), false);
    }

    public function test_a_tiered_promotion_is_stored_in_the_shape_the_engine_reads(): void
    {
        $this->grantAll();

        $this->post(route('distribution.promotions.store'), [
            'title' => 'Bulk',
            'type' => 'tiered_discount',
            'tiers' => [['min_qty' => '50', 'discount_percent' => '10'], ['min_qty' => '10', 'discount_percent' => '5'], ['min_qty' => '', 'discount_percent' => '']],
        ])->assertSessionHasNoErrors();

        $promotion = Promotion::firstOrFail();
        $this->assertTrue($promotion->is_active);
        $this->assertEquals([['min_qty' => 10.0, 'discount_percent' => 5.0], ['min_qty' => 50.0, 'discount_percent' => 10.0]], $promotion->rules['tiers']);

        $result = app(PromotionEngineService::class)->evaluate(null, [['product_id' => 1, 'quantity' => 60]], now());
        $this->assertSame(10.0, (float) $result['discounts'][0]['discount_percent']);
    }

    public function test_a_tiered_promotion_needs_at_least_one_complete_tier(): void
    {
        $this->grantAll();

        $this->post(route('distribution.promotions.store'), ['title' => 'Empty', 'type' => 'tiered_discount', 'tiers' => [['min_qty' => '', 'discount_percent' => '']]])
            ->assertSessionHasErrors('tiers');
        $this->assertSame(0, Promotion::count());
    }

    public function test_a_promotion_can_be_toggled_and_deleted_and_bad_type_is_rejected(): void
    {
        $this->grantAll();
        $promotion = Promotion::create(['title' => 'X', 'type' => 'tiered_discount', 'rules' => ['tiers' => [['min_qty' => 1, 'discount_percent' => 1]]], 'is_active' => true]);

        $this->put(route('distribution.promotions.update', $promotion))->assertSessionHasNoErrors();
        $this->assertFalse($promotion->fresh()->is_active);

        $this->post(route('distribution.promotions.store'), ['title' => 'Bad', 'type' => 'nope'])->assertSessionHasErrors('type');

        $this->delete(route('distribution.promotions.destroy', $promotion))->assertSessionHasNoErrors();
        $this->assertSame(0, Promotion::count());
    }

    public function test_the_sell_invoice_form_defaults_to_the_visitors_van_warehouse(): void
    {
        $this->grantAll();
        Warehouse::create(['name' => 'Main']);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.create']));
        $visitor = DistributionVisitor::create(['user_id' => $this->user->id, 'warehouse_id' => Warehouse::create(['name' => 'Van A'])->id]);

        $html = $this->get(route('invoices.create', ['invoice_type' => 'sell']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="'.$visitor->warehouse_id.'"\s+selected#', $html);
    }
}
