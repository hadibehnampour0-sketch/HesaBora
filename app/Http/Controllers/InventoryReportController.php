<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(private readonly InventoryReportService $service) {}

    public function valuation(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouse = isset($validated['warehouse_id']) ? Warehouse::findOrFail($validated['warehouse_id']) : null;
        $report = $this->service->valuationReport($warehouse);

        if ($request->expectsJson()) {
            return response()->json($report);
        }

        return view('inventory-reports.valuation', [
            'report' => $report,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'warehouse' => $warehouse,
        ]);
    }

    public function slowMoving(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1'],
        ]);

        $days = (int) ($validated['days'] ?? 90);
        $products = $this->service->slowMovingReport($days);

        if ($request->expectsJson()) {
            return response()->json(['products' => $products]);
        }

        return view('inventory-reports.slow-moving', ['products' => $products, 'days' => $days]);
    }
}
