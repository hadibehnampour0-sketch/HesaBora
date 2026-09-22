<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\StockReservationService;
use Illuminate\Http\Request;

class StockReservationController extends Controller
{
    public function __construct(private readonly StockReservationService $service) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $reservation = $this->service->reserve($product, $warehouse, (float) $validated['quantity'], null, $request->user());

        return redirect()->back()->with('success', __('Stock reserved successfully.'))->with('reservation_id', $reservation->id);
    }

    public function release(StockReservation $stockReservation)
    {
        $this->service->release($stockReservation);

        return redirect()->back()->with('success', __('Reservation released successfully.'));
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        return response()->json([
            'on_hand' => $this->service->onHandQuantity($product, $warehouse),
            'reserved' => $this->service->reservedQuantity($product, $warehouse),
            'available' => $this->service->availableQuantity($product, $warehouse),
        ]);
    }
}
