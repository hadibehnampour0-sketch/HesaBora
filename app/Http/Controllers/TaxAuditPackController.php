<?php

namespace App\Http\Controllers;

use App\Services\TaxAuditPackService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class TaxAuditPackController extends Controller
{
    public function __construct(private readonly TaxAuditPackService $service) {}

    public function download(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'jalali_year' => ['required', 'integer', 'min:1300', 'max:1500'],
            'quarter' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
        ]);

        $zipPath = $this->service->build(getActiveCompany(), (int) $validated['jalali_year'], (int) $validated['quarter']);
        $fileName = 'dakhl-tax-audit-pack-'.$validated['jalali_year'].'-q'.$validated['quarter'].'.zip';

        return response()->download($zipPath, $fileName, [], ResponseHeaderBag::DISPOSITION_ATTACHMENT)->deleteFileAfterSend(true);
    }
}
