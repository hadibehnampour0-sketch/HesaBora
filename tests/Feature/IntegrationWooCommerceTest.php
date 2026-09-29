<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\Integration\Adapters\WooCommerceAdapter;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\IntegrationEventType;
use App\Services\Integration\IntegrationHub;
use App\Services\Integration\IntegrationHubFactory;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md: the WooCommerce adapter, the
 * auto-approve-or-review-queue handler, and the public webhook endpoint end to end.
 */
class IntegrationWooCommerceTest extends TestCase
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

    private function connection(array $overrides = []): IntegrationConnection
    {
        $group = CustomerGroup::where('company_id', $this->companyId)->first();
        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->companyId, 'code' => 'MAIN'],
            ['name' => 'انبار اصلی']
        );

        return IntegrationConnection::create(array_merge([
            'source_system' => 'woocommerce',
            'name' => 'My Shop',
            'credentials' => [
                'site_url' => 'https://example-shop.test',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
                'webhook_secret' => 'whsec_test',
            ],
            'default_warehouse_id' => $warehouse->id,
            'default_customer_group_id' => $group?->id,
            'price_tolerance_percent' => 2,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function orderPayload(array $lineOverrides = [], array $overrides = []): array
    {
        return array_merge([
            'id' => 5001,
            'number' => '5001',
            'currency' => 'IRT',
            'customer_id' => 0,
            'billing' => ['first_name' => 'Sara', 'last_name' => 'Ahmadi', 'phone' => '09120000000', 'email' => 's@example.com'],
            'line_items' => [array_merge([
                'product_id' => 900,
                'sku' => 'SKU-1',
                'name' => 'Widget',
                'quantity' => 2,
                'price' => 1000,
            ], $lineOverrides)],
        ], $overrides);
    }

    private function signedHeaders(array $payload, string $secret): array
    {
        $body = json_encode($payload);

        return ['x-wc-webhook-signature' => base64_encode(hash_hmac('sha256', $body, $secret, true))];
    }

    // --- Adapter -------------------------------------------------------------------

    public function test_the_adapter_rejects_a_bad_signature(): void
    {
        $connection = $this->connection();
        $adapter = new WooCommerceAdapter($connection);
        $payload = $this->orderPayload();

        $this->expectException(InvalidArgumentException::class);
        $adapter->normalize($payload, ['x-wc-webhook-signature' => 'not-the-real-signature'], json_encode($payload));
    }

    public function test_the_adapter_normalizes_a_valid_order_payload(): void
    {
        $connection = $this->connection();
        $adapter = new WooCommerceAdapter($connection);
        $payload = $this->orderPayload();
        $body = json_encode($payload);

        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $this->assertCount(1, $events);
        $this->assertSame('5001', $events[0]->externalId);
        $this->assertSame('Sara Ahmadi', $events[0]->payload['customer']['name']);
        $this->assertSame(2.0, $events[0]->payload['lines'][0]['quantity']);
    }

    // --- Handler: auto-approve happy path -------------------------------------------

    public function test_a_clean_order_auto_creates_and_approves_an_invoice_and_decrements_stock(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PROCESSED, $result['outcome']);
        $invoice = Invoice::latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        // Matched the existing customer by phone rather than creating a duplicate "Sara Ahmadi".
        $this->assertSame($this->customer->id, $invoice->customer_id);

        $warehouse = Warehouse::withoutGlobalScopes()->where('company_id', $this->companyId)->first();
        $this->assertEqualsWithDelta(8.0, (float) WarehouseProductStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'), 0.001);
    }

    public function test_a_repeated_webhook_delivery_does_not_create_a_second_invoice(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $hub = app(IntegrationHubFactory::class)->make();
        $hub->ingest($events[0]);
        $second = $hub->ingest($events[0]);

        $this->assertSame(IntegrationHub::DUPLICATE, $second['outcome']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_genuinely_new_customer_is_auto_created_when_no_phone_matches(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload([], ['billing' => ['first_name' => 'Sara', 'last_name' => 'Ahmadi', 'phone' => '09339999999', 'email' => 'sara@example.com']]);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PROCESSED, $result['outcome']);
        $invoice = Invoice::latest('id')->first();
        $this->assertSame('Sara Ahmadi', $invoice->customer->name);
        $this->assertNotSame($this->customer->id, $invoice->customer_id);
    }

    // --- Handler: review-queue (ambiguous) cases -------------------------------------

    public function test_an_unmapped_product_goes_to_the_review_queue_not_a_guess(): void
    {
        $connection = $this->connection();
        // No product created/mapped at all, and the line's SKU matches nothing.

        $payload = $this->orderPayload(['sku' => 'DOES-NOT-EXIST']);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PENDING_DEPENDENCY, $result['outcome']);
        $this->assertSame(0, Invoice::count());
        $this->assertStringContainsString('وصل نشده', IntegrationEvent::first()->message);
    }

    public function test_a_price_mismatch_beyond_tolerance_goes_to_the_review_queue(): void
    {
        $connection = $this->connection(['price_tolerance_percent' => 2]);
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload(['price' => 2000]); // 100% off, well beyond 2% tolerance
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PENDING_DEPENDENCY, $result['outcome']);
        $this->assertSame(0, Invoice::count());
        $this->assertStringContainsString('اختلاف قیمت', IntegrationEvent::first()->message);
    }

    public function test_insufficient_stock_goes_to_the_review_queue(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 1, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload(['quantity' => 5]); // more than the 1 unit in stock
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PENDING_DEPENDENCY, $result['outcome']);
        $this->assertSame(0, Invoice::count());
        $this->assertStringContainsString('موجودی کافی', IntegrationEvent::first()->message);
    }

    public function test_fixing_the_mapping_and_retrying_processes_the_held_event(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SOME-OTHER-CODE', 'quantity' => 10, 'selling_price' => 1000]);
        // The order's SKU does not match any Dakhl product code, and nothing is mapped up front.

        $payload = $this->orderPayload(['sku' => 'NOT-A-MATCH']);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);
        $hub = app(IntegrationHubFactory::class)->make();
        $hub->ingest($events[0]);

        $this->assertSame(0, Invoice::count());

        // A human maps the product, exactly as the review-queue screen's form would.
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);
        $result = $hub->retryEvent(IntegrationEvent::firstOrFail());

        $this->assertSame(IntegrationHub::PROCESSED, $result['outcome']);
        $this->assertSame(1, Invoice::count());
    }

    // --- Public webhook endpoint end to end ------------------------------------------

    public function test_the_webhook_endpoint_accepts_a_correctly_signed_order(): void
    {
        $connection = $this->connection();
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $url = route('integrations.woocommerce.webhook', $connection->webhook_token);

        $response = $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, 'whsec_test', true)),
        ], $body);

        $response->assertOk();
        $this->assertSame(1, Invoice::count());
    }

    public function test_the_webhook_endpoint_rejects_a_bad_signature(): void
    {
        $connection = $this->connection();
        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $url = route('integrations.woocommerce.webhook', $connection->webhook_token);

        $response = $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-WC-Webhook-Signature' => 'forged',
        ], $body);

        $response->assertStatus(422);
        $this->assertSame(0, Invoice::count());
    }

    public function test_the_webhook_endpoint_404s_on_an_unknown_token(): void
    {
        $payload = $this->orderPayload();
        $url = route('integrations.woocommerce.webhook', 'not-a-real-token');

        $this->post($url, $payload)->assertNotFound();
    }

    public function test_a_connections_webhook_never_touches_another_companys_data(): void
    {
        $myConnection = $this->connection();
        $myProduct = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $myProduct->id);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        $otherWarehouse = Warehouse::create(['company_id' => $other->id, 'name' => 'Other WH']);
        $otherConnection = IntegrationConnection::create([
            'company_id' => $other->id,
            'source_system' => 'woocommerce',
            'name' => 'Other Shop',
            'credentials' => ['site_url' => 'https://other.test', 'consumer_key' => 'x', 'consumer_secret' => 'y', 'webhook_secret' => 'whsec_other'],
            'default_warehouse_id' => $otherWarehouse->id,
        ]);

        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $url = route('integrations.woocommerce.webhook', $otherConnection->webhook_token);

        $response = $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, 'whsec_other', true)),
        ], $body);

        // Held for review (no product mapped for the OTHER company), never posted, and never
        // able to see/touch company 1's product/stock/customer despite an identical payload.
        $response->assertOk();
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('company_id', $other->id)->count());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('company_id', $this->companyId)->count());
    }

    // --- Cancellation (order.updated -> status=cancelled) ---------------------------

    private function importOneOrder(IntegrationConnection $connection): Invoice
    {
        $product = $this->createProduct(['code' => 'SKU-1', 'quantity' => 10, 'selling_price' => 1000]);
        app(ExternalReferenceService::class)->link('woocommerce', '900', 'product', $product->id);

        $payload = $this->orderPayload();
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, $this->signedHeaders($payload, 'whsec_test'), $body);
        app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_a_cancelled_order_voids_the_invoice_that_was_created_for_it(): void
    {
        $connection = $this->connection();
        $invoice = $this->importOneOrder($connection);

        $cancelPayload = $this->orderPayload([], ['status' => 'cancelled']);
        $body = json_encode($cancelPayload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($cancelPayload, array_merge($this->signedHeaders($cancelPayload, 'whsec_test'), ['x-wc-webhook-topic' => 'order.updated']), $body);

        $this->assertCount(1, $events);
        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PROCESSED, $result['outcome']);
        $this->assertTrue($invoice->voidInvoice()->exists());
    }

    public function test_an_order_update_with_an_unhandled_status_produces_no_event(): void
    {
        $connection = $this->connection();
        $payload = $this->orderPayload([], ['status' => 'processing']);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);

        $events = $adapter->normalize($payload, array_merge($this->signedHeaders($payload, 'whsec_test'), ['x-wc-webhook-topic' => 'order.updated']), $body);

        $this->assertSame([], $events);
    }

    public function test_a_refund_notification_is_normalized_but_left_unhandled_for_a_human(): void
    {
        $connection = $this->connection();
        $invoice = $this->importOneOrder($connection);

        $payload = $this->orderPayload([], ['status' => 'refunded']);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, array_merge($this->signedHeaders($payload, 'whsec_test'), ['x-wc-webhook-topic' => 'order.updated']), $body);

        $this->assertSame(IntegrationEventType::REFUND_ISSUED, $events[0]->type);
        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::UNHANDLED, $result['outcome']);
        // Never guessed at: the original invoice is untouched.
        $this->assertFalse($invoice->fresh()->voidInvoice()->exists());
    }

    public function test_the_void_invoice_number_is_scoped_to_the_void_sequence_and_this_company_only(): void
    {
        // A regular sell invoice with a huge number, in the SAME company --
        // the void sequence must never be derived from it (the pre-fix bug
        // read `Invoice::withoutGlobalScopes()->max('number')` with no
        // `invoice_type` filter AND no company scope at all).
        $unrelatedProduct = $this->createProduct(['code' => 'SKU-UNRELATED', 'quantity' => 5, 'selling_price' => 1000]);
        $this->sell([$this->productItem($unrelatedProduct, 1, 1000)], true, 99999);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        $otherCustomer = Customer::factory()->create(['company_id' => $other->id]);
        // Invoice::creating() unconditionally stamps company_id from
        // getActiveCompany() -- the active company must actually be
        // switched for this fixture row to land where intended.
        config(['active-company-id' => $other->id]);
        Invoice::create([
            'customer_id' => $otherCustomer->id,
            'invoice_type' => InvoiceType::VOID,
            'status' => InvoiceStatus::APPROVED,
            'number' => 500_000,
            'date' => now()->toDateString(),
            'title' => 'other company void fixture',
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
        ]);
        config(['active-company-id' => $this->companyId]);

        $connection = $this->connection();
        $invoice = $this->importOneOrder($connection);

        $cancelPayload = $this->orderPayload([], ['status' => 'cancelled']);
        $body = json_encode($cancelPayload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($cancelPayload, array_merge($this->signedHeaders($cancelPayload, 'whsec_test'), ['x-wc-webhook-topic' => 'order.updated']), $body);
        app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $voidInvoice = $invoice->voidInvoice()->firstOrFail();
        $this->assertSame(1.0, (float) $voidInvoice->number, 'the void number must come from THIS company\'s own void sequence, not from an unrelated sell invoice or another company\'s void invoice');
    }

    public function test_cancelling_an_order_that_was_never_imported_is_held_for_review(): void
    {
        $connection = $this->connection();
        $payload = $this->orderPayload([], ['status' => 'cancelled']);
        $body = json_encode($payload);
        $adapter = new WooCommerceAdapter($connection);
        $events = $adapter->normalize($payload, array_merge($this->signedHeaders($payload, 'whsec_test'), ['x-wc-webhook-topic' => 'order.updated']), $body);

        $result = app(IntegrationHubFactory::class)->make()->ingest($events[0]);

        $this->assertSame(IntegrationHub::PENDING_DEPENDENCY, $result['outcome']);
    }
}
