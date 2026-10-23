<?php

namespace App\Services;

use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LicenseService
{
    // Canonical 16 Modular Capabilities
    public const MODULE_CORE_ACCOUNTING = 'core_accounting';

    public const MODULE_SALES_PURCHASE = 'sales_purchase';

    public const MODULE_INVENTORY = 'inventory_warehouse';

    public const MODULE_CHEQUES_TREASURY = 'cheques_treasury';

    public const MODULE_TAX_MOADIAN = 'tax_moadian';

    public const MODULE_POS_RETAIL = 'pos_retail';

    public const MODULE_DISTRIBUTION = 'distribution';

    public const MODULE_PRODUCTION_BOM = 'production_bom';

    public const MODULE_HR_PAYROLL = 'hr_payroll';

    public const MODULE_SMS_AUTOMATION = 'sms_automation';

    public const MODULE_CUSTOMER_LOYALTY = 'customer_loyalty';

    public const MODULE_TELEPHONY_VOIP = 'telephony_voip';

    public const MODULE_OFFICIAL_INQUIRY = 'official_inquiries';

    public const MODULE_FINANCING = 'installment_financing';

    public const MODULE_AI_COPILOT = 'ai_copilot';

    public const MODULE_MIGRATION_TOOLS = 'migration_tools';

    private const CACHE_KEY = 'dakhl_active_license';

    /**
     * Get secret salt for signing offline licenses.
     */
    public function getSecretKey(): string
    {
        return config('app.license_secret', config('app.key', 'dakhl-offline-secret-default-key')).':dakhl-lic-auth-v1';
    }

    /**
     * Generate a deterministic machine fingerprint for offline node-locking.
     */
    public function generateMachineFingerprint(): string
    {
        $components = [
            php_uname('s'), // OS name
            php_uname('n'), // Hostname
            php_uname('m'), // Machine architecture
            PHP_OS_FAMILY,
        ];

        // Read machine-id or computer GUID if present on OS
        if (PHP_OS_FAMILY === 'Windows') {
            $winGuid = env('COMPUTERNAME', gethostname());
            $components[] = $winGuid;
        } elseif (file_exists('/etc/machine-id')) {
            $components[] = trim((string) @file_get_contents('/etc/machine-id'));
        }

        $hash = strtoupper(hash('sha256', implode('|', $components)));

        // Format nicely: DAKHL-XXXX-XXXX-XXXX-XXXX
        return 'DAKHL-'.substr($hash, 0, 4).'-'.substr($hash, 4, 4).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4);
    }

    /**
     * Issue a cryptographically signed offline license token.
     */
    public function issueLicenseKey(array $data, ?string $secret = null): string
    {
        $issuedAt = isset($data['issued_at']) ? Carbon::parse($data['issued_at'])->timestamp : now()->timestamp;
        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->timestamp : null;

        $payload = [
            'type' => $data['type'] ?? 'base',
            'addon_name' => $data['addon_name'] ?? null,
            'edition' => $data['edition'] ?? 'professional',
            'customer_name' => $data['customer_name'] ?? 'مشتری گرامی دخل',
            'customer_national_id' => $data['customer_national_id'] ?? null,
            'hardware_fingerprint' => $data['hardware_fingerprint'] ?? null,
            'modules' => $data['modules'] ?? ['*'],
            'max_companies' => (int) ($data['max_companies'] ?? 1),
            'max_users' => (int) ($data['max_users'] ?? 2),
            'max_devices' => (int) ($data['max_devices'] ?? 1),
            'add_companies' => (int) ($data['add_companies'] ?? 0),
            'add_users' => (int) ($data['add_users'] ?? 0),
            'add_devices' => (int) ($data['add_devices'] ?? 0),
            'grace_period_days' => (int) ($data['grace_period_days'] ?? 7),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'nonce' => Str::random(16),
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadBase64 = rtrim(strtr(base64_encode($jsonPayload), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $payloadBase64, $secret ?? $this->getSecretKey());

        return "DAKHL-LIC-v1.{$payloadBase64}.{$signature}";
    }

    /**
     * Issue an Add-on Feature Key to unlock specific capabilities or quotas on an existing installation.
     */
    public function issueAddonKey(array $data, ?string $secret = null): string
    {
        return $this->issueLicenseKey(array_merge($data, [
            'type' => 'addon',
            'edition' => $data['edition'] ?? 'addon',
        ]), $secret);
    }

    /**
     * Verify an offline license token mathematically against tampering and hardware lock.
     */
    public function verifyLicenseKey(string $licenseKey, ?string $secret = null, bool $verifyHardware = true): array
    {
        $parts = explode('.', trim($licenseKey));

        if (count($parts) !== 3 || $parts[0] !== 'DAKHL-LIC-v1') {
            return [
                'valid' => false,
                'reason' => 'فرمت کد لایسنس نامعتبر است.',
                'payload' => null,
            ];
        }

        $payloadBase64 = $parts[1];
        $providedSignature = $parts[2];

        $computedSignature = hash_hmac('sha256', $payloadBase64, $secret ?? $this->getSecretKey());

        if (! hash_equals($computedSignature, $providedSignature)) {
            return [
                'valid' => false,
                'reason' => 'امضای دیجیتال لایسنس مخدوش یا نامعتبر است.',
                'payload' => null,
            ];
        }

        // Decode payload
        $jsonPayload = base64_decode(strtr($payloadBase64, '-_', '+/'));
        $payload = json_decode($jsonPayload, true);

        if (! is_array($payload)) {
            return [
                'valid' => false,
                'reason' => 'داده‌های ساختاری لایسنس قابل پردازش نیست.',
                'payload' => null,
            ];
        }

        // Check hardware lock
        if ($verifyHardware && ! empty($payload['hardware_fingerprint']) && $payload['hardware_fingerprint'] !== '*') {
            $currentHw = $this->generateMachineFingerprint();
            if ($payload['hardware_fingerprint'] !== $currentHw) {
                return [
                    'valid' => false,
                    'reason' => "شناسه سخت‌افزاری دستگاه تطابق ندارد (مخصوص سخت‌افزار {$payload['hardware_fingerprint']}).",
                    'payload' => $payload,
                ];
            }
        }

        // Check expiration
        if (! empty($payload['expires_at'])) {
            $expiresAt = Carbon::createFromTimestamp($payload['expires_at']);
            $graceDays = (int) ($payload['grace_period_days'] ?? 7);
            $hardDeadline = $expiresAt->copy()->addDays($graceDays);

            if (now()->greaterThan($hardDeadline)) {
                return [
                    'valid' => false,
                    'reason' => 'اعتبار لایسنس و دوره مهلت تمدید آن منقضی شده است.',
                    'payload' => $payload,
                ];
            }
        }

        return [
            'valid' => true,
            'reason' => null,
            'payload' => $payload,
            'signature' => $providedSignature,
        ];
    }

    /**
     * Activate a new license key in the system.
     */
    public function activateLicenseKey(string $licenseKey, ?int $companyId = null, bool $verifyHardware = true): array
    {
        $verification = $this->verifyLicenseKey($licenseKey, null, $verifyHardware);

        if (! $verification['valid']) {
            return [
                'success' => false,
                'message' => $verification['reason'],
                'license' => null,
            ];
        }

        $payload = $verification['payload'];
        $signature = $verification['signature'];
        $hash = hash('sha256', $licenseKey);

        // Check if this is an Add-on Feature Key meant to enrich the active license
        if (($payload['type'] ?? 'base') === 'addon') {
            $activeLicense = $this->getActiveLicense($companyId);
            $newModules = $payload['modules'] ?? [];
            $existingModules = $activeLicense->modules ?? [];

            $mergedModules = in_array('*', $existingModules, true)
                ? ['*']
                : array_values(array_unique(array_merge($existingModules, $newModules)));

            $maxCompanies = $activeLicense->max_companies === -1
                ? -1
                : $activeLicense->max_companies + (int) ($payload['add_companies'] ?? 0);

            $maxUsers = $activeLicense->max_users === -1
                ? -1
                : $activeLicense->max_users + (int) ($payload['add_users'] ?? 0);

            $maxDevices = $activeLicense->max_devices === -1
                ? -1
                : $activeLicense->max_devices + (int) ($payload['add_devices'] ?? 0);

            $metadata = $activeLicense->metadata ?? [];
            $metadata['addons'] = $metadata['addons'] ?? [];
            $metadata['addons'][] = [
                'name' => $payload['addon_name'] ?? 'افزونه ماژولار',
                'modules' => $newModules,
                'activated_at' => now()->toIso8601String(),
                'signature' => $signature,
            ];

            // If addon has an expiration that extends current expiry
            $expiresAt = $activeLicense->expires_at;
            if (! empty($payload['expires_at'])) {
                $addonExpires = Carbon::createFromTimestamp($payload['expires_at']);
                if ($expiresAt === null || $addonExpires->greaterThan($expiresAt)) {
                    $expiresAt = $addonExpires;
                }
            }

            $activeLicense->update([
                'modules' => $mergedModules,
                'max_companies' => $maxCompanies,
                'max_users' => $maxUsers,
                'max_devices' => $maxDevices,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);

            $this->flushCache();

            $addonName = $payload['addon_name'] ?? 'ماژول انتخابی';

            return [
                'success' => true,
                'message' => "افزونه «{$addonName}» با موفقیت فعال و به لایسنس شما اضافه گردید.",
                'license' => $activeLicense->fresh(),
            ];
        }

        // Deactivate old active licenses
        License::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
            ->update(['is_active' => false]);

        $license = License::create([
            'license_key' => $licenseKey,
            'license_hash' => $hash,
            'edition' => $payload['edition'] ?? 'custom',
            'customer_name' => $payload['customer_name'] ?? 'مشتری گرامی دخل',
            'customer_national_id' => $payload['customer_national_id'] ?? null,
            'hardware_fingerprint' => $payload['hardware_fingerprint'] ?? null,
            'max_companies' => $payload['max_companies'] ?? 1,
            'max_users' => $payload['max_users'] ?? 2,
            'max_devices' => $payload['max_devices'] ?? 1,
            'modules' => $payload['modules'] ?? ['*'],
            'issued_at' => Carbon::createFromTimestamp($payload['issued_at']),
            'expires_at' => ! empty($payload['expires_at']) ? Carbon::createFromTimestamp($payload['expires_at']) : null,
            'grace_period_days' => $payload['grace_period_days'] ?? 7,
            'is_active' => true,
            'signature' => $signature,
            'company_id' => $companyId,
            'metadata' => [
                'activated_at' => now()->toIso8601String(),
                'activated_by_user_id' => auth()->id(),
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ],
        ]);

        $this->flushCache();

        return [
            'success' => true,
            'message' => 'لایسنس با موفقیت فعال گردید و تمامی ماژول‌های مجاز فعال شدند.',
            'license' => $license,
        ];
    }

    /**
     * Get the active license (with auto-provisioning of 14-day trial if brand new).
     */
    public function getActiveLicense(?int $companyId = null): License
    {
        $cacheKey = self::CACHE_KEY.'_'.($companyId ?? 'global');

        return Cache::remember($cacheKey, 3600, function () use ($companyId) {
            $license = License::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('is_active', true)
                ->latest('id')
                ->first();

            if (! $license) {
                // If global license also not found, auto-provision 14-day trial
                if ($companyId) {
                    $global = License::whereNull('company_id')->where('is_active', true)->latest('id')->first();
                    if ($global) {
                        return $global;
                    }
                }

                $license = $this->provisionDefaultTrial($companyId);
            }

            return $license;
        });
    }

    /**
     * Auto-provisions a default 14-day trial license for new installations.
     */
    public function provisionDefaultTrial(?int $companyId = null): License
    {
        $trialData = [
            'edition' => 'trial',
            'customer_name' => 'کاربر آزمایشی دخل',
            'hardware_fingerprint' => '*',
            'modules' => ['*'], // All modules enabled for 14 days trial
            'max_companies' => 2,
            'max_users' => 5,
            'max_devices' => 2,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addDays(14)->timestamp,
            'grace_period_days' => 7,
        ];

        $key = $this->issueLicenseKey($trialData);
        $verification = $this->verifyLicenseKey($key, null, false);
        $payload = $verification['payload'];

        return License::create([
            'license_key' => $key,
            'license_hash' => hash('sha256', $key),
            'edition' => 'trial',
            'customer_name' => $payload['customer_name'],
            'hardware_fingerprint' => null,
            'max_companies' => $payload['max_companies'],
            'max_users' => $payload['max_users'],
            'max_devices' => $payload['max_devices'],
            'modules' => ['*'],
            'issued_at' => Carbon::createFromTimestamp($payload['issued_at']),
            'expires_at' => Carbon::createFromTimestamp($payload['expires_at']),
            'grace_period_days' => 7,
            'is_active' => true,
            'signature' => $verification['signature'],
            'company_id' => $companyId,
            'metadata' => ['provisioned' => 'auto_first_run'],
        ]);
    }

    /**
     * Check if a specific module key is licensed.
     */
    public function isModuleLicensed(string $moduleKey, ?int $companyId = null): bool
    {
        $license = $this->getActiveLicense($companyId);

        return $license->hasModule($moduleKey);
    }

    /**
     * Check if a new company can be created under active limits.
     */
    public function canAddCompany(): bool
    {
        $license = $this->getActiveLicense();

        if ($license->max_companies === -1) {
            return true;
        }

        $count = Company::count();

        return $count < $license->max_companies;
    }

    /**
     * Check if a new user can be created under active limits.
     */
    public function canAddUser(): bool
    {
        $license = $this->getActiveLicense();

        if ($license->max_users === -1) {
            return true;
        }

        $count = User::count();

        return $count < $license->max_users;
    }

    /**
     * Flushes cached license state.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY.'_global');
        $companies = Company::pluck('id');
        foreach ($companies as $id) {
            Cache::forget(self::CACHE_KEY."_{$id}");
        }
    }

    /**
     * All 16 Modular Capabilities with Persian labels and categories.
     */
    public function getAllModules(): array
    {
        return [
            self::MODULE_CORE_ACCOUNTING => [
                'key' => self::MODULE_CORE_ACCOUNTING,
                'title' => 'حسابداری پایه و دفاتر مالی',
                'description' => 'اسناد دوبل حسابداری، دفاتر کل، روزنامه، معین و تراز آزمایشی چهار و هشت ستونی.',
                'category' => 'حسابداری و مالی',
                'icon' => '📑',
                'is_core' => true,
            ],
            self::MODULE_SALES_PURCHASE => [
                'key' => self::MODULE_SALES_PURCHASE,
                'title' => 'خرید و فروش پیشرفته',
                'description' => 'صدور فاکتور رسمی و غیررسمی فروش، خرید، برگشت از فروش، پیش‌فاکتور و تخفیفات پلکانی.',
                'category' => 'فروش و مشتریان',
                'icon' => '📝',
                'is_core' => true,
            ],
            self::MODULE_INVENTORY => [
                'key' => self::MODULE_INVENTORY,
                'title' => 'انبارداری و چندانباره',
                'description' => 'مدیریت چند انبار، کاردکس کالا، حواله و رسید انبار، قیمت‌گذاری میانگین موزون و انبارگردانی.',
                'category' => 'انبار و کالا',
                'icon' => '📦',
                'is_core' => true,
            ],
            self::MODULE_CHEQUES_TREASURY => [
                'key' => self::MODULE_CHEQUES_TREASURY,
                'title' => 'خزانه‌داری و چک صیادی',
                'description' => 'چرخه کامل چک صیادی، خواباندن به حساب، پاس، برگشت، عودت و مغایرت‌گیری بانکی خودکار.',
                'category' => 'حسابداری و مالی',
                'icon' => '🏦',
                'is_core' => true,
            ],
            self::MODULE_TAX_MOADIAN => [
                'key' => self::MODULE_TAX_MOADIAN,
                'title' => 'سامانه مودیان و مالیات فصلی',
                'description' => 'اتصال مستقیم به سامانه مودیان سازمان امور مالیاتی، گزارش ارزش افزوده ماده ۱۰ و اظهارنامه.',
                'category' => 'مالیات و انطباق',
                'icon' => '⚖️',
                'is_core' => false,
            ],
            self::MODULE_POS_RETAIL => [
                'key' => self::MODULE_POS_RETAIL,
                'title' => 'صندوق فروشگاهی سریع (POS)',
                'description' => 'رابط تاچ فروشگاهی، بارکدخوان سریع، فیش‌پرینتر حرارتی، کشوی پول و کارتخوان متصل به LAN.',
                'category' => 'فروش و مشتریان',
                'icon' => '🛒',
                'is_core' => false,
            ],
            self::MODULE_DISTRIBUTION => [
                'key' => self::MODULE_DISTRIBUTION,
                'title' => 'پخش مویرگی و ویزیتوری',
                'description' => 'انبار ماشین (وان)، مسیربندی ویزیتورها، سفارش‌گیری گرم و سرد و تسویه فاکتور روزانه.',
                'category' => 'عملیات و پخش',
                'icon' => '🚚',
                'is_core' => false,
            ],
            self::MODULE_PRODUCTION_BOM => [
                'key' => self::MODULE_PRODUCTION_BOM,
                'title' => 'تولید سبک و فرمول ساخت (BOM)',
                'description' => 'تعریف فرمول ترکیب مواد اولیه، سفارش ساخت، بهای تمام‌شده کالای تولیدی و سربار مستقیم.',
                'category' => 'تولید و بهای تمام‌شده',
                'icon' => '⚙️',
                'is_core' => false,
            ],
            self::MODULE_HR_PAYROLL => [
                'key' => self::MODULE_HR_PAYROLL,
                'title' => 'حقوق، دستمزد و پرسنلی',
                'description' => 'احکام کارگزینی، ثبت ورود/خروج کارکرد، فیش حقوقی قانون کار، جدول مالیات حقوق و بیمه تامین اجتماعی.',
                'category' => 'منابع انسانی',
                'icon' => '👥',
                'is_core' => false,
            ],
            self::MODULE_SMS_AUTOMATION => [
                'key' => self::MODULE_SMS_AUTOMATION,
                'title' => 'اتوماسیون پیامک هوشمند',
                'description' => 'ارسال خودکار پیامک صدور فاکتور، یادآوری سررسید چک، تبریک تولد و اطلاع‌رسانی بدهی با پترن خدماتی.',
                'category' => 'ارتباطات و مارکتینگ',
                'icon' => '💬',
                'is_core' => false,
            ],
            self::MODULE_CUSTOMER_LOYALTY => [
                'key' => self::MODULE_CUSTOMER_LOYALTY,
                'title' => 'باشگاه مشتریان و کیف پول اعتباری',
                'description' => 'محاسبه امتیاز وفاداری از هر خرید، کش‌بک درصدی و کیف پول شارژی جهت خریدهای بعدی.',
                'category' => 'ارتباطات و مارکتینگ',
                'icon' => '🎁',
                'is_core' => false,
            ],
            self::MODULE_TELEPHONY_VOIP => [
                'key' => self::MODULE_TELEPHONY_VOIP,
                'title' => 'کالرآیدی و تلفن سانترال',
                'description' => 'پاپ‌آپ مشخصات مشتری با زنگ خوردن تلفن، مشاهده آخرین فاکتورها، مانده حساب و چک‌های فعال در حین مکالمه.',
                'category' => 'ارتباطات و مارکتینگ',
                'icon' => '📞',
                'is_core' => false,
            ],
            self::MODULE_OFFICIAL_INQUIRY => [
                'key' => self::MODULE_OFFICIAL_INQUIRY,
                'title' => 'استعلامات رسمی شاهکار و بانکی',
                'description' => 'استعلام تطابق کدملی با شماره موبایل (شاهکار)، صحت‌سنجی شماره شبا و نام صاحب حساب بانکی.',
                'category' => 'اعتبارسنجی و مالی',
                'icon' => '🔍',
                'is_core' => false,
            ],
            self::MODULE_FINANCING => [
                'key' => self::MODULE_FINANCING,
                'title' => 'فروش اقساطی و محاسبات سود/دیرکرد',
                'description' => 'تقسیط مبالغ فاکتور با فرمول بانکی یا تجاری، جریمه تاخیر روزشمار و دفترچه اقساط چاپی.',
                'category' => 'اعتبارسنجی و مالی',
                'icon' => '💳',
                'is_core' => false,
            ],
            self::MODULE_AI_COPILOT => [
                'key' => self::MODULE_AI_COPILOT,
                'title' => 'دستیار مالی هوش مصنوعی و OCR فاکتور',
                'description' => 'تحلیل و مکالمه هوشمند صوتی و متنی، خواندن فاکتورهای کاغذی با دوربین/اسکنر و تبدیل خودکار به سند.',
                'category' => 'هوشمند و فناوری',
                'icon' => '✨',
                'is_core' => false,
            ],
            self::MODULE_MIGRATION_TOOLS => [
                'key' => self::MODULE_MIGRATION_TOOLS,
                'title' => 'مهاجرت از نرم‌افزارهای رقیب',
                'description' => 'انتقال ۱۰۰٪ خودکار اشخاص، کالاها، انبارها و مانده‌حساب‌ها از هلو، سپیدار، پارسیان و همکاران سیستم.',
                'category' => 'عملیات و پخش',
                'icon' => '🔄',
                'is_core' => false,
            ],
        ];
    }

    /**
     * Commercial Pricing Plans Catalog.
     */
    public function getAllPlans(): array
    {
        return [
            'basic' => [
                'id' => 'basic',
                'name' => 'نسخه برنزی (پایه اصناف خرد)',
                'price' => '۳,۹۰۰,۰۰۰',
                'period' => 'سالانه',
                'description' => 'مناسب برای فروشگاه‌های تک‌شعبه و کسب‌وکارهای خدماتی که به دنبال جایگزین مدرن دفاتر کاغذی هستند.',
                'popular' => false,
                'max_companies' => 1,
                'max_users' => 2,
                'badge' => 'اقتصادی',
                'badge_color' => 'badge-ghost',
                'modules' => [
                    self::MODULE_CORE_ACCOUNTING,
                    self::MODULE_SALES_PURCHASE,
                    self::MODULE_INVENTORY,
                    self::MODULE_CHEQUES_TREASURY,
                ],
            ],
            'professional' => [
                'id' => 'professional',
                'name' => 'نسخه نقره‌ای (حرفه‌ای / رشد کسب‌وکار)',
                'price' => '۹,۵۰۰,۰۰۰',
                'period' => 'سالانه',
                'description' => 'کامل‌ترین انتخاب برای خرده‌فروشی‌ها، اصناف تجاری و بازرگانی‌های نیازمند سامانه مودیان و صندوق POS.',
                'popular' => true,
                'max_companies' => 3,
                'max_users' => 5,
                'badge' => 'پرفروش‌ترین',
                'badge_color' => 'badge-primary',
                'modules' => [
                    self::MODULE_CORE_ACCOUNTING,
                    self::MODULE_SALES_PURCHASE,
                    self::MODULE_INVENTORY,
                    self::MODULE_CHEQUES_TREASURY,
                    self::MODULE_TAX_MOADIAN,
                    self::MODULE_POS_RETAIL,
                    self::MODULE_CUSTOMER_LOYALTY,
                    self::MODULE_SMS_AUTOMATION,
                    self::MODULE_OFFICIAL_INQUIRY,
                    self::MODULE_FINANCING,
                    self::MODULE_MIGRATION_TOOLS,
                ],
            ],
            'enterprise' => [
                'id' => 'enterprise',
                'name' => 'نسخه طلایی (سازمانی / نامحدود)',
                'price' => '۱۹,۸۰۰,۰۰۰',
                'period' => 'سالانه (یا دائمی)',
                'description' => 'برای کارخانجات، شرکت‌های پخش مویرگی و هلدینگ‌هایی که به تمامی امکانات بدون هیچ‌گونه محدودیتی نیاز دارند.',
                'popular' => false,
                'max_companies' => -1,
                'max_users' => -1,
                'badge' => 'کامل‌ترین پکیج',
                'badge_color' => 'badge-accent',
                'modules' => ['*'], // All 16 modules
            ],
        ];
    }
}
