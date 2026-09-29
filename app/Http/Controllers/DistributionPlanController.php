<?php

namespace App\Http\Controllers;

use App\Enums\VisitPlanStatus;
use App\Models\DistributionVisitor;
use App\Models\SalesRoute;
use App\Models\VisitPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Daily visit plans (برنامه ویزیت): one row per visitor per date, optionally bound to a route. */
class DistributionPlanController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->filled('date') ? jalaliInputToGregorian($request->input('date'), 'date') : null;

        // The week grid always starts on Saturday (the Iranian week's first
        // day) regardless of locale -- Carbon::SATURDAY, not startOfWeek()'s
        // own locale-dependent default (Monday).
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->input('week'))->startOfDay()
            : now()->startOfDay();
        $weekStart = $weekStart->copy()->subDays(($weekStart->dayOfWeek - Carbon::SATURDAY + 7) % 7);
        $weekDays = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));

        $weekPlans = VisitPlan::with(['user', 'route'])
            ->whereBetween('plan_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->get()
            ->groupBy(fn (VisitPlan $plan) => $plan->plan_date->toDateString());

        return view('distribution.plans', [
            'plans' => VisitPlan::with(['user', 'route'])
                ->when($date, fn ($q) => $q->whereDate('plan_date', $date))
                ->orderByDesc('plan_date')->limit(200)->get(),
            'visitors' => DistributionVisitor::with('user')->where('is_active', true)->get()->sortBy(fn ($v) => $v->user?->name)->values(),
            'routes' => SalesRoute::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'filterDate' => $request->input('date'),
            'weekStart' => $weekStart,
            'weekDays' => $weekDays,
            'weekPlans' => $weekPlans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['plan_date' => $request->filled('plan_date') ? jalaliInputToGregorian($request->input('plan_date'), 'plan_date') : null]);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('distribution_visitors', 'user_id')->where('company_id', getActiveCompany())->where('is_active', true)],
            'route_id' => ['nullable', 'integer', Rule::exists('sales_routes', 'id')->where('company_id', getActiveCompany())],
            'plan_date' => ['required', 'date'],
        ]);

        if (VisitPlan::where('user_id', $validated['user_id'])->whereDate('plan_date', $validated['plan_date'])->exists()) {
            throw ValidationException::withMessages(['plan_date' => [__('A visit plan already exists for this user on this date.')]]);
        }

        VisitPlan::create($validated);

        return back()->with('success', __('Visit plan created successfully.'));
    }

    public function updateStatus(Request $request, VisitPlan $visitPlan): RedirectResponse
    {
        $visitPlan->update($request->validate(['status' => ['required', 'string', Rule::in(VisitPlanStatus::valueNames())]]));

        return back()->with('success', __('Visit plan updated successfully.'));
    }

    public function destroy(VisitPlan $visitPlan): RedirectResponse
    {
        $visitPlan->delete();

        return back()->with('success', __('Visit plan deleted successfully.'));
    }
}
