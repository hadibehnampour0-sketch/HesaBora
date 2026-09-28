<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Tax module completeness (T-5, docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md):
 * "error-free" requires every reported figure to be checkable against an independent source, not just
 * trusted. `SeasonalTaxReportService` computes VAT straight from `invoices.vat`/`amount`
 * (`InvoiceTransactionBuilder`'s own input). This service re-derives the SAME period's VAT from the
 * general ledger (the `sell_vat`/`buy_vat` subjects `InvoiceTransactionBuilder::buildVatTransaction()`
 * actually posts to) and flags any difference -- which can only come from a manual journal entry
 * touching the VAT subject directly, or a posting-layer bug, since the two paths are otherwise
 * mathematically identical for the same invoices.
 *
 * Sign convention (guardrail #36): a Transaction value > 0 is a credit, < 0 a debit.
 * `sell_vat` is a liability -- SELL credits it (+totalVat), so its ledger sum is directly
 * comparable to the report's `vat_collected`. `buy_vat` is an asset -- BUY debits it (-totalVat),
 * so its ledger sum is the NEGATIVE of the report's `creditable_input_vat`.
 */
class TaxReconciliationService
{
    /** Rounding-only differences below this are not reported as a mismatch. */
    private const TOLERANCE = 0.5;

    /**
     * @return array{
     *   report_vat_collected: float, ledger_vat_collected: float, vat_collected_difference: float, vat_collected_matches: bool,
     *   report_input_vat: float, ledger_input_vat: float, input_vat_difference: float, input_vat_matches: bool,
     *   is_balanced: bool,
     * }
     */
    public function reconcileSeasonalVat(int $companyId, array $report, string $startDate, string $endDate): array
    {
        $ledgerSellVat = $this->subjectLedgerSum($companyId, (int) config('amir.sell_vat'), $startDate, $endDate);
        $ledgerBuyVat = $this->subjectLedgerSum($companyId, (int) config('amir.buy_vat'), $startDate, $endDate);
        $ledgerInputVat = round(-$ledgerBuyVat, 2);

        $vatCollectedDifference = round($report['vat_collected'] - $ledgerSellVat, 2);
        $inputVatDifference = round($report['creditable_input_vat'] - $ledgerInputVat, 2);

        $vatCollectedMatches = abs($vatCollectedDifference) <= self::TOLERANCE;
        $inputVatMatches = abs($inputVatDifference) <= self::TOLERANCE;

        return [
            'report_vat_collected' => $report['vat_collected'],
            'ledger_vat_collected' => $ledgerSellVat,
            'vat_collected_difference' => $vatCollectedDifference,
            'vat_collected_matches' => $vatCollectedMatches,
            'report_input_vat' => $report['creditable_input_vat'],
            'ledger_input_vat' => $ledgerInputVat,
            'input_vat_difference' => $inputVatDifference,
            'input_vat_matches' => $inputVatMatches,
            'is_balanced' => $vatCollectedMatches && $inputVatMatches,
        ];
    }

    private function subjectLedgerSum(int $companyId, ?int $subjectId, string $startDate, string $endDate): float
    {
        if (! $subjectId) {
            return 0.0;
        }

        // Transaction has no FiscalYearScope of its own and a plain query-builder ->join() to
        // `documents` does not apply Document's own scope either (guardrail #36) -- filter
        // company_id explicitly.
        $sum = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.company_id', $companyId)
            ->where('transactions.subject_id', $subjectId)
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->sum('transactions.value');

        return round((float) $sum, 2);
    }
}
