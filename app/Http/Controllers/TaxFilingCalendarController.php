<?php

namespace App\Http\Controllers;

use App\Services\TaxFilingCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaxFilingCalendarController extends Controller
{
    public function __construct(private readonly TaxFilingCalendarService $service) {}

    public function index(Request $request): View
    {
        [$currentJalaliYear] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $jalaliYear = (int) $request->input('jalali_year', $currentJalaliYear);

        $rows = $this->service->calendar(getActiveCompany(), $jalaliYear);

        return view('tax-center.calendar', compact('rows', 'jalaliYear'));
    }

    public function markFiled(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'jalali_year' => ['required', 'integer', 'min:1300', 'max:1500'],
            'quarter' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->markFiled((int) $validated['jalali_year'], (int) $validated['quarter'], $request->user(), $validated['notes'] ?? null);

        return redirect()->route('tax-center.calendar', ['jalali_year' => $validated['jalali_year']])->with('success', __('Marked as filed.'));
    }
}
