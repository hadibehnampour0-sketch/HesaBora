<?php

namespace App\Http\Controllers;

use App\Models\RepCommission;
use App\Models\RouteMetric;
use App\Services\RepCommissionEngineService;
use App\Services\RouteAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine). One
 * dashboard covering both RepCommission and RouteMetric for a shared
 * period, with a single "Recalculate" action that runs both engines --
 * they're read together everywhere a rep/route manager would look at
 * this, so a single controller/view avoids splitting one dashboard's
 * worth of context across two near-identical screens.
 */
class FieldOperationsAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($request);

        // Guardrail #35: a plain ->where() against a 'date'-cast column
        // compares the raw string against its stored, datetime-formatted
        // value and never matches -- whereDate() is required here too.
        $commissions = RepCommission::with('user')
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->get();

        $routeMetrics = RouteMetric::with('route')
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->get();

        return view('field-operations.analytics.index', compact('commissions', 'routeMetrics', 'periodStart', 'periodEnd'));
    }

    public function recalculate(Request $request, RepCommissionEngineService $commissionService, RouteAnalyticsService $routeAnalyticsService): RedirectResponse
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($request);

        $commissionService->calculateForPeriod(getActiveCompany(), $periodStart, $periodEnd);
        $routeAnalyticsService->analyzeForPeriod(getActiveCompany(), $periodStart, $periodEnd);

        return redirect()->route('field-operations-analytics.index', ['period_start' => $periodStart, 'period_end' => $periodEnd])
            ->with('success', __('Commissions and route metrics recalculated successfully.'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $periodStart = $request->filled('period_start') ? $request->input('period_start') : Carbon::now()->startOfMonth()->toDateString();
        $periodEnd = $request->filled('period_end') ? $request->input('period_end') : Carbon::now()->toDateString();

        return [$periodStart, $periodEnd];
    }
}
