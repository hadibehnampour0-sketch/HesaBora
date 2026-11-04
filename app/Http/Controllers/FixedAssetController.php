<?php

namespace App\Http\Controllers;

use App\Models\AssetMaintenance;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\Subject;
use App\Services\FixedAssetService;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service) {}

    public function index(Request $request)
    {
        $assets = FixedAsset::with(['depreciationEntries', 'maintenances'])
            ->orderByDesc('id')
            ->get();

        $companyId = (int) getActiveCompany();

        $metrics = [
            'total_assets_count' => $assets->count(),
            'total_acquisition_cost' => (float) $assets->sum('acquisition_cost'),
            'total_accumulated_depreciation' => (float) $assets->sum(fn ($a) => $this->service->accumulatedDepreciation($a)),
            'total_maintenance_expenses' => (float) $assets->sum(fn ($a) => $a->totalMaintenanceCost()),
            'critical_burn_count' => $assets->filter(fn ($a) => $a->maintenanceToAssetRatio() >= 35.0)->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'assets' => $assets,
                'metrics' => $metrics,
            ]);
        }

        $serviceProviders = Customer::orderBy('name')->get(['id', 'name', 'mobile']);
        $subjects = Subject::orderBy('code')->get(['id', 'name', 'code']);

        return view('fixed-assets.index', [
            'assets' => $assets,
            'metrics' => $metrics,
            'service' => $this->service,
            'subjects' => $subjects,
            'serviceProviders' => $serviceProviders,
            'categories' => FixedAsset::categoryLabels(),
        ]);
    }

    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load(['depreciationEntries.document', 'maintenances.document', 'maintenances.serviceProvider', 'maintenances.createdBy']);

        $serviceProviders = Customer::orderBy('name')->get(['id', 'name', 'mobile']);
        $subjects = Subject::orderBy('code')->get(['id', 'name', 'code']);
        $accumulated = $this->service->accumulatedDepreciation($fixedAsset);
        $remaining = $this->service->remainingDepreciableAmount($fixedAsset);
        $health = $fixedAsset->healthStatus();
        $aiAdvice = $fixedAsset->aiRecommendation();

        return view('fixed-assets.show', [
            'asset' => $fixedAsset,
            'service' => $this->service,
            'accumulated' => $accumulated,
            'remaining' => $remaining,
            'health' => $health,
            'aiAdvice' => $aiAdvice,
            'serviceProviders' => $serviceProviders,
            'subjects' => $subjects,
            'maintenanceTypes' => AssetMaintenance::typeLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $this->convertJalali($request, ['acquisition_date']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:60'],
            'serial_or_plate' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'asset_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'depreciation_expense_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'accumulated_depreciation_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $asset = $this->service->register($validated, $request->user());

        return redirect()->route('fixed-assets.show', $asset)
            ->with('success', __('وسیله / دارایی با موفقیت در سیستم ثبت گردید.'));
    }

    public function storeMaintenance(Request $request, FixedAsset $fixedAsset)
    {
        $this->convertJalali($request, ['date']);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'maintenance_type' => ['required', 'string', 'in:service,repair,part_replacement,capital_improvement,inspection_insurance'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'service_provider_id' => ['nullable', 'integer', 'exists:customers,id'],
            'odometer_or_hours' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'payment_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'invoice_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $attachment = $request->file('invoice_attachment');
        $maintenance = $this->service->recordMaintenance($fixedAsset, $validated, $request->user(), $attachment);

        return redirect()->route('fixed-assets.show', $fixedAsset)
            ->with('success', __('هزینه تعمیر و نگهداری با موفقیت در اسناد مالی و پرونده دارایی ثبت شد.'));
    }

    public function destroyMaintenance(FixedAsset $fixedAsset, AssetMaintenance $maintenance)
    {
        if ($maintenance->fixed_asset_id !== $fixedAsset->id) {
            abort(404);
        }

        $this->service->deleteMaintenance($fixedAsset, $maintenance, request()->user());

        return redirect()->route('fixed-assets.show', $fixedAsset)
            ->with('success', __('رکورد تعمیرات با موفقیت حذف گردید.'));
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
