<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Display the License & Entitlements Management Hub.
     */
    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->can('configs.index') || auth()->user()->isSuperAdmin(),
            403,
            __('You do not have permission to view the license management page.')
        );

        $activeLicense = $this->licenseService->getActiveLicense();
        $modules = $this->licenseService->getAllModules();
        $plans = $this->licenseService->getAllPlans();
        $hardwareFingerprint = $this->licenseService->generateMachineFingerprint();
        $daysRemaining = $activeLicense->getDaysRemaining();

        return view('management.license.index', [
            'license' => $activeLicense,
            'modules' => $modules,
            'plans' => $plans,
            'hardwareFingerprint' => $hardwareFingerprint,
            'daysRemaining' => $daysRemaining,
        ]);
    }

    /**
     * Activate a new cryptographically signed license key.
     */
    public function activate(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('configs.edit') || auth()->user()->isSuperAdmin(),
            403,
            __('You do not have permission to activate or update licenses.')
        );

        $request->validate([
            'license_key' => 'required|string',
        ], [
            'license_key.required' => 'لطفاً کد لایسنس را وارد کنید.',
        ]);

        $result = $this->licenseService->activateLicenseKey($request->license_key);

        if (! $result['success']) {
            return redirect()->route('license.index')
                ->with('error', $result['message'])
                ->withInput();
        }

        return redirect()->route('license.index')
            ->with('success', $result['message']);
    }

    /**
     * Public Pricing & Online License Catalog Portal.
     */
    public function publicPricing(): View
    {
        $modules = $this->licenseService->getAllModules();
        $plans = $this->licenseService->getAllPlans();
        $hardwareFingerprint = $this->licenseService->generateMachineFingerprint();

        return view('portal.pricing', compact('modules', 'plans', 'hardwareFingerprint'));
    }

    /**
     * Flagship Public Sales & Product Presentation Landing Page.
     */
    public function landing(): View
    {
        $modules = $this->licenseService->getAllModules();
        $plans = $this->licenseService->getAllPlans();
        $hardwareFingerprint = $this->licenseService->generateMachineFingerprint();
        $profiles = app(\App\Services\Industry\IndustryProfileRegistry::class)->all();

        return view('portal.landing', [
            'modules' => $modules,
            'plans' => $plans,
            'hardwareFingerprint' => $hardwareFingerprint,
            'profiles' => $profiles,
        ]);
    }

    /**
     * Instant 14-day Free Trial Request.
     */
    public function requestTrial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'business_type' => 'nullable|string|max:50',
        ], [
            'customer_name.required' => 'نام و نام خانوادگی یا نام تجاری الزامی است.',
            'phone.required' => 'شماره تماس جهت دریافت لایسنس الزامی است.',
        ]);

        $trialData = [
            'edition' => 'trial',
            'customer_name' => $validated['customer_name'] . ' (' . $validated['phone'] . ')',
            'hardware_fingerprint' => '*',
            'modules' => ['*'],
            'max_companies' => 2,
            'max_users' => 5,
            'max_devices' => 2,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addDays(14)->timestamp,
            'grace_days' => 14,
        ];

        $licenseKey = $this->licenseService->issueLicenseKey($trialData);

        return response()->json([
            'success' => true,
            'message' => 'لایسنس آزمایشی ۱۴ روزه با دسترسی کامل به تمام ماژول‌ها برای شما صادر شد.',
            'license_key' => $licenseKey,
            'edition' => 'نسخه آزمایشی نامحدود (Trial)',
            'days' => 14,
            'expires_at' => now()->addDays(14)->format('Y-m-d'),
        ]);
    }

    /**
     * Public Online License Order Submission.
     */
    public function submitOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:shop,basic,commercial,professional,distribution,manufacturing,enterprise',
            'customer_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'company_name' => 'nullable|string|max:100',
            'hardware_fingerprint' => 'nullable|string|max:100',
            'extra_companies' => 'nullable|integer|min:0|max:50',
            'extra_users' => 'nullable|integer|min:0|max:100',
        ], [
            'plan.required' => 'لطفاً یکی از پلن‌های تجاری را انتخاب کنید.',
            'customer_name.required' => 'نام و نام خانوادگی خریدار الزامی است.',
            'phone.required' => 'شماره موبایل جهت هماهنگی و پشتیبانی الزامی است.',
        ]);

        $plans = $this->licenseService->getAllPlans();
        $planKey = match ($validated['plan']) {
            'shop' => 'basic',
            'commercial' => 'professional',
            default => $validated['plan'],
        };
        $selectedPlan = $plans[$planKey] ?? reset($plans);

        $priceStr = $selectedPlan['price'] ?? '7,800,000';
        $basePrice = (int) preg_replace('/[^\d]/', '', str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $priceStr
        ));
        if ($basePrice === 0) {
            $basePrice = 7800000;
        }

        $extraCompanies = (int) ($validated['extra_companies'] ?? 0);
        $extraUsers = (int) ($validated['extra_users'] ?? 0);

        $extraCompaniesCost = $extraCompanies * 1500000;
        $extraUsersCost = $extraUsers * 1200000;
        $totalPrice = $basePrice + $extraCompaniesCost + $extraUsersCost;

        $orderReference = 'DK-ORD-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));

        return response()->json([
            'success' => true,
            'order_reference' => $orderReference,
            'plan_title' => $selectedPlan['name'] ?? $selectedPlan['title'] ?? $validated['plan'],
            'total_price_irr' => $totalPrice,
            'total_price_formatted' => number_format($totalPrice).' تومان',
            'message' => 'سفارش لایسنس شما با موفقیت ثبت شد. کارشناسان دخل جهت فعال‌سازی نهایی و صدور فاکتور با شما تماس خواهند گرفت.',
        ]);
    }

    /**
     * API Status Endpoint for client POS and LAN devices.
     */
    public function status(): JsonResponse
    {
        $license = $this->licenseService->getActiveLicense();
        $fingerprint = $this->licenseService->generateMachineFingerprint();

        return response()->json([
            'valid' => $license->isValid(),
            'edition' => $license->edition,
            'edition_label' => $license->getEditionLabel(),
            'customer_name' => $license->customer_name,
            'hardware_fingerprint' => $fingerprint,
            'hardware_locked' => ! empty($license->hardware_fingerprint) && $license->hardware_fingerprint !== '*',
            'is_lifetime' => $license->isLifetime(),
            'is_expired' => $license->isExpired(),
            'is_in_grace_period' => $license->isInGracePeriod(),
            'days_remaining' => $license->getDaysRemaining(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'modules' => $license->modules,
            'max_companies' => $license->max_companies,
            'max_users' => $license->max_users,
            'max_devices' => $license->max_devices,
        ]);
    }
}
