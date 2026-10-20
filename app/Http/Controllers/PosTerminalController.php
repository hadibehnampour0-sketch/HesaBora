<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Pos\PosInventoryLockService;
use App\Services\Pos\PosTerminalService;
use Illuminate\Http\Request;

class PosTerminalController extends Controller
{
    public function __construct(
        protected PosTerminalService $posService,
        protected PosInventoryLockService $lockService
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) getActiveCompany();
        $company = Company::find($companyId);
        $terminalId = $request->input('terminal', 'POS-01');

        $quickProducts = Product::where('company_id', $companyId)
            ->where('quantity', '>', 0)
            ->limit(24)
            ->get();

        $customers = Customer::where('company_id', $companyId)
            ->limit(30)
            ->get();

        return view('pos.terminal', compact('company', 'terminalId', 'quickProducts', 'customers'));
    }

    public function scanBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'terminal_id' => 'nullable|string',
            'warehouse_id' => 'nullable|integer',
        ]);

        $companyId = (int) getActiveCompany();

        try {
            $item = $this->posService->lookupBarcode(
                companyId: $companyId,
                rawBarcode: $request->input('barcode'),
                warehouseId: (int) $request->input('warehouse_id', 1),
                terminalId: $request->input('terminal_id', 'POS-01')
            );

            return response()->json([
                'success' => true,
                'item' => $item,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reserveItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'terminal_id' => 'required|string',
            'warehouse_id' => 'nullable|integer',
        ]);

        $companyId = (int) getActiveCompany();
        $reserved = $this->lockService->reserveStock(
            companyId: $companyId,
            warehouseId: (int) $request->input('warehouse_id', 1),
            productId: (int) $request->input('product_id'),
            quantity: (float) $request->input('quantity'),
            terminalId: $request->input('terminal_id')
        );

        return response()->json([
            'success' => $reserved,
            'message' => $reserved ? 'موجودی در شبکه محلی رزرو شد.' : 'موجودی کالا در حال حاضر کافی نمی‌باشد.',
        ]);
    }

    public function releaseItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'terminal_id' => 'required|string',
            'warehouse_id' => 'nullable|integer',
        ]);

        $companyId = (int) getActiveCompany();
        $this->lockService->releaseReservation(
            companyId: $companyId,
            warehouseId: (int) $request->input('warehouse_id', 1),
            productId: (int) $request->input('product_id'),
            terminalId: $request->input('terminal_id')
        );

        return response()->json(['success' => true]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'terminal_id' => 'nullable|string',
            'open_drawer' => 'nullable|boolean',
        ]);

        $companyId = (int) getActiveCompany();
        $result = $this->posService->fastCheckout(
            user: $request->user(),
            companyId: $companyId,
            data: $request->all()
        );

        if (! $result->successful) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'فاکتور با موفقیت ثبت و تسویه شد.',
            'invoice_id' => $result->invoice?->id,
            'invoice_number' => $result->invoice?->number,
            'receipt_base64' => $result->receiptBase64,
            'metadata' => $result->metadata,
        ]);
    }
}
