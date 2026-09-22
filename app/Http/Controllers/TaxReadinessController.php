<?php

namespace App\Http\Controllers;

use App\Models\TaxReadinessLog;
use App\Services\TaxReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxReadinessController extends Controller
{
    public function __construct(private readonly TaxReadinessService $service) {}

    public function index(): View
    {
        $latest = TaxReadinessLog::latest('calculated_at')->first();
        $history = TaxReadinessLog::orderByDesc('calculated_at')->limit(12)->get()->reverse()->values();

        return view('tax-readiness.index', compact('latest', 'history'));
    }

    public function recalculate(): RedirectResponse
    {
        $this->service->calculateScore(getActiveCompany());

        return redirect()->route('tax-readiness.index')->with('success', __('Tax readiness score recalculated successfully.'));
    }
}
