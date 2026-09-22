<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Jobs\DeliverWebhookJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P6 (API v1 Platform). Webhooks are a pure, additive notification layer
 * fed by domain events (InvoiceStatusChanged/ChequeStatusChanged) that the
 * services themselves fire with no awareness webhooks exist at all -- see
 * AppServiceProvider's translation listeners. QUEUE_CONNECTION defaults to
 * `sync` in this app (config/queue.php), so DeliverWebhookJob's own
 * self-re-dispatch retry loop runs to exhaustion synchronously within a
 * single test call rather than waiting for real delayed execution -- see
 * DeliverWebhookJob's own docblock. Tests assert the resulting TERMINAL
 * state (delivered, or failed after 5 attempts), not an intermediate one.
 */
class WebhookTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8800;

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
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'webhooks.store']));
    }

    /**
     * Sanctum's Guard checks the WEB session guard before the bearer token
     * (Laravel\Sanctum\Guard::__invoke(), lines 32-38) -- actingAs() here
     * would silently hijack every later Bearer-token request in the same
     * test method, authenticating via a TransientToken (no `abilities`
     * property at all) instead of the real PersonalAccessToken. Scoped to
     * only the tests that trigger an invoice approval and need no token.
     */
    private function approveABuyInvoice(): void
    {
        $this->actingAs($this->user);
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, 100)], approved: false);
        $this->approveInvoice($result['invoice']);
    }

    public function test_a_registered_webhook_receives_a_signed_delivery_when_its_subscribed_event_fires(): void
    {
        Http::fake(['https://example.test/hook' => Http::response('ok', 200)]);

        $webhook = app(WebhookService::class)->register(
            $this->companyId,
            'https://example.test/hook',
            ['invoice.status_changed'],
            $this->user->id
        );

        $this->approveABuyInvoice();

        Http::assertSent(function ($request) use ($webhook) {
            $expectedSignature = WebhookService::signature($webhook->secret, $request->body());

            return $request->url() === 'https://example.test/hook'
                && $request->header('X-Webhook-Signature')[0] === $expectedSignature
                && $request['event'] === 'invoice.status_changed';
        });

        $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->firstOrFail();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
    }

    public function test_a_webhook_not_subscribed_to_the_event_receives_no_delivery(): void
    {
        Http::fake();

        app(WebhookService::class)->register(
            $this->companyId,
            'https://example.test/hook',
            ['cheque.status_changed'],
            $this->user->id
        );

        $this->approveABuyInvoice();

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_an_inactive_webhook_receives_no_delivery(): void
    {
        Http::fake();

        $webhook = app(WebhookService::class)->register(
            $this->companyId,
            'https://example.test/hook',
            ['invoice.status_changed'],
            $this->user->id
        );
        app(WebhookService::class)->deactivate($webhook);

        $this->approveABuyInvoice();

        Http::assertNothingSent();
    }

    public function test_a_failing_endpoint_is_retried_and_eventually_marked_failed(): void
    {
        Http::fake(['https://example.test/hook' => Http::response('error', 500)]);

        $webhook = app(WebhookService::class)->register(
            $this->companyId,
            'https://example.test/hook',
            ['invoice.status_changed'],
            $this->user->id
        );

        $this->approveABuyInvoice();

        $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(5, $delivery->attempt_count);
        $this->assertNull($delivery->next_retry_at);
        Http::assertSentCount(5);
    }

    public function test_delivering_to_a_missing_delivery_id_is_a_safe_no_op(): void
    {
        Http::fake();

        (new DeliverWebhookJob(999999))->handle();

        Http::assertNothingSent();
    }

    public function test_webhook_registration_returns_the_secret_at_creation(): void
    {
        foreach (['webhooks.store', 'api.access'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $response = $this->postJson("/api/v1/companies/{$this->companyId}/webhooks", [
            'url' => 'https://example.test/hook',
            'event_types' => ['invoice.status_changed'],
        ], $this->tokenHeaders(['webhooks.store', 'api.access']));

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'url', 'event_types', 'secret']]);
    }

    public function test_webhook_index_never_exposes_the_secret(): void
    {
        app(WebhookService::class)->register($this->companyId, 'https://example.test/hook', ['invoice.status_changed'], $this->user->id);

        foreach (['webhooks.index', 'api.access'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $indexResponse = $this->getJson("/api/v1/companies/{$this->companyId}/webhooks", $this->tokenHeaders(['webhooks.index', 'api.access']));
        $indexResponse->assertOk();
        $this->assertArrayNotHasKey('secret', $indexResponse->json('data.0'));
    }

    public function test_webhooks_are_isolated_per_company(): void
    {
        $otherCompany = Company::factory()->create();

        Webhook::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'url' => 'https://example.test/other',
            'secret' => 'x',
            'event_types' => ['invoice.status_changed'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        app(WebhookService::class)->register($this->companyId, 'https://example.test/mine', ['invoice.status_changed'], $this->user->id);

        foreach (['webhooks.index', 'api.access'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $response = $this->getJson("/api/v1/companies/{$this->companyId}/webhooks", $this->tokenHeaders(['webhooks.index', 'api.access']));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('https://example.test/mine', $response->json('data.0.url'));
    }

    /**
     * Grants must happen BEFORE any HTTP call in the test, never after an
     * Auth::forgetGuards() -- Spatie's own guard resolution for
     * Permission::firstOrCreate()/givePermissionTo() otherwise throws
     * GuardDoesNotMatch, empirically found while writing this suite. This
     * helper only ever creates the token itself.
     */
    private function tokenHeaders(array $abilities): array
    {
        $token = $this->user->createToken('test', $abilities)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
