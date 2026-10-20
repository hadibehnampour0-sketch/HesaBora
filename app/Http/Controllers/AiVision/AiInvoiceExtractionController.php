<?php

namespace App\Http\Controllers\AiVision;

use App\Http\Controllers\Controller;
use App\Models\AiInvoiceExtraction;
use App\Models\Customer;
use App\Services\AiVision\AiInvoiceExtractionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiInvoiceExtractionController extends Controller
{
    public function __construct(
        protected AiInvoiceExtractionService $extractionService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();
        $status = $request->query('status');

        $query = AiInvoiceExtraction::query()
            ->where('company_id', $companyId)
            ->with(['matchedCustomer', 'convertedInvoice', 'user'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $extractions = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => AiInvoiceExtraction::where('company_id', $companyId)->count(),
            'PENDING_REVIEW' => AiInvoiceExtraction::where('company_id', $companyId)->where('status', 'PENDING_REVIEW')->count(),
            'VERIFIED' => AiInvoiceExtraction::where('company_id', $companyId)->where('status', 'VERIFIED')->count(),
            'CONVERTED' => AiInvoiceExtraction::where('company_id', $companyId)->where('status', 'CONVERTED')->count(),
            'REJECTED' => AiInvoiceExtraction::where('company_id', $companyId)->where('status', 'REJECTED')->count(),
        ];

        return view('ai_vision.index', compact('extractions', 'counts', 'status'));
    }

    public function create()
    {
        return view('ai_vision.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ]);

        $companyId = getActiveCompany();

        try {
            $extraction = $this->extractionService->ingest(
                $request->file('file'),
                $companyId,
                auth()->id()
            );

            return redirect()
                ->route('ai-invoices.review', $extraction->id)
                ->with('success', __('فایل فاکتور با موفقیت دریافت و اطلاعات توسط هوش مصنوعی استخراج شد. لطفاً اقلام را بررسی نمایید.'));
        } catch (Exception $e) {
            return back()->withInput()->with('error', __('خطا در پردازش هوشمند تصویر: ').$e->getMessage());
        }
    }

    public function review(AiInvoiceExtraction $extraction)
    {
        $companyId = getActiveCompany();
        abort_unless($extraction->company_id === $companyId, 403);

        $extraction->load(['matchedCustomer', 'convertedInvoice']);

        $customers = Customer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $fileUrl = Storage::disk('public')->url($extraction->file_path);

        return view('ai_vision.review', compact('extraction', 'customers', 'fileUrl'));
    }

    public function update(AiInvoiceExtraction $extraction, Request $request)
    {
        $companyId = getActiveCompany();
        abort_unless($extraction->company_id === $companyId, 403);

        $validated = $request->validate([
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_national_code' => ['nullable', 'string', 'max:32'],
            'vendor_phone' => ['nullable', 'string', 'max:32'],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'invoice_date' => ['nullable', 'string', 'max:32'],
            'subtotal' => ['nullable', 'numeric'],
            'vat_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'matched_customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_amount' => ['nullable', 'numeric'],
            'items.*.discount' => ['nullable', 'numeric'],
        ]);

        $this->extractionService->updateDraftExtraction($extraction, $validated, auth()->id());

        return redirect()
            ->route('ai-invoices.review', $extraction->id)
            ->with('success', __('اطلاعات استخراج شده با موفقیت به‌روزرسانی شد.'));
    }

    public function convert(AiInvoiceExtraction $extraction, Request $request)
    {
        $companyId = getActiveCompany();
        abort_unless($extraction->company_id === $companyId, 403);

        try {
            $invoice = $this->extractionService->convertToBuyInvoice($extraction, auth()->user(), [
                'customer_id' => $request->input('customer_id'),
            ]);

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->with('success', __('فاکتور خرید پیش‌نویس با موفقیت صادر و اقلام در سامانه درج شد. می‌توانید فاکتور را مشاهده و تأیید نمایید.'));
        } catch (Exception $e) {
            return back()->with('error', __('خطا در تبدیل به فاکتور خرید: ').$e->getMessage());
        }
    }

    public function reject(AiInvoiceExtraction $extraction, Request $request)
    {
        $companyId = getActiveCompany();
        abort_unless($extraction->company_id === $companyId, 403);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->extractionService->reject($extraction, auth()->id(), $request->input('reason'));

        return redirect()
            ->route('ai-invoices.index')
            ->with('info', __('فاکتور استخراج شده رد شد و به بایگانی اسناد نامعتبر منتقل گردید.'));
    }

    public function destroy(AiInvoiceExtraction $extraction)
    {
        $companyId = getActiveCompany();
        abort_unless($extraction->company_id === $companyId, 403);

        if ($extraction->status === 'CONVERTED') {
            return back()->with('error', __('فاکتورهای تبدیل شده به اسناد حسابداری قابل حذف مستقیم نیستند.'));
        }

        if (Storage::disk('public')->exists($extraction->file_path)) {
            Storage::disk('public')->delete($extraction->file_path);
        }

        $extraction->delete();

        return redirect()
            ->route('ai-invoices.index')
            ->with('success', __('سند استخراج با موفقیت حذف گردید.'));
    }
}
