<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceFulfillmentService;
use Illuminate\Http\Request;

class InvoiceFulfillmentController extends Controller
{
    public function __construct(private readonly InvoiceFulfillmentService $service) {}

    public function storeReceipt(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'received_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->recordGoodsReceipt($invoice, $validated, $request->user());

        return redirect()->back()->with('success', __('Goods receipt recorded successfully.'));
    }

    public function storeDelivery(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'delivered_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->recordDelivery($invoice, $validated, $request->user());

        return redirect()->back()->with('success', __('Delivery note recorded successfully.'));
    }
}
