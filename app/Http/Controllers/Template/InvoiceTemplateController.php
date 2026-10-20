<?php

namespace App\Http\Controllers\Template;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePrintTemplate;
use App\Services\Template\InvoicePrintTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceTemplateController extends Controller
{
    public function __construct(
        protected InvoicePrintTemplateService $templateService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();
        $templates = $this->templateService->getTemplates($companyId);

        return view('templates.index', compact('templates'));
    }

    public function create(Request $request)
    {
        $template = new InvoicePrintTemplate([
            'paper_size' => 'A4_PORTRAIT',
            'header_config' => InvoicePrintTemplate::defaultHeaderConfig(),
            'columns_config' => InvoicePrintTemplate::defaultColumnsConfig(),
            'footer_config' => InvoicePrintTemplate::defaultFooterConfig(),
        ]);

        return view('templates.designer', compact('template'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = getActiveCompany();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'paper_size' => ['required', 'string', 'in:A4_PORTRAIT,A4_LANDSCAPE,A5_PORTRAIT,A5_LANDSCAPE,THERMAL_80MM,THERMAL_58MM'],
            'is_default' => ['nullable'],
            'header_config' => ['nullable', 'array'],
            'columns_config' => ['nullable', 'array'],
            'footer_config' => ['nullable', 'array'],
            'watermark_text' => ['nullable', 'string', 'max:50'],
            'custom_css' => ['nullable', 'string'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        $this->templateService->saveTemplate($companyId, $validated);

        return redirect()->route('templates.index')->with('success', __('قالب چاپ فاکتور با موفقیت ذخیره شد.'));
    }

    public function edit(InvoicePrintTemplate $template)
    {
        return view('templates.designer', compact('template'));
    }

    public function update(InvoicePrintTemplate $template, Request $request): RedirectResponse
    {
        $companyId = getActiveCompany();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'paper_size' => ['required', 'string', 'in:A4_PORTRAIT,A4_LANDSCAPE,A5_PORTRAIT,A5_LANDSCAPE,THERMAL_80MM,THERMAL_58MM'],
            'is_default' => ['nullable'],
            'header_config' => ['nullable', 'array'],
            'columns_config' => ['nullable', 'array'],
            'footer_config' => ['nullable', 'array'],
            'watermark_text' => ['nullable', 'string', 'max:50'],
            'custom_css' => ['nullable', 'string'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        $this->templateService->saveTemplate($companyId, $validated, $template);

        return redirect()->route('templates.index')->with('success', __('تغییرات قالب با موفقیت ذخیره شد.'));
    }

    public function destroy(InvoicePrintTemplate $template): RedirectResponse
    {
        if ($template->is_default) {
            return back()->with('error', __('قالب پیش‌فرض سیستم قابل حذف نیست. ابتدا قالب دیگری را به عنوان پیش‌فرض انتخاب کنید.'));
        }

        $template->delete();

        return redirect()->route('templates.index')->with('success', __('قالب مورد نظر حذف شد.'));
    }

    public function duplicate(InvoicePrintTemplate $template, Request $request): RedirectResponse
    {
        $newName = $template->name . ' (' . __('کپی') . ')';
        $this->templateService->duplicateTemplate($template, $newName);

        return redirect()->route('templates.index')->with('success', __('از روی قالب کپی جدید ایجاد شد.'));
    }

    public function setDefault(InvoicePrintTemplate $template): RedirectResponse
    {
        $this->templateService->setDefault($template);

        return redirect()->route('templates.index')->with('success', __('قالب به عنوان پیش‌فرض این سایز کاغذ انتخاب شد.'));
    }

    public function preview(InvoicePrintTemplate $template, Request $request): Response
    {
        $invoiceId = $request->query('invoice_id');
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        if (! $invoice) {
            // Build mock invoice for preview
            $invoice = new Invoice([
                'number' => '1405-10023',
                'date' => now()->toDateString(),
                'company_id' => $template->company_id,
                'amount' => 12500000,
                'vat' => 1125000,
                'subtraction' => 500000,
            ]);
            $invoice->setRelation('customer', new \App\Models\Customer([
                'name' => 'شرکت نمونه بازرگانی امید (پیش‌نمایش خریدار)',
                'personal_code' => '10103456789',
                'ecnmcs_code' => '411456789123',
                'phone' => '021-88776655',
                'address' => 'تهران، خیابان سهروردی شمالی، پلاک ۱۲۰',
                'postal_code' => '1554678912',
            ]));
        }

        $html = $this->templateService->renderInvoiceHtml($invoice, $template);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
