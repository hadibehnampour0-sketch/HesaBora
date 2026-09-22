<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductSerialNumber;
use App\Models\Warehouse;
use App\Services\ProductTrackingService;
use Illuminate\Http\Request;

class ProductTrackingController extends Controller
{
    public function __construct(private readonly ProductTrackingService $service) {}

    public function storeLot(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lot_number' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'received_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $lot = $this->service->receiveLot($product, $warehouse, $validated['lot_number'], (float) $validated['quantity'], $validated['received_date'], $validated['expiry_date'] ?? null);

        return redirect()->back()->with('success', __('Lot registered successfully.'))->with('lot_id', $lot->id);
    }

    public function storeSerial(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'serial_number' => ['required', 'string', 'max:150'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $serial = $this->service->registerSerial($product, $warehouse, $validated['serial_number']);

        return redirect()->back()->with('success', __('Serial number registered successfully.'))->with('serial_id', $serial->id);
    }

    public function markSerialSold(Request $request, ProductSerialNumber $productSerialNumber)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $this->service->markSold($productSerialNumber, $invoice);

        return redirect()->back()->with('success', __('Serial number marked as sold.'));
    }
}
