<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceQuote;
use App\Models\Product;
use App\Services\PriceQuoteService;
use Illuminate\Http\Request;

class PriceQuoteController extends Controller
{
    public function __construct(
        protected PriceQuoteService $quoteService
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) getActiveCompany();

        $customerSearch = $request->input('customer');
        $productSearch = $request->input('product');

        if ($customerSearch || $productSearch) {
            $quotes = $this->quoteService->searchQuotes($companyId, $customerSearch, $productSearch);
        } else {
            $quotes = $this->quoteService->getRecentQuotes($companyId, 50);
        }

        $customers = Customer::where('company_id', $companyId)->orderBy('name')->take(100)->get(['id', 'name', 'mobile']);
        $products = Product::where('company_id', $companyId)->orderBy('name')->take(100)->get(['id', 'name', 'selling_price']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'quotes' => $quotes,
            ]);
        }

        return view('price_quotes.index', [
            'quotes' => $quotes,
            'customers' => $customers,
            'products' => $products,
            'customerSearch' => $customerSearch,
            'productSearch' => $productSearch,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'quoted_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|in:toman,rial',
            'notes' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'customer_phone' => 'nullable|string|max:32',
        ]);

        $companyId = (int) getActiveCompany();

        $quote = $this->quoteService->recordQuote(
            companyId: $companyId,
            userId: $request->user()?->id,
            customerName: $request->input('customer_name'),
            productName: $request->input('product_name'),
            quotedPrice: (float) $request->input('quoted_price'),
            currency: $request->input('currency', 'toman'),
            notes: $request->input('notes'),
            customerId: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
            productId: $request->input('product_id') ? (int) $request->input('product_id') : null,
            customerPhone: $request->input('customer_phone')
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'استعلام قیمت با موفقیت ثبت شد و در حافظه ذخیره گردید.',
                'quote' => $quote,
            ]);
        }

        return redirect()->route('price-quotes.index')->with('success', 'استعلام قیمت با موفقیت ثبت شد.');
    }

    public function destroy(PriceQuote $priceQuote)
    {
        $priceQuote->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('price-quotes.index')->with('success', 'استعلام قیمت حذف شد.');
    }
}
