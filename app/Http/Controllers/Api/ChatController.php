<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatbotService $chatbotService
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
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ], [
            'message.required' => 'A mensagem é obrigatória.',
            'message.max' => 'A mensagem é muito longa.',
        ]);

        $user = $request->user();
        $store = $user->activeStore;

        // Get or create conversation
        $conversation = ChatConversation::firstOrCreate(
            [
                'user_id' => $user->id,
                'status' => 'active',
            ],
            [
                'store_id' => $store?->id,
            ]
        );

        // Save user message
        $userMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['message'],
            'context' => $validated['context'] ?? null,
        ]);

        // Get AI response
        try {
            $response = $this->chatbotService->getResponse(
                $user,
                $conversation,
                $validated['message'],
                $validated['context'] ?? null
            );

            // Save assistant message
            $assistantMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response,
            ]);

            return response()->json([
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            // Delete user message on failure
            $userMessage->delete();

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
