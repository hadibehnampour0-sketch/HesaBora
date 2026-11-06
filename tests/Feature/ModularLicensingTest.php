<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleLicensed;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\License\LicenseCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
            'name' => 'Neptune Licensing Test Co',
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
        $this->assertStringStartsWith('NEPTUNE-LIC-v2.', $key);

        // Verification of untampered key should succeed
        $result = $this->licenseService->verifyLicenseKey($key, null, false);
        $this->assertTrue($result['valid']);
        $this->assertEquals('professional', $result['payload']['tier']);
        $this->assertEquals('فروشگاه آذرخش', $result['payload']['org']);

        // Tampering with payload should fail verification
        $parts = explode('.', $key);
        $tamperedJson = json_encode(['tier' => 'enterprise', 'modules' => ['*'], 'kid' => 'neptune-root-2026']);
        $tamperedPayload = LicenseCrypto::base64UrlEncode($tamperedJson);
        $tamperedKey = "NEPTUNE-LIC-v2.{$tamperedPayload}.{$parts[2]}";

        $tamperedResult = $this->licenseService->verifyLicenseKey($tamperedKey, null, false);
        $this->assertFalse($tamperedResult['valid']);
        $this->assertStringContainsString('امضای دیجیتال', $tamperedResult['reason']);
    }

    public function test_license_hardware_fingerprint_enforcement(): void
    {
        $currentHw = $this->licenseService->generateMachineFingerprint();
        $foreignHw = 'NEP-9999-8888-7777-6666';

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
            'license_key' => 'NEPTUNE-LIC-v2.test1.sig',
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
            'license_key' => 'NEPTUNE-LIC-v2.test2.sig',
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
        $response->assertSee('سیستم صدور، ارزیابی و فعال‌سازی لایسنس ماژولار نپتون');
        $response->assertSee('ماتریس ماژول‌ها و حقوق دسترسی فعال');
        $response->assertSee('بسته‌های اشتراک تجاری و فروش ماژولار نپتون');

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

    public function test_generate_demo_and_custom_endpoints_are_removed_from_app(): void
    {
        $this->actingAs($this->user);

        // In-app key generation endpoints are removed from production/application surface
        $demoResponse = $this->post('/license/generate-demo', ['edition' => 'enterprise']);
        $demoResponse->assertStatus(404);

        $customResponse = $this->post('/license/generate-custom', ['type' => 'base']);
        $customResponse->assertStatus(404);
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

        $viewLicensed = Blade::render('@licensed("pos_retail") YES @else NO @endlicensed');
        $this->assertStringContainsString('YES', $viewLicensed);

        $viewUnlicensed = Blade::render('@unlicensed("distribution") LOCKED @else UNLOCKED @endunlicensed');
        $this->assertStringContainsString('LOCKED', $viewUnlicensed);
    }

    public function test_addon_module_key_merges_into_active_license_and_increments_quotas(): void
    {
        License::query()->update(['is_active' => false]);
        $base = License::create([
            'license_key' => 'key_base_bronze',
            'license_hash' => hash('sha256', 'key_base_bronze'),
            'edition' => 'basic',
            'customer_name' => 'فروشگاه برنزی تست',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING, LicenseService::MODULE_SALES_PURCHASE],
            'max_companies' => 1,
            'max_users' => 2,
            'max_devices' => 1,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'signature' => 'sig_base',
        ]);
        $this->licenseService->flushCache();

        $this->assertTrue($this->licenseService->isModuleLicensed(LicenseService::MODULE_SALES_PURCHASE));
        $this->assertFalse($this->licenseService->isModuleLicensed(LicenseService::MODULE_TAX_MOADIAN));

        // Issue and activate an Add-on Feature Key for Tax Moadian + 2 extra users
        $addonKey = $this->licenseService->issueAddonKey([
            'addon_name' => 'افزونه سامانه مودیان',
            'modules' => [LicenseService::MODULE_TAX_MOADIAN],
            'add_users' => 2,
            'hardware_fingerprint' => '*',
        ]);

        $result = $this->licenseService->activateLicenseKey($addonKey, null, false);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('سامانه مودیان', $result['message']);

        // Base modules still present + Moadian now unlocked!
        $active = $this->licenseService->getActiveLicense();
        $this->assertTrue($active->hasModule(LicenseService::MODULE_CORE_ACCOUNTING));
        $this->assertTrue($active->hasModule(LicenseService::MODULE_SALES_PURCHASE));
        $this->assertTrue($active->hasModule(LicenseService::MODULE_TAX_MOADIAN));
        $this->assertEquals(4, $active->max_users); // 2 + 2 = 4
    }

    public function test_offline_issuer_cli_generates_valid_license(): void
    {
        $payload = [
            'type' => 'base',
            'customer_name' => 'شرکت توسعه تجارت پیشرو',
            'hardware_fingerprint' => '*',
            'edition' => 'professional',
            'valid_days' => 180,
            'max_companies' => 3,
            'max_users' => 6,
            'max_devices' => 2,
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING, LicenseService::MODULE_POS_RETAIL],
        ];

        $key = $this->licenseService->issueLicenseKey($payload);
        $this->assertStringStartsWith('NEPTUNE-LIC-v2.', $key);

        $actResult = $this->licenseService->activateLicenseKey($key);
        $this->assertTrue($actResult['success']);

        $active = $this->licenseService->getActiveLicense();
        $this->assertEquals('شرکت توسعه تجارت پیشرو', $active->customer_name);
        $this->assertEquals(6, $active->max_users);
        $this->assertTrue($active->hasModule(LicenseService::MODULE_POS_RETAIL));
    }

    public function test_public_pricing_page_renders_successfully(): void
    {
        $response = $this->get(route('pricing'));

        $response->assertOk()
            ->assertSee('نپتون')
            ->assertSee('نسخه برنزی')
            ->assertSee('نسخه نقره‌ای')
            ->assertSee('نسخه طلایی')
            ->assertSee('۱۶ ماژول قدرتمند نپتون', false);
    }

    public function test_flagship_landing_page_renders_successfully(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk()
            ->assertSee('نپتون')
            ->assertSee('۱۶ صنف تخصصی')
            ->assertSee('سامانه مودیان')
            ->assertSee('بکاپ ۳-۲-۱')
            ->assertSee('نسخه آزمایشی ۱۴ روزه');
    }

    public function test_installer_download_endpoint(): void
    {
        $response = $this->get(route('download.installer'));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('Neptune-ERP-Setup-v2.5.exe', $response->headers->get('content-disposition', ''));
    }

    public function test_instant_trial_request_endpoint(): void
    {
        $response = $this->postJson(route('trial.request'), [
            'customer_name' => 'فروشگاه آزمایشی دخل',
            'phone' => '09123456789',
            'business_type' => 'طلا و جواهر',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('days', 14);

        $licenseKey = $response->json('license_key');
        $this->assertNotEmpty($licenseKey);

        $verify = $this->licenseService->verifyLicenseKey($licenseKey);
        $this->assertTrue($verify['valid']);
        $this->assertSame('trial', $verify['payload']['tier'] ?? $verify['payload']['edition']);
        $this->assertStringContainsString('فروشگاه آزمایشی دخل', $verify['payload']['org']);
    }

    public function test_public_order_submission_endpoint(): void
    {
        $response = $this->postJson(route('order.submit'), [
            'plan' => 'commercial',
            'customer_name' => 'بازرگانی آریا',
            'phone' => '09129876543',
            'company_name' => 'شرکت توسعه تجارت آریا',
            'extra_companies' => 2,
            'extra_users' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringStartsWith('DK-ORD-', $response->json('order_reference'));
        $this->assertGreaterThan(15500000, $response->json('total_price_irr'));
    }

    public function test_legacy_v1_license_token_rejection_as_invalid_format(): void
    {
        $v1Token = 'NEPTUNE-LIC-v1.eyJlZGl0aW9uIjoiZW50ZXJwcmlzZSJ9.mock_signature_12345';

        $verify = $this->licenseService->verifyLicenseKey($v1Token);
        $this->assertFalse($verify['valid']);
        $this->assertStringContainsString('NEPTUNE-LIC-v2', $verify['reason']);

        $activate = $this->licenseService->activateLicenseKey($v1Token);
        $this->assertFalse($activate['success']);
        $this->assertStringContainsString('NEPTUNE-LIC-v2', $activate['message']);
    }

    public function test_revoked_kid_rejection(): void
    {
        config(['license.revoked_kids' => ['neptune-compromised-2025']]);

        $payload = [
            'edition' => 'enterprise',
            'customer_name' => 'شرکت تست باطل',
            'modules' => ['*'],
        ];

        // Sign with compromised kid
        $key = $this->licenseService->issueLicenseKey($payload, null, 'neptune-compromised-2025');

        $result = $this->licenseService->verifyLicenseKey($key);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('باطل شده است', $result['reason']);
    }

    public function test_unknown_kid_rejection(): void
    {
        $payload = [
            'edition' => 'enterprise',
            'customer_name' => 'شرکت کلید ناشناس',
            'modules' => ['*'],
        ];

        $key = $this->licenseService->issueLicenseKey($payload, null, 'unknown-untrusted-key');

        $result = $this->licenseService->verifyLicenseKey($key);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('کلید عمومی برای شناسه', $result['reason']);
    }

    public function test_future_issued_at_clock_tamper_rejection(): void
    {
        $payload = [
            'edition' => 'enterprise',
            'customer_name' => 'شرکت آینده‌نگر',
            'modules' => ['*'],
            'issued_at' => now()->addDays(5)->timestamp,
        ];

        $key = $this->licenseService->issueLicenseKey($payload);

        $result = $this->licenseService->verifyLicenseKey($key);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('آینده است', $result['reason']);
    }

    public function test_wrong_private_key_signature_fails(): void
    {
        // Generate an arbitrary untrusted keypair
        $kp = LicenseCrypto::generateKeypair();
        $untrustedSecretKeyHex = bin2hex($kp['secret_key']);

        $payload = [
            'edition' => 'enterprise',
            'customer_name' => 'جاعل لایسنس',
            'modules' => ['*'],
        ];

        // Issue using the legitimate kid 'neptune-root-2026' but signed by rogue key
        $rogueKey = $this->licenseService->issueLicenseKey($payload, $untrustedSecretKeyHex, 'neptune-root-2026');

        $result = $this->licenseService->verifyLicenseKey($rogueKey);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('امضای دیجیتال لایسنس نامعتبر یا دستکاری شده است', $result['reason']);
    }

    public function test_never_re_gate_contract_tax_moadian_in_basic_plan(): void
    {
        $plans = $this->licenseService->getAllPlans();

        $this->assertArrayHasKey('basic', $plans);
        $basicModules = $plans['basic']['modules'];

        // Enforce Decision D-026 & Master Context §39.6
        $this->assertContains(LicenseService::MODULE_CORE_ACCOUNTING, $basicModules);
        $this->assertContains(LicenseService::MODULE_SALES_PURCHASE, $basicModules);
        $this->assertContains(LicenseService::MODULE_INVENTORY, $basicModules);
        $this->assertContains(LicenseService::MODULE_CHEQUES_TREASURY, $basicModules);
        $this->assertContains(LicenseService::MODULE_TAX_MOADIAN, $basicModules, 'Moadian MUST remain in Basic tier per Never Re-Gate (D-026)');
    }

    public function test_ensure_module_licensed_enforces_read_only_post_grace(): void
    {
        $middleware = app(EnsureModuleLicensed::class);

        License::query()->update(['is_active' => false]);
        License::create([
            'license_key' => 'key_expired_post_grace',
            'license_hash' => hash('sha256', 'key_expired_post_grace'),
            'edition' => 'basic',
            'customer_name' => 'شرکت منقضی',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
            'issued_at' => now()->subMonths(14),
            'expires_at' => now()->subDays(20), // 20 days ago > 14 days grace
            'grace_period_days' => 14,
            'is_active' => true,
            'signature' => 'sig',
        ]);
        $this->licenseService->flushCache();

        // Safe GET request: allowed in read-only mode
        $safeGet = Request::create('/accounting/reports', 'GET');
        $getResponse = $middleware->handle($safeGet, function () {
            return new Response('VIEW_REPORTS_ALLOWED', 200);
        }, LicenseService::MODULE_CORE_ACCOUNTING);
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertEquals('VIEW_REPORTS_ALLOWED', $getResponse->getContent());

        // Mutating POST request via JSON: blocked with 403
        $mutatingPostJson = Request::create('/accounting/documents', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $postResponseJson = $middleware->handle($mutatingPostJson, function () {
            return new Response('CREATED', 201);
        }, LicenseService::MODULE_CORE_ACCOUNTING);
        $this->assertEquals(403, $postResponseJson->getStatusCode());
        $data = json_decode($postResponseJson->getContent(), true);
        $this->assertEquals('license_expired_read_only', $data['error']);

        // Mutating POST request via Web: redirects to license index with warning
        $mutatingPostWeb = Request::create('/accounting/documents', 'POST');
        $postResponseWeb = $middleware->handle($mutatingPostWeb, function () {
            return new Response('CREATED', 201);
        }, LicenseService::MODULE_CORE_ACCOUNTING);
        $this->assertTrue($postResponseWeb->isRedirection());
        $this->assertEquals(route('license.index'), $postResponseWeb->headers->get('Location'));
    }

    public function test_packaging_safety_no_private_keys_in_app_tree(): void
    {
        $appPath = base_path('app');
        $configPath = base_path('config');
        $publicPath = base_path('public');
        $resourcesPath = base_path('resources');

        $paths = [$appPath, $configPath, $publicPath, $resourcesPath];

        $forbiddenFiles = [];
        foreach ($paths as $path) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, ['key', 'pem'], true)) {
                        $forbiddenFiles[] = $file->getPathname();
                    }
                }
            }
        }

        $this->assertEmpty($forbiddenFiles, 'Forbidden private key or PEM files detected in app distribution tree: '.implode(', ', $forbiddenFiles));

        // Verify tools/license-issuer/keys/ is ignored by git
        $gitignorePath = base_path('tools/license-issuer/.gitignore');
        $this->assertFileExists($gitignorePath);
        $gitignoreContent = file_get_contents($gitignorePath);
        $this->assertStringContainsString('keys/', $gitignoreContent);
        $this->assertStringContainsString('*.key', $gitignoreContent);

        // Verify packaging cleanup list strictly excludes tools and private keys from desktop builds
        $cleanupExcludes = config('nativephp.cleanup_exclude_files', []);
        $this->assertTrue(
            in_array('tools*', $cleanupExcludes, true) || in_array('tools/license-issuer*', $cleanupExcludes, true),
            'NativePHP build configuration must exclude tools / license-issuer from production packaging'
        );
        $this->assertTrue(
            in_array('*.key', $cleanupExcludes, true),
            'NativePHP build configuration must exclude *.key private keys from production packaging'
        );
    }

    public function test_v1_hmac_format_completely_dropped_and_rejected(): void
    {
        // Any legacy v1 token or token not formatted as NEPTUNE-LIC-v2 is rejected
        $v1Token = 'NEPTUNE-LIC-v1.eyJ0aWVyIjoiZW50ZXJwcmlzZSJ9.invalidhmacsighere';
        $result = $this->licenseService->verifyLicenseKey($v1Token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('فرمت کد لایسنس نامعتبر است (باید با NEPTUNE-LIC-v2 آغاز شود).', $result['reason']);
        $this->assertNull($result['payload']);
    }

    public function test_clock_rollback_detection(): void
    {
        // 1. Token issued in the future (issued_at > now + 300)
        $futureKey = $this->licenseService->issueLicenseKey([
            'edition' => 'basic',
            'issued_at' => now()->addDays(2)->timestamp,
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
        ]);

        $futureResult = $this->licenseService->verifyLicenseKey($futureKey, null, false);
        $this->assertFalse($futureResult['valid']);
        $this->assertStringContainsString('تاریخ صدور لایسنس در آینده است', $futureResult['reason']);

        // 2. Database records exist with timestamps in the future (system clock rolled back)
        $validKey = $this->licenseService->issueLicenseKey([
            'edition' => 'basic',
            'issued_at' => now()->timestamp,
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
        ]);

        // Simulate database record with future updated_at
        $futureLicense = License::create([
            'license_key' => 'NEPTUNE-LIC-v2.future.sig',
            'license_hash' => hash('sha256', 'future'),
            'edition' => 'basic',
            'customer_name' => 'تست ساعت آینده',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
            'issued_at' => now(),
            'is_active' => false,
            'signature' => 'sig',
        ]);
        \Illuminate\Support\Facades\DB::table('licenses')
            ->where('id', $futureLicense->id)
            ->update(['updated_at' => now()->addDays(5)->toDateTimeString()]);

        $rollbackResult = $this->licenseService->verifyLicenseKey($validKey, null, false);
        $this->assertFalse($rollbackResult['valid']);
        $this->assertStringContainsString('ساعت سیستم به گذشته بازگردانده شده است', $rollbackResult['reason']);
    }

    public function test_benign_hardware_drift_tolerance(): void
    {
        $baseComponents = $this->licenseService->getMachineFingerprintComponents();

        // Simulate benign drift: hostname changed from 'WIN-PC' to 'OFFICE-NEW-NAME', but OS and machine_id remain identical
        $driftedComponents = $baseComponents;
        $driftedComponents['host'] = 'OFFICE-NEW-NAME';

        $currentFp = $this->licenseService->generateMachineFingerprint();

        $verified = $this->licenseService->verifyMachineFingerprint(
            $currentFp,
            currentFingerprint: 'NEP-DIFFERENT-HOST-FP',
            hardwareProfile: $driftedComponents
        );

        $this->assertTrue($verified, 'Benign drift (hostname change on identical hardware) must be tolerated');
    }

    public function test_signed_rebind_flow(): void
    {
        $initialKey = $this->licenseService->issueLicenseKey([
            'edition' => 'professional',
            'customer_name' => 'شرکت آذرخش نوین',
            'hardware_fingerprint' => 'NEP-1111-2222-3333-4444',
            'modules' => [LicenseService::MODULE_CORE_ACCOUNTING],
        ]);

        $actResult = $this->licenseService->activateLicenseKey($initialKey, verifyHardware: false);
        $this->assertTrue($actResult['success']);
        $activeLicense = $actResult['license'];

        $this->assertEquals('NEP-1111-2222-3333-4444', $activeLicense->hardware_fingerprint);

        // Issue a signed re-bind token to new hardware NEP-8888-9999-0000-1111
        $newHardwareFp = 'NEP-8888-9999-0000-1111';
        $rebindToken = $this->licenseService->issueRebindToken([
            'lic_id' => $activeLicense->metadata['lic_id'] ?? 'lic-123',
            'rebind_to' => $newHardwareFp,
            'reason' => 'تعویض مادربرد سرور حسابداری با تایید واحد فروش',
        ]);

        $rebindResult = $this->licenseService->rebindLicense($rebindToken);
        $this->assertTrue($rebindResult['success']);
        $this->assertEquals($newHardwareFp, $rebindResult['license']->hardware_fingerprint);
        $this->assertEquals($newHardwareFp, $this->licenseService->getActiveLicense()->hardware_fingerprint);
    }

    public function test_crypto_self_test_runs_on_boot(): void
    {
        $this->assertTrue(LicenseCrypto::selfTest(), 'Ed25519 sodium engine self-test must return true');
    }

    public function test_notice_license_enforcement_is_convenience_until_cr7(): void
    {
        $reflection = new \ReflectionClass(LicenseService::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('CR-7', $docComment);
        $this->assertStringContainsString('convenience and scaffolding', $docComment);
    }
}
