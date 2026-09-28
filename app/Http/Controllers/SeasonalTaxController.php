<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use App\Services\SeasonalTaxReportService;
use App\Services\TaxReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeasonalTaxController extends Controller
{
    public function __construct(
        private readonly SeasonalTaxReportService $service,
        private readonly TaxReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request): View
    {
        [$currentJalaliYear] = gregorian_to_jalali(now()->year, now()->month, now()->day);

        $jalaliYear = (int) $request->input('jalali_year', $currentJalaliYear);
        $quarter = (int) $request->input('quarter', 1);
        $quarter = in_array($quarter, [1, 2, 3, 4], true) ? $quarter : 1;

        $companyId = getActiveCompany();
        $report = $this->service->report($companyId, $jalaliYear, $quarter);
        $reconciliation = $this->reconciliationService->reconcileSeasonalVat($companyId, $report, $report['start_date'], $report['end_date']);

        return view('tax.seasonal.index', compact('report', 'jalaliYear', 'quarter', 'reconciliation'));
    }

    public function exportCsv(Request $request, ReportExportService $reportExportService): StreamedResponse
    {
        return $reportExportService->downloadResponse('seasonal_tax_csv', $request->all());
    }

    public function exportPdf(Request $request, ReportExportService $reportExportService): StreamedResponse
    {
        return $reportExportService->downloadResponse('seasonal_tax_pdf', $request->all());
    }
}
