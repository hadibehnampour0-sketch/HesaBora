<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxReadinessLog;
use Illuminate\Support\Carbon;

/**
 * P9.7 (Tax Readiness Score). A 100-point, 4-metric health check over
 * this company's tax posture, built entirely from real existing
 * mechanisms -- Company's own scattered Moadian/tax columns (P9's
 * existing integration), `MoadianHistory`/`MoadianStatus` (never a
 * nonexistent `invoices.moadian_status` column), `TaxEvidence` coverage
 * on AncillaryCost, and `TaxRuleService` (P9.2). Every count query below
 * is a single aggregate (COUNT ... WHERE NOT EXISTS), never a
 * load-everything-into-memory scan.
 */
class TaxReadinessService
{
    /**
     * AncillaryCost amount at/above which missing evidence counts against
     * the score. Deliberately a class constant rather than a new
     * per-company setting for this pass -- see AGENTS.md if this needs to
     * become configurable later.
     */
    public const MATERIAL_EXPENSE_THRESHOLD = 50_000_000;

    public function __construct(private readonly TaxRuleService $taxRuleService) {}

    public function calculateScore(int $companyId): TaxReadinessLog
    {
        $company = Company::findOrFail($companyId);

        $profile = $this->profileCompleteness($company);
        $moadian = $this->moadianCompliance();
        $evidence = $this->evidenceVaultCoverage();
        $taxRule = $this->taxRuleAlignment();

        $breakdown = [
            'profile_readiness' => $profile,
            'moadian_compliance' => $moadian,
            'evidence_vault_coverage' => $evidence,
            'tax_rule_alignment' => $taxRule,
        ];

        $score = array_sum(array_column($breakdown, 'score'));
        $recommendations = array_values(array_filter(array_merge(
            $profile['recommendations'],
            $moadian['recommendations'],
            $evidence['recommendations'],
            $taxRule['recommendations'],
        )));

        return TaxReadinessLog::create([
            'company_id' => $companyId,
            'score' => $score,
            'status' => $this->statusFor($score),
            'metrics_breakdown' => array_map(fn ($m) => ['score' => $m['score'], 'max' => $m['max']], $breakdown),
            'recommendations' => $recommendations,
            'calculated_at' => now(),
        ]);
    }

    private function statusFor(int $score): string
    {
        return match (true) {
            $score >= 95 => 'optimal',
            $score >= 75 => 'ready',
            $score >= 50 => 'warning',
            default => 'critical',
        };
    }

    /**
     * @return array{score: int, max: int, recommendations: array<string>}
     */
    private function profileCompleteness(Company $company): array
    {
        $max = 25;
        $score = $max;
        $recommendations = [];

        if (empty($company->economical_code) || empty($company->national_code)) {
            $score -= 10;
            $recommendations[] = __('The company economic code or national ID is not set.');
        }

        if (empty($company->moadian_username) || empty($company->tax_id)
            || ! $company->decryptedPrivateKey() || ! $company->decryptedCertificate()) {
            $score -= 15;
            $recommendations[] = __('Moadian credentials (username, tax ID, certificate, private key) are not fully configured.');
        }

        return ['score' => max(0, $score), 'max' => $max, 'recommendations' => $recommendations];
    }

    /**
     * @return array{score: int, max: int, recommendations: array<string>}
     */
    private function moadianCompliance(): array
    {
        $max = 35;

        $unsent = Invoice::whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereDoesntHave('moadianHistories', fn ($q) => $q->where('data->status', 'SUCCESS'))
            ->count();

        $recommendations = [];
        $score = $max;

        if ($unsent > 0) {
            $score = max(0, $max - min($max, $unsent * 5));
            $recommendations[] = __(':count approved sales invoice(s) have not been successfully submitted to Moadian.', ['count' => $unsent]);
        }

        return ['score' => $score, 'max' => $max, 'recommendations' => $recommendations];
    }

    /**
     * @return array{score: int, max: int, recommendations: array<string>}
     */
    private function evidenceVaultCoverage(): array
    {
        $max = 20;

        $missing = AncillaryCost::where('amount', '>=', self::MATERIAL_EXPENSE_THRESHOLD)
            ->whereDoesntHave('taxEvidences')
            ->count();

        $recommendations = [];
        $score = $max;

        if ($missing > 0) {
            $score = max(0, $max - min($max, $missing * 4));
            $recommendations[] = __(':count high-value ancillary cost(s) have no supporting evidence attached.', ['count' => $missing]);
        }

        return ['score' => $score, 'max' => $max, 'recommendations' => $recommendations];
    }

    /**
     * @return array{score: int, max: int, recommendations: array<string>}
     */
    private function taxRuleAlignment(): array
    {
        $max = 20;

        try {
            $this->taxRuleService->vatRateOn(null, Carbon::today());

            return ['score' => $max, 'max' => $max, 'recommendations' => []];
        } catch (\Illuminate\Validation\ValidationException) {
            return [
                'score' => 0,
                'max' => $max,
                'recommendations' => [__('No general VAT rate is configured for the current date.')],
            ];
        }
    }
}
