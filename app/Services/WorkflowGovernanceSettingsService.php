<?php

namespace App\Services;

use App\Models\Config;

/**
 * P7 (Role & Workflow Governance). Reuses the existing `Config` table +
 * `ConfigLoader` middleware infrastructure (the same mechanism
 * `config('amir.cash_book')`/etc. already rely on) rather than inventing a
 * new settings store -- these two keys become `config('amir.self_approval_prevented')`
 * and `config('amir.invoice_approval_chain_threshold')` automatically on
 * every request via ConfigLoader, exactly like any other `amir.*` config.
 *
 * Deliberately NOT wired into the existing `configs.edit`/`configs.update`
 * UI (ConfigController) -- that page is purpose-built for mapping a
 * config key to a chart-of-accounts Subject (`ConfigTitle` enum, a
 * `code` -> `subject_id` picker) and is the wrong shape for a boolean
 * toggle and a numeric threshold. This service backs its own small
 * settings form instead (WorkflowGovernanceController).
 */
class WorkflowGovernanceSettingsService
{
    private const SELF_APPROVAL_KEY = 'self_approval_prevented';

    private const APPROVAL_CHAIN_THRESHOLD_KEY = 'invoice_approval_chain_threshold';

    public function isSelfApprovalPrevented(): bool
    {
        return (bool) config('amir.'.self::SELF_APPROVAL_KEY);
    }

    /**
     * Null (or absent) means the two-step approval chain is disabled --
     * every invoice, regardless of amount, uses today's unchanged
     * single-step approval.
     */
    public function invoiceApprovalChainThreshold(): ?float
    {
        $value = config('amir.'.self::APPROVAL_CHAIN_THRESHOLD_KEY);

        return ($value === null || $value === '') ? null : (float) $value;
    }

    public function update(int $companyId, bool $selfApprovalPrevented, ?float $approvalChainThreshold): void
    {
        Config::updateOrCreate(
            ['key' => self::SELF_APPROVAL_KEY, 'company_id' => $companyId],
            [
                'value' => $selfApprovalPrevented ? '1' : '0',
                'desc' => __('Prevent a document from being approved by the user who created it'),
                'type' => 'boolean',
                'category' => 'workflow_governance',
            ]
        );

        // `configs.value` is NOT NULL -- "disabled" is represented by
        // deleting the row entirely (absence = disabled), not by a null
        // value, matching invoiceApprovalChainThreshold()'s own contract.
        if ($approvalChainThreshold === null) {
            Config::where('key', self::APPROVAL_CHAIN_THRESHOLD_KEY)->where('company_id', $companyId)->delete();
        } else {
            Config::updateOrCreate(
                ['key' => self::APPROVAL_CHAIN_THRESHOLD_KEY, 'company_id' => $companyId],
                [
                    'value' => (string) $approvalChainThreshold,
                    'desc' => __('Invoice amount at/above which a second, independent approval is required'),
                    'type' => 'numeric',
                    'category' => 'workflow_governance',
                ]
            );
        }

        config([
            'amir.'.self::SELF_APPROVAL_KEY => $selfApprovalPrevented ? '1' : '0',
            'amir.'.self::APPROVAL_CHAIN_THRESHOLD_KEY => $approvalChainThreshold !== null ? (string) $approvalChainThreshold : null,
        ]);
    }
}
