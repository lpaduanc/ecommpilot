<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Analysis;
use App\Models\ChatConversation;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use App\Models\User;
use App\Services\AI\AIManager;
use Carbon\Carbon;

class ChatbotService
{
    private const DEFAULT_PERIOD_DAYS = 15;

    private const MAX_PERIOD_DAYS = 30;

    public function __construct(
        private AIManager $aiManager
    ) {}

    public function getResponse(
        User $user,
        ?ChatConversation $conversation,
        string $message,
        ?array $context = null
    ): string {
        $store = $user->activeStore;
        $latestAnalysis = $this->getLatestAnalysis($user);
        $chatHistory = $conversation ? $this->getChatHistory($conversation) : [];

        // Check if this is a suggestion discussion context
        $isSuggestionContext = isset($context['type']) && $context['type'] === 'suggestion';

        // Detect if user requested a specific period
        $periodInfo = $this->detectRequestedPeriod($message);

        // Fetch real store data based on question type
        $storeData = $this->getStoreData($store, $periodInfo['days']);

        // Add period notice if user requested more than max
        $periodNotice = $periodInfo['exceeded_max']
            ? "\n\nNOTA IMPORTANTE: O período máximo de análise é de {self::MAX_PERIOD_DAYS} dias. Mostrando dados dos últimos {self::MAX_PERIOD_DAYS} dias."
            : '';

        // Build appropriate system prompt
        if ($isSuggestionContext) {
            $systemPrompt = $this->buildSuggestionDiscussionPrompt($store, $storeData, $context['suggestion']);
        } else {
            $systemPrompt = $this->buildSystemPrompt($store, $latestAnalysis, $storeData, $periodNotice);
        }

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

        // Format message based on context type
        if ($isSuggestionContext) {
            $message = $this->formatSuggestionUserMessage($context['suggestion'], $message);
        } elseif ($context) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $message = "Contexto adicional: {$contextStr}\n\nMensagem do usuário: {$message}";
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $this->aiManager->chat($messages, [
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ]);
    }

    private function detectRequestedPeriod(string $message): array
    {
        $days = self::DEFAULT_PERIOD_DAYS;
        $exceededMax = false;

        // Check for explicit period mentions
        $patterns = [
            '/(\d+)\s*dias?/i' => fn ($m) => (int) $m[1],
            '/(\d+)\s*semanas?/i' => fn ($m) => (int) $m[1] * 7,
            '/(?:último|ultimo)\s*m[eê]s/i' => fn () => 30,
            '/30\s*dias?/i' => fn () => 30,
            '/uma?\s*semana/i' => fn () => 7,
            '/duas?\s*semanas?/i' => fn () => 14,
            '/quinze(?:na)?|15\s*dias?/i' => fn () => 15,
        ];

        foreach ($patterns as $pattern => $extractor) {
            if (preg_match($pattern, $message, $matches)) {
                $requestedDays = $extractor($matches);
                if ($requestedDays > self::MAX_PERIOD_DAYS) {
                    $days = self::MAX_PERIOD_DAYS;
                    $exceededMax = true;
                } else {
                    $days = max(1, $requestedDays);
                }
                break;
            }
        }

        return [
            'days' => $days,
            'exceeded_max' => $exceededMax,
        ];
    }

    /**
     * Build a specialized prompt for suggestion discussion.
     */
    private function buildSuggestionDiscussionPrompt(?object $store, array $storeData, array $suggestion): string
    {
        $storeName = $store?->name ?? 'sua loja';
        $storeDataJson = json_encode($storeData, JSON_UNESCAPED_UNICODE);

        $categoryLabel = $this->getCategoryLabel($suggestion['category'] ?? 'geral');
        $impactLabel = $this->getImpactLabel($suggestion['expected_impact'] ?? 'medium');
        $recommendedAction = $this->formatRecommendedAction($suggestion['recommended_action'] ?? '');

        return <<<PROMPT
        Você é um assistente de marketing para e-commerce, especializado em ajudar lojistas a implementar sugestões de melhoria.
        Você trabalha para a plataforma Ecommpilot.

        CONTEXTO DA LOJA:
        Loja: {$storeName}
        Período de dados: {$storeData['period']['start']} a {$storeData['period']['end']}

        DADOS DA LOJA:
        {$storeDataJson}

        SUGESTÃO EM DISCUSSÃO:
        - Título: {$suggestion['title']}
        - Categoria: {$categoryLabel}
        - Impacto esperado: {$impactLabel}
        - Descrição: {$suggestion['description']}
        - Ação recomendada: {$recommendedAction}

        INSTRUÇÃO ESPECIAL PARA PRIMEIRA RESPOSTA:
        Na sua PRIMEIRA resposta, você DEVE:
        1. Começar com: "Oi, Ecommpilot assistente aqui. Já entendi essa sugestão feita pela análise. Com o que você precisa de ajuda? Aqui estão algumas idéias de como podemos trabalhar com essa sugestão:"
        2. Em seguida, listar EXATAMENTE 5 sugestões práticas e específicas de como o usuário pode trabalhar com essa sugestão
        3. As sugestões devem ser baseadas na categoria "{$categoryLabel}" e no contexto específico da sugestão
        4. Use formato de lista numerada (1. 2. 3. 4. 5.)
        5. Cada sugestão deve ser uma frase curta e acionável

        FORMATO DE RESPOSTA - USE MARKDOWN:
        - Use **negrito** para destacar pontos importantes
        - Use listas numeradas ou com bullet points
        - Seja conciso e direto

        REGRAS:
        - SEMPRE responda em português brasileiro
        - Seja prestativo, amigável e profissional
        - Foque em ajudar o usuário a implementar a sugestão
        - Ofereça passos práticos e acionáveis
        - Se o usuário pedir detalhes específicos, use os dados da loja para contextualizar
        - NÃO use emojis excessivamente
        PROMPT;
    }

    /**
     * Format the user message for suggestion discussion.
     */
    private function formatSuggestionUserMessage(array $suggestion, string $message): string
    {
        return "O usuário quer discutir a sugestão: \"{$suggestion['title']}\"\n\nMensagem do usuário: {$message}";
    }

    /**
     * Get translated category label.
     */
    private function getCategoryLabel(string $category): string
    {
        $labels = [
            'marketing' => 'Marketing',
            'pricing' => 'Precificação',
            'inventory' => 'Estoque',
            'product' => 'Produtos',
            'customer' => 'Clientes',
            'conversion' => 'Conversão',
            'coupon' => 'Cupons',
            'operational' => 'Operacional',
        ];

        return $labels[$category] ?? ucfirst($category);
    }

    /**
     * Get translated impact label.
     */
    private function getImpactLabel(string $impact): string
    {
        $labels = [
            'high' => 'Alto',
            'medium' => 'Médio',
            'low' => 'Baixo',
        ];

        return $labels[$impact] ?? ucfirst($impact);
    }

    /**
     * Format recommended action (can be string or array).
     */
    private function formatRecommendedAction(string|array $action): string
    {
        if (is_array($action)) {
            return implode('; ', $action);
        }

        return $action;
    }

    private function buildSystemPrompt(?object $store, ?Analysis $analysis, array $storeData, string $periodNotice = ''): string
    {
        $storeName = $store?->name ?? 'sua loja';

        $analysisContext = '';
        if ($analysis && $analysis->isCompleted()) {
            // Only include summary to reduce token usage
            $analysisContext = "\n\nÚLTIMA ANÁLISE (resumo): ".json_encode($analysis->summary ?? [], JSON_UNESCAPED_UNICODE);
        }

        $storeDataJson = json_encode($storeData, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um assistente de marketing para e-commerce, especializado em ajudar lojistas a aumentar suas vendas.
        Você trabalha para a plataforma Ecommpilot.

        CONTEXTO:
        Loja: {$storeName}
        Período: {$storeData['period']['start']} a {$storeData['period']['end']}
        {$analysisContext}

        DADOS DA LOJA (últimos {$storeData['period']['days']} dias):
        {$storeDataJson}

        LEGENDA: Dados diários: d=Data, r=Receita, p=Pedidos, t=Ticket médio | Produtos: n=Nome, q=Qtd vendida, r=Receita, e=Estoque | Clientes: n=Nome, p=Pedidos, t=Total gasto
        {$periodNotice}

        FORMATO DE RESPOSTA - USE MARKDOWN:
        Quando responder sobre vendas, receita, produtos ou métricas, formate SEMPRE usando Markdown:

        1. RESUMO EXECUTIVO: Inicie com um parágrafo resumindo os principais pontos
        2. USE TABELAS MARKDOWN para dados numéricos:
           | Data | Faturamento | Pedidos Pagos | Ticket Médio |
           |------|-------------|---------------|--------------|
           | 13/01/2026 | R$ 417,01 | 2 | R$ 208,51 |

        3. SEÇÕES COM HEADERS: Use ## para títulos de seção (Resumo Executivo, Dados Principais, Insights, Ações Recomendadas)
        4. LISTAS: Use - ou * para bullet points nos insights
        5. NÚMEROS EM DESTAQUE: Use **negrito** para valores importantes como **R$ 371.913,08**
        6. PERÍODO: Sempre mencione o período analisado no final

        REGRAS:
        - SEMPRE responda em português brasileiro
        - Seja prestativo, amigável e profissional
        - Use os dados reais fornecidos acima para criar tabelas e análises
        - SEMPRE inclua uma tabela com os dados diários quando perguntado sobre vendas/faturamento
        - Forneça insights baseados nos dados (tendências, anomalias, padrões)
        - Sugira ações concretas baseadas nos dados
        - NÃO use emojis excessivamente
        - Quando sugerir ações, seja específico para os dados da loja
        - Se perguntado sobre algo fora do escopo de e-commerce/marketing, redirecione educadamente

        CAPACIDADES:
        - Mostrar dados detalhados de vendas por dia com tabelas
        - Analisar performance de produtos
        - Identificar tendências e padrões
        - Calcular métricas como ticket médio, taxa de conversão
        - Sugerir ações baseadas nos dados
        - Explicar métricas e KPIs

        Agora, responda à mensagem do usuário de forma útil, personalizada e bem formatada usando Markdown.
        PROMPT;
    }

    private function getStoreData(?object $store, ?int $days = null): array
    {
        $days = $days ?? self::DEFAULT_PERIOD_DAYS;

        if (! $store) {
            \Log::warning('ChatbotService: No store provided');

            return $this->getEmptyStoreData($days);
        }

        try {
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subDays($days);

            // Get paid orders in period using query scope
            $orders = SyncedOrder::where('store_id', $store->id)
                ->whereBetween('external_created_at', [$startDate, $endDate])
                ->where('payment_status', PaymentStatus::Paid)
                ->get();

            // Calculate daily stats (simplified to reduce tokens)
            $dailyStats = [];
            foreach ($orders->groupBy(fn ($order) => $order->external_created_at->format('Y-m-d')) as $date => $dayOrders) {
                $revenue = $dayOrders->sum('total');
                $count = $dayOrders->count();
                $avgTicket = $count > 0 ? $revenue / $count : 0;
                $dailyStats[$date] = [
                    'd' => Carbon::parse($date)->format('d/m/Y'),
                    'r' => 'R$ '.number_format($revenue, 2, ',', '.'),
                    'p' => $count,
                    't' => 'R$ '.number_format($avgTicket, 2, ',', '.'),
                ];
            }

            // Sort by date descending
            krsort($dailyStats);

            // Calculate totals
            $totalRevenue = $orders->sum('total');
            $totalOrders = $orders->count();
            $averageTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

            // Get best selling products (top 5, simplified)
            $productSales = [];
            foreach ($orders as $order) {
                if (! is_array($order->items)) {
                    continue;
                }
                foreach ($order->items as $item) {
                    $productId = $item['product_id'] ?? null;
                    $productName = $item['name'] ?? 'Produto';
                    $quantity = $item['quantity'] ?? 1;
                    $price = $item['price'] ?? 0;

                    if (! isset($productSales[$productId])) {
                        $productSales[$productId] = ['n' => $productName, 'q' => 0, 'r' => 0];
                    }
                    $productSales[$productId]['q'] += $quantity;
                    $productSales[$productId]['r'] += $price * $quantity;
                }
            }

            // Sort by revenue and get top 5
            usort($productSales, fn ($a, $b) => $b['r'] <=> $a['r']);
            $topProducts = array_map(function ($p) {
                return ['n' => $p['n'], 'q' => $p['q'], 'r' => 'R$ '.number_format($p['r'], 2, ',', '.')];
            }, array_slice($productSales, 0, 5));

            // Get products with low stock (top 5)
            $lowStockProducts = SyncedProduct::where('store_id', $store->id)
                ->where('is_active', true)
                ->whereNotNull('stock_quantity')
                ->where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', 5)
                ->orderBy('stock_quantity')
                ->limit(5)
                ->get(['name', 'stock_quantity'])
                ->map(fn ($p) => ['n' => $p->name, 'e' => $p->stock_quantity])
                ->toArray();

            // Get top customers (top 5, simplified)
            $customerOrders = $orders->groupBy('customer_email')
                ->map(function ($customerOrders) {
                    return [
                        'n' => $customerOrders->first()->customer_name,
                        'p' => $customerOrders->count(),
                        't' => 'R$ '.number_format($customerOrders->sum('total'), 2, ',', '.'),
                    ];
                })
                ->sortByDesc(fn ($c) => $c['p'])
                ->take(5)
                ->values()
                ->toArray();

            return [
                'period' => [
                    'start' => $startDate->format('d/m/Y'),
                    'end' => $endDate->format('d/m/Y'),
                    'days' => $days,
                ],
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'R$ '.number_format($totalRevenue, 2, ',', '.'),
                    'total_orders' => $totalOrders,
                    'average_ticket' => $averageTicket,
                    'average_ticket_formatted' => 'R$ '.number_format($averageTicket, 2, ',', '.'),
                ],
                'daily_stats' => array_values($dailyStats),
                'top_products' => $topProducts,
                'low_stock_products' => $lowStockProducts,
                'top_customers' => $customerOrders,
            ];
        } catch (\Exception $e) {
            \Log::warning('Error fetching store data for chat: '.$e->getMessage());

            return $this->getEmptyStoreData($days);
        }
    }

    private function getEmptyStoreData(?int $days = null): array
    {
        $days = $days ?? self::DEFAULT_PERIOD_DAYS;
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($days);

        return [
            'period' => [
                'start' => $startDate->format('d/m/Y'),
                'end' => $endDate->format('d/m/Y'),
                'days' => $days,
            ],
            'summary' => [
                'total_revenue' => 0,
                'total_revenue_formatted' => 'R$ 0,00',
                'total_orders' => 0,
                'average_ticket' => 0,
                'average_ticket_formatted' => 'R$ 0,00',
            ],
            'daily_stats' => [],
            'top_products' => [],
            'low_stock_products' => [],
            'top_customers' => [],
        ];
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
