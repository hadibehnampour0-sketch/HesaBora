<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\User;
use App\Services\DailySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** End-of-day cash/POS/cheque reconciliation (تسویه روزانه) for visitors, over DailySettlementService. */
class DistributionSettlementController extends Controller
{
    public function __construct(private readonly DailySettlementService $service) {}

    public function index(): View
    {
        return view('distribution.settlements', [
            'settlements' => DailySettlement::with(['user', 'approver'])->orderByDesc('settlement_date')->limit(100)->get(),
            'visitors' => DistributionVisitor::with('user')->where('is_active', true)->get()->sortBy(fn ($v) => $v->user?->name)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['settlement_date' => $request->filled('settlement_date') ? jalaliInputToGregorian($request->input('settlement_date'), 'settlement_date') : null]);
        foreach (['cash_collected', 'pos_collected', 'cheque_collected'] as $field) {
            $request->merge([$field => convertToFloat($request->input($field, 0))]);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('distribution_visitors', 'user_id')->where('company_id', getActiveCompany())],
            'settlement_date' => ['required', 'date'],
            'cash_collected' => ['required', 'numeric', 'min:0'],
            'pos_collected' => ['required', 'numeric', 'min:0'],
            'cheque_collected' => ['required', 'numeric', 'min:0'],
        ]);

        $this->service->createOrUpdateDraft(User::findOrFail($validated['user_id']), Carbon::parse($validated['settlement_date']), [
            'cash_collected' => $validated['cash_collected'],
            'pos_collected' => $validated['pos_collected'],
            'cheque_collected' => $validated['cheque_collected'],
        ]);

        return back()->with('success', __('Settlement saved as a draft.'));
    }

    public function submit(DailySettlement $dailySettlement): RedirectResponse
    {
        $this->service->submit($dailySettlement);

        return back()->with('success', __('Settlement submitted for approval.'));
    }

    public function approve(Request $request, DailySettlement $dailySettlement): RedirectResponse
    {
        $this->service->approve($dailySettlement, $request->user());

        return back()->with('success', __('Settlement approved.'));
    }
}
