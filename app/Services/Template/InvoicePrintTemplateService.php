<?php

namespace App\Services\Template;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePrintTemplate;
use Illuminate\Database\Eloquent\Collection;

class InvoicePrintTemplateService
{
    /**
     * Get or initialize all print templates for the company.
     */
    public function getTemplates(int $companyId): Collection
    {
        $templates = InvoicePrintTemplate::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            $defaultA4 = $this->createDefaultA4Template($companyId);
            $defaultThermal = $this->createDefaultThermalTemplate($companyId);
            return new Collection([$defaultA4, $defaultThermal]);
        }

        return $templates;
    }

    /**
     * Get the active default template for a given company and paper size.
     */
    public function getDefaultTemplate(int $companyId, string $paperSize = 'A4_PORTRAIT'): InvoicePrintTemplate
    {
        $template = InvoicePrintTemplate::query()
            ->where('company_id', $companyId)
            ->where('paper_size', $paperSize)
            ->where('is_default', true)
            ->first();

        if ($template) {
            return $template;
        }

        $fallback = InvoicePrintTemplate::query()
            ->where('company_id', $companyId)
            ->where('paper_size', $paperSize)
            ->first();

        if ($fallback) {
            return $fallback;
        }

        if (str_starts_with($paperSize, 'THERMAL')) {
            return $this->createDefaultThermalTemplate($companyId);
        }

        return $this->createDefaultA4Template($companyId);
    }

    /**
     * Set a template as the default for its paper size.
     */
    public function setDefault(InvoicePrintTemplate $template): void
    {
        InvoicePrintTemplate::query()
            ->where('company_id', $template->company_id)
            ->where('paper_size', $template->paper_size)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);
    }

    /**
     * Create or update a template.
     */
    public function saveTemplate(int $companyId, array $data, ?InvoicePrintTemplate $template = null): InvoicePrintTemplate
    {
        $attributes = [
            'name' => $data['name'],
            'paper_size' => $data['paper_size'] ?? 'A4_PORTRAIT',
            'is_default' => (bool) ($data['is_default'] ?? false),
            'header_config' => $data['header_config'] ?? InvoicePrintTemplate::defaultHeaderConfig(),
            'columns_config' => $data['columns_config'] ?? InvoicePrintTemplate::defaultColumnsConfig(),
            'footer_config' => $data['footer_config'] ?? InvoicePrintTemplate::defaultFooterConfig(),
            'watermark_text' => $data['watermark_text'] ?? null,
            'custom_css' => $data['custom_css'] ?? null,
        ];

        if ($template) {
            $template->update($attributes);
            if ($template->is_default) {
                $this->setDefault($template);
            }
            return $template->fresh();
        }

        $attributes['company_id'] = $companyId;
        $newTemplate = InvoicePrintTemplate::create($attributes);

        if ($newTemplate->is_default) {
            $this->setDefault($newTemplate);
        }

        return $newTemplate;
    }

    /**
     * Duplicate a template.
     */
    public function duplicateTemplate(InvoicePrintTemplate $template, string $newName): InvoicePrintTemplate
    {
        return InvoicePrintTemplate::create([
            'company_id' => $template->company_id,
            'name' => $newName,
            'paper_size' => $template->paper_size,
            'is_default' => false,
            'header_config' => $template->header_config,
            'columns_config' => $template->columns_config,
            'footer_config' => $template->footer_config,
            'watermark_text' => $template->watermark_text,
            'custom_css' => $template->custom_css,
        ]);
    }

    /**
     * Render invoice HTML with given or default template.
     */
    public function renderInvoiceHtml(Invoice $invoice, ?InvoicePrintTemplate $template = null): string
    {
        $template = $template ?? $this->getDefaultTemplate($invoice->company_id);
        $company = Company::find($invoice->company_id);

        return view('templates.rendered_invoice', compact('invoice', 'template', 'company'))->render();
    }

    protected function createDefaultA4Template(int $companyId): InvoicePrintTemplate
    {
        return InvoicePrintTemplate::create([
            'company_id' => $companyId,
            'name' => 'قالب پیش‌فرض رسمی مالیاتی (A4)',
            'paper_size' => 'A4_PORTRAIT',
            'is_default' => true,
            'header_config' => InvoicePrintTemplate::defaultHeaderConfig(),
            'columns_config' => InvoicePrintTemplate::defaultColumnsConfig(),
            'footer_config' => InvoicePrintTemplate::defaultFooterConfig(),
            'watermark_text' => null,
        ]);
    }

    protected function createDefaultThermalTemplate(int $companyId): InvoicePrintTemplate
    {
        $columns = [
            ['key' => 'row_num', 'label' => '#', 'visible' => true, 'width_percent' => 10],
            ['key' => 'description', 'label' => 'کالا / خدمات', 'visible' => true, 'width_percent' => 45],
            ['key' => 'quantity', 'label' => 'تعداد', 'visible' => true, 'width_percent' => 15],
            ['key' => 'total_amount', 'label' => 'مبلغ', 'visible' => true, 'width_percent' => 30],
        ];

        $header = [
            'show_logo' => false,
            'show_company_name' => true,
            'show_economic_code' => false,
            'show_national_code' => false,
            'show_address' => true,
            'show_phone' => true,
            'show_qr' => true,
            'custom_title' => 'رسید خرید فروشگاهی',
            'font_size_pt' => 9,
        ];

        $footer = [
            'show_words_amount' => false,
            'show_bank_sheba' => false,
            'show_signatures' => false,
            'legal_notice' => 'از خرید شما سپاسگزاریم.',
        ];

        return InvoicePrintTemplate::create([
            'company_id' => $companyId,
            'name' => 'فیش پرینتر حرارتی ۸۰ میلی‌متری',
            'paper_size' => 'THERMAL_80MM',
            'is_default' => false,
            'header_config' => $header,
            'columns_config' => $columns,
            'footer_config' => $footer,
            'watermark_text' => null,
        ]);
    }
}
