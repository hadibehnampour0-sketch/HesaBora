<?php

namespace App\Http\Controllers;

use App\Models\SmsConfiguration;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    public function __construct(
        protected readonly SmsService $smsService
    ) {}

    public function index(): View
    {
        $companyId = getActiveCompany();

        $config = SmsConfiguration::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $templates = SmsTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('event_type');

        $logs = SmsLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(15);

        return view('sms.index', compact('config', 'templates', 'logs'));
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $companyId = getActiveCompany();

        $data = $request->validate([
            'provider' => 'required|string|in:log,kavenegar,melipayamak,farazsms',
            'api_key' => 'nullable|string|max:500',
            'sender_number' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'auto_invoice_approved' => 'nullable|boolean',
            'auto_payment_received' => 'nullable|boolean',
            'auto_cheque_due_reminder' => 'nullable|boolean',
            'auto_debt_reminder' => 'nullable|boolean',
        ]);

        $config = SmsConfiguration::withoutGlobalScopes()
            ->firstOrNew(['company_id' => $companyId]);

        $config->provider = $data['provider'];
        if (!empty($data['api_key'])) {
            $config->api_key = $data['api_key'];
        }
        $config->sender_number = $data['sender_number'] ?? null;
        $config->is_active = $request->boolean('is_active');
        $config->auto_invoice_approved = $request->boolean('auto_invoice_approved');
        $config->auto_payment_received = $request->boolean('auto_payment_received');
        $config->auto_cheque_due_reminder = $request->boolean('auto_cheque_due_reminder');
        $config->auto_debt_reminder = $request->boolean('auto_debt_reminder');
        $config->save();

        return redirect()->back()->with('success', __('SMS configuration updated successfully.'));
    }

    public function updateTemplate(Request $request, string $eventType): RedirectResponse
    {
        $companyId = getActiveCompany();

        $data = $request->validate([
            'template_text' => 'required|string|max:1000',
            'pattern_code' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        $template = SmsTemplate::withoutGlobalScopes()
            ->firstOrNew([
                'company_id' => $companyId,
                'event_type' => $eventType,
            ]);

        $template->template_text = $data['template_text'];
        $template->pattern_code = $data['pattern_code'] ?? null;
        $template->is_active = $request->boolean('is_active', true);
        $template->save();

        return redirect()->back()->with('success', __('SMS template updated successfully.'));
    }

    public function testSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string',
            'message' => 'required|string|max:500',
        ]);

        $cleanPhone = SmsService::normalizePhone($data['recipient']);
        if (!$cleanPhone) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid mobile phone number format.'),
            ], 422);
        }

        $log = $this->smsService->dispatchAsync(
            recipient: $cleanPhone,
            message: $data['message'],
            eventType: 'manual_test',
            companyId: getActiveCompany()
        );

        return response()->json([
            'success' => true,
            'message' => __('SMS queued for delivery.'),
            'log_id' => $log->id,
        ]);
    }
}
