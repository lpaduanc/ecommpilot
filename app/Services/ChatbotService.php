<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\AI\AIManager;

class ChatbotService
{
    public function __construct(
        private AIManager $aiManager
    ) {}

    public function getResponse(
        User $user,
        ChatConversation $conversation,
        string $message,
        ?array $context = null
    ): string {
        $store = $user->activeStore;
        $latestAnalysis = $this->getLatestAnalysis($user);
        $chatHistory = $this->getChatHistory($conversation);

        $systemPrompt = $this->buildSystemPrompt($store, $latestAnalysis);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add chat history
        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Add context if provided
        if ($context) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $message = "Contexto adicional: {$contextStr}\n\nMensagem do usuário: {$message}";
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $this->aiManager->chat($messages, [
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);
    }

    private function buildSystemPrompt(?object $store, ?Analysis $analysis): string
    {
        $storeName = $store?->name ?? 'sua loja';
        
        $analysisContext = '';
        if ($analysis && $analysis->isCompleted()) {
            $analysisJson = json_encode([
                'summary' => $analysis->summary,
                'suggestions' => $analysis->suggestions,
                'opportunities' => $analysis->opportunities,
            ], JSON_UNESCAPED_UNICODE);
            
            $analysisContext = "\n\nÚLTIMA ANÁLISE:\n{$analysisJson}";
        }

        return <<<PROMPT
        Você é um assistente de marketing para e-commerce, especializado em ajudar lojistas a aumentar suas vendas.
        Você trabalha para a plataforma Ecommpilot.

        CONTEXTO:
        Loja: {$storeName}{$analysisContext}

        REGRAS:
        - SEMPRE responda em português brasileiro
        - Seja prestativo, amigável e profissional
        - Referencie dados específicos da análise quando relevante
        - Forneça conselhos acionáveis e específicos para a loja
        - Se perguntado sobre algo fora do escopo de e-commerce/marketing, redirecione educadamente
        - Mantenha respostas concisas mas informativas
        - Use emojis com moderação para ser amigável
        - Quando sugerir ações, seja específico para os dados da loja

        CAPACIDADES:
        - Explicar sugestões da análise em mais detalhes
        - Ajudar a priorizar ações
        - Responder dúvidas sobre estratégias de marketing
        - Dar dicas de otimização de vendas
        - Explicar métricas e KPIs

        Agora, responda à mensagem do usuário de forma útil e personalizada.
        PROMPT;
    }

    private function getLatestAnalysis(User $user): ?Analysis
    {
        return Analysis::where('user_id', $user->id)
            ->completed()
            ->latest()
            ->first();
    }

    private function getChatHistory(ChatConversation $conversation, int $limit = 10): array
    {
        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->values()
            ->toArray();
    }
}

