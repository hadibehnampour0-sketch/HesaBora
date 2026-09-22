<?php

namespace App\Http\Controllers;

use App\Services\WorkflowGovernanceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * P7 (Role & Workflow Governance). A small, purpose-built settings page
 * for the two P7 company-level toggles -- deliberately separate from
 * ConfigController, which is a chart-of-accounts Subject picker and the
 * wrong shape for a boolean + a numeric threshold.
 */
class WorkflowGovernanceController extends Controller
{
    public function __construct(private readonly WorkflowGovernanceSettingsService $settings) {}

    public function edit(): View
    {
        return view('workflow-governance.edit', [
            'selfApprovalPrevented' => $this->settings->isSelfApprovalPrevented(),
            'approvalChainThreshold' => $this->settings->invoiceApprovalChainThreshold(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'self_approval_prevented' => ['nullable', 'boolean'],
            'invoice_approval_chain_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->settings->update(
            getActiveCompany(),
            (bool) ($validated['self_approval_prevented'] ?? false),
            isset($validated['invoice_approval_chain_threshold']) ? (float) $validated['invoice_approval_chain_threshold'] : null
        );

        return redirect()->route('workflow-governance.edit')
            ->with('success', __('Workflow governance settings updated successfully.'));
    }
}
