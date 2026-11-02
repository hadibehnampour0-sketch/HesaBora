<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\DailySettlementStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\VisitPlan;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mobile Companion for Field Distribution Sales Reps and WMS Warehouse Operators.
 * Fast, touch-first, offline-capable PWA interface with HTML5 barcode scanner.
 */
class MobileCompanionController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = getActiveCompany();
        $warehouses = Warehouse::where('company_id', $companyId)->get(['id', 'name', 'code']);

        return view('mobile.index', [
            'user' => $request->user(),
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $warehouses->first()?->id,
        ]);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'دخل همراه | پخش و انبارداری',
            'short_name' => 'دخل همراه',
            'description' => 'اپلیکیشن همراه دخل برای ویزیتورهای پخش مویرگی و انبارداران',
            'start_url' => '/mobile',
            'display' => 'standalone',
            'background_color' => '#0f172a',
            'theme_color' => '#06b6d4',
            'orientation' => 'portrait',
            'dir' => 'rtl',
            'lang' => 'fa',
            'icons' => [
                [
                    'src' => '/favicon.ico',
                    'sizes' => '64x64 32x32 24x24 16x16',
                    'type' => 'image/x-icon',
                ],
            ],
        ]);
    }

    public function serviceWorker(): Response
    {
        $swScript = <<<'JS'
const CACHE_NAME = 'dakhl-mobile-shell-v1';
const SHELL_URLS = [
    '/mobile',
    '/mobile/manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) => {
            return Promise.all(
                names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
JS;

        return response($swScript, 200, ['Content-Type' => 'application/javascript']);
    }

    public function catalog(Request $request): JsonResponse
    {
        $companyId = getActiveCompany();
        $warehouseId = $request->input('warehouse_id');

        $products = Product::where('company_id', $companyId)
            ->with(['warehouseStocks' => function ($query) use ($warehouseId) {
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                }
            }])
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(function ($product) {
                $onHand = (float) $product->warehouseStocks->sum('quantity');

                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'selling_price' => (float) $product->selling_price,
                    'on_hand' => $onHand,
                    'location' => $product->location ?? '',
                    'vat' => (float) ($product->vat ?? 0),
                    'group_id' => $product->group_id,
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'products' => $products,
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $companyId = getActiveCompany();
        $user = $request->user();

        $plan = VisitPlan::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->whereDate('plan_date', today())
            ->with(['route.routeCustomers.customer'])
            ->first();

        $customers = collect();

        if ($plan && $plan->route) {
            $routeCustomers = $plan->route->routeCustomers()->with('customer')->orderBy('visit_priority')->get();
            $customers = $routeCustomers->map(function ($rc) {
                $cust = $rc->customer;

                return [
                    'id' => $cust->id,
                    'name' => $cust->name,
                    'mobile' => $cust->mobile,
                    'phone' => $cust->phone,
                    'address' => $cust->address ?? '',
                    'priority' => $rc->visit_priority,
                    'group_id' => $cust->group_id,
                ];
            });
        } else {
            $customers = Customer::where('company_id', $companyId)
                ->orderBy('name')
                ->limit(100)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'mobile' => $c->mobile,
                    'phone' => $c->phone,
                    'address' => $c->address ?? '',
                    'priority' => 1,
                    'group_id' => $c->group_id,
                ]);
        }

        return response()->json([
            'success' => true,
            'plan_id' => $plan?->id,
            'plan_status' => $plan?->status ?? 'open',
            'route_title' => $plan?->route?->title ?? 'مشتریان فعال',
            'customers' => $customers,
        ]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,pos,cheque,credit'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = getActiveCompany();
        $user = $request->user();

        return DB::transaction(function () use ($validated, $user, $companyId) {
            $items = array_map(function ($item) {
                return [
                    'itemable_type' => 'product',
                    'itemable_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'unit' => (float) $item['price'],
                    'unit_discount' => 0,
                    'vat' => 0,
                ];
            }, $validated['items']);

            $maxNumber = (Invoice::where('company_id', $companyId)->where('invoice_type', InvoiceType::SELL->value)->max('number') ?? 0) + 1;

            $invoiceData = [
                'title' => 'فاکتور سفارش همراه - ویزیتور: '.$user->name,
                'date' => now()->toDateString(),
                'invoice_type' => InvoiceType::SELL,
                'customer_id' => $validated['customer_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'document_number' => $maxNumber,
                'number' => $maxNumber,
                'description' => $validated['notes'] ?? 'ثبت شده از اپلیکیشن همراه دخل',
            ];

            $createResult = InvoiceService::createInvoice($user, $invoiceData, $items, true);
            $invoice = $createResult['invoice'];
            $totalAmount = (float) ($invoice->amount ?? 0) + (float) ($invoice->vat ?? 0) - (float) ($invoice->subtraction ?? 0);

            return response()->json([
                'success' => true,
                'message' => 'فاکتور با موفقیت در دخل ثبت گردید.',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'total_amount' => $totalAmount,
                'printable_receipt' => [
                    'number' => $invoice->number,
                    'customer' => $invoice->customer?->name,
                    'date' => formatDate($invoice->date),
                    'items_count' => count($items),
                    'total_amount' => $totalAmount,
                ],
            ]);
        });
    }

    public function scanBarcode(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->input('code'));
        if ($barcode === '') {
            return response()->json(['success' => false, 'message' => 'بارکد وارد نشده است.'], 422);
        }

        $companyId = getActiveCompany();

        $product = Product::where('company_id', $companyId)
            ->where(function ($q) use ($barcode) {
                $q->where('code', $barcode)
                    ->orWhere('sstid', $barcode);
            })
            ->first();

        if (! $product) {
            $product = Product::where('company_id', $companyId)
                ->where('id', (int) $barcode)
                ->first();
        }

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'کالایی با بارکد یا کد شناسایی '.$barcode.' یافت نشد.',
            ], 404);
        }

        $stocks = WarehouseProductStock::where('product_id', $product->id)
            ->with('warehouse:id,name,code')
            ->get()
            ->map(fn ($s) => [
                'warehouse_name' => $s->warehouse?->name ?? 'انبار',
                'quantity' => (float) $s->quantity,
            ]);

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'selling_price' => (float) $product->selling_price,
                'average_cost' => (float) ($product->average_cost ?? 0),
                'total_on_hand' => (float) $stocks->sum('quantity'),
                'location' => $product->location ?? 'تعیین‌نشده',
                'stocks' => $stocks,
            ],
        ]);
    }

    public function recordStockCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $companyId = getActiveCompany();

        $stock = WarehouseProductStock::firstOrNew([
            'warehouse_id' => $validated['warehouse_id'],
            'product_id' => $validated['product_id'],
        ]);

        $systemQuantity = (float) ($stock->quantity ?? 0);
        $countedQuantity = (float) $validated['counted_quantity'];
        $variance = $countedQuantity - $systemQuantity;

        return response()->json([
            'success' => true,
            'message' => 'شمارش با موفقیت ثبت شد.',
            'product_id' => $validated['product_id'],
            'system_quantity' => $systemQuantity,
            'counted_quantity' => $countedQuantity,
            'variance' => $variance,
        ]);
    }

    public function submitSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_collected' => ['required', 'numeric', 'min:0'],
            'pos_collected' => ['required', 'numeric', 'min:0'],
            'cheque_collected' => ['required', 'numeric', 'min:0'],
        ]);

        $companyId = getActiveCompany();
        $user = $request->user();

        $todayInvoiced = (float) Invoice::where('company_id', $companyId)
            ->where('invoice_type', InvoiceType::SELL->value)
            ->where('creator_id', $user->id)
            ->whereDate('date', today())
            ->sum(DB::raw('COALESCE(amount, 0) + COALESCE(vat, 0) - COALESCE(subtraction, 0)'));

        $settlement = DailySettlement::updateOrCreate([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'settlement_date' => today(),
        ], [
            'total_invoiced_amount' => $todayInvoiced,
            'cash_collected' => $validated['cash_collected'],
            'pos_collected' => $validated['pos_collected'],
            'cheque_collected' => $validated['cheque_collected'],
            'status' => DailySettlementStatus::SUBMITTED->value,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تسویه حساب شیفت روزانه برای تایید صندوق‌دار ارسال گردید.',
            'settlement' => [
                'id' => $settlement->id,
                'total_invoiced' => $todayInvoiced,
                'total_collected' => (float) ($validated['cash_collected'] + $validated['pos_collected'] + $validated['cheque_collected']),
                'status' => $settlement->status,
            ],
        ]);
    }
}
