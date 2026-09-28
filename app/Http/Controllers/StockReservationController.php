<?php

namespace App\Http\Controllers;

use App\Enums\StockReservationStatus;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\StockReservationService;
use Illuminate\Http\Request;

class StockReservationController extends Controller
{
    public function __construct(private readonly StockReservationService $service) {}

    /**
     * Active reservations across every product/warehouse. `StockReservation` has no direct
     * "list all active" helper on the service (it is deliberately keyed by product+warehouse),
     * so this reads the model directly -- the same FiscalYearScope every other index does.
     */
    public function index()
    {
        $reservations = StockReservation::with(['product:id,name', 'warehouse:id,name', 'creator:id,name'])
            ->where('status', StockReservationStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('stock-reservations.index', [
            'reservations' => $reservations,
            'products' => Product::orderBy('name')->limit(1000)->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

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
