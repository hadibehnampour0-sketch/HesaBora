<?php

namespace App\Http\Controllers\Inquiry;

use App\Http\Controllers\Controller;
use App\Models\OfficialInquiryLog;
use App\Services\Inquiry\OfficialInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OfficialInquiryController extends Controller
{
    public function __construct(
        protected OfficialInquiryService $inquiryService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();
        $logs = OfficialInquiryLog::query()
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(15);

        return view('inquiries.index', compact('logs'));
    }

    public function shahkar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'national_code' => ['required', 'string'],
            'mobile' => ['required', 'string'],
        ]);

        $companyId = getActiveCompany();

        try {
            $result = $this->inquiryService->inquireShahkar(
                $validated['national_code'],
                $validated['mobile'],
                $companyId,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sheba(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sheba' => ['required', 'string'],
        ]);

        $companyId = getActiveCompany();

        try {
            $result = $this->inquiryService->inquireSheba(
                $validated['sheba'],
                $companyId,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function card(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_number' => ['required', 'string'],
        ]);

        $companyId = getActiveCompany();

        try {
            $result = $this->inquiryService->inquireCard(
                $validated['card_number'],
                $companyId,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function postalCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string'],
        ]);

        $companyId = getActiveCompany();

        try {
            $result = $this->inquiryService->inquirePostalCode(
                $validated['postal_code'],
                $companyId,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
