<?php

namespace App\Services;

use App\Models\TaxFilingRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * T-3 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md). A per-quarter
 * filing calendar built entirely from data that already exists (quarter boundaries from
 * `SeasonalTaxReportService`, readiness from `TaxFilingPreparationService`) plus one small new
 * table recording only "this period was marked filed". Due-date offset is a class constant, NOT
 * asserted as verified law -- flagged in the UI, matching this app's existing seasonal-report
 * disclaimer precedent.
 */
class TaxFilingCalendarService
{
    /**
     * Days after a quarter's end date the seasonal VAT return is commonly understood to be due.
     * This is a reasonable default, not a confirmed legal deadline -- see the UI disclaimer.
     */
    public const DEFAULT_DUE_DAYS_AFTER_QUARTER_END = 15;

    public const STATUS_FILED = 'filed';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_PREPARED = 'prepared';

    public const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly SeasonalTaxReportService $seasonalTaxReportService,
        private readonly TaxFilingPreparationService $preparationService,
    ) {}

    /**
     * @return array<int, array{quarter: int, quarter_label: string, start_date: string, end_date: string, due_date: string, status: string, filed_at: ?Carbon}>
     */
    public function calendar(int $companyId, int $jalaliYear): array
    {
        $filedRecords = TaxFilingRecord::where('jalali_year', $jalaliYear)->get()->keyBy('quarter');
        $today = Carbon::today();

        $rows = [];
        foreach ([1, 2, 3, 4] as $quarter) {
            [$start, $end] = $this->seasonalTaxReportService->quarterDateRange($jalaliYear, $quarter);
            $dueDate = Carbon::parse($end)->addDays(self::DEFAULT_DUE_DAYS_AFTER_QUARTER_END);
            $filed = $filedRecords->get($quarter);

            $status = match (true) {
                $filed !== null => self::STATUS_FILED,
                $today->gt($dueDate) => self::STATUS_OVERDUE,
                $this->preparationService->prepare($companyId, $jalaliYear, $quarter)['is_ready'] => self::STATUS_PREPARED,
                default => self::STATUS_PENDING,
            };

            $rows[] = [
                'quarter' => $quarter,
                'quarter_label' => $this->seasonalTaxReportService->quarterLabel($quarter),
                'start_date' => $start,
                'end_date' => $end,
                'due_date' => $dueDate->toDateString(),
                'status' => $status,
                'filed_at' => $filed?->filed_at,
            ];
        }

        return $rows;
    }

    public function markFiled(int $jalaliYear, int $quarter, User $user, ?string $notes = null): TaxFilingRecord
    {
        return TaxFilingRecord::updateOrCreate(
            ['jalali_year' => $jalaliYear, 'quarter' => $quarter],
            ['filed_at' => now(), 'filed_by' => $user->id, 'notes' => $notes],
        );
    }

    /** Overdue-and-unfiled quarters among the current and previous one -- used by the Tax Alert engine. */
    public function overdueUnfiledQuarters(int $companyId): array
    {
        [$currentYear, $currentMonth] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $currentQuarter = (int) ceil($currentMonth / 3);

        $candidates = $currentQuarter > 1
            ? [[$currentYear, $currentQuarter - 1], [$currentYear, $currentQuarter]]
            : [[$currentYear - 1, 4], [$currentYear, $currentQuarter]];

        $overdue = [];
        foreach ($candidates as [$year, $quarter]) {
            $row = collect($this->calendar($companyId, $year))->firstWhere('quarter', $quarter);
            if ($row && $row['status'] === self::STATUS_OVERDUE) {
                $overdue[] = $row + ['jalali_year' => $year];
            }
        }

        return $overdue;
    }
}
