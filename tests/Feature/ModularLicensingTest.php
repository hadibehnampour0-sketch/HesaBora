<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleLicensed;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ModularLicensingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected LicenseService $licenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl Licensing Test Co',
            'fiscal_year' => 1405,
        ]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        Cache::forever('active_company_id', $this->company->id);
        config(['active-company-id' => $this->company->id]);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'configs.index']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'configs.edit']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'license.*']));

        $this->licenseService = app(LicenseService::class);
    }

    public function test_system_auto_provisions_14_day_trial_on_fresh_setup(): void
    {
        // Ensure table is empty
        License::query()->delete();
        $this->licenseService->flushCache();

        $activeLicense = $this->licenseService->getActiveLicense();

        $this->assertNotNull($activeLicense);
        $this->assertEquals('trial', $activeLicense->edition);
        $this->assertTrue($activeLicense->isValid());
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_POS_RETAIL));
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_DISTRIBUTION));
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_AI_COPILOT));
        $this->assertGreaterThanOrEqual(13, $activeLicense->getDaysRemaining());
    }

    public function test_license_key_generation_and_tamper_detection(): void
    {
        $payload = [
            'edition' => 'professional',
            'customer_name' => 'فروشگاه آذرخش',
            'modules' => [
                LicenseService::MODULE_CORE_ACCOUNTING,
                LicenseService::MODULE_SALES_PURCHASE,
                LicenseService::MODULE_TAX_MOADIAN,
            ],
            'max_companies' => 3,
            'max_users' => 5,
            'expires_at' => now()->addYear()->toDateTimeString(),
        ];

        $key = $this->licenseService->issueLicenseKey($payload);
        $this->assertStringStartsWith('DAKHL-LIC-v1.', $key);

        // Verification of untampered key should succeed
        $result = $this->licenseService->verifyLicenseKey($key, null, false);
        $this->assertTrue($result['valid']);
        $this->assertEquals('professional', $result['payload']['edition']);
        $this->assertEquals('فروشگاه آذرخش', $result['payload']['customer_name']);

        // Tampering with payload should fail verification
        $parts = explode('.', $key);
        $tamperedPayload = base64_encode(json_encode(['edition' => 'enterprise', 'modules' => ['*']]));
        $tamperedKey = "DAKHL-LIC-v1.{$tamperedPayload}.{$parts[2]}";

        $tamperedResult = $this->licenseService->verifyLicenseKey($tamperedKey, null, false);
        $this->assertFalse($tamperedResult['valid']);
        $this->assertStringContainsString('امضای دیجیتال', $tamperedResult['reason']);
    }

    public function test_license_hardware_fingerprint_enforcement(): void
    {
        $currentHw = $this->licenseService->generateMachineFingerprint();
        $foreignHw = 'DAKHL-9999-8888-7777-6666';

        // Key locked to foreign hardware
        $keyForeign = $this->licenseService->issueLicenseKey([
            'edition' => 'basic',
            'hardware_fingerprint' => $foreignHw,
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
        ]);

        $foreignResult = $this->licenseService->verifyLicenseKey($keyForeign, null, true);
        $this->assertFalse($foreignResult['valid']);
        $this->assertStringContainsString('شناسه سخت‌افزاری', $foreignResult['reason']);

        // Key locked to this machine's hardware
        $keyCurrent = $this->licenseService->issueLicenseKey([
            'edition' => 'basic',
            'hardware_fingerprint' => $currentHw,
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
        ]);

        $currentResult = $this->licenseService->verifyLicenseKey($keyCurrent, null, true);
        $this->assertTrue($currentResult['valid']);
    }

    public function test_license_expiration_and_grace_period(): void
    {
        // 1. Expired 2 days ago with 7-day grace period => valid (in grace period)
        $expiredInGrace = License::create([
            'license_key' => 'DAKHL-LIC-v1.test1.sig',
            'license_hash' => hash('sha256', 'test1'),
            'edition' => 'basic',
            'customer_name' => 'تست ۱',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
            'issued_at' => now()->subMonths(12),
            'expires_at' => now()->subDays(2),
            'grace_period_days' => 7,
            'is_active' => true,
            'signature' => 'sig',
        ]);

        $this->assertTrue($expiredInGrace->isExpired());
        $this->assertTrue($expiredInGrace->isInGracePeriod());
        $this->assertFalse($expiredInGrace->isPastGracePeriod());
        $this->assertTrue($expiredInGrace->isValid());
        $this->assertTrue($expiredInGrace->hasModule(LicenseService::MODULE_CORE_ACCOUNTING));

        // 2. Expired 15 days ago with 7-day grace period => invalid (past grace period)
        $expiredPastGrace = License::create([
            'license_key' => 'DAKHL-LIC-v1.test2.sig',
            'license_hash' => hash('sha256', 'test2'),
            'edition' => 'basic',
            'customer_name' => 'تست ۲',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
            'issued_at' => now()->subMonths(12),
            'expires_at' => now()->subDays(15),
            'grace_period_days' => 7,
            'is_active' => true,
            'signature' => 'sig',
        ]);

        $this->assertTrue($expiredPastGrace->isExpired());
        $this->assertFalse($expiredPastGrace->isInGracePeriod());
        $this->assertTrue($expiredPastGrace->isPastGracePeriod());
        $this->assertFalse($expiredPastGrace->isValid());
        $this->assertFalse($expiredPastGrace->hasModule(LicenseService::MODULE_CORE_ACCOUNTING));
    }

    public function test_ensure_module_licensed_middleware_allows_and_blocks(): void
    {
        $middleware = app(EnsureModuleLicensed::class);

        // Active license has only core_accounting and sales_purchase
        License::query()->update(['is_active' => false]);
        License::create([
            'license_key' => 'key_basic',
            'license_hash' => hash('sha256', 'key_basic'),
            'edition' => 'basic',
            'customer_name' => 'تست پایه',
            'modules' => [
                LicenseService::MODULE_CORE_ACCOUNTING,
                LicenseService::MODULE_SALES_PURCHASE,
            ],
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'signature' => 'sig',
        ]);
        $this->licenseService->flushCache();

        // 1. Allowed module: sales_purchase
        $requestAllowed = Request::create('/sales', 'GET');
        $responseAllowed = $middleware->handle($requestAllowed, function () {
            return new Response('OK', 200);
        }, LicenseService::MODULE_SALES_PURCHASE);

        $this->assertEquals(200, $responseAllowed->getStatusCode());

        // 2. Blocked module via Web: distribution => redirects to license index with warning
        $requestBlockedWeb = Request::create('/distribution', 'GET');
        $responseBlockedWeb = $middleware->handle($requestBlockedWeb, function () {
            return new Response('OK', 200);
        }, LicenseService::MODULE_DISTRIBUTION);

        $this->assertTrue($responseBlockedWeb->isRedirection());
        $this->assertEquals(route('license.index'), $responseBlockedWeb->headers->get('Location'));

        // 3. Blocked module via JSON: pos_retail => returns 403 JSON
        $requestBlockedJson = Request::create('/api/pos', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $responseBlockedJson = $middleware->handle($requestBlockedJson, function () {
            return new Response('OK', 200);
        }, LicenseService::MODULE_POS_RETAIL);

        $this->assertEquals(403, $responseBlockedJson->getStatusCode());
        $data = json_decode($responseBlockedJson->getContent(), true);
        $this->assertEquals('module_not_licensed', $data['error']);
    }

    public function test_license_controller_index_and_status_api(): void
    {
        $this->actingAs($this->user);

        // GET /license
        $response = $this->get(route('license.index'));
        $response->assertStatus(200);
        $response->assertSee('سیستم صدور، ارزیابی و فعال‌سازی لایسنس ماژولار دخل');
        $response->assertSee('ماتریس ماژول‌ها و حقوق دسترسی فعال');
        $response->assertSee('بسته‌های اشتراک تجاری و فروش ماژولار دخل');

        // GET /license/status
        $apiResponse = $this->get(route('license.status'));
        $apiResponse->assertStatus(200);
        $apiResponse->assertJsonStructure([
            'valid',
            'edition',
            'edition_label',
            'customer_name',
            'hardware_fingerprint',
            'modules',
            'max_companies',
            'max_users',
        ]);
    }

    public function test_license_activation_flow_with_valid_and_invalid_keys(): void
    {
        $this->actingAs($this->user);

        // 1. Invalid key submission
        $badResponse = $this->post(route('license.activate'), [
            'license_key' => 'INVALID_TOKEN_FORMAT',
        ]);
        $badResponse->assertSessionHas('error');

        // 2. Valid key submission
        $validKey = $this->licenseService->issueLicenseKey([
            'edition' => 'enterprise',
            'customer_name' => 'سازمان صنایع ملی',
            'modules' => ['*'],
            'max_companies' => -1,
            'max_users' => -1,
            'expires_at' => now()->addMonths(6)->toDateTimeString(),
        ]);

        $goodResponse = $this->post(route('license.activate'), [
            'license_key' => $validKey,
        ]);
        $goodResponse->assertSessionHas('success');

        $activeLicense = $this->licenseService->getActiveLicense();
        $this->assertEquals('enterprise', $activeLicense->edition);
        $this->assertEquals('سازمان صنایع ملی', $activeLicense->customer_name);
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_PRODUCTION_BOM));
    }

    public function test_company_and_user_quota_limits(): void
    {
        License::query()->update(['is_active' => false]);
        License::create([
            'license_key' => 'key_limit',
            'license_hash' => hash('sha256', 'key_limit'),
            'edition' => 'basic',
            'customer_name' => 'تست محدودیت',
            'modules' => ['*'],
            'max_companies' => 1,
            'max_users' => 1,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'signature' => 'sig',
        ]);
        $this->licenseService->flushCache();

        // 1 company already exists, max_companies is 1 => cannot add
        $this->assertFalse($this->licenseService->canAddCompany());

        // 1 user already exists, max_users is 1 => cannot add
        $this->assertFalse($this->licenseService->canAddUser());
    }

    public function test_admin_can_generate_demo_license_via_http(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('license.generate-demo'), [
            'edition' => 'enterprise',
            'valid_days' => 30,
        ]);

        $response->assertRedirect(route('license.index'));
        $response->assertSessionHas('success');

        $activeLicense = $this->licenseService->getActiveLicense();
        $this->assertEquals('enterprise', $activeLicense->edition);
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_DISTRIBUTION));
        $this->assertTrue($activeLicense->hasModule(LicenseService::MODULE_POS_RETAIL));
    }

    public function test_blade_directives_render_conditionally(): void
    {
        License::query()->update(['is_active' => false]);
        License::create([
            'license_key' => 'key_blade',
            'license_hash' => hash('sha256', 'key_blade'),
            'edition' => 'custom',
            'customer_name' => 'تست بلید',
            'modules' => [LicenseService::MODULE_POS_RETAIL],
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'signature' => 'sig',
        ]);
        $this->licenseService->flushCache();

        $viewLicensed = \Illuminate\Support\Facades\Blade::render('@licensed("pos_retail") YES @else NO @endlicensed');
        $this->assertStringContainsString('YES', $viewLicensed);

        $viewUnlicensed = \Illuminate\Support\Facades\Blade::render('@unlicensed("distribution") LOCKED @else UNLOCKED @endunlicensed');
        $this->assertStringContainsString('LOCKED', $viewUnlicensed);
    }
}
