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
