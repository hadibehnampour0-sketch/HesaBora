<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function service(): MarketIntelligenceService
    {
        return app(MarketIntelligenceService::class);
    }

    public function test_baseline_quotes_are_initialized_automatically(): void
    {
        $quotes = $this->service()->getQuotes($this->company->id);

        $this->assertNotEmpty($quotes);
        $this->assertNotNull($quotes->firstWhere('symbol', 'USD'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'EUR'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'AED'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'GBP'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'CNY'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'GOLD_18K'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'GOLD_24K'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'GOLD_MAZANEH'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'COIN_EMAMI'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'SILVER'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'USDT'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'BTC'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'QAR'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'SAR'));
        $this->assertNotNull($quotes->firstWhere('symbol', 'AZN'));
    }

    public function test_home_page_displays_compact_market_ticker(): void
    {
        $this->grant('home', 'documents.show', 'invoices.index', 'products.index');

        $this->get('/')
            ->assertOk()
            ->assertSee('مظنه زنده بازار و قیمت‌های روز', false)
            ->assertSee('دلار آمریکا', false);
    }

    public function test_manual_quote_update_computes_fluctuation_and_records_user(): void
    {
        $quote = $this->service()->updateQuoteManually('USD', 950000, $this->company->id, $this->user->id);

        $this->assertSame(950000.0, (float) $quote->price);
        $this->assertSame('manual', $quote->source);
        $this->assertSame($this->user->id, $quote->recorded_by_user_id);

        // Update again with higher price: 1,000,000
        $updated = $this->service()->updateQuoteManually('USD', 1000000, $this->company->id, $this->user->id);
        $this->assertSame(950000.0, (float) $updated->previous_price);
        $this->assertSame(50000.0, (float) $updated->change_amount);
        $this->assertEqualsWithDelta(5.26, (float) $updated->change_percent, 0.01);
    }

    public function test_product_market_price_and_spread_calculation(): void
    {
        $product = Product::create([
            'name' => 'یخچال ساید بای ساید',
            'code' => 'P-REF-01',
            'average_cost' => 50000000,
            'selling_price' => 55000000,
            'company_id' => $this->company->id,
            'quantity' => 10,
        ]);

        $this->service()->updateProductMarketPrice(
            $product->id,
            $this->company->id,
            [
                'market_price' => 60000000,
                'market_source_url' => 'https://torob.com/p/12345',
                'is_wholesale_broadcast' => true,
                'wholesale_price' => 54000000,
                'wholesale_min_qty' => 5,
                'wholesale_note' => 'تحویل پای انبار',
                'guild_category' => 'home_appliances',
            ],
            $this->user->id
        );

        $comparison = $this->service()->getInventoryMarketComparison($this->company->id);
        $item = collect($comparison)->firstWhere('product_id', $product->id);

        $this->assertNotNull($item);
        $this->assertSame(60000000.0, (float) $item['market_price']);
        $this->assertSame(10000000.0, (float) $item['spread_amount']);
        $this->assertSame(20.0, (float) $item['spread_percent']);
        $this->assertSame('https://torob.com/p/12345', $item['market_source_url']);
        $this->assertTrue($item['is_wholesale_broadcast']);
        $this->assertSame(54000000.0, (float) $item['wholesale_price']);
    }

    public function test_b2b_guild_wholesale_broadcast_network(): void
    {
        $wholesalerProduct = Product::create([
            'name' => 'روغن موتور قوطی ۴ لیتری',
            'code' => 'OIL-04L',
            'selling_price' => 3000000,
            'company_id' => $this->company->id,
            'quantity' => 200,
        ]);

        $this->service()->updateProductMarketPrice(
            $wholesalerProduct->id,
            $this->company->id,
            [
                'is_wholesale_broadcast' => true,
                'wholesale_price' => 2500000,
                'wholesale_min_qty' => 24,
                'wholesale_note' => 'کارتن ۶ عددی، نقد',
                'guild_category' => 'auto_parts',
            ],
            $this->user->id
        );

        $broadcasts = $this->service()->getGuildBroadcastQuotations('auto_parts');
        $this->assertCount(1, $broadcasts);
        $this->assertSame('روغن موتور قوطی ۴ لیتری', $broadcasts[0]['product_name']);
        $this->assertSame(2500000.0, (float) $broadcasts[0]['wholesale_price']);
        $this->assertSame(24.0, (float) $broadcasts[0]['wholesale_min_qty']);

        // Filter by another guild should not return it
        $empty = $this->service()->getGuildBroadcastQuotations('gold');
        $this->assertEmpty($empty);
    }

    public function test_http_endpoints_render_and_update_successfully(): void
    {
        $this->grant(
            'access-super-admin-panel',
            'market-intelligence.index',
            'market-intelligence.sync-online',
            'market-intelligence.update-quote',
            'market-intelligence.update-product-price',
            'market-intelligence.api-quotes'
        );

        $this->get(route('market-intelligence.index'))
            ->assertOk()
            ->assertSee('رصد زنده بازار', false);

        $this->post(route('market-intelligence.update-quote', 'USD'), ['price' => '960000'])
            ->assertRedirect();

        $this->assertSame(960000.0, (float) MarketQuote::where('symbol', 'USD')->first()->price);

        $this->getJson(route('market-intelligence.api-quotes'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_provider_configuration_and_connection_testing(): void
    {
        $this->grant(
            'access-super-admin-panel',
            'market-intelligence.save-settings',
            'market-intelligence.test-connection'
        );

        $this->post(route('market-intelligence.save-settings'), [
            'provider' => 'navasan',
            'api_key' => 'test_key_12345',
        ])->assertRedirect();

        $config = $this->service()->getProviderConfig($this->company->id);
        $this->assertSame('navasan', $config['provider']);
        $this->assertSame('test_key_12345', $config['api_key']);

        $this->postJson(route('market-intelligence.test-connection'), [
            'provider' => 'navasan',
            'api_key' => 'test_key_12345',
        ])->assertOk()
            ->assertJsonPath('success', true);

        // Missing key for navasan should fail
        $this->postJson(route('market-intelligence.test-connection'), [
            'provider' => 'navasan',
            'api_key' => '',
        ])->assertOk()
            ->assertJsonPath('success', false);
    }
}
