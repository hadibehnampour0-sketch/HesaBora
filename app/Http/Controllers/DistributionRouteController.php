<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SalesRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Distribution routes (مسیر پخش): named sets of customers a visitor works, with visit priority and days. */
class DistributionRouteController extends Controller
{
    public function index(): View
    {
        return view('distribution.routes', [
            'routes' => SalesRoute::withCount('customers')->with('customers')->orderBy('title')->get(),
            'customers' => Customer::orderBy('name')->limit(1000)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SalesRoute::create($request->validate([
            'title' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sales_routes', 'code')->where('company_id', getActiveCompany())],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', __('Route created successfully.'));
    }

    public function update(Request $request, SalesRoute $salesRoute): RedirectResponse
    {
        $salesRoute->update($request->validate([
            'title' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sales_routes', 'code')->where('company_id', getActiveCompany())->ignore($salesRoute)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Route updated successfully.'));
    }

    public function destroy(SalesRoute $salesRoute): RedirectResponse
    {
        $salesRoute->delete();

        return back()->with('success', __('Route deleted successfully.'));
    }

    public function assignCustomer(Request $request, SalesRoute $salesRoute): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', getActiveCompany())],
            'visit_priority' => ['nullable', 'integer', 'min:0'],
            'preferred_visit_days' => ['nullable', 'string', 'max:50'],
        ]);

        $salesRoute->customers()->syncWithoutDetaching([
            $validated['customer_id'] => [
                'visit_priority' => $validated['visit_priority'] ?? 0,
                'preferred_visit_days' => $validated['preferred_visit_days'] ?? null,
            ],
        ]);

        return back()->with('success', __('Customer added to the route.'));
    }

    public function removeCustomer(SalesRoute $salesRoute, int $customer): RedirectResponse
    {
        $salesRoute->customers()->detach($customer);

        return back()->with('success', __('Customer removed from the route.'));
    }
}
