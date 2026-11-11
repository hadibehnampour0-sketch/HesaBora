<?php

namespace App\Http\Controllers;

use App\Models\PortalLead;
use App\Models\PortalOrder;
use App\Services\Industry\IndustryProfileRegistry;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $profiles = app(IndustryProfileRegistry::class)->all();

        return view('portal.landing', [
            'modules' => $modules,
            'plans' => $plans,
            'hardwareFingerprint' => $hardwareFingerprint,
            'profiles' => $profiles,
        ]);
    }

    /**
     * Official Printable Product Brochure (A4 / Standalone).
     */
    public function brochure()
    {
        $file = public_path('brochure.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Official Visual Tour with Screenshots & Functional Explanations.
     */
    public function tour()
    {
        $file = public_path('tour.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Interactive Video Walkthrough with Pan/Zoom, Pointers & Audio Narration.
     */
    public function videoTour()
    {
        $file = public_path('video-tour.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Official 60-Second Dramatic Commercial Teaser.
     */
    public function teaser()
    {
        $file = public_path('teaser.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Masterclass Episode 1: Executive Cockpit & AI Financial Copilot.
     */
    public function episode01()
    {
        $file = public_path('episode-01.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Masterclass Episode 2: Retail POS & 100% Offline-First Architecture.
     */
    public function episode02()
    {
        $file = public_path('episode-02.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Masterclass Episode 3: Warehouse & Real-time COGS Intelligence.
     */
    public function episode03()
    {
        $file = public_path('episode-03.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
    }

    /**
     * Masterclass Episode 4: Moadian Tax Shield & Direct Clearance.
     */
    public function episode04()
    {
        $file = public_path('episode-04.html');
        if (file_exists($file)) {
            return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        abort(404);
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
            'customer_name' => $validated['customer_name'].' ('.$validated['phone'].')',
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

        // Persist lead to database for sales CRM and tracking
        try {
            PortalLead::create([
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'business_type' => $validated['business_type'] ?? null,
                'ip_address' => $request->ip(),
                'status' => 'new',
                'trial_license_key' => $licenseKey,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist portal lead: '.$e->getMessage());
        }

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

        // Persist order to database for portal admin management
        try {
            PortalOrder::create([
                'order_reference' => $orderReference,
                'plan' => $validated['plan'],
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'company_name' => $validated['company_name'] ?? null,
                'hardware_fingerprint' => $validated['hardware_fingerprint'] ?? null,
                'extra_companies' => $extraCompanies,
                'extra_users' => $extraUsers,
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist portal order: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'order_reference' => $orderReference,
            'plan_title' => $selectedPlan['name'] ?? $selectedPlan['title'] ?? $validated['plan'],
            'total_price_irr' => $totalPrice,
            'total_price_formatted' => number_format($totalPrice).' تومان',
            'message' => 'سفارش لایسنس شما با موفقیت ثبت شد. کارشناسان نپتون جهت فعال‌سازی نهایی و هماهنگی با شما تماس خواهند گرفت.',
        ]);
    }

    /**
     * Download the standalone Windows Desktop Installer (.exe).
     */
    public function downloadInstaller()
    {
        $githubReleaseUrl = 'https://github.com/hadibehnampour0-sketch/HesaBora/releases/download/v2.6.0/Neptune-ERP-Setup-v2.6.0.exe';

        $filePath = public_path('downloads/Neptune-ERP-Setup-v2.6.0.exe');
        if (! file_exists($filePath)) {
            $backupPath = base_path('build/output/Neptune-ERP-Setup-v2.6.0.exe');
            if (file_exists($backupPath)) {
                $filePath = $backupPath;
            } else {
                return redirect()->away($githubReleaseUrl);
            }
        }

        if (request()->has('cdn') || request()->isSecure()) {
            return redirect()->away($githubReleaseUrl);
        }

        return response()->download(
            $filePath,
            'Neptune-ERP-Setup-v2.6.0.exe',
            ['Content-Type' => 'application/vnd.microsoft.portable-executable']
        );
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
