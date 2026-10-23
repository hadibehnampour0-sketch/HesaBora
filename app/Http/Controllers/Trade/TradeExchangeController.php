<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TradeExchange;
use App\Models\TradeExchangeItem;
use App\Services\Trade\TradeExchangeService;
use Illuminate\Http\Request;

class TradeExchangeController extends Controller
{
    public function __construct(
        protected TradeExchangeService $tradeService
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) getActiveCompany();

        $status = $request->input('status', 'open');

        $query = TradeExchange::where('company_id', $companyId)
            ->with(['customer', 'items'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $exchanges = $query->paginate(20);

        // Calculate summary cards
        $openCount = TradeExchange::where('company_id', $companyId)->where('status', 'open')->count();
        $settledCount = TradeExchange::where('company_id', $companyId)->where('status', 'settled')->count();

        return view('trade.index', [
            'exchanges' => $exchanges,
            'status' => $status,
            'openCount' => $openCount,
            'settledCount' => $settledCount,
        ]);
    }

    public function create()
    {
        $companyId = (int) getActiveCompany();
        $customers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        $products = Product::where('company_id', $companyId)->orderBy('name')->take(200)->get();

        return view('trade.create', [
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.direction' => 'required|in:out,in',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:32',
            'items.*.product_id' => 'nullable|integer',
        ]);

        $companyId = (int) getActiveCompany();

        $exchange = $this->tradeService->createExchange(
            companyId: $companyId,
            customerId: (int) $request->input('customer_id'),
            title: $request->input('title'),
            createdBy: $request->user()?->id,
            notes: $request->input('notes'),
            items: $request->input('items', [])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'exchange' => $exchange->load('items'),
            ]);
        }

        return redirect()->route('trades.show', $exchange)->with('success', 'پرونده تبادل کالا با همکار با موفقیت تشکیل شد.');
    }

    public function show(TradeExchange $trade)
    {
        $trade->load(['customer', 'items.product', 'creator']);
        $summary = $trade->calculateValuationSummary();

        $companyId = (int) getActiveCompany();
        $products = Product::where('company_id', $companyId)->orderBy('name')->take(200)->get();

        return view('trade.show', [
            'exchange' => $trade,
            'summary' => $summary,
            'products' => $products,
        ]);
    }

    public function addItem(Request $request, TradeExchange $trade)
    {
        $request->validate([
            'direction' => 'required|in:out,in',
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:32',
            'product_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $item = $this->tradeService->addItem(
            exchange: $trade,
            direction: $request->input('direction'),
            itemName: $request->input('item_name'),
            quantity: (float) $request->input('quantity'),
            unitPrice: (float) $request->input('unit_price', 0),
            productId: $request->input('product_id') ? (int) $request->input('product_id') : null,
            unit: $request->input('unit', 'عدد'),
            notes: $request->input('notes')
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'item' => $item]);
        }

        return back()->with('success', 'ردیف کالا با موفقیت به تبادل افزوده شد.');
    }

    public function returnItem(Request $request, TradeExchangeItem $item)
    {
        $request->validate([
            'returned_quantity' => 'required|numeric|min:0.01',
        ]);

        $this->tradeService->recordReturn($item, (float) $request->input('returned_quantity'));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'item' => $item]);
        }

        return back()->with('success', 'عودت و برگشت کالا با موفقیت در کاردکس تبادل ثبت گردید.');
    }

    public function settle(Request $request, TradeExchange $trade)
    {
        $request->validate([
            'settlement_type' => 'required|in:goods,cash,ledger',
            'agreed_cash_amount' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->tradeService->settleExchange(
            exchange: $trade,
            user: $request->user(),
            settlementType: $request->input('settlement_type'),
            agreedCashAmount: $request->filled('agreed_cash_amount') ? (float) $request->input('agreed_cash_amount') : null,
            notes: $request->input('notes')
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تبادل با موفقیت تسویه و بسته شد.']);
        }

        return redirect()->route('trades.show', $trade)->with('success', 'تبادل با موفقیت تسویه و پرونده بسته شد.');
    }
}
