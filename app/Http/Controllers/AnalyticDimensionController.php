<?php

namespace App\Http\Controllers;

use App\Enums\AnalyticDimensionType;
use App\Models\AnalyticDimension;
use App\Models\Transaction;
use App\Services\AnalyticDimensionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticDimensionController extends Controller
{
    public function __construct(private readonly AnalyticDimensionService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', 'integer', Rule::in(AnalyticDimensionType::values())],
        ]);

        $dimensions = isset($validated['type'])
            ? $this->service->listByType(AnalyticDimensionType::from((int) $validated['type']))
            : AnalyticDimension::orderBy('type')->orderBy('code')->get();

        if ($request->expectsJson()) {
            return response()->json(['dimensions' => $dimensions]);
        }

        return view('analytic-dimensions.index', ['dimensions' => $dimensions->groupBy(fn ($dimension) => $dimension->type->value)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'integer', Rule::in(AnalyticDimensionType::values())],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dimension = $this->service->create($validated);

        return redirect()->back()->with('success', __('Analytic dimension created successfully.'))->with('dimension_id', $dimension->id);
    }

    public function update(Request $request, AnalyticDimension $analyticDimension)
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->service->update($analyticDimension, $validated);

        return redirect()->back()->with('success', __('Analytic dimension updated successfully.'));
    }

    public function destroy(AnalyticDimension $analyticDimension)
    {
        $this->service->delete($analyticDimension);

        return redirect()->back()->with('success', __('Analytic dimension deleted successfully.'));
    }

    public function syncOnTransaction(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->document?->company_id === getActiveCompany(), 404);

        $validated = $request->validate([
            'dimension_ids' => ['array'],
            'dimension_ids.*' => ['integer'],
        ]);

        $this->service->syncOnTransaction($transaction, $validated['dimension_ids'] ?? []);

        return redirect()->back()->with('success', __('Analytic dimensions updated successfully.'));
    }

    public function report(Request $request, AnalyticDimension $analyticDimension)
    {
        $breakdown = $this->service->subjectBreakdown($analyticDimension);

        if ($request->expectsJson()) {
            return response()->json(['dimension' => $analyticDimension, 'breakdown' => $breakdown]);
        }

        return view('analytic-dimensions.report', ['dimension' => $analyticDimension, 'breakdown' => $breakdown]);
    }
}
