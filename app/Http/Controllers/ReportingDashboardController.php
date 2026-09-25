<?php

namespace App\Http\Controllers;

use App\Enums\AnalyticDimensionType;
use App\Services\CashFlowReportService;
use App\Services\ChequeRiskDashboardService;
use App\Services\ProfitabilityByDimensionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cash-flow, cheque-risk and profitability dashboards. API callers (Accept: JSON) get the same
 * JSON as before; a browser gets the screen. Dates typed in the Persian UI are Jalali.
 */
class ReportingDashboardController extends Controller
{
    public function __construct(
        private readonly CashFlowReportService $cashFlowService,
        private readonly ChequeRiskDashboardService $chequeRiskService,
        private readonly ProfitabilityByDimensionService $profitabilityService,
    ) {}

    public function cashFlow(Request $request)
    {
        $screen = ! $request->expectsJson();
        if ($screen) {
            $request->merge([
                'from' => $request->filled('from') ? jalaliInputToGregorian($request->input('from'), 'from') : now()->subMonths(5)->startOfMonth()->toDateString(),
                'to' => $request->filled('to') ? jalaliInputToGregorian($request->input('to'), 'to') : now()->toDateString(),
            ]);
        }

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ]);

        $report = $this->cashFlowService->report($validated['from'], $validated['to'], $validated['group_by'] ?? 'month');

        if (! $screen) {
            return response()->json($report);
        }

        return view('dashboards.cash-flow', [
            'report' => $report,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'groupBy' => $validated['group_by'] ?? 'month',
        ]);
    }

    public function cashFlowDrillDown(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(['transactions' => $this->cashFlowService->transactionsForPeriod($validated['from'], $validated['to'])]);
    }

    public function chequeRisk(Request $request)
    {
        $validated = $request->validate([
            'due_soon_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $days = (int) ($validated['due_soon_days'] ?? 7);
        $report = $this->chequeRiskService->report($days);

        if ($request->expectsJson()) {
            return response()->json($report);
        }

        return view('dashboards.cheque-risk', ['report' => $report, 'days' => $days]);
    }

    public function profitabilityByDimension(Request $request)
    {
        $screen = ! $request->expectsJson();
        if ($screen && ! $request->filled('type')) {
            $request->merge(['type' => AnalyticDimensionType::BRANCH->value]);
        }

        $validated = $request->validate([
            'type' => ['required', 'integer', Rule::in(AnalyticDimensionType::values())],
        ]);

        $type = AnalyticDimensionType::from((int) $validated['type']);
        $rows = $this->profitabilityService->report($type);

        if (! $screen) {
            return response()->json(['dimensions' => $rows]);
        }

        return view('dashboards.profitability', ['rows' => $rows, 'type' => $type]);
    }
}
