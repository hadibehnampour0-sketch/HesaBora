<?php

namespace Tests\Feature;

use App\Enums\DailySettlementStatus;
use App\Enums\PromotionType;
use App\Enums\VisitPlanStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DailySettlement;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\VisitPlan;
use App\Services\DailySettlementService;
use App\Services\PromotionEngineService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P11 (Field Sales & Distribution -- "FieldOperations"). Backend-only:
 * routes/visits/promotions/settlement. Van Warehouse support needed NO
 * new code (confirmed by a dedicated audit) -- `Warehouse` rows can
 * already be created ad hoc per rep/van, `WarehouseService::transfer()`
 * already moves stock atomically between them, and
 * `InvoiceService::createInvoice()` already deducts stock at the specific
 * warehouse an invoice targets -- so it is deliberately NOT re-tested
 * here; see `tests/Feature/WarehouseTest.php` for that existing coverage.
 */
class FieldOperationsTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8000;

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
        $this->actingAs($this->user);
    }

    private function grant(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    // -----------------------------------------------------------------
    // SalesRoute
    // -----------------------------------------------------------------

    public function test_creating_a_sales_route_and_assigning_a_customer(): void
    {
        $this->grant(['sales-routes.store', 'sales-routes.assign-customer']);

        $response = $this->postJson(route('sales-routes.store'), [
            'title' => 'North District',
            'code' => 'RT-1',
        ]);
        $response->assertCreated();
        $routeId = $response->json('route.id');

        $this->postJson(route('sales-routes.assign-customer', $routeId), [
            'customer_id' => $this->customer->id,
            'visit_priority' => 1,
        ])->assertOk();

        $route = SalesRoute::with('customers')->find($routeId);
        $this->assertCount(1, $route->customers);
        $this->assertSame(1, $route->customers->first()->pivot->visit_priority);
    }

    public function test_a_route_code_must_be_unique_per_company(): void
    {
        $this->grant(['sales-routes.store']);
        SalesRoute::create(['title' => 'A', 'code' => 'DUP']);

        $response = $this->postJson(route('sales-routes.store'), ['title' => 'B', 'code' => 'DUP']);

        $response->assertJsonValidationErrors('code');
    }

    public function test_sales_routes_are_isolated_per_company(): void
    {
        $this->grant(['sales-routes.index']);
        $otherCompany = Company::factory()->create();
        config(['active-company-id' => $otherCompany->id]);
        SalesRoute::create(['title' => 'Other', 'code' => 'OTHER-1']);
        config(['active-company-id' => $this->companyId]);
        SalesRoute::create(['title' => 'Mine', 'code' => 'MINE-1']);

        $response = $this->getJson(route('sales-routes.index'));

        $response->assertOk();
        $titles = collect($response->json('routes'))->pluck('title');
        $this->assertTrue($titles->contains('Mine'));
        $this->assertFalse($titles->contains('Other'));
    }

    // -----------------------------------------------------------------
    // VisitPlan
    // -----------------------------------------------------------------

    public function test_creating_a_visit_plan_for_a_company_member(): void
    {
        $this->grant(['visit-plans.store']);

        $response = $this->postJson(route('visit-plans.store'), [
            'user_id' => $this->user->id,
            'plan_date' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertSame(VisitPlanStatus::PLANNED->value, $response->json('visit_plan.status'));
    }

    public function test_a_visit_plan_cannot_be_assigned_to_a_user_outside_the_company(): void
    {
        $this->grant(['visit-plans.store']);
        $outsider = User::factory()->create();

        $response = $this->postJson(route('visit-plans.store'), [
            'user_id' => $outsider->id,
            'plan_date' => now()->toDateString(),
        ]);

        $response->assertJsonValidationErrors('user_id');
    }

    public function test_only_one_visit_plan_per_user_per_date(): void
    {
        $this->grant(['visit-plans.store']);
        VisitPlan::create(['user_id' => $this->user->id, 'plan_date' => '2026-06-01']);

        $response = $this->postJson(route('visit-plans.store'), [
            'user_id' => $this->user->id,
            'plan_date' => '2026-06-01',
        ]);

        $response->assertJsonValidationErrors('plan_date');
    }

    public function test_updating_visit_plan_status(): void
    {
        $this->grant(['visit-plans.store', 'visit-plans.update-status']);
        $plan = VisitPlan::create(['user_id' => $this->user->id, 'plan_date' => now()->toDateString()]);

        $response = $this->patchJson(route('visit-plans.update-status', $plan), ['status' => 'completed']);

        $response->assertOk();
        $this->assertSame(VisitPlanStatus::COMPLETED, $plan->fresh()->status);
    }

    // -----------------------------------------------------------------
    // PromotionEngineService
    // -----------------------------------------------------------------

    public function test_tiered_discount_applies_the_highest_qualifying_tier(): void
    {
        $product = $this->createProduct();
        Promotion::create([
            'title' => 'Volume discount',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'rules' => [
                'product_id' => $product->id,
                'tiers' => [
                    ['min_qty' => 10, 'discount_percent' => 5],
                    ['min_qty' => 50, 'discount_percent' => 10],
                ],
            ],
        ]);

        $result = app(PromotionEngineService::class)->evaluate(null, [
            ['product_id' => $product->id, 'quantity' => 60],
        ]);

        $this->assertCount(1, $result['discounts']);
        $this->assertSame(10.0, $result['discounts'][0]['discount_percent']);
    }

    public function test_tiered_discount_does_not_apply_below_the_lowest_tier(): void
    {
        $product = $this->createProduct();
        Promotion::create([
            'title' => 'Volume discount',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'rules' => [
                'product_id' => $product->id,
                'tiers' => [['min_qty' => 10, 'discount_percent' => 5]],
            ],
        ]);

        $result = app(PromotionEngineService::class)->evaluate(null, [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $this->assertEmpty($result['discounts']);
    }

    public function test_bonus_goods_promotion_calculates_free_quantity_in_multiples(): void
    {
        $buyProduct = $this->createProduct();
        $freeProduct = $this->createProduct();
        Promotion::create([
            'title' => 'Buy 5 get 1 free',
            'type' => PromotionType::BONUS_GOODS->value,
            'rules' => [
                'buy_product_id' => $buyProduct->id,
                'buy_qty' => 5,
                'free_product_id' => $freeProduct->id,
                'free_qty' => 1,
            ],
        ]);

        $result = app(PromotionEngineService::class)->evaluate(null, [
            ['product_id' => $buyProduct->id, 'quantity' => 12],
        ]);

        $this->assertCount(1, $result['bonus_items']);
        $this->assertSame(2.0, $result['bonus_items'][0]['quantity']);
    }

    public function test_an_inactive_promotion_is_never_evaluated(): void
    {
        $product = $this->createProduct();
        Promotion::create([
            'title' => 'Disabled',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'is_active' => false,
            'rules' => ['product_id' => $product->id, 'tiers' => [['min_qty' => 1, 'discount_percent' => 50]]],
        ]);

        $result = app(PromotionEngineService::class)->evaluate(null, [
            ['product_id' => $product->id, 'quantity' => 100],
        ]);

        $this->assertEmpty($result['discounts']);
    }

    public function test_a_promotion_outside_its_date_window_is_not_evaluated(): void
    {
        $product = $this->createProduct();
        Promotion::create([
            'title' => 'Expired',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'starts_at' => '2020-01-01',
            'ends_at' => '2020-01-31',
            'rules' => ['product_id' => $product->id, 'tiers' => [['min_qty' => 1, 'discount_percent' => 50]]],
        ]);

        $result = app(PromotionEngineService::class)->evaluate(null, [
            ['product_id' => $product->id, 'quantity' => 100],
        ], Carbon::parse('2026-06-01'));

        $this->assertEmpty($result['discounts']);
    }

    public function test_promotion_evaluate_endpoint_returns_the_calculated_suggestions(): void
    {
        $this->grant(['promotions.evaluate']);
        $product = $this->createProduct();
        Promotion::create([
            'title' => 'Volume discount',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'rules' => ['product_id' => $product->id, 'tiers' => [['min_qty' => 1, 'discount_percent' => 5]]],
        ]);

        $response = $this->postJson(route('promotions.evaluate'), [
            'cart' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('discounts'));
    }

    public function test_the_evaluate_endpoint_resolves_the_promotions_group_from_a_customer_id(): void
    {
        $this->grant(['promotions.evaluate']);
        $product = $this->createProduct();
        $group = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId]);
        Promotion::create([
            'title' => 'Group-only discount',
            'type' => PromotionType::TIERED_DISCOUNT->value,
            'customer_group_id' => $group->id,
            'rules' => ['product_id' => $product->id, 'tiers' => [['min_qty' => 1, 'discount_percent' => 8]]],
        ]);

        // Positive control: without the customer_id, the group-scoped
        // promotion must NOT apply -- otherwise the resolution path below
        // proves nothing.
        $this->postJson(route('promotions.evaluate'), [
            'cart' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonCount(0, 'discounts');

        $response = $this->postJson(route('promotions.evaluate'), [
            'customer_id' => $customer->id,
            'cart' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('discounts'));
        $this->assertSame(8.0, (float) $response->json('discounts.0.discount_percent'));
    }

    // -----------------------------------------------------------------
    // DailySettlement
    // -----------------------------------------------------------------

    public function test_suggested_total_invoiced_sums_the_users_invoices_for_the_date(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);

        $suggested = app(DailySettlementService::class)->suggestedTotalInvoiced($this->user, now());

        $this->assertGreaterThan(0, $suggested);
    }

    public function test_creating_and_submitting_a_settlement(): void
    {
        $this->grant(['daily-settlements.store', 'daily-settlements.submit']);

        $store = $this->postJson(route('daily-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'cash_collected' => 100,
            'pos_collected' => 50,
            'cheque_collected' => 0,
        ]);
        $store->assertOk();
        $settlementId = $store->json('settlement.id');

        $submit = $this->postJson(route('daily-settlements.submit', $settlementId));
        $submit->assertOk();
        $this->assertSame(DailySettlementStatus::SUBMITTED, DailySettlement::find($settlementId)->status);
    }

    public function test_a_submitted_settlement_cannot_be_edited_again(): void
    {
        $this->grant(['daily-settlements.store', 'daily-settlements.submit']);
        $store = $this->postJson(route('daily-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'cash_collected' => 100,
            'pos_collected' => 0,
            'cheque_collected' => 0,
        ]);
        $this->postJson(route('daily-settlements.submit', $store->json('settlement.id')));

        $response = $this->postJson(route('daily-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'cash_collected' => 999,
            'pos_collected' => 0,
            'cheque_collected' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_approving_a_settlement_requires_it_to_be_submitted_first(): void
    {
        $this->grant(['daily-settlements.store', 'daily-settlements.approve']);
        $store = $this->postJson(route('daily-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'cash_collected' => 100,
            'pos_collected' => 0,
            'cheque_collected' => 0,
        ]);

        $response = $this->postJson(route('daily-settlements.approve', $store->json('settlement.id')));

        $response->assertStatus(422);
    }

    public function test_approving_a_submitted_settlement_records_the_approver(): void
    {
        $this->grant(['daily-settlements.store', 'daily-settlements.submit', 'daily-settlements.approve']);
        $store = $this->postJson(route('daily-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'cash_collected' => 100,
            'pos_collected' => 0,
            'cheque_collected' => 0,
        ]);
        $id = $store->json('settlement.id');
        $this->postJson(route('daily-settlements.submit', $id));

        $approver = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($approver);
        $approver->givePermissionTo(Permission::firstOrCreate(['name' => 'daily-settlements.approve']));
        $this->actingAs($approver);

        $response = $this->postJson(route('daily-settlements.approve', $id));

        $response->assertOk();
        $settlement = DailySettlement::find($id);
        $this->assertSame(DailySettlementStatus::APPROVED, $settlement->status);
        $this->assertSame($approver->id, $settlement->approved_by);
    }

    public function test_settlement_variance_is_computed_correctly(): void
    {
        $settlement = DailySettlement::create([
            'user_id' => $this->user->id,
            'settlement_date' => now()->toDateString(),
            'total_invoiced_amount' => 1000,
            'cash_collected' => 600,
            'pos_collected' => 300,
            'cheque_collected' => 50,
        ]);

        $this->assertSame(-50.0, $settlement->variance);
    }
}
