<?php

namespace App\Services\AiCopilot;

use App\Models\CopilotConversation;
use App\Models\CopilotMessage;
use App\Models\User;
use Illuminate\Support\Str;

class FinancialCopilotService
{
    public function __construct(
        protected GeminiFinancialChatEngine $chatEngine
    ) {}

    public function handleUserMessage(int $companyId, User $user, string $query, ?int $conversationId = null): array
    {
        $conversation = null;

        if ($conversationId) {
            $conversation = CopilotConversation::where('id', $conversationId)->first();
        }

        if (!$conversation) {
            $title = Str::limit(trim($query), 50, '...');
            $conversation = CopilotConversation::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'title' => $title ?: 'گفتگوی مالی جدید',
            ]);
        }

        // Save User Message
        $userMessage = CopilotMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $companyId,
            'role' => 'user',
            'content' => $query,
        ]);

        // Get past messages for context
        $history = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Generate Copilot response
        $engineResponse = $this->chatEngine->answer($companyId, $query, $history);

        // Save Assistant Message
        $assistantMessage = CopilotMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $companyId,
            'role' => 'assistant',
            'content' => $engineResponse['reply'],
            'tool_calls' => $engineResponse['tool_calls'] ?? [],
            'sources' => $engineResponse['sources'] ?? [],
        ]);

        return [
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }
}
