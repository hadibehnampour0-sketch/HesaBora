<?php

namespace Tests\Feature;

use App\Enums\DailySettlementStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\Warehouse;
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
        foreach (['index', 'routes.index', 'visitors.index', 'plans.index', 'settlements.index'] as $suffix) {
            $name = $suffix === 'index' ? 'distribution.index' : 'distribution.'.$suffix;
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_the_screens_render_for_a_permitted_user(): void
    {
        $this->grantAll();

        foreach (['distribution.index', 'distribution.routes.index', 'distribution.visitors.index', 'distribution.plans.index', 'distribution.settlements.index'] as $name) {
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
}
