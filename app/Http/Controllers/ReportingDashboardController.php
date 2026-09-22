<?php

namespace App\Http\Controllers;

use App\Enums\AnalyticDimensionType;
use App\Models\AnalyticDimension;
use App\Services\CashFlowReportService;
use App\Services\ChequeRiskDashboardService;
use App\Services\ProfitabilityByDimensionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportingDashboardController extends Controller
{
    public function __construct(
        private readonly CashFlowReportService $cashFlowService,
        private readonly ChequeRiskDashboardService $chequeRiskService,
        private readonly ProfitabilityByDimensionService $profitabilityService,
    ) {}

    public function cashFlow(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ]);

        return response()->json($this->cashFlowService->report($validated['from'], $validated['to'], $validated['group_by'] ?? 'month'));
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

        return response()->json($this->chequeRiskService->report((int) ($validated['due_soon_days'] ?? 7)));
    }

    public function profitabilityByDimension(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'integer', Rule::in(AnalyticDimensionType::values())],
        ]);

        return response()->json(['dimensions' => $this->profitabilityService->report(AnalyticDimensionType::from((int) $validated['type']))]);
    }
}
