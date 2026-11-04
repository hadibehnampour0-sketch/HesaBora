<?php

namespace App\Services\AiCopilot;

use App\Http\Controllers\AiCopilot\FinancialCopilotController;
use App\Models\Config;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini LLM Copilot Engine.
 * Combines grounded live accounting facts & market intelligence with Gemini
 * generative reasoning for strategic financial explanations and recommendations.
 * Supports Google Interactions API (primary) with generateContent API (fast fallback),
 * and graceful fallback to DeterministicFinancialChatEngine when offline or unconfigured.
 */
class GeminiFinancialChatEngine
{
    public function __construct(
        protected FinancialKnowledgeService $knowledgeService,
        protected DeterministicFinancialChatEngine $fallbackEngine
    ) {}

    public function answer(int $companyId, string $query, array $conversationHistory = []): array
    {
        // In testing environment without explicit enable flag, use deterministic engine
        if (app()->environment('testing') && ! config('services.gemini.enable_in_tests', false)) {
            return $this->fallbackEngine->answer($companyId, $query);
        }

        // Security (D9 & D-040): Check explicit opt-in for external AI data transmission
        $optIn = Config::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'ai_data_sharing_opt_in')
            ->value('value');

        if ($optIn === '0') {
            return $this->fallbackEngine->answer($companyId, $query);
        }

        $companyApiKey = FinancialCopilotController::getDecryptedApiKey($companyId);
        $apiKey = $companyApiKey ?: (config('services.gemini.api_key') ?? env('GEMINI_API_KEY'));

        if (empty($apiKey)) {
            return $this->fallbackEngine->answer($companyId, $query);
        }

        try {
            $snapshot = $this->knowledgeService->getFullSnapshot($companyId);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $systemInstruction = <<<EOT
شما «مدیر مالی ارشد و هوش تجاری نرم‌افزار حسابداری دخل (Dakhl AI Copilot)» هستید؛ یک مشاور اقتصادی و حسابدار زبده و باتجربه در بازار ایران.
شما دستیار هوشمند و همیار استراتژیک صاحب کسب‌وکار (هادی بهنام‌پور / مدیر مجموعه) هستید.

اختیارات و سبک پاسخ‌دهی شما (دست شما کاملاً باز است):
۱. درک عمیق خواسته کاربر: نیت و هدف کاربر را عمیقاً درک کنید. هرگز پاسخ‌های خشک، از پیش‌تعیین‌شده یا رباتیک ندهید. به عنوان یک شریک تجاری امین و دلسوز صحبت کنید.
۲. تحلیل داده‌های مالی و حسابداری: هرگاه کاربر درباره فروش، چک‌ها، بدهکاران، اقساط، انبار یا تراز مالی سوال کرد، از داده‌های زنده و واقعی زیر استفاده کنید و آنها را تحلیل و تفسیر کنید (نشان دادن ریسک‌ها، مطالبات معوق، وضعیت نقدینگی).
۳. استراتژی اقتصادی، دلار و تورم: دست شما در ارائه مشاوره اقتصادی، فرمول‌های قیمت‌گذاری شناور متناسب با دلار (بهای جایگزینی D-137)، نحوه وصول مطالبات بدون تنش، مدیریت جریان نقدینگی، تبدیل نسیه به نقد و تدابیر ضد تورم کاملاً باز است.
۴. زبان و لحن: فارسی روان، بسیار حرفه‌ای، صمیمی، انگیزاننده، قاطع و کاربردی. برای خوانایی بهینه از تیترهای مارک‌داون (###)، لیست‌های بالت و جداول کوتاه و ایموجی‌های مناسب استفاده کنید.
۵. مبالغ: مبالغ را به تومان یا ریال با جداکننده سه رقمی هزارگان بنویسید تا برای کاربر کاملاً ملموس باشد.

داده‌های مالی لحظه‌ای شرکت و مظنه زنده بازار در نرم‌افزار دخل:
{$snapshotJson}
EOT;

            // Strategy 1: Modern Google Interactions API (gemini-3-flash-preview)
            $replyText = $this->callInteractionsApi($apiKey, $systemInstruction, $query, $conversationHistory);

            // Strategy 2: Fast fallback via generateContent API (gemini-3.5-flash / gemini-3.1-flash-lite)
            if (empty($replyText)) {
                $replyText = $this->callGenerateContentApi($apiKey, $systemInstruction, $query, $conversationHistory);
            }

            if (! empty($replyText)) {
                return [
                    'reply' => $replyText,
                    'tool_calls' => ['gemini_strategic_reasoning'],
                    'sources' => [
                        ['title' => 'میز کار و نبض مالی دخل', 'url' => route('home.today')],
                        ['title' => 'گزارش سنی مطالبات', 'url' => route('ageing.receivables')],
                        ['title' => 'میز کار تهاتر و معاملات', 'url' => route('trades.index')],
                    ],
                ];
            }

            Log::info('Gemini Copilot API returned empty text, falling back to deterministic engine.');
        } catch (Exception $e) {
            Log::warning('Gemini Copilot execution failed: '.$e->getMessage().', falling back to deterministic engine.');
        }

        return $this->fallbackEngine->answer($companyId, $query);
    }

    /**
     * Primary: Call Google Interactions API with gemini-3-flash-preview
     */
    protected function callInteractionsApi(string $apiKey, string $systemInstruction, string $query, array $conversationHistory): ?string
    {
        try {
            $input = '';
            $recent = array_slice($conversationHistory, -4);
            if (! empty($recent)) {
                $input .= "تاریخچه گفتگوی قبلی:\n";
                foreach ($recent as $msg) {
                    $roleLabel = ($msg['role'] === 'assistant') ? 'دستیار' : 'کاربر';
                    $input .= "{$roleLabel}: {$msg['content']}\n";
                }
                $input .= "\nپرسش جدید کاربر:\n";
            }
            $input .= $query;

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/interactions?key={$apiKey}";

            $response = Http::withoutVerifying()
                ->timeout(25)
                ->post($endpoint, [
                    'model' => 'gemini-3-flash-preview',
                    'system_instruction' => $systemInstruction,
                    'input' => $input,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                foreach ($data['steps'] ?? [] as $step) {
                    if (($step['type'] ?? '') === 'model_output' && isset($step['content'])) {
                        $text = '';
                        foreach ($step['content'] as $c) {
                            if (isset($c['text'])) {
                                $text .= $c['text'];
                            }
                        }
                        if (! empty(trim($text))) {
                            return trim($text);
                        }
                    }
                }
            } else {
                Log::warning('Gemini Interactions API HTTP error: '.$response->status().' - '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini Interactions API exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Secondary: Call generateContent API across resilient models
     */
    protected function callGenerateContentApi(string $apiKey, string $systemInstruction, string $query, array $conversationHistory): ?string
    {
        $models = ['gemini-3.5-flash', 'gemini-3.1-flash-lite'];

        foreach ($models as $model) {
            try {
                $contents = [];
                foreach (array_slice($conversationHistory, -6) as $msg) {
                    $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
                    $contents[] = [
                        'role' => $role,
                        'parts' => [['text' => $msg['content']]],
                    ];
                }
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $query]],
                ];

                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->post($endpoint, [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemInstruction]],
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 2000,
                        ],
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (! empty(trim($text))) {
                        return trim($text);
                    }
                } else {
                    Log::warning("Gemini generateContent ({$model}) HTTP error: ".$response->status());
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini generateContent ({$model}) exception: ".$e->getMessage());
            }
        }

        return null;
    }
}
