<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Suggestion;
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
            ->whereNull('suggestion_id')
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

    public function getSuggestionConversation(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'message' => 'Nenhuma loja ativa.',
            ], 400);
        }

        // Validar que a sugestão pertence ao usuário e sua loja ativa (evita IDOR)
        if ($suggestion->store_id !== $store->id) {
            return response()->json([
                'message' => 'Sugestão não encontrada.',
            ], 404);
        }

        // Load analysis relationship
        $suggestion->load('analysis');

        // Buscar conversa específica para esta sugestão
        $conversation = ChatConversation::where('user_id', $user->id)
            ->where('suggestion_id', $suggestion->id)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->first();

        if (! $conversation) {
            return response()->json([
                'exists' => false,
                'messages' => [],
            ]);
        }

        return response()->json([
            'exists' => true,
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
        $suggestion = null;
        if ($shouldPersist) {
            // Determina o suggestion_id baseado no contexto
            $suggestionDbId = null;
            if ($isSuggestionContext && isset($context['suggestion']['id'])) {
                $suggestionUuid = $context['suggestion']['id'];

                // Validar que a sugestão pertence à loja ativa do usuário (evita IDOR)
                if ($store) {
                    $suggestion = Suggestion::where('uuid', $suggestionUuid)
                        ->where('store_id', $store->id)
                        ->first();

                    if (! $suggestion) {
                        return response()->json([
                            'message' => 'Sugestão não encontrada.',
                        ], 404);
                    }

                    // Use the actual database ID for foreign key
                    $suggestionDbId = $suggestion->id;
                }
            }

            // Cria ou busca conversa com ou sem suggestion_id
            $conversationWhere = [
                'user_id' => $user->id,
                'status' => 'active',
            ];

            // Adiciona suggestion_id ao where se for chat de sugestão
            if ($suggestionDbId !== null) {
                $conversationWhere['suggestion_id'] = $suggestionDbId;
            } else {
                // Para chat geral, garante que não há suggestion_id
                $conversation = ChatConversation::where($conversationWhere)
                    ->whereNull('suggestion_id')
                    ->first();

                if (! $conversation) {
                    $conversation = ChatConversation::create([
                        'user_id' => $user->id,
                        'store_id' => $store?->id,
                        'suggestion_id' => null,
                        'status' => 'active',
                    ]);
                }
            }

            // Para chat de sugestão, usa firstOrCreate normalmente
            if ($suggestionDbId !== null && ! $conversation) {
                $conversation = ChatConversation::firstOrCreate(
                    $conversationWhere,
                    [
                        'store_id' => $store?->id,
                    ]
                );
            }
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
            $errorId = 'err_'.uniqid();

            // Log the actual error for debugging
            \Log::error('Chat error', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                // Stack trace apenas em ambiente local
                'trace' => app()->isLocal() ? $e->getTraceAsString() : null,
            ]);

            // Delete user message on failure (if it was persisted)
            $userMessage?->delete();

            return response()->json([
                'message' => 'Desculpe, não foi possível processar sua mensagem. Tente novamente.',
                'error_id' => $errorId,
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
