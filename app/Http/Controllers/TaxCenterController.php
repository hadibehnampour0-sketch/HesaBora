<?php

namespace App\Http\Controllers;

use App\Models\TaxReadinessLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One landing page that gathers the tax-authority work areas (readiness score, seasonal VAT
 * report, Moadian submissions, alerts, evidence, tax settings). Navigation only: every card
 * links to an existing screen and is shown only when the user may open that screen.
 */
class TaxCenterController extends Controller
{
    public function index(Request $request): View
    {
        $latest = $request->user()->can('tax-readiness.index')
            ? TaxReadinessLog::latest('calculated_at')->first()
            : null;

        [$currentJalaliYear, $currentJalaliMonth] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $currentQuarter = (int) ceil($currentJalaliMonth / 3);

        return view('tax-center.index', compact('latest', 'currentJalaliYear', 'currentQuarter'));
    }
}
