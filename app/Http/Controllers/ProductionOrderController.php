<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(private readonly ManufacturingService $service) {}

    public function index(Request $request): View
    {
        $recipes = ProductionRecipe::with('product')->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $selectedRecipe = $request->filled('recipe_id') ? $recipes->firstWhere('id', (int) $request->input('recipe_id')) : null;
        $selectedWarehouse = $request->filled('warehouse_id') ? $warehouses->firstWhere('id', (int) $request->input('warehouse_id')) : null;
        $quantity = (float) $request->input('quantity', 0);

        $estimate = null;
        if ($selectedRecipe && $selectedWarehouse && $quantity > 0) {
            $recipeModel = ProductionRecipe::with('items.product')->findOrFail($selectedRecipe->id);
            $warehouseModel = Warehouse::findOrFail($selectedWarehouse->id);
            $estimate = $this->service->estimate($recipeModel, $quantity, $warehouseModel);
        }

        return view('production.orders', [
            'recipes' => $recipes,
            'warehouses' => $warehouses,
            'selectedRecipeId' => $selectedRecipe?->id,
            'selectedWarehouseId' => $selectedWarehouse?->id,
            'quantity' => $quantity,
            'estimate' => $estimate,
            'orders' => ProductionOrder::with('product', 'warehouse', 'creator')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipe_id' => ['required', 'integer', Rule::exists('production_recipes', 'id')->where('company_id', getActiveCompany())],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $recipe = ProductionRecipe::findOrFail($validated['recipe_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        try {
            $order = $this->service->produce(
                $recipe,
                (float) $validated['quantity'],
                $warehouse,
                $request->user(),
                (float) ($validated['labor_cost'] ?? 0),
                (float) ($validated['overhead_cost'] ?? 0),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('production.orders.index')->with('success', __('Produced :quantity units at a unit cost of :cost.', [
            'quantity' => rtrim(rtrim(number_format((float) $order->quantity_produced, 3), '0'), '.'),
            'cost' => number_format((float) $order->unit_cost),
        ]));
    }
}
