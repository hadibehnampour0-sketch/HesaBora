<?php

namespace App\Http\Controllers\Financing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Services\Financing\FinancingService;
use App\Services\Financing\InstallmentCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinancingController extends Controller
{
    public function __construct(
        protected FinancingService $financingService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();

        $contracts = InstallmentContract::query()
            ->where('company_id', $companyId)
            ->with(['customer', 'plan'])
            ->latest()
            ->paginate(15);

        $plans = FinancingPlan::query()
            ->where('company_id', $companyId)
            ->get();

        $metrics = [
            'active_count' => InstallmentContract::query()->where('company_id', $companyId)->where('status', 'ACTIVE')->count(),
            'total_financing' => InstallmentContract::query()->where('company_id', $companyId)->sum('total_amount'),
            'total_collected' => InstallmentSchedule::query()->where('company_id', $companyId)->sum('paid_amount'),
            'overdue_count' => InstallmentSchedule::query()->where('company_id', $companyId)->where('status', 'OVERDUE')->count(),
        ];

        return view('financing.index', compact('contracts', 'plans', 'metrics'));
    }

    public function create(Request $request)
    {
        $companyId = getActiveCompany();
        $customers = Customer::query()->where('company_id', $companyId)->orderBy('name')->get();
        $plans = FinancingPlan::query()->where('company_id', $companyId)->where('is_active', true)->get();

        return view('financing.create', compact('customers', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'plan_id' => ['nullable', 'exists:financing_plans,id'],
            'calculation_method' => ['nullable', 'string', 'in:MARKET_MONTHLY,BANKING_AMORTIZATION'],
            'total_price' => ['required', 'integer', 'min:10000'],
            'down_payment' => ['nullable', 'integer', 'min:0'],
            'monthly_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['company_id'] = getActiveCompany();

        $contract = $this->financingService->createContract($validated, $request->user());

        return redirect()->route('financing.show', $contract->id)->with('success', __('قرارداد فروش اقساطی با موفقیت ثبت و جدول اقساط تولید شد.'));
    }

    public function show(InstallmentContract $contract)
    {
        $contract->load(['customer', 'plan', 'schedules']);
        $summary = $this->financingService->getContractSummary($contract);

        return view('financing.show', compact('contract', 'summary'));
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'principal' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:MARKET_MONTHLY,BANKING_AMORTIZATION'],
            'monthly_rate' => ['nullable', 'numeric'],
            'annual_rate' => ['nullable', 'numeric'],
            'tenor_months' => ['required', 'integer', 'min:1'],
        ]);

        if ($validated['method'] === 'BANKING_AMORTIZATION') {
            $result = InstallmentCalculator::calculateBankingPlan(
                $validated['principal'],
                (float) ($validated['annual_rate'] ?? 23.0),
                $validated['tenor_months']
            );
        } else {
            $result = InstallmentCalculator::calculateMarketPlan(
                $validated['principal'],
                (float) ($validated['monthly_rate'] ?? 3.0),
                $validated['tenor_months']
            );
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function paySchedule(InstallmentSchedule $schedule, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $updated = $this->financingService->payInstallment($schedule, $validated['amount'], $request->user());

        return response()->json([
            'success' => true,
            'message' => __('دریافت قسط با موفقیت ثبت شد.'),
            'data' => $updated,
        ]);
    }

    public function applyPenalties(InstallmentContract $contract, Request $request): RedirectResponse
    {
        $totalPenalties = $this->financingService->calculateAndApplyOverduePenalties($contract);

        return back()->with('success', __('خسارت تأخیر تأدیه اقساط معوق به مبلغ :amount ریال محاسبه و اعمال شد.', [
            'amount' => number_format($totalPenalties),
        ]));
    }
}
