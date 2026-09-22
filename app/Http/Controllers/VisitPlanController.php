<?php

namespace App\Http\Controllers;

use App\Enums\VisitPlanStatus;
use App\Models\VisitPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = VisitPlan::with(['user', 'route']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('plan_date', $request->date('date'));
        }

        return response()->json(['visit_plans' => $query->orderByDesc('plan_date')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', getActiveCompany())],
            'route_id' => ['nullable', 'integer', Rule::exists('sales_routes', 'id')->where('company_id', getActiveCompany())],
            'plan_date' => ['required', 'date'],
        ]);

        $exists = VisitPlan::where('user_id', $validated['user_id'])
            ->whereDate('plan_date', $validated['plan_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'plan_date' => [__('A visit plan already exists for this user on this date.')],
            ]);
        }

        $plan = VisitPlan::create($validated);

        return response()->json(['visit_plan' => $plan->load(['user', 'route'])], 201);
    }

    public function updateStatus(Request $request, VisitPlan $visitPlan)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(VisitPlanStatus::valueNames())],
        ]);

        $visitPlan->update(['status' => $validated['status']]);

        return response()->json(['visit_plan' => $visitPlan->fresh()]);
    }

    public function destroy(VisitPlan $visitPlan)
    {
        $visitPlan->delete();

        return response()->json(['deleted' => true]);
    }
}
