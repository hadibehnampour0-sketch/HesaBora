<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use App\Services\SeasonalTaxReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeasonalTaxController extends Controller
{
    public function __construct(private readonly SeasonalTaxReportService $service) {}

    public function index(Request $request): View
    {
        [$currentJalaliYear] = gregorian_to_jalali(now()->year, now()->month, now()->day);

        $jalaliYear = (int) $request->input('jalali_year', $currentJalaliYear);
        $quarter = (int) $request->input('quarter', 1);
        $quarter = in_array($quarter, [1, 2, 3, 4], true) ? $quarter : 1;

        $report = $this->service->report(getActiveCompany(), $jalaliYear, $quarter);

        return view('tax.seasonal.index', compact('report', 'jalaliYear', 'quarter'));
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
