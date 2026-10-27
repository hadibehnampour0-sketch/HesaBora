<?php

namespace App\Services\AiCopilot;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini LLM Copilot Engine.
 * Combines grounded live accounting facts from FinancialKnowledgeService
 * with Gemini generative reasoning for deep financial explanations and recommendations.
 * Falls back seamlessly to DeterministicFinancialChatEngine on missing key, timeout, or network error.
 */
class GeminiFinancialChatEngine
{
    public function __construct(
        protected FinancialKnowledgeService $knowledgeService,
        protected DeterministicFinancialChatEngine $fallbackEngine
    ) {}

    public function answer(int $companyId, string $query, array $conversationHistory = []): array
    {
        // For accounting actions (payments, trade, quotes) use deterministic action-card engine
        if ($this->fallbackEngine->isActionOrQuoteQuery($query)) {
            return $this->fallbackEngine->answer($companyId, $query);
        }

        $companyApiKey = \App\Models\Config::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'gemini_api_key')
            ->value('value');

        $apiKey = $companyApiKey ?: (config('services.gemini.api_key') ?? env('GEMINI_API_KEY'));

        if (empty($apiKey)) {
            return $this->fallbackEngine->answer($companyId, $query);
        }

        try {
            $snapshot = $this->knowledgeService->getFullSnapshot($companyId);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $systemInstruction = <<<EOT
شما «دستیار ارشد مالی و حسابداری دخل (Dakhl Financial Copilot)» هستید؛ یک مشاور اقتصادی و حسابدار رسمی خبره در ایران.
شما به صاحبان کسب‌وکار، حسابداران و مدیران مالی در مدیریت فروش، بدهکاران، چک‌ها، انبار و مالیات کمک می‌کنید.

اصول و قواعد پاسخ‌دهی شما:
1. حقیقت قطعی مالی (One Financial Truth): تمامی اعداد، گزارش‌ها و مبالغ باید دقیقاً منطبق بر داده‌های واقعی موجود در داده‌های زنده زیر باشد و هرگز عددی خارج از این فکت‌ها تولید یا حدس نزنید.
2. مبالغ و ارز: مبالغ را به ریال یا تومان بر حسب وضوح با جداکننده سه رقمی هزارگان بنویسید.
3. تحلیل راهبردی و پیشنهادی: فقط عدد را نخوانید، بلکه مفهوم آن برای کسب‌وکار و راهکار عملیاتی (مثلاً نحوه وصول مطالبات، کنترل نقدینگی، پیشگیری از جرایم مالیاتی) را بیان کنید.
4. لحن: حرفه‌ای، محترمانه، صمیمی، دقیق و به زبان فارسی شیوا و روان همراه با ایموجی‌های مناسب کسب‌وکار.
5. فرمت پاسخ: از تیترهای مارک‌داون (###)، لیست‌های بالت و جداول جهت خوانایی بهینه استفاده کنید.

داده‌های مالی لحظه‌ای شرکت (Grounded Accounting Snapshot):
{$snapshotJson}
EOT;

            $contents = [];
            // Add previous history (up to last 6 messages)
            foreach (array_slice($conversationHistory, -6) as $msg) {
                $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]],
                ];
            }

            // Add current user query
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $query]],
            ];

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

            $response = Http::timeout(20)->post($endpoint, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1500,
                ],
            ]);

            if ($response->successful()) {
                $body = $response->json();
                $replyText = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (! empty($replyText)) {
                    return [
                        'reply' => $replyText,
                        'tool_calls' => ['gemini_grounded_reasoning'],
                        'sources' => [
                            ['title' => 'داشبورد مالی دخل', 'url' => route('home.today')],
                            ['title' => 'گزارش سنی مطالبات', 'url' => route('ageing.receivables')],
                        ],
                    ];
                }
            }

            Log::info('Gemini Copilot API response not satisfactory, falling back to deterministic engine.');
        } catch (Exception $e) {
            Log::warning('Gemini Copilot execution failed: '.$e->getMessage().', falling back to deterministic engine.');
        }

        return $this->fallbackEngine->answer($companyId, $query);
    }
}
