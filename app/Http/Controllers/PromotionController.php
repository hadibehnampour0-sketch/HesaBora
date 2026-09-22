<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Services\PromotionEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionEngineService $engine) {}

    public function index()
    {
        return response()->json(['promotions' => Promotion::orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(PromotionType::valueNames())],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'rules' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $promotion = Promotion::create($validated);

        return response()->json(['promotion' => $promotion], 201);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'rules' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $promotion->update($validated);

        return response()->json(['promotion' => $promotion->fresh()]);
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return response()->json(['deleted' => true]);
    }

    public function evaluate(Request $request)
    {
        $validated = $request->validate([
            'customer_group_id' => ['nullable', 'integer'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.product_id' => ['required', 'integer'],
            'cart.*.quantity' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
        ]);

        $result = $this->engine->evaluate(
            $validated['customer_group_id'] ?? null,
            $validated['cart'],
            isset($validated['date']) ? Carbon::parse($validated['date']) : null
        );

        return response()->json($result);
    }
}
