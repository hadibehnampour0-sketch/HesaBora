<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * Artisan CLI for issuing cryptographically signed Ed25519 Dakhl license keys.
 */
class IssueLicenseCommand extends Command
{
    protected $signature = 'dakhl:issue-license
                            {--customer= : نام خریدار یا شرکت}
                            {--edition=basic : پلن لایسنس (basic, shop, professional, commercial, distribution, manufacturing, enterprise)}
                            {--fingerprint=* : شناسه سخت‌افزار ماشین مشتری (یا * برای تمام سیستم‌ها)}
                            {--days=365 : مدت اعتبار لایسنس به روز (0 برای دائمی و مادام‌العمر)}
                            {--national-id= : کدملی یا شناسه ملی خریدار}
                            {--companies= : سقف تعداد شرکت‌ها (خالی = مقدار پیش‌فرض پلن)}
                            {--users= : سقف تعداد کاربران همزمان LAN (خالی = مقدار پیش‌فرض پلن)}';

    protected $description = 'تولید و صدور کلید لایسنس رسمی و رمزنگاری‌شده نرم‌افزار دخل';

    public function handle(LicenseService $licenseService): int
    {
        $isInteractive = $this->input->isInteractive();
        $customer = $this->option('customer') ?: ($isInteractive ? $this->ask('نام مشتری یا شرکت خریدار چیست؟', 'مشتری تجاری دخل') : 'مشتری تجاری دخل');
        $edition = strtolower($this->option('edition') ?: ($isInteractive ? $this->choice(
            'کدام نسخه را می‌خواهید صادر کنید؟',
            ['basic', 'professional', 'distribution', 'manufacturing', 'enterprise'],
            0
        ) : 'basic'));

        // Map alias
        if ($edition === 'shop') $edition = 'basic';
        if ($edition === 'commercial') $edition = 'professional';

        $plans = $licenseService->getAllPlans();
        $plan = $plans[$edition] ?? $plans['basic'];

        $fingerprint = $this->option('fingerprint') ?: ($isInteractive ? $this->ask('شناسه سخت‌افزار (Machine Fingerprint)؟ (جهت لایسنس باز از * استفاده کنید)', '*') : '*');
        $fingerprintStr = is_array($fingerprint) ? implode(',', $fingerprint) : (string) $fingerprint;
        $days = (int) ($this->option('days') !== null ? $this->option('days') : ($isInteractive ? $this->ask('مدت اعتبار به روز؟ (0 برای مادام‌العمر)', '365') : 365));
        $nationalId = $this->option('national-id') ?: ($isInteractive ? $this->ask('شناسه یا کد ملی خریدار (اختیاری)؟', '') : '');

        $maxCompanies = $this->option('companies') !== null ? (int) $this->option('companies') : $plan['max_companies'];
        $maxUsers = $this->option('users') !== null ? (int) $this->option('users') : $plan['max_users'];

        $payload = [
            'type' => 'base',
            'edition' => $edition,
            'customer_name' => $customer,
            'customer_national_id' => $nationalId,
            'hardware_fingerprint' => $fingerprintStr,
            'valid_days' => $days,
            'max_companies' => $maxCompanies,
            'max_users' => $maxUsers,
            'max_devices' => $maxUsers,
            'modules' => $plan['modules'],
            'metadata' => [
                'max_industries' => $plan['max_industries'] ?? 1,
            ],
        ];

        if ($days > 0) {
            $payload['expires_at'] = now()->addDays($days)->toDateTimeString();
        }

        $licenseKey = $licenseService->issueLicenseKey($payload);
        $verify = $licenseService->verifyLicenseKey($licenseKey, null, false);

        $this->newLine();
        $this->info('========================================================================');
        $this->info('  🎉 کلید لایسنس رسمی و امضاشده نرم‌افزار دخل با موفقیت صادر شد:');
        $this->info('========================================================================');
        $this->line("<fg=yellow;options=bold>{$licenseKey}</>");
        $this->info('========================================================================');
        $this->table(
            ['مشخصه', 'مقدار صادرشده'],
            [
                ['خریدار', $customer],
                ['نگارش لایسنس', $plan['name']],
                ['شناسه ماشین (Lock)', $fingerprintStr],
                ['اعتبار', $days > 0 ? "{$days} روز (تا " . ($payload['expires_at'] ?? '') . ")" : 'مادام‌العمر و دائمی'],
                ['سقف شرکت‌ها', $maxCompanies === -1 ? 'نامحدود' : "{$maxCompanies} شرکت"],
                ['سقف کاربران محلی', $maxUsers === -1 ? 'نامحدود' : "{$maxUsers} کاربر LAN"],
                ['اصناف مجاز', $plan['max_industries'] === 16 ? 'تمام ۱۶ صنف باز' : "{$plan['max_industries']} صنف فعال"],
                ['وضعیت امضای دیجیتال', $verify['valid'] ? '✅ معتبر (Ed25519 Verified)' : '❌ نامعتبر'],
            ]
        );
        $this->newLine();

        return self::SUCCESS;
    }
}
