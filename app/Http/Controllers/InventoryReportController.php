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

        return response()->json($this->service->valuationReport($warehouse));
    }

    public function slowMoving(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'products' => $this->service->slowMovingReport((int) ($validated['days'] ?? 90)),
        ]);
    }
}
