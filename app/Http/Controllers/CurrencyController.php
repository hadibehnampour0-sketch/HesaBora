<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $service) {}

    public function index(Request $request)
    {
        $currencies = Currency::with('exchangeRates')->orderBy('code')->get();

        if ($request->expectsJson()) {
            return response()->json(['currencies' => $currencies]);
        }

        return view('currencies.index', compact('currencies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_base' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $currency = $this->service->createCurrency($validated);

        return redirect()->back()->with('success', __('Currency created successfully.'))->with('currency_id', $currency->id);
    }

    public function storeRate(Request $request, Currency $currency)
    {
        if ($request->filled('effective_date')) {
            $request->merge(['effective_date' => jalaliInputToGregorian($request->input('effective_date'), 'effective_date')]);
        }

        $validated = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
        ]);

        $rate = $this->service->recordRate($currency, (float) $validated['rate'], $validated['effective_date'], $request->user()->id);

        return redirect()->back()->with('success', __('Exchange rate recorded successfully.'))->with('rate_id', $rate->id);
    }
}
