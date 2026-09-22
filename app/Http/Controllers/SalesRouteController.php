<?php

namespace App\Http\Controllers;

use App\Models\SalesRoute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesRouteController extends Controller
{
    public function index()
    {
        return response()->json(['routes' => SalesRoute::with('customers')->orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sales_routes', 'code')->where('company_id', getActiveCompany())],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $route = SalesRoute::create($validated);

        return response()->json(['route' => $route], 201);
    }

    public function update(Request $request, SalesRoute $salesRoute)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sales_routes', 'code')->where('company_id', getActiveCompany())->ignore($salesRoute)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $salesRoute->update($validated);

        return response()->json(['route' => $salesRoute->fresh()]);
    }

    public function destroy(SalesRoute $salesRoute)
    {
        $salesRoute->delete();

        return response()->json(['deleted' => true]);
    }

    public function assignCustomer(Request $request, SalesRoute $salesRoute)
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

        return response()->json(['route' => $salesRoute->fresh('customers')]);
    }

    public function removeCustomer(SalesRoute $salesRoute, int $customer)
    {
        $salesRoute->customers()->detach($customer);

        return response()->json(['route' => $salesRoute->fresh('customers')]);
    }
}
