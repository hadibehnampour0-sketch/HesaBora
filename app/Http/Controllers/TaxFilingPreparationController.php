<?php

namespace App\Http\Controllers;

use App\Services\TaxFilingPreparationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxFilingPreparationController extends Controller
{
    public function __construct(private readonly TaxFilingPreparationService $service) {}

    public function index(Request $request): View
    {
        [$currentJalaliYear, $currentJalaliMonth] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $currentQuarter = (int) ceil($currentJalaliMonth / 3);

        $jalaliYear = (int) $request->input('jalali_year', $currentJalaliYear);
        $quarter = (int) $request->input('quarter', $currentQuarter);
        $quarter = in_array($quarter, [1, 2, 3, 4], true) ? $quarter : $currentQuarter;

        $preparation = $this->service->prepare(getActiveCompany(), $jalaliYear, $quarter);

        return view('tax-center.prepare', compact('preparation', 'jalaliYear', 'quarter'));
    }
}
