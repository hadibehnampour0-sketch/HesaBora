<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Services\ManufacturingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionRecipeController extends Controller
{
    public function __construct(private readonly ManufacturingService $service) {}

    public function index(): View
    {
        return view('production.recipes', [
            'recipes' => ProductionRecipe::with('product', 'items.product')->orderByDesc('id')->get(),
            'products' => Product::orderBy('name')->limit(1000)->get(['id', 'name', 'code', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'name' => ['required', 'string', 'max:150'],
            'output_quantity' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $items = array_values(array_filter($validated['items'], fn ($item) => filled($item['product_id'] ?? null) && filled($item['quantity'] ?? null)));

        if (empty($items)) {
            return back()->withErrors(['items' => __('A recipe must have at least one material.')])->withInput();
        }

        foreach ($items as $item) {
            if ((int) $item['product_id'] === (int) $validated['product_id']) {
                return back()->withErrors(['items' => __('A product cannot be a material for its own recipe.')])->withInput();
            }
        }

        $this->service->createRecipe($validated['product_id'], $validated['name'], (float) $validated['output_quantity'], $items, $request->user());

        return back()->with('success', __('Recipe created successfully.'));
    }

    public function update(Request $request, ProductionRecipe $recipe): RedirectResponse
    {
        $recipe->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Recipe updated successfully.'));
    }

    public function destroy(ProductionRecipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return back()->with('success', __('Recipe deleted successfully.'));
    }
}
