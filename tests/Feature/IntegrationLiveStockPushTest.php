<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integration\ExternalReferenceService;
use App\Services\WarehouseService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * The live half of the two-way stock sync: WarehouseProductStockObserver -> PushProductStockJob
 * fires whenever ANY existing service changes a mapped product's stock, with zero changes to
 * those services themselves.
 */
class IntegrationLiveStockPushTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9500;

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

    private function connectedProduct(int $quantity): Product
    {
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => $quantity]);
        IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'My Shop', 'is_active' => true,
            'credentials' => ['site_url' => 'https://myshop.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh'],
        ]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        return $product;
    }

    public function test_selling_a_mapped_product_pushes_its_new_quantity_live(): void
    {
        Http::fake(['*' => Http::response(['id' => 900], 200)]);
        $product = $this->connectedProduct(10);

        $this->sell([$this->productItem($product, 3, 100)], true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/products/900') && $request['stock_quantity'] === 7);
    }

    public function test_a_warehouse_transfer_of_a_mapped_product_pushes_the_unchanged_total(): void
    {
        Http::fake(['*' => Http::response(['id' => 900], 200)]);
        $product = $this->connectedProduct(10);
        $secondWarehouse = Warehouse::create(['company_id' => $this->companyId, 'name' => 'Second']);
        $mainWarehouse = Warehouse::withoutGlobalScopes()->where('company_id', $this->companyId)->where('code', 'MAIN')->firstOrFail();

        app(WarehouseService::class)->transfer($product, $mainWarehouse, $secondWarehouse, 4);

        // Total across both warehouses is unchanged by a transfer -- still 10.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/products/900') && $request['stock_quantity'] === 10);
    }

    public function test_an_unmapped_products_stock_change_never_calls_the_platform(): void
    {
        Http::fake(['*' => Http::response(['id' => 999], 200)]);
        // A connection exists, but this product is never mapped to it.
        IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'My Shop', 'is_active' => true,
            'credentials' => ['site_url' => 'https://myshop.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh'],
        ]);
        $product = $this->createProduct(['code' => 'SKU-UNMAPPED', 'quantity' => 10]);

        $this->sell([$this->productItem($product, 1, 100)], true);

        Http::assertNothingSent();
    }

    public function test_with_no_active_connection_at_all_no_http_call_is_ever_attempted(): void
    {
        Http::fake(['*' => Http::response(['id' => 900], 200)]);
        // No IntegrationConnection row exists at all -- the observer's cheap early exit applies.
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10]);

        $this->sell([$this->productItem($product, 1, 100)], true);

        Http::assertNothingSent();
    }
}
