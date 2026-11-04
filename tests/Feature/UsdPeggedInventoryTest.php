<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\ProductMarketPrice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Market\MarketIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UsdPeggedInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected MarketIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl D-137 Test Co',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        config(['active-company-id' => $this->company->id]);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'market-intelligence.index']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'market-intelligence.update-product-price']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.index']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.create']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.approve']));

        $this->service = app(MarketIntelligenceService::class);

        // Seed authoritative baseline USD quote (2,719,050 Rials = 271,905 Tomans)
        MarketQuote::create([
            'company_id' => $this->company->id,
            'symbol' => 'USD',
            'title_fa' => 'دلار آمریکا',
            'category' => 'currency',
            'price' => 2719050.0,
            'source' => 'online',
        ]);
    }

    public function test_usd_pegged_valuation_calculates_replacement_cost_correctly(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'پاوربانک ۲۰۰۰۰ شیائومی',
            'code' => 'PB-X20',
            'type' => 'general',
            'average_cost' => 27190500.0, // 27,190,500 Rials (~$10.00 at 2,719,050 rate)
            'selling_price' => 28000000.0, // Selling at 28,000,000 Rials
            'quantity' => 10,
        ]);

        ProductMarketPrice::create([
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'purchase_usd_rate' => 2719050.0,
            'base_usd_cost' => 10.0, // $10 per unit
            'target_margin_percent' => 20.0,
            'is_pegged_to_usd' => true,
        ]);

        // Scenario 1: USD rises to 3,000,000 Rials
        $valuation = $this->service->calculateUsdPeggedValuation($product, 3000000.0);

        // Replacement cost = $10 * 3,000,000 = 30,000,000 Rials
        $this->assertEquals(30000000.0, $valuation['replacement_cost_irr']);
        // Suggested selling price with 20% margin = 30,000,000 * 1.2 = 36,000,000 Rials
        $this->assertEquals(36000000.0, $valuation['suggested_selling_price_irr']);
        // Selling price (28M) < Replacement cost (30M) -> Capital erosion danger!
        $this->assertTrue($valuation['is_below_replacement']);
        $this->assertTrue($valuation['is_below_suggested']);
        $this->assertEquals(2000000.0, $valuation['capital_erosion_per_unit']);
        $this->assertEquals(20000000.0, $valuation['total_capital_erosion']);
    }

    public function test_selling_price_at_or_above_suggested_is_safe(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'هدفون انکر اسپیس وان',
            'code' => 'HD-ANK-1',
            'type' => 'general',
            'average_cost' => 27190500.0,
            'selling_price' => 38000000.0, // Above suggested price
            'quantity' => 5,
        ]);

        ProductMarketPrice::create([
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'purchase_usd_rate' => 2719050.0,
            'base_usd_cost' => 10.0,
            'target_margin_percent' => 20.0,
            'is_pegged_to_usd' => true,
        ]);

        $valuation = $product->usdPeggedValuation(3000000.0);

        $this->assertFalse($valuation['is_below_replacement']);
        $this->assertFalse($valuation['is_below_suggested']);
        $this->assertEquals(0.0, $valuation['capital_erosion_per_unit']);
    }

    public function test_market_intelligence_inventory_comparison_returns_d137_metrics(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'لپ‌تاپ لنوو تینک‌پد',
            'code' => 'NB-LEN-T14',
            'type' => 'general',
            'average_cost' => 543810000.0, // $200 at 2,719,050
            'selling_price' => 600000000.0,
            'quantity' => 2,
        ]);

        ProductMarketPrice::create([
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'purchase_usd_rate' => 2719050.0,
            'base_usd_cost' => 200.0,
            'target_margin_percent' => 25.0,
            'is_pegged_to_usd' => true,
        ]);

        $comparison = $this->service->getInventoryMarketComparison($this->company->id, 'NB-LEN-T14');

        $this->assertCount(1, $comparison);
        $item = $comparison[0];
        $this->assertEquals(200.0, $item['base_usd_cost']);
        $this->assertArrayHasKey('replacement_cost_irr', $item);
        $this->assertArrayHasKey('suggested_selling_price_irr', $item);
        $this->assertArrayHasKey('is_below_replacement', $item);
    }

    public function test_update_product_market_price_persists_usd_pegging_fields(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'مانیتور گیمینگ ایسوس',
            'code' => 'MN-ASUS-27',
            'type' => 'general',
            'average_cost' => 100000000.0,
            'selling_price' => 120000000.0,
            'quantity' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('market-intelligence.update-product-price', $product->id), [
                'purchase_usd_rate' => 2800000.0,
                'base_usd_cost' => 50.0,
                'target_margin_percent' => 30.0,
                'is_pegged_to_usd' => 1,
                'market_price' => 150000000.0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_market_prices', [
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'purchase_usd_rate' => 2800000.0,
            'base_usd_cost' => 50.0,
            'target_margin_percent' => 30.0,
            'is_pegged_to_usd' => 1,
        ]);
    }
}
