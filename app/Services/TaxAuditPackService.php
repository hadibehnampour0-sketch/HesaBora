<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\TaxEvidence;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * T-1 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md): one export a
 * non-expert can hand to the tax office or an auditor for a period, instead of gathering it from
 * five screens. Bundles the seasonal VAT report + its ledger reconciliation, the period's
 * invoices, a Moadian submission-status summary, and every tax-evidence file on file for the
 * company, into one ZIP. Read-only: nothing here mutates any record.
 */
class TaxAuditPackService
{
    public function __construct(
        private readonly SeasonalTaxReportService $seasonalTaxReportService,
        private readonly TaxReconciliationService $reconciliationService,
    ) {}

    /** @return string absolute path to a temporary ZIP file the caller must stream and delete */
    public function build(int $companyId, int $jalaliYear, int $quarter): string
    {
        $report = $this->seasonalTaxReportService->report($companyId, $jalaliYear, $quarter);
        $reconciliation = $this->reconciliationService->reconcileSeasonalVat($companyId, $report, $report['start_date'], $report['end_date']);

        $zipPath = tempnam(sys_get_temp_dir(), 'dakhl_audit_pack_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('manifest.txt', $this->manifest($report, $reconciliation));
        $zip->addFromString('seasonal-vat-report.csv', $this->seasonalVatCsv($report, $reconciliation));
        $zip->addFromString('invoices.csv', $this->invoicesCsv($companyId, $report['start_date'], $report['end_date']));
        $zip->addFromString('moadian-status-summary.csv', $this->moadianStatusSummaryCsv($companyId, $report['start_date'], $report['end_date']));

        $this->addEvidenceFiles($zip, $companyId);

        $zip->close();

        return $zipPath;
    }

    private function manifest(array $report, array $reconciliation): string
    {
        $lines = [
            __('Dakhl tax audit pack'),
            __('Generated at').': '.now()->toDateTimeString(),
            __('Period').': '.$report['quarter_label'].' '.$report['jalali_year'].' ('.$report['start_date'].' - '.$report['end_date'].')',
            '',
            __('Contents').':',
            '- seasonal-vat-report.csv',
            '- invoices.csv',
            '- moadian-status-summary.csv',
            '- evidence/ ('.__('supporting documents on file').')',
            '',
            __('Ledger reconciliation').': '.($reconciliation['is_balanced'] ? __('Matches the ledger') : __('Does not match the ledger -- see seasonal-vat-report.csv')),
            '',
            __('This report is a reasonable, invoice-level approximation of the Article 169 seasonal VAT return. It has not been verified against the exact byte-level TTMS filing format.'),
        ];

        return implode("\n", $lines);
    }

    private function seasonalVatCsv(array $report, array $reconciliation): string
    {
        $rows = [
            [__('Taxable Sales'), $report['taxable_sales']],
            [__('Exempt Sales'), $report['exempt_sales']],
            [__('VAT Collected'), $report['vat_collected']],
            [__('Taxable Purchases'), $report['taxable_purchases']],
            [__('Creditable Input VAT'), $report['creditable_input_vat']],
            [__('Net VAT Payable'), $report['net_vat_payable']],
            ['', ''],
            [__('VAT Collected').' ('.__('From ledger').')', $reconciliation['ledger_vat_collected']],
            [__('Creditable Input VAT').' ('.__('From ledger').')', $reconciliation['ledger_input_vat']],
            [__('Ledger reconciliation'), $reconciliation['is_balanced'] ? __('Matches the ledger') : __('Does not match the ledger')],
        ];

        return $this->toCsv([__('Metric'), __('Amount')], $rows);
    }

    private function invoicesCsv(int $companyId, string $startDate, string $endDate): string
    {
        $rows = [];

        Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['customer', 'latestMoadianHistory'])
            ->orderBy('date')
            ->chunk(200, function ($invoices) use (&$rows) {
                foreach ($invoices as $invoice) {
                    $rows[] = [
                        $invoice->number,
                        $invoice->invoice_type instanceof InvoiceType ? $invoice->invoice_type->label() : $invoice->invoice_type,
                        $invoice->customer?->name,
                        formatDate($invoice->date),
                        (float) $invoice->amount,
                        (float) $invoice->vat,
                        $invoice->latestMoadianHistory?->data['status'] ?? __('Not sent'),
                    ];
                }
            });

        return $this->toCsv([__('Invoice Number'), __('Type'), __('Customer Name'), __('Date'), __('Amount'), __('Tax'), __('Moadian Status')], $rows);
    }

    private function moadianStatusSummaryCsv(int $companyId, string $startDate, string $endDate): string
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$startDate, $endDate])
            ->with('latestMoadianHistory')
            ->get();

        $counts = $invoices->groupBy(fn ($invoice) => $invoice->latestMoadianHistory?->data['status'] ?? __('Not sent'))
            ->map->count();

        $rows = $counts->map(fn ($count, $status) => [$status, $count])->values()->all();

        return $this->toCsv([__('Moadian Status'), __('Invoice Count')], $rows);
    }

    private function addEvidenceFiles(ZipArchive $zip, int $companyId): void
    {
        $ancillaryCostIds = AncillaryCost::withoutGlobalScopes()->where('company_id', $companyId)->pluck('id');

        TaxEvidence::where('company_id', $companyId)
            ->where('evidenceable_type', AncillaryCost::class)
            ->whereIn('evidenceable_id', $ancillaryCostIds)
            ->chunk(100, function ($evidences) use ($zip) {
                foreach ($evidences as $evidence) {
                    if (! Storage::disk('local')->exists($evidence->file_path)) {
                        continue;
                    }

                    $zip->addFile(Storage::disk('local')->path($evidence->file_path), 'evidence/'.$evidence->id.'_'.$evidence->file_name);
                }
            });
    }

    private function toCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
