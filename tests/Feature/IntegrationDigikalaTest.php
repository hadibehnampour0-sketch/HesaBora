<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\Integration\Adapters\DigikalaAdapter;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\IntegrationEventType;
use App\Services\Integration\IntegrationInventoryPushService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class IntegrationDigikalaTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

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
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId, 'mobile' => '09120000000']);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function connection(array $overrides = []): IntegrationConnection
    {
        $group = CustomerGroup::where('company_id', $this->companyId)->first();
        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->companyId, 'code' => 'MAIN'],
            ['name' => 'انبار اصلی']
        );

        return IntegrationConnection::create(array_merge([
            'source_system' => 'digikala',
            'name' => 'Digikala Seller',
            'credentials' => [
                'site_url' => 'https://seller.digikala.com',
                'api_key' => 'dk_api_test_key_12345',
                'seller_id' => '98765',
                'webhook_secret' => 'digi_secret_xyz',
            ],
            'default_warehouse_id' => $warehouse->id,
            'default_customer_group_id' => $group->id,
            'price_tolerance_percent' => 5.0,
            'is_active' => true,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_digikala_adapter_normalizes_valid_order_into_canonical_event(): void
    {
        $conn = $this->connection();
        $adapter = new DigikalaAdapter($conn);

        $payload = [
            'order_id' => 778899,
            'order_number' => 'DK-778899',
            'status' => 'processing',
            'customer' => [
                'name' => 'محمد رضایی',
                'mobile' => '09121112233',
                'city' => 'تهران',
            ],
            'order_items' => [
                [
                    'variant_id' => '102030',
                    'seller_code' => 'SKU-DK-1',
                    'product_title' => 'هدفون بی‌سیم',
                    'quantity' => 2,
                    'selling_price' => 2500000,
                ],
            ],
        ];

        $events = $adapter->normalize($payload, []);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('digikala', $event->source);
        $this->assertSame('778899', $event->externalId);
        $this->assertSame(IntegrationEventType::ORDER_CREATED, $event->type);
        $this->assertSame('DK-778899', $event->payload['order_number']);
        $this->assertSame('محمد رضایی', $event->payload['customer']['name']);
        $this->assertCount(1, $event->payload['lines']);
        $this->assertSame('102030', $event->payload['lines'][0]['external_product_id']);
        $this->assertSame(2.0, $event->payload['lines'][0]['quantity']);
        $this->assertSame(2500000.0, $event->payload['lines'][0]['unit_price']);
    }

    public function test_digikala_adapter_normalizes_cancelled_order(): void
    {
        $conn = $this->connection();
        $adapter = new DigikalaAdapter($conn);

        $payload = [
            'order_id' => 778899,
            'order_number' => 'DK-778899',
            'status' => 'cancelled',
        ];

        $events = $adapter->normalize($payload, []);

        $this->assertCount(1, $events);
        $this->assertSame(IntegrationEventType::ORDER_CANCELLED, $events[0]->type);
    }

    public function test_digikala_adapter_rejects_invalid_payload(): void
    {
        $conn = $this->connection();
        $adapter = new DigikalaAdapter($conn);

        $this->expectException(InvalidArgumentException::class);
        $adapter->normalize(['unknown_field' => 'value'], []);
    }

    public function test_digikala_adapter_verifies_signature_when_present(): void
    {
        $conn = $this->connection(['credentials' => [
            'api_key' => 'key',
            'seller_id' => '1',
            'webhook_secret' => 'mysecret',
        ]]);
        $adapter = new DigikalaAdapter($conn);

        $body = json_encode(['order_id' => 123, 'order_items' => []]);
        $validSignature = base64_encode(hash_hmac('sha256', $body, 'mysecret', true));

        $events = $adapter->normalize(['order_id' => 123, 'order_items' => []], ['x-dk-signature' => $validSignature], $body);
        $this->assertCount(1, $events);

        $this->expectException(InvalidArgumentException::class);
        $adapter->normalize(['order_id' => 123, 'order_items' => []], ['x-dk-signature' => 'invalid_sig'], $body);
    }

    public function test_push_inventory_to_digikala_makes_proper_put_request(): void
    {
        Http::fake([
            'https://seller.digikala.com/open-api/v1/variants/102030/' => Http::response(['status' => 'ok'], 200),
        ]);

        $conn = $this->connection();
        $adapter = new DigikalaAdapter($conn);

        $adapter->pushInventory('102030', 15.0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://seller.digikala.com/open-api/v1/variants/102030/'
                && $request->method() === 'PUT'
                && $request->header('api-key')[0] === 'dk_api_test_key_12345'
                && $request['seller_stock'] === 15;
        });
    }

    public function test_push_inventory_service_integrates_with_digikala(): void
    {
        Http::fake([
            'https://seller.digikala.com/open-api/v1/variants/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $conn = $this->connection();
        $product = Product::factory()->create(['company_id' => $this->companyId, 'selling_price' => 100000]);

        WarehouseProductStock::create([
            'warehouse_id' => $conn->default_warehouse_id,
            'product_id' => $product->id,
            'quantity' => 25,
        ]);

        app(ExternalReferenceService::class)->link('digikala', 'var_999', 'product', $product->id);

        $service = app(IntegrationInventoryPushService::class);
        $result = $service->pushAll($conn);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_digikala_webhook_endpoint_ingests_order(): void
    {
        $conn = $this->connection(['credentials' => [
            'api_key' => 'key',
            'seller_id' => '1',
            'webhook_secret' => '',
        ]]);

        $product = Product::factory()->create([
            'company_id' => $this->companyId,
            'selling_price' => 100000,
        ]);

        WarehouseProductStock::create([
            'warehouse_id' => $conn->default_warehouse_id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        app(ExternalReferenceService::class)->link('digikala', 'dk_var_123', 'product', $product->id);

        $payload = [
            'order_id' => 990011,
            'order_number' => 'DK-990011',
            'customer' => [
                'name' => 'خریدار دیجی‌کالا',
                'mobile' => '09350001122',
            ],
            'order_items' => [
                [
                    'variant_id' => 'dk_var_123',
                    'product_title' => $product->name,
                    'quantity' => 2,
                    'selling_price' => 100000,
                ],
            ],
        ];

        $response = $this->postJson(route('integrations.digikala.webhook', $conn->webhook_token), $payload);

        $response->assertStatus(200);

        $this->assertSame(1, Invoice::count());
        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->companyId,
            'invoice_type' => InvoiceType::SELL->value,
        ]);
    }

    public function test_test_connection_action_for_digikala(): void
    {
        Http::fake([
            'https://seller.digikala.com/open-api/v1/variants/?size=1' => Http::response(['data' => []], 200),
        ]);

        $this->grant('integrations.connections.test');
        $conn = $this->connection();

        $response = $this->post(route('integrations.connections.test', $conn));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
