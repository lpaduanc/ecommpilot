<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatbotService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatbotService $chatbotService,
        private PlanLimitService $planLimitService
    ) {}

    public function conversation(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversation = ChatConversation::where('user_id', $user->id)
            ->active()
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->first();

        if (! $conversation) {
            return response()->json([
                'conversation_id' => null,
                'messages' => [],
            ]);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages' => $conversation->messages->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ]),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check plan access to chat (skip in local/dev environment)
        $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

        if (! $isLocalEnv && ! $this->planLimitService->canAccessChat($user)) {
            return response()->json([
                'message' => 'Seu plano não inclui acesso ao Assistente IA.',
                'upgrade_required' => true,
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ], [
            'message.required' => 'A mensagem é obrigatória.',
            'message.max' => 'A mensagem é muito longa.',
        ]);

        $context = $validated['context'] ?? null;
        $isSuggestionContext = isset($context['type']) && $context['type'] === 'suggestion';

        // Check permission for suggestion discussion
        if (! $isLocalEnv && $isSuggestionContext && ! $this->planLimitService->canDiscussSuggestion($user)) {
            return response()->json([
                'message' => 'Seu plano não inclui discussão de sugestões com IA.',
                'upgrade_required' => true,
            ], 403);
        }

        // Determine if messages should be persisted
        $shouldPersist = true;
        if ($isSuggestionContext) {
            $shouldPersist = $isLocalEnv || $this->planLimitService->shouldPersistSuggestionHistory($user);
        }

        $store = $user->activeStore;

        // Get or create conversation (only if persisting)
        $conversation = null;
        if ($shouldPersist) {
            $conversation = ChatConversation::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => 'active',
                ],
                [
                    'store_id' => $store?->id,
                ]
            );
        }

        // Save user message (only if persisting)
        $userMessage = null;
        if ($shouldPersist && $conversation) {
            $userMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $validated['message'],
                'context' => $context,
            ]);
        }

        // Get AI response
        try {
            $response = $this->chatbotService->getResponse(
                $user,
                $conversation,
                $validated['message'],
                $context
            );

            // Save assistant message (only if persisting)
            $assistantMessage = null;
            if ($shouldPersist && $conversation) {
                $assistantMessage = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $response,
                ]);
            }

            return response()->json([
                'user_message_id' => $userMessage?->id ?? 'temp-'.uniqid(),
                'assistant_message_id' => $assistantMessage?->id ?? 'temp-'.uniqid(),
                'response' => $response,
                'persisted' => $shouldPersist,
            ]);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            \Log::error('Chat error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'message' => $validated['message'],
                'exception' => $e->getTraceAsString(),
            ]);

            // Delete user message on failure (if it was persisted)
            $userMessage?->delete();

            return response()->json([
                'message' => 'Desculpe, não foi possível processar sua mensagem. Tente novamente.',
            ], 500);
        }
    }

    public function clearConversation(Request $request): JsonResponse
    {
        $user = $request->user();

        ChatConversation::where('user_id', $user->id)
            ->active()
            ->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Conversa limpa com sucesso.',
        ]);
    }
}
