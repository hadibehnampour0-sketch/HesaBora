<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P5.1 (Cash Flow Dashboard). Deliberately scoped as a net cash-movement
 * report (inflow/outflow/net per period across every bank/cash subject),
 * NOT a full 3-way IFRS statement (Operating/Investing/Financing) -- that
 * would require classifying every Subject in the chart of accounts into
 * one of those 3 categories, a much larger modeling exercise this app's
 * current chart of accounts has no support for. Reuses
 * PaymentService::settlementSubjectIds() (the exact same "which subjects
 * are bank/cash accounts" concept Payment/Settlement already rely on)
 * rather than re-deriving it.
 */
class CashFlowReportService
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * @return array{periods: Collection, total_inflow: float, total_outflow: float, net: float}
     */
    public function report(string $fromDate, string $toDate, string $groupBy = 'month'): array
    {
        $cashSubjectIds = $this->paymentService->settlementSubjectIds();

        if (empty($cashSubjectIds)) {
            return ['periods' => collect(), 'total_inflow' => 0.0, 'total_outflow' => 0.0, 'net' => 0.0];
        }

        // strftime() exists only in SQLite (tests); MySQL needs DATE_FORMAT(). Week = Monday-first week of year in both.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $periodExpression = match ($groupBy) {
            'day' => $sqlite ? "strftime('%Y-%m-%d', documents.date)" : "DATE_FORMAT(documents.date, '%Y-%m-%d')",
            'week' => $sqlite ? "strftime('%Y-%W', documents.date)" : "DATE_FORMAT(documents.date, '%Y-%u')",
            default => $sqlite ? "strftime('%Y-%m', documents.date)" : "DATE_FORMAT(documents.date, '%Y-%m')",
        };

        // Transaction has no FiscalYearScope of its own, and a plain
        // query-builder ->join() to `documents` does NOT apply Document's
        // own FiscalYearScope either (guardrail #36's exact lesson) --
        // filter company_id explicitly rather than relying on either.
        //
        // Sign convention (guardrail #36, part 4): value < 0 is a debit
        // (Transaction::getDebitAttribute()). For an asset account like a
        // cash/bank subject, a debit INCREASES the balance -- a real
        // inflow -- while a credit (value > 0) DECREASES it (an outflow).
        // This is the opposite of the naive value>0=inflow assumption.
        $rows = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.company_id', getActiveCompany())
            ->whereIn('transactions.subject_id', $cashSubjectIds)
            ->whereBetween('documents.date', [$fromDate, $toDate])
            ->selectRaw("{$periodExpression} as period, SUM(CASE WHEN transactions.value < 0 THEN -transactions.value ELSE 0 END) as inflow, SUM(CASE WHEN transactions.value > 0 THEN transactions.value ELSE 0 END) as outflow")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $periods = $rows->map(fn ($row) => [
            'period' => $row->period,
            'inflow' => round((float) $row->inflow, 2),
            'outflow' => round((float) $row->outflow, 2),
            'net' => round((float) $row->inflow - (float) $row->outflow, 2),
        ]);

        return [
            'periods' => $periods,
            'total_inflow' => round((float) $periods->sum('inflow'), 2),
            'total_outflow' => round((float) $periods->sum('outflow'), 2),
            'net' => round((float) $periods->sum('net'), 2),
        ];
    }

    /**
     * Drill-down (P5.5): every cash/bank transaction line within a single
     * period, down to its accounting document.
     */
    public function transactionsForPeriod(string $fromDate, string $toDate): Collection
    {
        $cashSubjectIds = $this->paymentService->settlementSubjectIds();

        return Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.company_id', getActiveCompany())
            ->whereIn('transactions.subject_id', $cashSubjectIds)
            ->whereBetween('documents.date', [$fromDate, $toDate])
            ->select('transactions.*', 'documents.date as document_date', 'documents.title as document_title')
            ->orderBy('documents.date')
            ->get();
    }
}
