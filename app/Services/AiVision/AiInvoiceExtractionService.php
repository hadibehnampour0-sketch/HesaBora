<?php

namespace App\Services\AiVision;

use App\Enums\FinancialProvenance;
use App\Enums\InvoiceType;
use App\Models\AiInvoiceExtraction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\AiVision\Contracts\InvoiceVisionParserInterface;
use App\Services\InvoiceService;
use App\Services\Telephony\CallerIdService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AiInvoiceExtractionService
{
    public function __construct(
        protected InvoiceVisionParserInterface $visionParser,
        protected CallerIdService $callerIdService
    ) {}

    /**
     * Ingest a paper invoice/receipt image or PDF, execute vision extraction, and save to queue.
     */
    public function ingest(
        UploadedFile|string $file,
        int $companyId,
        ?int $userId = null,
        ?string $originalName = null
    ): AiInvoiceExtraction {
        $storageDir = "ai_invoices/{$companyId}";
        Storage::disk('public')->makeDirectory($storageDir);

        if ($file instanceof UploadedFile) {
            $originalName = $originalName ?: $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType() ?: ($file->getMimeType() ?: 'image/jpeg');
            $savedPath = $file->store($storageDir, 'public');
            $absolutePath = Storage::disk('public')->path($savedPath);
        } else {
            // Local file path string
            $originalName = $originalName ?: basename($file);
            $mimeType = function_exists('mime_content_type') && file_exists($file) ? (mime_content_type($file) ?: 'image/jpeg') : 'image/jpeg';
            $extension = pathinfo($file, PATHINFO_EXTENSION) ?: 'jpg';
            $targetRelative = $storageDir.'/'.uniqid('ai_inv_').'.'.$extension;
            copy($file, Storage::disk('public')->path($targetRelative));
            $savedPath = $targetRelative;
            $absolutePath = Storage::disk('public')->path($savedPath);
        }

        // Run cognitive vision / OCR extraction
        $extracted = $this->visionParser->parseInvoiceFile($absolutePath, $mimeType);

        $vendor = $extracted['vendor'] ?? [];
        $rawPhone = $vendor['phone'] ?? null;
        $vendorName = $vendor['name'] ?? null;
        $nationalCode = $vendor['national_code'] ?? null;

        // Auto-match vendor if customer exists in company
        $matchedCustomer = null;
        if (! empty($rawPhone)) {
            $normalizedPhone = $this->callerIdService->normalizePhoneNumber($rawPhone);
            $matchedCustomer = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($normalizedPhone) {
                    $q->where('mobile', $normalizedPhone)
                        ->orWhere('phone', $normalizedPhone)
                        ->orWhere('phone', 'like', "%{$normalizedPhone}%");
                })
                ->first();
        }

        if (! $matchedCustomer && ! empty($nationalCode)) {
            $matchedCustomer = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($nationalCode) {
                    $q->where('personal_code', $nationalCode)
                        ->orWhere('ecnmcs_code', $nationalCode);
                })
                ->first();
        }

        if (! $matchedCustomer && ! empty($vendorName)) {
            $matchedCustomer = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', $vendorName)
                ->first();
        }

        return AiInvoiceExtraction::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'file_path' => $savedPath,
            'original_filename' => $originalName,
            'file_type' => $mimeType,
            'status' => 'PENDING_REVIEW',
            'vendor_name' => $vendorName,
            'vendor_national_code' => $nationalCode,
            'vendor_phone' => $rawPhone,
            'invoice_number' => $extracted['invoice_number'] ?? null,
            'invoice_date' => $extracted['invoice_date'] ?? now()->toDateString(),
            'subtotal' => (int) ($extracted['subtotal'] ?? 0),
            'vat_amount' => (int) ($extracted['vat_amount'] ?? 0),
            'total_amount' => (int) ($extracted['total_amount'] ?? 0),
            'confidence_score' => (float) ($extracted['confidence_score'] ?? 0.95),
            'raw_extracted_data' => $extracted,
            'matched_customer_id' => $matchedCustomer?->id,
        ]);
    }

    /**
     * Update extraction fields during human review before final conversion.
     */
    public function updateDraftExtraction(
        AiInvoiceExtraction $extraction,
        array $data,
        ?int $reviewerId = null
    ): AiInvoiceExtraction {
        $raw = $extraction->raw_extracted_data ?? [];

        if (isset($data['items']) && is_array($data['items'])) {
            $raw['items'] = $data['items'];
        }

        if (isset($data['notes'])) {
            $raw['notes'] = $data['notes'];
        }

        $extraction->update([
            'vendor_name' => $data['vendor_name'] ?? $extraction->vendor_name,
            'vendor_national_code' => $data['vendor_national_code'] ?? $extraction->vendor_national_code,
            'vendor_phone' => $data['vendor_phone'] ?? $extraction->vendor_phone,
            'invoice_number' => $data['invoice_number'] ?? $extraction->invoice_number,
            'invoice_date' => $data['invoice_date'] ?? $extraction->invoice_date,
            'subtotal' => isset($data['subtotal']) ? (int) $data['subtotal'] : $extraction->subtotal,
            'vat_amount' => isset($data['vat_amount']) ? (int) $data['vat_amount'] : $extraction->vat_amount,
            'total_amount' => isset($data['total_amount']) ? (int) $data['total_amount'] : $extraction->total_amount,
            'matched_customer_id' => $data['matched_customer_id'] ?? $extraction->matched_customer_id,
            'raw_extracted_data' => $raw,
            'status' => 'VERIFIED',
            'reviewer_id' => $reviewerId ?? $extraction->reviewer_id,
        ]);

        return $extraction->fresh();
    }

    /**
     * Reject an extracted paper invoice with reason.
     */
    public function reject(AiInvoiceExtraction $extraction, ?int $reviewerId = null, ?string $reason = null): void
    {
        $extraction->update([
            'status' => 'REJECTED',
            'reviewer_id' => $reviewerId,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Convert verified extraction into a Draft Buy Invoice (InvoiceType::BUY).
     *
     * In accordance with Decision D-114 and D-085:
     * - AI extractions are never posted directly.
     * - Conversion outputs a standard Draft Buy Invoice (status: DRAFT / PENDING).
     * - Provenance is FinancialProvenance::NATIVE.
     */
    public function convertToBuyInvoice(AiInvoiceExtraction $extraction, User $user, array $overrides = []): Invoice
    {
        if ($extraction->status === 'CONVERTED' && $extraction->converted_invoice_id) {
            throw new Exception('این فاکتور قبلاً به فاکتور خرید تبدیل شده است.');
        }

        return DB::transaction(function () use ($extraction, $user, $overrides) {
            $companyId = $extraction->company_id;

            // 1. Resolve Supplier (Customer)
            $customerId = $overrides['customer_id'] ?? $extraction->matched_customer_id;
            $customer = null;

            if ($customerId) {
                $customer = Customer::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->find($customerId);
            }

            if (! $customer) {
                $vendorName = $extraction->vendor_name ?: ('تأمین‌کننده فاکتور #'.($extraction->invoice_number ?: $extraction->id));
                $customer = Customer::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('name', $vendorName)
                    ->first();

                if (! $customer) {
                    $customer = Customer::create([
                        'company_id' => $companyId,
                        'name' => $vendorName,
                        'mobile' => $extraction->vendor_phone,
                        'personal_code' => $extraction->vendor_national_code,
                        'balance' => 0,
                    ]);
                }
            }

            // 2. Resolve Line Items & Products
            $itemsData = $extraction->raw_extracted_data['items'] ?? [];
            if (empty($itemsData)) {
                $itemsData = [
                    [
                        'name' => 'اقلام تجمیعی فاکتور خرید کاغذی',
                        'quantity' => 1,
                        'unit_price' => $extraction->subtotal,
                        'total_price' => $extraction->subtotal,
                        'vat_amount' => $extraction->vat_amount,
                        'discount' => 0,
                    ],
                ];
            }

            $mappedItems = [];
            foreach ($itemsData as $idx => $item) {
                $itemName = trim($item['name'] ?? ('کالای سطر '.($idx + 1)));

                $product = Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('name', $itemName)
                    ->first();

                if (! $product) {
                    $maxId = (int) (Product::withoutGlobalScopes()->where('company_id', $companyId)->max('id') ?? 0);
                    $code = (string) ($maxId + 1001);
                    $product = Product::create([
                        'company_id' => $companyId,
                        'name' => $itemName,
                        'code' => $code,
                        'selling_price' => $item['unit_price'] ?? 0,
                        'average_cost' => $item['unit_price'] ?? 0,
                        'quantity' => 0,
                        'vat' => 0,
                    ]);
                }

                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $vatAmount = (float) ($item['vat_amount'] ?? 0);

                $mappedItems[] = [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => $quantity > 0 ? $quantity : 1,
                    'unit' => $unitPrice,
                    'unit_discount' => $discount,
                    'vat' => $vatAmount,
                    'vat_is_value' => true,
                ];
            }

            // 3. Resolve Next Invoice Number for BUY invoices in this company
            $nextNumber = (int) (Invoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('invoice_type', InvoiceType::BUY)
                ->max('number') ?? 0) + 1;

            $invoiceDate = $extraction->invoice_date ?: now()->toDateString();
            $title = 'فاکتور خرید استخراج هوشمند '.($extraction->invoice_number ?: ('#'.$extraction->id));

            $invoiceData = [
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'invoice_type' => InvoiceType::BUY,
                'number' => $nextNumber,
                'date' => $invoiceDate,
                'title' => $title,
                'desc' => 'ثبت خودکار از فاکتور کاغذی/فیش اسکن شده با شناسه استخراج #'.$extraction->id,
                'subtraction' => 0,
            ];

            // 4. Create Draft Buy Invoice via standard InvoiceService
            $createResult = InvoiceService::createInvoice($user, $invoiceData, $mappedItems, approved: false);
            /** @var Invoice $createdInvoice */
            $createdInvoice = $createResult['invoice'];

            // 5. Update Extraction record
            $extraction->update([
                'status' => 'CONVERTED',
                'matched_customer_id' => $customer->id,
                'converted_invoice_id' => $createdInvoice->id,
                'converted_at' => now(),
                'reviewer_id' => $user->id,
            ]);

            return $createdInvoice;
        });
    }
}
