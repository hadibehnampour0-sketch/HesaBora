<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\ManufacturingService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Manufacturing Phase 1 ("Production Lite"), Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md §4.
 */
class ManufacturingTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected Warehouse $warehouse;

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

        $this->warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->companyId, 'code' => 'MAIN'],
            ['name' => 'انبار اصلی']
        );
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function stockOf(int $productId): float
    {
        return (float) WarehouseProductStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $productId)->value('quantity');
    }

    // --- Service-level: the actual production math ------------------------------------

    public function test_producing_consumes_material_including_waste_and_blends_output_cost(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 50, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0, 'average_cost' => 0]);

        $recipe = app(ManufacturingService::class)->createRecipe(
            $basket->id, 'Basket recipe', 15,
            [['product_id' => $granule->id, 'quantity' => 5, 'unit' => 'kg', 'waste_percent' => 10]],
            $this->user
        );

        $order = app(ManufacturingService::class)->produce($recipe, 15, $this->warehouse, $this->user);

        // 5kg nominal * 1 batch * 1.10 waste = 5.5kg actually consumed.
        $this->assertEqualsWithDelta(44.5, $this->stockOf($granule->id), 0.001);
        $this->assertEqualsWithDelta(15.0, $this->stockOf($basket->id), 0.001);

        $this->assertEqualsWithDelta(550.0, (float) $order->material_cost, 0.01); // 5.5kg * 100
        $this->assertEqualsWithDelta(550.0, (float) $order->total_cost, 0.01);
        $this->assertEqualsWithDelta(36.6667, (float) $order->unit_cost, 0.001); // 550 / 15

        // WarehouseProductStock.average_cost is a 2-decimal column (its own existing precision,
        // unlike ProductionOrder.unit_cost's 4 decimals) -- a coarser but expected rounding.
        $basketStock = WarehouseProductStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $basket->id)->first();
        $this->assertEqualsWithDelta(36.67, (float) $basketStock->average_cost, 0.01);

        $this->assertSame(1, $order->materials()->count());
        $material = $order->materials()->first();
        $this->assertEqualsWithDelta(5.5, (float) $material->quantity_consumed, 0.001);
    }

    public function test_scaling_a_batch_up_multiplies_material_need_proportionally(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 100, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe(
            $basket->id, 'Basket recipe', 15,
            [['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 0]],
            $this->user
        );

        // Producing 30 (2 batches worth) should need 10kg, not 5kg.
        app(ManufacturingService::class)->produce($recipe, 30, $this->warehouse, $this->user);

        $this->assertEqualsWithDelta(90.0, $this->stockOf($granule->id), 0.001);
        $this->assertEqualsWithDelta(30.0, $this->stockOf($basket->id), 0.001);
    }

    public function test_labor_and_overhead_are_added_to_the_unit_cost(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 50, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe($basket->id, 'r', 10, [
            ['product_id' => $granule->id, 'quantity' => 1, 'waste_percent' => 0],
        ], $this->user);

        $order = app(ManufacturingService::class)->produce($recipe, 10, $this->warehouse, $this->user, laborCost: 200, overheadCost: 50);

        // material: 1kg * 100 = 100; + 200 labor + 50 overhead = 350 total / 10 units = 35
        $this->assertEqualsWithDelta(350.0, (float) $order->total_cost, 0.01);
        $this->assertEqualsWithDelta(35.0, (float) $order->unit_cost, 0.001);
    }

    public function test_insufficient_material_rejects_and_changes_nothing(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 2, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe($basket->id, 'r', 15, [
            ['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 0],
        ], $this->user);

        $this->expectException(ValidationException::class);
        try {
            app(ManufacturingService::class)->produce($recipe, 15, $this->warehouse, $this->user);
        } finally {
            $this->assertEqualsWithDelta(2.0, $this->stockOf($granule->id), 0.001);
            $this->assertEqualsWithDelta(0.0, $this->stockOf($basket->id), 0.001);
            $this->assertSame(0, ProductionOrder::count());
        }
    }

    public function test_an_oversell_flagged_material_can_still_be_produced_when_short(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 1, 'average_cost' => 100, 'oversell' => 1]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe($basket->id, 'r', 15, [
            ['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 0],
        ], $this->user);

        $order = app(ManufacturingService::class)->produce($recipe, 15, $this->warehouse, $this->user);

        $this->assertEqualsWithDelta(-4.0, $this->stockOf($granule->id), 0.001);
        $this->assertSame(1, ProductionOrder::count());
    }

    public function test_estimate_flags_shortage_without_changing_any_stock(): void
    {
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 2, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe($basket->id, 'r', 15, [
            ['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 0],
        ], $this->user);

        $estimate = app(ManufacturingService::class)->estimate($recipe, 15, $this->warehouse);

        $this->assertFalse($estimate['can_produce']);
        $this->assertFalse($estimate['materials'][0]['sufficient']);
        $this->assertEqualsWithDelta(2.0, $this->stockOf($granule->id), 0.001); // untouched
    }

    // --- HTTP / permissions / isolation ------------------------------------------------

    public function test_the_recipe_and_order_screens_need_their_own_permissions(): void
    {
        $this->get(route('production.recipes.index'))->assertForbidden();
        $this->get(route('production.orders.index'))->assertForbidden();
    }

    public function test_a_recipe_can_be_created_from_the_screen_and_rejects_self_reference(): void
    {
        $this->grant('production.recipes.index', 'production.recipes.store');
        $granule = $this->createProduct(['code' => 'GRANULE']);
        $basket = $this->createProduct(['code' => 'BASKET']);

        $this->post(route('production.recipes.store'), [
            'product_id' => $basket->id, 'name' => 'Basket recipe', 'output_quantity' => 15,
            'items' => [['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 10]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ProductionRecipe::count());

        $this->post(route('production.recipes.store'), [
            'product_id' => $basket->id, 'name' => 'Bad recipe', 'output_quantity' => 15,
            'items' => [['product_id' => $basket->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');
    }

    public function test_producing_from_the_screen_updates_stock_and_shows_the_orders_history(): void
    {
        $this->grant('production.recipes.index', 'production.recipes.store', 'production.orders.index', 'production.orders.store');
        $granule = $this->createProduct(['code' => 'GRANULE', 'quantity' => 50, 'average_cost' => 100]);
        $basket = $this->createProduct(['code' => 'BASKET', 'quantity' => 0]);
        $recipe = app(ManufacturingService::class)->createRecipe($basket->id, 'r', 15, [
            ['product_id' => $granule->id, 'quantity' => 5, 'waste_percent' => 0],
        ], $this->user);

        $this->get(route('production.orders.index', ['recipe_id' => $recipe->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 15]))
            ->assertOk()
            ->assertSee(__('Confirm production'));

        $this->post(route('production.orders.store'), [
            'recipe_id' => $recipe->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 15,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(15.0, $this->stockOf($basket->id), 0.001);
        $this->get(route('production.orders.index'))->assertOk()->assertSee($basket->name);
    }

    public function test_another_companys_recipes_and_orders_are_never_visible(): void
    {
        $this->grant('production.recipes.index', 'production.orders.index');
        $granule = $this->createProduct(['code' => 'GRANULE']);
        $basket = $this->createProduct(['code' => 'BASKET', 'name' => 'Mine Basket']);
        app(ManufacturingService::class)->createRecipe($basket->id, 'Mine recipe', 15, [
            ['product_id' => $granule->id, 'quantity' => 1],
        ], $this->user);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        $otherGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $other->id]);
        $otherGranule = Product::factory()->withGroup($otherGroup)->withSubjects()->create(['company_id' => $other->id, 'code' => 'OG']);
        $otherBasket = Product::factory()->withGroup($otherGroup)->withSubjects()->create(['company_id' => $other->id, 'code' => 'OB', 'name' => 'Other Basket']);
        $otherUser = User::factory()->create();
        $other->users()->syncWithoutDetaching($otherUser);
        app(ManufacturingService::class)->createRecipe($otherBasket->id, 'Other recipe', 15, [
            ['product_id' => $otherGranule->id, 'quantity' => 1],
        ], $otherUser);
        config(['active-company-id' => $this->companyId]);

        $this->get(route('production.recipes.index'))->assertOk()->assertSee('Mine recipe')->assertDontSee('Other recipe');
    }
}
