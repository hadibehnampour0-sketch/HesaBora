<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\TaxReadinessLog;

/**
 * T-8/T-9 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md §7 owner
 * requirement #1): "a person with no audit knowledge must be able to complete the tax package".
 * This is a presentation layer only -- it composes the readiness score (`TaxReadinessService`),
 * the ledger reconciliation (`TaxReconciliationService`) and the seasonal report
 * (`SeasonalTaxReportService`) that already exist and are already tested, into one plain-language
 * checklist for a specific period. It calculates nothing new and never mutates any record.
 */
class TaxFilingPreparationService
{
    public function __construct(
        private readonly SeasonalTaxReportService $seasonalTaxReportService,
        private readonly TaxReconciliationService $reconciliationService,
    ) {}

    /**
     * @return array{
     *   readiness: ?TaxReadinessLog,
     *   report: array,
     *   reconciliation: array,
     *   pending_moadian_count: int,
     *   steps: array<int, array{key: string, label: string, done: bool, detail: ?string, fix_route: ?string}>,
     *   is_ready: bool,
     * }
     */
    public function prepare(int $companyId, int $jalaliYear, int $quarter): array
    {
        $readiness = TaxReadinessLog::latest('calculated_at')->first();
        $report = $this->seasonalTaxReportService->report($companyId, $jalaliYear, $quarter);
        $reconciliation = $this->reconciliationService->reconcileSeasonalVat($companyId, $report, $report['start_date'], $report['end_date']);
        $pendingCount = $this->pendingMoadianCount($companyId, $report['start_date'], $report['end_date']);

        $steps = [
            [
                'key' => 'profile',
                'label' => __('Company tax profile and Moadian credentials are complete'),
                'done' => $readiness !== null && ($readiness->metrics_breakdown['profile_readiness']['score'] ?? 0) >= ($readiness->metrics_breakdown['profile_readiness']['max'] ?? 1),
                'detail' => $readiness === null ? __('Calculate the readiness score first.') : ((($readiness->metrics_breakdown['profile_readiness']['score'] ?? 0) < ($readiness->metrics_breakdown['profile_readiness']['max'] ?? 1)) ? __('Your company tax profile or Moadian credentials need attention.') : null),
                'fix_route' => 'companies.index',
            ],
            [
                'key' => 'moadian',
                'label' => __('All of this period\'s sales invoices reached Moadian'),
                'done' => $pendingCount === 0,
                'detail' => $pendingCount > 0 ? trans_choice(':count invoice still needs to be sent or resent.|:count invoices still need to be sent or resent.', $pendingCount, ['count' => $pendingCount]) : null,
                'fix_route' => 'invoices.moadian-queue.index',
            ],
            [
                'key' => 'reconciliation',
                'label' => __('This period\'s VAT matches the accounting ledger'),
                'done' => $reconciliation['is_balanced'],
                'detail' => $reconciliation['is_balanced'] ? null : __('Difference').': '.number_format($reconciliation['vat_collected_difference'] + $reconciliation['input_vat_difference']),
                'fix_route' => 'seasonal-tax.index',
            ],
            [
                'key' => 'evidence',
                'label' => __('High-value costs have supporting evidence attached'),
                'done' => $readiness !== null && ($readiness->metrics_breakdown['evidence_vault_coverage']['score'] ?? 0) >= ($readiness->metrics_breakdown['evidence_vault_coverage']['max'] ?? 1),
                'detail' => ($readiness !== null && (($readiness->metrics_breakdown['evidence_vault_coverage']['score'] ?? 0) < ($readiness->metrics_breakdown['evidence_vault_coverage']['max'] ?? 1))) ? __('One or more high-value costs are missing supporting evidence.') : null,
                'fix_route' => 'ancillary-costs.index',
            ],
        ];

        return [
            'readiness' => $readiness,
            'report' => $report,
            'reconciliation' => $reconciliation,
            'pending_moadian_count' => $pendingCount,
            'steps' => $steps,
            'is_ready' => collect($steps)->every(fn ($step) => $step['done']),
        ];
    }

    private function pendingMoadianCount(int $companyId, string $startDate, string $endDate): int
    {
        return Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$startDate, $endDate])
            ->whereDoesntHave('moadianHistories', fn ($q) => $q->where('data->status', 'SUCCESS'))
            ->count();
    }
}
