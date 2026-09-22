<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxAlert;
use App\Models\TaxReadinessLog;
use Illuminate\Support\Carbon;

/**
 * P12.2 (Tax Alert & Smart Notification Engine). Evaluates a fixed set of
 * rules against a single company's current tax posture and raises a
 * TaxAlert row for each one that's newly true. Reuses the exact same
 * signals TaxReadinessService (P9.7) already computes from real data --
 * this is deliberately not a second scoring engine, just a set of
 * threshold trips over the same underlying facts.
 *
 * Deduplication: each rule has a stable dedup_key. A rule is skipped if an
 * alert with the same (company_id, dedup_key) was already raised within
 * the last 24h, regardless of whether it has since been read -- this
 * mirrors the "don't re-notify for a condition that hasn't changed"
 * behavior MoadianReconciliationService already established for score
 * recalculation (only recalculates on an actual status change).
 */
class TaxAlertEngineService
{
    private const DEDUP_WINDOW_HOURS = 24;

    public function evaluateCompanyRules(int $companyId): int
    {
        $company = Company::withoutGlobalScopes()->findOrFail($companyId);

        $raised = 0;
        $raised += $this->evaluateReadinessScore($company);
        $raised += $this->evaluateRejectedMoadianInvoices($company);
        $raised += $this->evaluateMissingEvidence($company);

        return $raised;
    }

    private function evaluateReadinessScore(Company $company): int
    {
        $latest = TaxReadinessLog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->latest('calculated_at')
            ->first();

        if (! $latest || $latest->score >= 50) {
            return 0;
        }

        return $this->raise(
            company: $company,
            type: 'low_readiness_score',
            severity: TaxAlert::SEVERITY_CRITICAL,
            title: __('Tax readiness score is critical'),
            message: __('Your tax readiness score has dropped to :score/100. Review the recommendations on the Tax Readiness dashboard.', ['score' => $latest->score]),
            dedupKey: 'low_readiness_score',
            related: $latest,
        );
    }

    private function evaluateRejectedMoadianInvoices(Company $company): int
    {
        $cutoff = Carbon::now()->subDay();

        $invoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereHas('latestMoadianHistory', fn ($q) => $q->where('data->status', 'FAILED'))
            ->with('latestMoadianHistory')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->latestMoadianHistory && $invoice->latestMoadianHistory->created_at->lte($cutoff));

        $raised = 0;

        foreach ($invoices as $invoice) {
            $raised += $this->raise(
                company: $company,
                type: 'rejected_moadian_invoice',
                severity: TaxAlert::SEVERITY_WARNING,
                title: __('Invoice rejected by Moadian'),
                message: __('Invoice #:number was rejected by Moadian more than 24 hours ago and has not been resent.', ['number' => formatDocumentNumber($invoice->number ?? $invoice->id)]),
                dedupKey: "rejected_moadian_invoice:{$invoice->id}",
                related: $invoice,
            );
        }

        return $raised;
    }

    private function evaluateMissingEvidence(Company $company): int
    {
        $costs = AncillaryCost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('amount', '>=', \App\Services\TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD)
            ->whereDoesntHave('taxEvidences')
            ->get();

        $raised = 0;

        foreach ($costs as $cost) {
            $raised += $this->raise(
                company: $company,
                type: 'missing_evidence',
                severity: TaxAlert::SEVERITY_WARNING,
                title: __('High-value expense missing evidence'),
                message: __('A high-value ancillary cost has no supporting document attached in the Evidence Vault.'),
                dedupKey: "missing_evidence:{$cost->id}",
                related: $cost,
            );
        }

        return $raised;
    }

    private function raise(Company $company, string $type, string $severity, string $title, string $message, string $dedupKey, $related = null): int
    {
        $recentDuplicate = TaxAlert::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('dedup_key', $dedupKey)
            ->where('created_at', '>=', Carbon::now()->subHours(self::DEDUP_WINDOW_HOURS))
            ->exists();

        if ($recentDuplicate) {
            return 0;
        }

        TaxAlert::create([
            'company_id' => $company->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'dedup_key' => $dedupKey,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id,
        ]);

        return 1;
    }
}
