<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\PortalLead;
use App\Models\PortalOrder;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalAdminController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Commercial Command Center & Portal Admin Dashboard.
     */
    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->can('configs.index') || auth()->user()->isSuperAdmin(),
            403,
            __('دسترسی به پنل مدیریت پرتال و لایسنس‌های نپتون مجاز نیست.')
        );

        $totalLeads = PortalLead::count();
        $newLeads = PortalLead::where('status', 'new')->count();
        $totalOrders = PortalOrder::count();
        $pendingOrders = PortalOrder::where('status', 'pending')->count();
        $totalRevenue = PortalOrder::whereIn('status', ['paid', 'completed'])->sum('total_price');

        $recentLeads = PortalLead::latest()->take(15)->get();
        $recentOrders = PortalOrder::latest()->take(15)->get();
        $recentLicenses = License::latest()->take(10)->get();

        $plans = $this->licenseService->getAllPlans();
        $modules = $this->licenseService->getAllModules();
        $hardwareFingerprint = $this->licenseService->generateMachineFingerprint();

        $releaseInfo = [
            'version' => 'v2.5.0',
            'release_date' => '۱۴۰۳/۰۸/۱۶',
            'download_url' => 'https://github.com/hadibehnampour0-sketch/HesaBora/releases/download/v2.5.0/Neptune-ERP-Setup-v2.5.exe',
            'installer_name' => 'Neptune-ERP-Setup-v2.5.exe',
            'size' => '217 MB',
            'architecture' => 'Standalone Offline-First (Embedded PHP + SQLite + MariaDB)',
        ];

        return view('management.portal.index', compact(
            'totalLeads',
            'newLeads',
            'totalOrders',
            'pendingOrders',
            'totalRevenue',
            'recentLeads',
            'recentOrders',
            'recentLicenses',
            'plans',
            'modules',
            'hardwareFingerprint',
            'releaseInfo'
        ));
    }

    /**
     * Update lead status and follow-up notes.
     */
    public function updateLead(Request $request, PortalLead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,converted,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lead->status = $validated['status'];
        if ($validated['status'] === 'converted' && ! $lead->converted_at) {
            $lead->converted_at = now();
        }
        if (isset($validated['notes'])) {
            $lead->notes = $validated['notes'];
        }
        $lead->save();

        return redirect()->back()->with('success', 'وضعیت سرنخ با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Update order status.
     */
    public function updateOrder(Request $request, PortalOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,completed,cancelled',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $order->status = $validated['status'];
        if (isset($validated['admin_notes'])) {
            $order->admin_notes = $validated['admin_notes'];
        }
        $order->save();

        return redirect()->back()->with('success', 'وضعیت سفارش با موفقیت ثبت شد.');
    }

    /**
     * Issue an official license key for a paid portal order.
     */
    public function issueOrderLicense(Request $request, PortalOrder $order): RedirectResponse
    {
        $plans = $this->licenseService->getAllPlans();
        $planConfig = $plans[$order->plan] ?? reset($plans);

        $licenseData = [
            'edition' => $order->plan,
            'customer_name' => $order->customer_name.($order->company_name ? " ({$order->company_name})" : ''),
            'hardware_fingerprint' => $order->hardware_fingerprint ?: '*',
            'modules' => $planConfig['modules'] ?? ['*'],
            'max_companies' => ($planConfig['max_companies'] ?? 1) + $order->extra_companies,
            'max_users' => ($planConfig['max_users'] ?? 2) + $order->extra_users,
            'max_devices' => 10,
            'issued_at' => now()->timestamp,
            'expires_at' => null, // Lifetime commercial license
            'grace_days' => 30,
        ];

        $licenseKey = $this->licenseService->issueLicenseKey($licenseData);

        $order->issued_license_key = $licenseKey;
        $order->status = 'completed';
        $order->save();

        return redirect()->back()->with('success', 'لایسنس رسمی دائمی با موفقیت برای این سفارش صادر و ثبت شد.')
            ->with('generated_key', $licenseKey);
    }

    /**
     * Manually issue a custom Ed25519 signed license.
     */
    public function issueCustomLicense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'edition' => 'required|string',
            'hardware_fingerprint' => 'nullable|string|max:64',
            'max_companies' => 'required|integer|min:-1|max:1000',
            'max_users' => 'required|integer|min:-1|max:1000',
            'duration_months' => 'required|integer|min:0|max:120', // 0 = lifetime
            'modules' => 'nullable|array',
        ]);

        $modules = $validated['modules'] ?? ['*'];
        $expiresAt = $validated['duration_months'] > 0
            ? now()->addMonths((int) $validated['duration_months'])->timestamp
            : null;

        $payload = [
            'edition' => $validated['edition'],
            'customer_name' => $validated['customer_name'],
            'hardware_fingerprint' => ! empty($validated['hardware_fingerprint']) ? $validated['hardware_fingerprint'] : '*',
            'modules' => $modules,
            'max_companies' => (int) $validated['max_companies'],
            'max_users' => (int) $validated['max_users'],
            'max_devices' => 10,
            'issued_at' => now()->timestamp,
            'expires_at' => $expiresAt,
            'grace_days' => 14,
        ];

        $key = $this->licenseService->issueLicenseKey($payload);

        return redirect()->back()
            ->with('success', 'کلید لایسنس جدید نپتون با موفقیت تولید و امضا شد.')
            ->with('generated_key', $key);
    }
}
