<?php

namespace App\Services\Pos;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Hardware\BarcodeScannerParser;
use App\Services\Hardware\EscPosReceiptBuilder;
use App\Services\PaymentService;
use App\Services\Pos\DTO\PosCheckoutResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosTerminalService
{
    public function __construct(
        protected BarcodeScannerParser $scannerParser,
        protected PosInventoryLockService $lockService,
        protected PaymentService $paymentService
    ) {}

    public function lookupBarcode(int $companyId, string $rawBarcode, int $warehouseId = 1, ?string $terminalId = null): array
    {
        $parsed = $this->scannerParser->parse($rawBarcode);

        $trimmedCode = ltrim($parsed->productCode, '0');
        $paddedCode = str_pad($trimmedCode, 5, '0', STR_PAD_LEFT);
        $possibleCodes = array_values(array_unique(array_filter([$parsed->productCode, $trimmedCode, $paddedCode, $rawBarcode])));

        // Try lookup by barcode or code
        $product = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($possibleCodes, $rawBarcode) {
                $q->where('barcode', $rawBarcode)
                  ->orWhereIn('code', $possibleCodes)
                  ->orWhereIn('barcode', $possibleCodes);
            })
            ->first();

        if (! $product) {
            throw new InvalidArgumentException("کالایی با بارکد '{$rawBarcode}' یافت نشد.");
        }

        $availableStock = $this->lockService->getAvailableStock($companyId, $warehouseId, $product->id, $terminalId);

        $unitPrice = (float) $product->selling_price;
        $quantity = $parsed->quantity;
        $totalPrice = $unitPrice * $quantity;

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'barcode' => $product->barcode,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'is_weighted' => $parsed->isWeighted,
            'weight_in_grams' => $parsed->weightInGrams,
            'total_price' => $totalPrice,
            'available_stock' => $availableStock,
        ];
    }

    public function fastCheckout(User $user, int $companyId, array $data): PosCheckoutResult
    {
        $terminalId = $data['terminal_id'] ?? 'POS-01';
        $warehouseId = (int) ($data['warehouse_id'] ?? 1);
        $itemsData = $data['items'] ?? [];

        if (empty($itemsData)) {
            return PosCheckoutResult::failure('سبد خرید صندوق خالی است.');
        }

        return DB::transaction(function () use ($user, $companyId, $terminalId, $warehouseId, $itemsData, $data) {
            // 1. Resolve Warehouse
            $warehouse = \App\Models\Warehouse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->when($warehouseId, fn ($q) => $q->where('id', $warehouseId))
                ->first();

            if (! $warehouse) {
                $warehouse = \App\Models\Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->first();
            }

            if (! $warehouse) {
                $warehouse = \App\Models\Warehouse::create([
                    'company_id' => $companyId,
                    'name' => 'انبار پیش‌فرض فروشگاه',
                ]);
            }
            $resolvedWarehouseId = $warehouse->id;

            // 2. Resolve Customer (Default to walk-in customer if omitted)
            $customerId = $data['customer_id'] ?? null;
            if (! $customerId) {
                $defaultCustomer = Customer::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->first();
                $customerId = $defaultCustomer?->id;
            }

            // 3. Validate stock and calculate totals
            $totalAmount = 0.0;
            $itemsToCreate = [];

            foreach ($itemsData as $row) {
                $productId = (int) $row['product_id'];
                $qty = (float) ($row['quantity'] ?? 1);
                $unitPrice = (float) ($row['price'] ?? 0);
                $discount = (float) ($row['discount'] ?? 0);

                $product = Product::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($productId);

                $available = $this->lockService->getAvailableStock($companyId, $resolvedWarehouseId, $productId, $terminalId);
                if ($qty > $available) {
                    return PosCheckoutResult::failure("موجودی کالای '{$product->name}' کافی نیست (موجودی آزاد: {$available}).");
                }

                $rowAmount = max(0.0, ($qty * $unitPrice) - $discount);
                $totalAmount += $rowAmount;

                $itemsToCreate[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_discount' => $discount,
                    'amount' => $rowAmount,
                ];
            }

            // 4. Create Sales Invoice
            $company = Company::find($companyId);
            $nextNumber = (Invoice::withoutGlobalScopes()->where('company_id', $companyId)->max('number') ?? 1000) + 1;

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'warehouse_id' => $resolvedWarehouseId,
                'invoice_type' => InvoiceType::SELL,
                'status' => InvoiceStatus::APPROVED,
                'number' => $nextNumber,
                'amount' => $totalAmount,
                'final_amount' => $totalAmount,
                'vat' => 0,
                'date' => Carbon::today()->toDateString(),
            ]);

            // 4. Create Line Items & Decrement Product Quantity
            foreach ($itemsToCreate as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'itemable_id' => $item['product']->id,
                    'itemable_type' => Product::class,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_discount' => $item['unit_discount'],
                    'vat' => 0,
                    'amount' => $item['amount'],
                ]);

                // Decrement stock
                $item['product']->decrement('quantity', $item['quantity']);

                // Commit/release LAN lock
                $this->lockService->commitReservation($companyId, $warehouseId, $item['product']->id, $terminalId);
            }

            // 5. Build ESC/POS Thermal Receipt
            $receiptBuilder = new EscPosReceiptBuilder(42);
            $storeName = $company?->name ?? 'فروشگاه دخل';
            $receiptBuilder->initialize()
                ->addHeader($storeName, 'صندوق: ' . $terminalId, (string) $invoice->number, Carbon::now()->toDateTimeString());

            foreach ($itemsToCreate as $item) {
                $receiptBuilder->addItemRow(
                    $item['product']->name,
                    $item['quantity'],
                    $item['unit_price'],
                    $item['amount']
                );
            }

            $receiptBuilder->addSummary($totalAmount, payable: $totalAmount);

            if (! empty($data['open_drawer'])) {
                $receiptBuilder->openCashDrawer();
            }

            $receiptBuilder->addFooter('سپاس از اعتماد شما - سیستم صندوق هوشمند دخل');
            $receiptBuilder->cutPaper();

            return PosCheckoutResult::success(
                invoice: $invoice,
                receiptBase64: $receiptBuilder->getBase64(),
                rawEscPos: $receiptBuilder->getRawBytes(),
                metadata: [
                    'terminal_id' => $terminalId,
                    'total_items' => count($itemsToCreate),
                    'total_amount' => $totalAmount,
                ]
            );
        });
    }
}
