<?php

namespace Tests\Feature;

use App\Enums\DailySettlementStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class MobileFieldCompanionTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected int $companyId;

    protected Warehouse $warehouse;

    protected Customer $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->companyId, 'code' => 'MAIN'],
            ['name' => 'انبار مرکزی پخش']
        );

        $productGroup = ProductGroup::factory()->withSubjects()->create(['name' => 'گروه کالایی', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'مشتریان پخش', 'company_id' => $this->companyId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'فروشگاه سپهر',
            'mobile' => '09123456789',
        ]);

        $this->product = Product::factory()->create([
            'company_id' => $this->companyId,
            'code' => '6260123456',
            'name' => 'کالای تست بارکد',
            'selling_price' => 50000,
        ]);

        WarehouseProductStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 40,
        ]);

        $this->user = User::factory()->create(['name' => 'ویزیتور نمونه']);
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
    }

    public function test_mobile_index_screen_renders_successfully(): void
    {
        $response = $this->get(route('mobile.index'));

        $response->assertOk();
        $response->assertSee('دخل همراه');
        $response->assertSee('ویزیتور پخش');
    }

    public function test_mobile_manifest_returns_proper_pwa_json(): void
    {
        $response = $this->get(route('mobile.manifest'));

        $response->assertOk();
        $response->assertJson([
            'short_name' => 'دخل همراه',
            'display' => 'standalone',
            'dir' => 'rtl',
        ]);
    }

    public function test_mobile_service_worker_returns_javascript(): void
    {
        $response = $this->get(route('mobile.sw'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');
        $response->assertSee('CACHE_NAME');
    }

    public function test_mobile_catalog_endpoint_returns_products_with_stock(): void
    {
        $response = $this->getJson(route('mobile.catalog', ['warehouse_id' => $this->warehouse->id]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $response->json('count'));

        $found = collect($response->json('products'))->firstWhere('id', $this->product->id);
        $this->assertNotNull($found);
        $this->assertSame(40.0, (float) $found['on_hand']);
        $this->assertSame(50000.0, (float) $found['selling_price']);
    }

    public function test_mobile_visits_endpoint_returns_route_customers(): void
    {
        $route = SalesRoute::create([
            'company_id' => $this->companyId,
            'title' => 'مسیر بازار تهران',
            'code' => 'R-01',
            'is_active' => true,
        ]);

        $route->routeCustomers()->create([
            'customer_id' => $this->customer->id,
            'visit_priority' => 1,
        ]);

        VisitPlan::create([
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'route_id' => $route->id,
            'plan_date' => today(),
            'status' => 'planned',
        ]);

        $response = $this->getJson(route('mobile.visits'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('route_title', 'مسیر بازار تهران');

        $customers = $response->json('customers');
        $this->assertNotEmpty($customers);
        $this->assertSame($this->customer->id, $customers[0]['id']);
    }

    public function test_mobile_store_order_creates_invoice(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'price' => 50000,
                ],
            ],
            'payment_method' => 'cash',
            'notes' => 'تحویل فوری پای ماشین',
        ];

        $response = $this->postJson(route('mobile.orders.store'), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $invoiceId = $response->json('invoice_id');
        $this->assertNotNull($invoiceId);

        $invoice = Invoice::find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::SELL, $invoice->invoice_type);
        $this->assertSame($this->customer->id, $invoice->customer_id);
        $this->assertEquals(250000.0, (float) $invoice->amount);
    }

    public function test_mobile_scan_barcode_returns_product_details(): void
    {
        $response = $this->getJson(route('mobile.scan', ['code' => '6260123456']));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('product.id', $this->product->id);
        $this->assertEquals(40, $response->json('product.total_on_hand'));
    }

    public function test_mobile_scan_barcode_returns_404_when_not_found(): void
    {
        $response = $this->getJson(route('mobile.scan', ['code' => '0000000000']));

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_mobile_record_stock_count_calculates_variance(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'counted_quantity' => 38, // 40 on-hand, 38 counted -> variance -2
        ];

        $response = $this->postJson(route('mobile.stock-count'), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(40, $response->json('system_quantity'));
        $this->assertEquals(38, $response->json('counted_quantity'));
        $this->assertEquals(-2, $response->json('variance'));
    }

    public function test_mobile_submit_settlement_creates_or_updates_daily_settlement(): void
    {
        $payload = [
            'cash_collected' => 500000,
            'pos_collected' => 1200000,
            'cheque_collected' => 300000,
        ];

        $response = $this->postJson(route('mobile.settlement.submit'), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(2000000, $response->json('settlement.total_collected'));

        $this->assertDatabaseHas('daily_settlements', [
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'status' => DailySettlementStatus::SUBMITTED->value,
            'cash_collected' => 500000,
            'pos_collected' => 1200000,
            'cheque_collected' => 300000,
        ]);
    }
}
