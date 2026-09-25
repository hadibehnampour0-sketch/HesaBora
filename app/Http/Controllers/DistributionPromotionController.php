<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Promotions (تخفیف و کالای جایزه) without hand-written JSON: the form is turned into the
 * `rules` shape PromotionEngineService already reads. Nothing here touches an invoice.
 */
class DistributionPromotionController extends Controller
{
    public function index(): View
    {
        return view('distribution.promotions', [
            'promotions' => Promotion::with('customerGroup')->orderByDesc('id')->limit(200)->get(),
            'products' => Product::orderBy('name')->limit(1000)->get(['id', 'name']),
            'customerGroups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
            'productNames' => Product::pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'starts_at' => $request->filled('starts_at') ? jalaliInputToGregorian($request->input('starts_at'), 'starts_at') : null,
            'ends_at' => $request->filled('ends_at') ? jalaliInputToGregorian($request->input('ends_at'), 'ends_at') : null,
        ]);

        $base = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(PromotionType::valueNames())],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $rules = $base['type'] === PromotionType::TIERED_DISCOUNT->value
            ? $this->tieredRules($request)
            : $this->bonusRules($request);

        Promotion::create($base + ['rules' => $rules, 'is_active' => true]);

        return back()->with('success', __('Promotion created successfully.'));
    }

    public function update(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', __('Promotion updated successfully.'));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        return back()->with('success', __('Promotion deleted successfully.'));
    }

    private function tieredRules(Request $request): array
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'tiers' => ['required', 'array'],
            'tiers.*.min_qty' => ['nullable', 'numeric', 'gt:0'],
            'tiers.*.discount_percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        $tiers = collect($data['tiers'])
            ->filter(fn ($tier) => filled($tier['min_qty'] ?? null) && filled($tier['discount_percent'] ?? null))
            ->map(fn ($tier) => ['min_qty' => (float) $tier['min_qty'], 'discount_percent' => (float) $tier['discount_percent']])
            ->sortBy('min_qty')->values()->all();

        if ($tiers === []) {
            throw ValidationException::withMessages(['tiers' => [__('Enter at least one tier (quantity and discount percent).')]]);
        }

        return ['product_id' => $data['product_id'] ?? null, 'product_group_id' => null, 'tiers' => $tiers];
    }

    private function bonusRules(Request $request): array
    {
        $data = $request->validate([
            'buy_product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'buy_qty' => ['required', 'numeric', 'gt:0'],
            'free_product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'free_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        return [
            'buy_product_id' => (int) $data['buy_product_id'],
            'buy_qty' => (float) $data['buy_qty'],
            'free_product_id' => (int) $data['free_product_id'],
            'free_qty' => (float) $data['free_qty'],
        ];
    }
}
