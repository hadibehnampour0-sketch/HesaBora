<?php

namespace App\Http\Controllers\Telephony;

use App\Http\Controllers\Controller;
use App\Models\TelephonyCallLog;
use App\Services\Telephony\CallerIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelephonyController extends Controller
{
    public function __construct(
        protected CallerIdService $callerIdService
    ) {}

    public function index(Request $request)
    {
        $companyId = getActiveCompany();

        $logs = TelephonyCallLog::query()
            ->where('company_id', $companyId)
            ->with('customer')
            ->latest()
            ->paginate(20);

        return view('telephony.index', compact('logs'));
    }

    /**
     * Webhook endpoint for Asterisk, Issabel, FreePBX, or Dakhl Hardware Bridge.
     */
    public function webhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'caller_number' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'in:voip,usb_modem'],
            'extension' => ['nullable', 'string'],
        ]);

        $companyId = getActiveCompany();

        $log = $this->callerIdService->handleIncomingCall(
            $validated['caller_number'],
            $companyId,
            $validated['channel'] ?? 'voip',
            $validated['extension'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => __('تماس ورودی با موفقیت دریافت و پروفایل مشتری استخراج شد.'),
            'data' => [
                'log_id' => $log->id,
                'caller_number' => $log->caller_number,
                'call_status' => $log->call_status,
                'profile' => $log->customer_snapshot,
            ],
        ]);
    }

    /**
     * Real-time query endpoint for web clients (e.g. desktop pop-up).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string'],
        ]);

        $companyId = getActiveCompany();
        $profile = $this->callerIdService->resolveCustomerProfile($validated['number'], $companyId);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    public function updateStatus(TelephonyCallLog $log, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:RINGING,ANSWERED,MISSED'],
        ]);

        $this->callerIdService->updateCallStatus($log, $validated['status']);

        return response()->json([
            'success' => true,
            'data' => $log->fresh(),
        ]);
    }
}
