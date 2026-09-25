<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\Subject;
use App\Services\FixedAssetService;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service) {}

    public function index(Request $request)
    {
        $assets = FixedAsset::orderBy('code')->get();

        if ($request->expectsJson()) {
            return response()->json(['assets' => $assets]);
        }

        return view('fixed-assets.index', [
            'assets' => $assets,
            'service' => $this->service,
            'subjects' => Subject::orderBy('code')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $this->convertJalali($request, ['acquisition_date']);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'asset_subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'depreciation_expense_subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'accumulated_depreciation_subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $asset = $this->service->register($validated, $request->user());

        return redirect()->back()->with('success', __('Fixed asset registered successfully.'))->with('asset_id', $asset->id);
    }

    /** Form dates arrive as Jalali in the Persian UI; API callers send Gregorian (passed through outside `fa`). */
    private function convertJalali(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => jalaliInputToGregorian($request->input($field), $field)]);
            }
        }
    }

    public function postDepreciation(Request $request, FixedAsset $fixedAsset)
    {
        $this->convertJalali($request, ['period_date']);

        $validated = $request->validate([
            'period_date' => ['required', 'date'],
        ]);

        $entry = $this->service->postDepreciation($fixedAsset, $validated['period_date'], $request->user());

        return redirect()->back()->with('success', __('Depreciation posted successfully.'))->with('entry_id', $entry->id);
    }

    public function dispose(Request $request, FixedAsset $fixedAsset)
    {
        $this->convertJalali($request, ['disposed_at']);

        $validated = $request->validate([
            'disposed_at' => ['required', 'date'],
        ]);

        $this->service->dispose($fixedAsset, $validated['disposed_at']);

        return redirect()->back()->with('success', __('Asset disposed successfully.'));
    }
}
