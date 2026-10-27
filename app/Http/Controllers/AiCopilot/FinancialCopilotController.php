<?php

namespace App\Http\Controllers\AiCopilot;

use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\CopilotConversation;
use App\Services\AiCopilot\FinancialCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class FinancialCopilotController extends Controller
{
    public function __construct(
        protected FinancialCopilotService $copilotService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('copilot.chat');

        $companyId = getActiveCompany();
        $conversations = CopilotConversation::where('company_id', $companyId)
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->take(20)
            ->get();

        $activeConversation = null;
        if ($request->has('c')) {
            $activeConversation = CopilotConversation::where('id', $request->input('c'))
                ->where('company_id', $companyId)
                ->with('messages')
                ->first();
        }

        $companyApiKey = Config::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'gemini_api_key')
            ->value('value');
        $envApiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');

        $activeKey = $companyApiKey ?: $envApiKey;
        $isConfigured = ! empty($activeKey);
        $apiKeySource = $companyApiKey ? 'company' : ($envApiKey ? 'env' : 'none');
        $maskedKey = $isConfigured ? substr($activeKey, 0, 7) . '...' . substr($activeKey, -4) : '';

        return view('copilot.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'isConfigured' => $isConfigured,
            'apiKeySource' => $apiKeySource,
            'maskedKey' => $maskedKey,
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $this->authorizePermission('copilot.chat');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        $companyId = getActiveCompany();

        $result = $this->copilotService->handleUserMessage(
            companyId: $companyId,
            user: $request->user(),
            query: $validated['message'],
            conversationId: $validated['conversation_id'] ?? null,
        );

        return response()->json([
            'success' => true,
            'conversation_id' => $result['conversation_id'],
            'title' => $result['conversation_title'],
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'action_card' => $result['action_card'] ?? null,
        ]);
    }

    public function show(CopilotConversation $conversation): JsonResponse
    {
        $this->authorizePermission('copilot.chat');

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    public function destroy(CopilotConversation $conversation): JsonResponse
    {
        $this->authorizePermission('copilot.destroy');

        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->authorizePermission('copilot.chat');

        $validated = $request->validate([
            'gemini_api_key' => 'nullable|string|max:500',
        ]);

        $companyId = getActiveCompany();
        $key = trim($validated['gemini_api_key'] ?? '');

        if (! empty($key)) {
            Config::updateOrCreate(
                ['company_id' => $companyId, 'key' => 'gemini_api_key'],
                ['value' => $key, 'category' => 'ai', 'type' => 'string']
            );
        } else {
            Config::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('key', 'gemini_api_key')
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => ! empty($key)
                ? __('کلید هوش مصنوعی اختصاصی شرکت با موفقیت ذخیره شد.')
                : __('کلید هوش مصنوعی اختصاصی حذف شد و به حالت پیش‌فرض برگردانده شد.'),
            'is_configured' => ! empty($key) || ! empty(config('services.gemini.api_key') ?? env('GEMINI_API_KEY')),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->authorizePermission('copilot.chat');

        $companyId = getActiveCompany();
        $inputKey = trim($request->input('api_key') ?? '');

        if ($request->has('api_key')) {
            $keyToTest = $inputKey;
        } else {
            $keyToTest = Config::withoutGlobalScopes()->where('company_id', $companyId)->where('key', 'gemini_api_key')->value('value')
                ?: (config('services.gemini.api_key') ?? env('GEMINI_API_KEY'));
        }

        if (empty($keyToTest)) {
            return response()->json([
                'success' => false,
                'message' => __('هیچ کلید هوش مصنوعی برای تست وارد یا تنظیم نشده است.'),
            ], 422);
        }

        try {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash?key={$keyToTest}";
            $start = microtime(true);
            $response = Http::timeout(8)->get($endpoint);
            $latency = round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $body = $response->json();
                $modelName = $body['displayName'] ?? 'Gemini 2.5 Flash';

                return response()->json([
                    'success' => true,
                    'latency_ms' => $latency,
                    'model' => $modelName,
                    'message' => "اتصال به Google AI Studio با موفقیت برقرار شد ({$latency}ms). مدل فعال: {$modelName}",
                ]);
            }

            $errorMessage = $response->json('error.message') ?? 'کد خطا: '.$response->status();

            return response()->json([
                'success' => false,
                'message' => "خطا در تأیید کلید از سوی گوگل: {$errorMessage}",
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ارتباط با سرور گوگل برقرار نشد (بررسی اینترنت یا تحریم‌شکن/DNS): '.$e->getMessage(),
            ], 500);
        }
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403, 'دسترسی به دستیار هوش مصنوعی مجاز نمی‌باشد.');
    }
}
