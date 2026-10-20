<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Invoice;
use App\Services\Loyalty\CustomerLoyaltyWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerLoyaltyController extends Controller
{
    public function __construct(
        protected CustomerLoyaltyWalletService $loyaltyService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();
        $settings = $this->loyaltyService->getSettings($companyId);

        $wallets = CustomerWallet::query()
            ->where('company_id', $companyId)
            ->with('customer')
            ->orderByDesc('points')
            ->paginate(15);

        $recentTransactions = CustomerWalletTransaction::query()
            ->where('company_id', $companyId)
            ->with(['customer', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        return view('loyalty.index', compact('settings', 'wallets', 'recentTransactions'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $companyId = getActiveCompany();
        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'spend_per_point' => ['required', 'integer', 'min:1'],
            'point_value_rials' => ['required', 'integer', 'min:1'],
            'silver_points_threshold' => ['required', 'integer', 'min:1'],
            'gold_points_threshold' => ['required', 'integer', 'min:1'],
            'vip_points_threshold' => ['required', 'integer', 'min:1'],
            'silver_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'gold_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'vip_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');

        $this->loyaltyService->updateSettings($companyId, $validated);

        return redirect()->route('loyalty.index')->with('success', __('تنظیمات باشگاه مشتریان با موفقیت ذخیره شد.'));
    }

    public function customerWallet(Customer $customer)
    {
        $wallet = $this->loyaltyService->getOrCreateWallet($customer);
        $transactions = $wallet->transactions()->latest()->paginate(15);

        return view('loyalty.customer_wallet', compact('customer', 'wallet', 'transactions'));
    }

    public function convertPoints(Customer $customer, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
        ]);

        $this->loyaltyService->convertPointsToWalletCredit(
            $customer,
            $validated['points'],
            $request->user()
        );

        return back()->with('success', __('امتیازهای درخواستی با موفقیت به اعتبار کیف پول تبدیل شد.'));
    }

    public function deposit(Customer $customer, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $this->loyaltyService->depositWalletCredit(
            $customer,
            $validated['amount'],
            $validated['description'] ?? '',
            $request->user()
        );

        return back()->with('success', __('کیف پول مشتری با موفقیت شارژ شد.'));
    }

    public function payInvoice(Invoice $invoice, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $tx = $this->loyaltyService->payInvoiceFromWallet(
            $invoice,
            $validated['amount'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => __('پرداخت از محل کیف پول با موفقیت ثبت شد.'),
            'data' => $tx,
        ]);
    }
}
