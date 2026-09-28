<?php

namespace App\Http\Controllers;

use App\Enums\TaxEvidenceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\TaxEvidence;
use App\Services\TaxEvidenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * T-2 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md): the Evidence
 * Vault (P9.6) started scoped to AncillaryCost, and had no UI on ANY record -- only the
 * route/controller/service existed. Extended to Invoice (the other record an auditor asks
 * about) and given a reusable upload/list widget (`x-tax-evidence-vault`) used on both show
 * pages. The underlying model/service were already polymorphic; nothing there changed.
 */
class TaxEvidenceController extends Controller
{
    public function __construct(private readonly TaxEvidenceService $evidenceService) {}

    public function storeForAncillaryCost(Request $request, AncillaryCost $ancillaryCost): RedirectResponse
    {
        return $this->store($request, $ancillaryCost);
    }

    public function storeForInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        return $this->store($request, $invoice);
    }

    private function store(Request $request, Model $evidenceable): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(TaxEvidenceType::valueNames())],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $this->evidenceService->store(
            $evidenceable,
            $validated['file'],
            $validated['document_type'],
            $validated['title'],
            getActiveCompany(),
            $request->user()->id
        );

        return redirect()->back()->with('success', __('Evidence uploaded successfully.'));
    }

    public function download(TaxEvidence $taxEvidence)
    {
        return $this->evidenceService->download($taxEvidence);
    }

    public function destroy(TaxEvidence $taxEvidence): RedirectResponse
    {
        $this->evidenceService->delete($taxEvidence);

        return redirect()->back()->with('success', __('Evidence deleted successfully.'));
    }
}
