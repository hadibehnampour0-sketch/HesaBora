<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\IntegrationHub;
use App\Services\Integration\IntegrationInventoryPushService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** Settings (connections) and review-queue screens, plus the outbound inventory-push service. */
class IntegrationConnectionsAndQueueTest extends TestCase
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
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

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

    // --- Connections screen -----------------------------------------------------------

    public function test_the_connections_screen_needs_its_own_permission(): void
    {
        $this->get(route('integrations.connections.index'))->assertForbidden();
    }

    public function test_a_connection_can_be_created_with_encrypted_credentials_and_a_unique_webhook_token(): void
    {
        $this->grant('integrations.connections.index', 'integrations.connections.store');

        $this->post(route('integrations.connections.store'), [
            'source_system' => 'woocommerce',
            'name' => 'My Shop',
            'site_url' => 'https://myshop.test',
            'consumer_key' => 'ck_x',
            'consumer_secret' => 'cs_x',
            'webhook_secret' => 'whsec_x',
        ])->assertSessionHasNoErrors();

        $connection = IntegrationConnection::firstOrFail();
        $this->assertSame('ck_x', $connection->credentials['consumer_key']);
        $this->assertNotEmpty($connection->webhook_token);

        // Credentials are stored encrypted at rest, not as plain JSON.
        $raw = \DB::table('integration_connections')->where('id', $connection->id)->value('credentials');
        $this->assertStringNotContainsString('ck_x', $raw);

        $this->get(route('integrations.connections.index'))->assertOk()->assertSee('My Shop');
    }

    public function test_a_connection_can_be_updated_and_deactivated(): void
    {
        $this->grant('integrations.connections.index', 'integrations.connections.store', 'integrations.connections.update');
        $this->post(route('integrations.connections.store'), [
            'source_system' => 'woocommerce', 'name' => 'My Shop', 'site_url' => 'https://myshop.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh',
        ]);
        $connection = IntegrationConnection::firstOrFail();

        $this->put(route('integrations.connections.update', $connection), ['price_tolerance_percent' => 5])
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(5.0, (float) $connection->fresh()->price_tolerance_percent, 0.001);
        $this->assertFalse($connection->fresh()->is_active); // is_active defaults to false in the request unless the checkbox is sent
    }

    public function test_a_connection_can_be_removed(): void
    {
        $this->grant('integrations.connections.index', 'integrations.connections.store', 'integrations.connections.destroy');
        $this->post(route('integrations.connections.store'), [
            'source_system' => 'woocommerce', 'name' => 'My Shop', 'site_url' => 'https://myshop.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh',
        ]);
        $connection = IntegrationConnection::firstOrFail();

        $this->delete(route('integrations.connections.destroy', $connection))->assertSessionHasNoErrors();

        $this->assertSame(0, IntegrationConnection::count());
    }

    public function test_another_companys_connections_are_never_listed(): void
    {
        $this->grant('integrations.connections.index');

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'Other Shop',
            'credentials' => ['site_url' => 'https://x.test', 'consumer_key' => 'a', 'consumer_secret' => 'b', 'webhook_secret' => 'c'],
        ]);
        config(['active-company-id' => $this->companyId]);

        $this->get(route('integrations.connections.index'))->assertOk()->assertDontSee('Other Shop');
    }

    public function test_pushing_inventory_calls_the_platform_for_every_mapped_product(): void
    {
        $this->grant('integrations.connections.index', 'integrations.connections.push-inventory');
        Http::fake(['*' => Http::response(['id' => 900], 200)]);

        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 7]);
        $connection = IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'My Shop',
            'credentials' => ['site_url' => 'https://myshop.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh'],
        ]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $this->post(route('integrations.connections.push-inventory', $connection))->assertSessionHasNoErrors();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/products/900') && $request['stock_quantity'] === 7;
        });
    }

    public function test_the_inventory_push_service_reports_failures_without_throwing(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 3]);
        $connection = IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'My Shop',
            'credentials' => ['site_url' => 'https://myshop.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh'],
        ]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $result = app(IntegrationInventoryPushService::class)->pushAll($connection);

        $this->assertSame(0, $result['pushed']);
        $this->assertSame(1, $result['failed']);
    }

    // --- Review queue screen ------------------------------------------------------

    public function test_the_queue_screen_needs_its_own_permission(): void
    {
        $this->get(route('integrations.queue.index'))->assertForbidden();
    }

    public function test_the_queue_lists_held_events_and_hides_processed_ones(): void
    {
        $this->grant('integrations.queue.index');

        IntegrationEvent::create([
            'source_system' => 'woocommerce', 'external_id' => '1', 'event_type' => 'order_created',
            'payload_hash' => 'h1', 'payload' => [], 'status' => IntegrationHub::PENDING_DEPENDENCY,
            'message' => 'Product not mapped', 'attempts' => 1,
        ]);
        IntegrationEvent::create([
            'source_system' => 'woocommerce', 'external_id' => '2', 'event_type' => 'order_created',
            'payload_hash' => 'h2', 'payload' => [], 'status' => IntegrationHub::PROCESSED,
            'message' => 'ok', 'attempts' => 1, 'processed_at' => now(),
        ]);

        $this->get(route('integrations.queue.index'))
            ->assertOk()
            ->assertSee('Product not mapped')
            ->assertDontSee('external_id">2', false);
    }

    public function test_mapping_a_product_from_the_queue_screen_retries_and_resolves_the_event(): void
    {
        $this->grant('integrations.queue.index', 'integrations.queue.map-product');
        $product = $this->createProduct(['code' => 'SKU-2', 'quantity' => 10, 'selling_price' => 500]);
        IntegrationConnection::create([
            'source_system' => 'woocommerce', 'name' => 'My Shop',
            'credentials' => ['site_url' => 'https://myshop.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'webhook_secret' => 'wh'],
            'default_warehouse_id' => Warehouse::withoutGlobalScopes()->where('company_id', $this->companyId)->first()?->id,
            'default_customer_group_id' => CustomerGroup::where('company_id', $this->companyId)->first()?->id,
        ]);

        $event = IntegrationEvent::create([
            'source_system' => 'woocommerce', 'external_id' => '77', 'event_type' => 'order_created',
            'payload_hash' => 'h', 'status' => IntegrationHub::PENDING_DEPENDENCY, 'attempts' => 1,
            'payload' => [
                'order_number' => '77',
                'customer' => ['name' => 'Reza Karimi', 'phone' => '09121112233'],
                'lines' => [['external_product_id' => '901', 'sku' => 'UNRELATED', 'name' => 'Widget', 'quantity' => 1, 'unit_price' => 500]],
            ],
        ]);

        $this->post(route('integrations.queue.map-product'), [
            'source_system' => 'woocommerce',
            'external_id' => '901',
            'product_id' => $product->id,
            'event_id' => $event->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(IntegrationHub::PROCESSED, $event->fresh()->status);
        $this->assertSame(1, Invoice::count());
    }

    public function test_another_companys_queue_rows_are_never_visible(): void
    {
        $this->grant('integrations.queue.index');

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        IntegrationEvent::create([
            'source_system' => 'woocommerce', 'external_id' => 'x', 'event_type' => 'order_created',
            'payload_hash' => 'hx', 'payload' => [], 'status' => IntegrationHub::PENDING_DEPENDENCY,
            'message' => 'Secret other-company reason', 'attempts' => 1,
        ]);
        config(['active-company-id' => $this->companyId]);

        $this->get(route('integrations.queue.index'))->assertOk()->assertDontSee('Secret other-company reason');
    }
}
