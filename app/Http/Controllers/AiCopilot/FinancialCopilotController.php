<?php

namespace App\Http\Controllers\AiCopilot;

use App\Http\Controllers\Controller;
use App\Models\CopilotConversation;
use App\Services\AiCopilot\FinancialCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return view('copilot.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
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

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403, 'دسترسی به دستیار هوش مصنوعی مجاز نمی‌باشد.');
    }
}
