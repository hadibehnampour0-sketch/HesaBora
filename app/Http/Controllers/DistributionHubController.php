<?php

namespace App\Http\Controllers;

use App\Enums\DailySettlementStatus;
use App\Enums\VisitPlanStatus;
use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\SalesRoute;
use App\Models\VisitPlan;
use Illuminate\View\View;

/**
 * Capillary distribution (پخش مویرگی) landing page. A presentation layer over the P11/P12.4
 * backend: routes, visitors, visit plans, daily settlements and (existing) analytics.
 */
class DistributionHubController extends Controller
{
    public function index(): View
    {
        return view('distribution.index', [
            'routeCount' => SalesRoute::where('is_active', true)->count(),
            'visitorCount' => DistributionVisitor::where('is_active', true)->count(),
            'todayPlans' => VisitPlan::whereDate('plan_date', today())->where('status', '!=', VisitPlanStatus::COMPLETED->value)->count(),
            'pendingSettlements' => DailySettlement::where('status', DailySettlementStatus::SUBMITTED->value)->count(),
        ]);
    }
}
