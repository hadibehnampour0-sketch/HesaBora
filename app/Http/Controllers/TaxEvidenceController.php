<?php

namespace App\Http\Controllers;

use App\Enums\TaxEvidenceType;
use App\Models\AncillaryCost;
use App\Models\TaxEvidence;
use App\Services\TaxEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P9.6 (Evidence Vault). Scoped to AncillaryCost for this pass (this
 * app's closest existing concept to a standalone "expense" record) --
 * the underlying model/service are polymorphic and can attach to other
 * financial records later without changes here.
 */
class TaxEvidenceController extends Controller
{
    public function __construct(private readonly TaxEvidenceService $evidenceService) {}

    public function storeForAncillaryCost(Request $request, AncillaryCost $ancillaryCost): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(TaxEvidenceType::valueNames())],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $this->evidenceService->store(
            $ancillaryCost,
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
