<?php

namespace App\Http\Controllers;

use App\Services\PlainReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Phase U: plain-language business summary ("How is my business doing?"). Read-only. */
class PlainReportController extends Controller
{
    public function __construct(private readonly PlainReportService $service) {}

    public function index(Request $request): View
    {
        $period = (string) $request->query('period', 'this_month');

        return view('reports.plain', ['report' => $this->service->build($request->user(), $period)]);
    }
}
