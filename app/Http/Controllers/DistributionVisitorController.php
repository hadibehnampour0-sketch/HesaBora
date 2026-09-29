<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DistributionVisitor;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Visitors (ویزیتور): company users who work the field, each optionally with a van warehouse. */
class DistributionVisitorController extends Controller
{
    public function index(): View
    {
        $visitors = DistributionVisitor::with(['user', 'warehouse'])->get()->sortBy(fn ($v) => $v->user?->name)->values();

        return view('distribution.visitors', [
            'visitors' => $visitors,
            'candidates' => Company::findOrFail(getActiveCompany())->users()->whereNotIn('users.id', $visitors->pluck('user_id'))->orderBy('name')->get(['users.id', 'users.name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', getActiveCompany()),
                Rule::unique('distribution_visitors', 'user_id')->where('company_id', getActiveCompany())],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $visitor = DistributionVisitor::create([
            'user_id' => $validated['user_id'],
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->boolean('create_van_warehouse') && ! $visitor->warehouse_id) {
            $visitor->update(['warehouse_id' => $this->createVanWarehouse($visitor)->id]);
        }

        return back()->with('success', __('Visitor added successfully.'));
    }

    public function update(Request $request, DistributionVisitor $visitor): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $visitor->update($validated + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Visitor updated successfully.'));
    }

    public function destroy(DistributionVisitor $visitor): RedirectResponse
    {
        $visitor->delete(); // the van warehouse and past plans/settlements are kept

        return back()->with('success', __('Visitor removed. Their warehouse and history were kept.'));
    }

    private function createVanWarehouse(DistributionVisitor $visitor): Warehouse
    {
        $base = __('Van warehouse').' - '.$visitor->user->name;
        $name = $base;
        $suffix = 2;
        while (Warehouse::where('name', $name)->exists()) {
            $name = $base.' ('.$suffix++.')';
        }

        return Warehouse::create(['name' => mb_substr($name, 0, 100)]);
    }
}
