<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private const DEFAULT_PERIOD_DAYS = 15;

    private const ALL_TIME_DAYS = 3650;

    public function __construct(
        private AIManager $aiManager,
        private ChatContextBuilder $contextBuilder,
    ) {}

    public function getResponse(
        User $user,
        ?ChatConversation $conversation,
        string $message,
        ?array $context = null
    ): string {
        $startTime = microtime(true);
        $store = $user->activeStore;
        $latestAnalysis = $this->getLatestAnalysis($user);
        $chatHistory = $conversation ? $this->getChatHistory($conversation) : [];

        // Check if this is a suggestion discussion context
        $isSuggestionContext = isset($context['type']) && $context['type'] === 'suggestion';
        $chatType = $isSuggestionContext ? 'suggestion' : 'general';

        // Detect if user requested a specific period
        $periodInfo = $this->detectRequestedPeriod($message);

        // Step 1: AI-powered intent extraction to determine what data to fetch
        $intentStartTime = microtime(true);
        $queries = $store ? $this->extractQueryIntents($message, $chatHistory) : [];
        $intentDuration = round((microtime(true) - $intentStartTime) * 1000);

        // Step 1.5: Keyword-based fallback when AI extraction returns empty
        if ($store && empty($queries)) {
            $queries = $this->fallbackQueryExtraction($message);
            if (! empty($queries)) {
                Log::channel('chat')->info('[CHAT] Fallback de keywords ativado', [
                    'message' => mb_substr($message, 0, 200),
                    'fallback_queries' => array_column($queries, 'type'),
                ]);
            }
        }

        // Step 2: Fetch enriched store data based on extracted intents (with caching)
        $storeData = $store
            ? $this->getCachedStoreData($store, $queries, $periodInfo['days'], $message)
            : $this->getEmptyStoreData($periodInfo['days']);

        // Step 2.5: Generate proactive insights (lightweight, always runs for general chat)
        if ($store && ! $isSuggestionContext) {
            try {
                $proactiveAlerts = $this->contextBuilder->generateProactiveInsights($store, $periodInfo['days']);
                if (! empty($proactiveAlerts)) {
                    $storeData['proactive_alerts'] = $proactiveAlerts;
                }
            } catch (\Exception $e) {
                Log::warning('ChatbotService: Proactive insights failed', ['error' => $e->getMessage()]);
            }
        }

        // Step 3: Build system prompt with enriched data and generate response
        if ($isSuggestionContext) {
            $systemPrompt = $this->buildSuggestionDiscussionPrompt($store, $storeData, $context['suggestion']);
        } else {
            $systemPrompt = $this->buildSystemPrompt($store, $latestAnalysis, $storeData, $queries);
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
        $originalMessage = $message;
        if ($isSuggestionContext) {
            $message = $this->formatSuggestionUserMessage($context['suggestion'], $message);
        } elseif ($context) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $message = "Contexto adicional: {$contextStr}\n\nMensagem do usuário: {$message}";
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        // Calculate estimated input tokens (system prompt + history + user message)
        $inputChars = mb_strlen($systemPrompt);
        foreach ($chatHistory as $msg) {
            $inputChars += mb_strlen($msg['content']);
        }
        $inputChars += mb_strlen($message);
        $estimatedInputTokens = (int) ceil($inputChars / 3);

        // Compute storeData keys present (for legend/data tracking)
        $storeDataKeys = array_diff(array_keys($storeData), ['period']);

        try {
            $responseStartTime = microtime(true);
            $response = $this->aiManager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]);
            $responseDuration = round((microtime(true) - $responseStartTime) * 1000);
            $totalDuration = round((microtime(true) - $startTime) * 1000);

            $estimatedOutputTokens = (int) ceil(mb_strlen($response) / 3);

            Log::channel('chat')->info('[CHAT] Mensagem processada', [
                'user_id' => $user->id,
                'store_id' => $store?->id,
                'conversation_id' => $conversation?->id,
                'chat_type' => $chatType,
                'user_message' => mb_substr($originalMessage, 0, 500),
                'queries_extracted' => array_column($queries, 'type'),
                'store_data_keys' => array_values($storeDataKeys),
                'store_data_size_bytes' => strlen(json_encode($storeData, JSON_UNESCAPED_UNICODE)),
                'system_prompt_chars' => mb_strlen($systemPrompt),
                'history_messages_count' => count($chatHistory),
                'estimated_input_tokens' => $estimatedInputTokens,
                'estimated_output_tokens' => $estimatedOutputTokens,
                'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
                'model_intent' => 'gemini-2.5-flash-lite',
                'model_response' => $this->getResponseModel(),
                'intent_extraction_ms' => $intentDuration,
                'response_generation_ms' => $responseDuration,
                'total_duration_ms' => $totalDuration,
                'period_days' => $periodInfo['days'],
            ]);

            return $response;
        } catch (\Exception $e) {
            $totalDuration = round((microtime(true) - $startTime) * 1000);

            Log::channel('chat')->error('[CHAT] Erro ao processar mensagem', [
                'user_id' => $user->id,
                'store_id' => $store?->id,
                'conversation_id' => $conversation?->id,
                'chat_type' => $chatType,
                'user_message' => mb_substr($originalMessage, 0, 500),
                'queries_extracted' => array_column($queries, 'type'),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'total_duration_ms' => $totalDuration,
            ]);

            Log::error('ChatbotService: AI response failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return 'Desculpe, ocorreu um erro inesperado ao processar sua mensagem. '
                .'Estamos trabalhando para resolver o mais rápido possível. '
                .'Por favor, tente novamente em alguns minutos.';
        }
    }

    /**
     * Use a lightweight AI call to extract structured query intents from the user message.
     * Returns an array of queries with type and params for ChatContextBuilder.
     */
    private function extractQueryIntents(string $message, array $chatHistory = []): array
    {
        $prompt = $this->buildIntentExtractionPrompt();

        try {
            $messages = [
                ['role' => 'system', 'content' => $prompt],
            ];

            // Add last 5 messages for multi-turn context (resolve pronouns/references)
            $recentHistory = array_slice($chatHistory, -5);
            foreach ($recentHistory as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => mb_substr($msg['content'], 0, 200),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $message];

            $response = $this->aiManager->chat($messages, [
                'temperature' => 0.1,
                'max_tokens' => 400,
                'model' => 'gemini-2.5-flash-lite',
            ]);

            $parsed = JsonExtractor::extract($response);

            if ($parsed && isset($parsed['queries']) && is_array($parsed['queries'])) {
                return $this->sanitizeQueries($parsed['queries']);
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('ChatbotService: Intent extraction failed, using empty queries', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Keyword-based fallback when AI intent extraction returns empty queries.
     * Maps common Portuguese keywords to query types without an API call.
     */
    private function fallbackQueryExtraction(string $message): array
    {
        $lower = mb_strtolower($message);
        $queries = [];

        $keywordMap = [
            'top_products' => ['mais vendido', 'mais vendidos', 'mais vende', 'ranking de produto', 'produto mais', 'vendas de produto', 'vendeu mais', 'vendem mais', 'top produto'],
            'products_catalog' => ['catálogo', 'catalogo', 'listar produto', 'meus produto', 'todos os produto', 'lista de produto'],
            'stock_status' => ['estoque baixo', 'esgotado', 'sem estoque', 'falta de estoque', 'acabando'],
            'recent_orders' => ['últimos pedidos', 'ultimos pedidos', 'pedidos recentes', 'pedidos de hoje', 'últimas vendas', 'ultimas vendas'],
            'top_customers' => ['melhores clientes', 'top clientes', 'clientes que mais', 'quem mais compra', 'quem mais comprou'],
            'coupon_ranking' => ['cupom', 'cupons', 'desconto', 'descontos', 'promoção', 'promoções'],
            'order_status' => ['status dos pedidos', 'status pedido', 'pedidos pagos', 'pedidos pendentes'],
            'payment_methods' => ['forma de pagamento', 'método de pagamento', 'pix', 'cartão de crédito', 'boleto'],
            'orders_by_region' => ['região', 'estado', 'cidade', 'onde vende', 'localização', 'geografia'],
            'repeat_customers' => ['clientes recorrentes', 'clientes fiéis', 'recompra', 'compraram mais de uma vez'],
            'analysis_summary' => ['saúde da loja', 'score', 'diagnóstico', 'health score'],
            'store_overview' => ['visão geral', 'overview', 'kpi', 'indicadores', 'como vai minha loja', 'como está minha loja'],
            'revenue_by_category' => ['receita por categoria', 'categoria mais', 'vendas por categoria'],
        ];

        foreach ($keywordMap as $queryType => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $queries[] = ['type' => $queryType, 'params' => []];
                    break;
                }
            }
        }

        // Broad fallback: if message mentions product/sales concepts but no specific match
        if (empty($queries) && preg_match('/\b(produto|vend[aeiou]|kit|receita|faturamento|pedido|cliente|compra)\b/iu', $lower)) {
            $queries[] = ['type' => 'top_products', 'params' => []];
            $queries[] = ['type' => 'products_catalog', 'params' => []];
        }

        // Enrich product-related queries: add products_catalog for broader context
        $queryTypes = array_column($queries, 'type');
        if (in_array('top_products', $queryTypes) && ! in_array('products_catalog', $queryTypes)) {
            $queries[] = ['type' => 'products_catalog', 'params' => []];
        }

        return $queries;
    }

    private function buildIntentExtractionPrompt(): string
    {
        return <<<'PROMPT'
        Você é um classificador de intenções para um chatbot de e-commerce.
        Dada a mensagem do usuário (e o histórico recente da conversa, se disponível), determine quais consultas ao banco de dados são necessárias.

        CONSULTAS DISPONÍVEIS:

        PRODUTOS:
        - top_products: {} → Produtos mais vendidos (ranking geral)
        - products_catalog: {} → Listar catálogo de produtos da loja
        - product_search: {"search": "nome ou SKU"} → Buscar produto específico por nome ou SKU com detalhes completos
        - products_by_category: {"category": "nome da categoria"} → Produtos filtrados por categoria
        - products_by_coupon: {"codes": ["CODIGO1", "CODIGO2"]} → Produtos vendidos com cupons específicos
        - revenue_by_product: {"product_name": "nome do produto"} → Receita de um produto específico
        - new_products: {} → Produtos adicionados recentemente ao catálogo
        - discounted_products: {} → Produtos em promoção (com desconto ativo)
        - product_margins: {} → Margem de lucro dos produtos (requer dados de custo cadastrados)
        - product_abc: {} → Classificação ABC dos produtos (quais geram 80% da receita)
        - price_analysis: {} → Análise de distribuição de preços dos produtos

        ESTOQUE:
        - stock_status: {} → Produtos com estoque baixo ou esgotado
        - excess_stock: {} → Produtos com EXCESSO de estoque (maior quantidade em estoque)
        - slow_moving_products: {} → Produtos encalhados (têm estoque mas não venderam no período)
        - stock_summary: {} → Resumo geral do estoque (valor total, saúde, distribuição)

        PEDIDOS:
        - order_status: {} → Breakdown de status dos pedidos
        - recent_orders: {} → Últimos pedidos realizados (mais recentes)
        - order_search: {"order_number": "12345"} → Buscar pedido específico por número
        - high_value_orders: {} → Pedidos com maior valor total
        - cancelled_orders: {} → Pedidos cancelados ou reembolsados
        - payment_methods: {} → Receita e pedidos por método de pagamento
        - orders_by_region: {} → Vendas por estado/cidade (distribuição geográfica)
        - shipping_analysis: {} → Análise de custos de frete (médio, grátis, por faixa de valor)
        - sales_by_weekday: {} → Vendas por dia da semana (qual dia vende mais)
        - pending_orders: {} → Pedidos não pagos/abandonados
        - best_selling_days: {} → Dias com maior faturamento (datas específicas)
        - sales_by_hour: {} → Vendas por horário do dia (qual hora vende mais)
        - discount_impact: {} → Impacto dos descontos (pedidos com vs sem desconto)
        - order_items_analysis: {} → Análise de itens por pedido (média, distribuição)

        CLIENTES:
        - top_customers: {} → Clientes que mais compram
        - repeat_customers: {} → Clientes recorrentes (compraram mais de uma vez)
        - customer_orders: {"name_or_email": "nome ou email"} → Pedidos de um cliente específico
        - customer_details: {"name_or_email": "nome ou email"} → Perfil completo de um cliente (total gasto, nº pedidos, desde quando)
        - customer_segments: {} → Segmentação de clientes por faixa de gasto (VIP, regular, ocasional)
        - new_vs_returning: {} → Clientes novos vs recorrentes no período

        CUPONS:
        - coupon_stats: {} → Estatísticas gerais de cupons/descontos
        - coupon_details: {"codes": ["CODIGO1"]} → Detalhes de cupons específicos
        - coupon_ranking: {} → Ranking dos cupons por receita gerada (qual cupom performa melhor)

        ANÁLISE AI:
        - analysis_summary: {} → Resumo da última análise AI (saúde da loja, score, insights)
        - active_suggestions: {} → Sugestões ativas/pendentes da análise (título, categoria, impacto)
        - knowledge_base: {} → Busca boas práticas e benchmarks do segmento na base de conhecimento
        - cross_domain_analysis: {} → Sinaliza que o usuário quer correlacionar dados de múltiplos domínios
        - store_overview: {} → Visão geral completa da loja (KPIs, taxa de conversão, crescimento vs período anterior)
        - period_comparison: {} → Comparação do período atual vs anterior (receita, pedidos, ticket, clientes)
        - revenue_by_category: {} → Receita por categoria de produto

        REGRAS GERAIS:
        - Se o usuário usar pronomes ("ele", "esse", "desse", "dela", "desse produto"), use o HISTÓRICO da conversa para resolver a referência e extrair o query correto com os parâmetros certos
        - Extraia códigos de cupom exatos mencionados (ex: "cupom PROMO10" → codes: ["PROMO10"])
        - Extraia nomes de produtos mencionados (ex: "shampoo loiro" → product_name: "shampoo loiro")
        - Extraia nomes/emails de clientes mencionados
        - Extraia nomes de categorias mencionadas (ex: "produtos de moda" → category: "moda")
        - Extraia números de pedido mencionados (ex: "pedido 12345" → order_number: "12345")
        - Se a pergunta não precisa de dados do banco, retorne queries vazio
        - Múltiplas queries são permitidas (ex: produtos de um cupom + detalhes do cupom)

        MAPEAMENTO DE PERGUNTAS:
        - Perguntas sobre vendas/faturamento geralmente precisam de top_products
        - Perguntas gerais como "como vai minha loja?" → top_products
        - Perguntas sobre "excesso de estoque", "estoque alto", "sobrando estoque" → excess_stock
        - Perguntas sobre "produtos encalhados", "sem venda", "não venderam", "estoque parado" → slow_moving_products
        - Perguntas sobre buscar/encontrar um produto específico → product_search
        - Perguntas sobre "promoção", "desconto", "liquidação", "produtos em oferta" → discounted_products
        - Perguntas sobre "produtos novos", "adicionei recentemente", "últimos produtos cadastrados" → new_products
        - Perguntas sobre "margem", "lucro", "custo", "rentabilidade", "lucratividade" → product_margins
        - Perguntas sobre "forma de pagamento", "método de pagamento", "pix", "cartão", "boleto" → payment_methods
        - Perguntas sobre "região", "estado", "cidade", "localização", "onde vendem mais", "geografia" → orders_by_region
        - Perguntas sobre "frete", "custo de envio", "frete grátis", "entrega" → shipping_analysis
        - Perguntas sobre "dia da semana", "qual dia vende mais", "melhor dia", "segunda", "sábado" → sales_by_weekday
        - Perguntas sobre "últimos pedidos", "pedidos recentes", "pedidos de hoje", "últimas vendas" → recent_orders
        - Perguntas sobre buscar um pedido específico por número → order_search
        - Perguntas sobre "maiores pedidos", "pedidos mais caros", "maiores compras" → high_value_orders
        - Perguntas sobre "cancelamentos", "reembolsos", "pedidos cancelados", "devoluções" → cancelled_orders
        - Perguntas sobre "clientes recorrentes", "clientes fiéis", "compraram mais de uma vez", "recompra" → repeat_customers
        - Perguntas sobre perfil/dados de um cliente específico → customer_details
        - Perguntas sobre "qual cupom", "melhor cupom", "cupom que mais vendeu", "ranking de cupons" → coupon_ranking
        - Perguntas sobre "categoria", "tipo de produto", "segmento de produtos" → products_by_category
        - Perguntas sobre "análise", "saúde da loja", "score", "diagnóstico", "relatório" → analysis_summary
        - Perguntas sobre "sugestões", "recomendações", "o que fazer", "melhorias", "próximos passos" → active_suggestions
        - Perguntas estratégicas ("como aumentar vendas?", "melhores práticas", "benchmark", "estratégia") → knowledge_base
        - Se o usuário quer correlacionar dados de diferentes áreas (ex: produto+cupom+campanha) → cross_domain_analysis (adicione junto com as outras queries relevantes)
        - Perguntas sobre "classificação ABC", "curva ABC", "quais produtos importam", "80/20", "pareto" → product_abc
        - Perguntas sobre "faixa de preço", "distribuição de preço", "preço médio dos produtos" → price_analysis
        - Perguntas sobre "valor do estoque", "saúde do estoque", "resumo do estoque" → stock_summary
        - Perguntas sobre "pedidos pendentes", "pedidos não pagos", "carrinho abandonado", "abandonos" → pending_orders
        - Perguntas sobre "melhor dia de vendas", "dia que mais vendeu", "pico de vendas" → best_selling_days
        - Perguntas sobre "horário", "hora que mais vende", "melhor horário", "pico de horário" → sales_by_hour
        - Perguntas sobre "impacto do desconto", "pedidos com desconto", "vale dar desconto" → discount_impact
        - Perguntas sobre "itens por pedido", "quantos produtos por pedido", "combos", "cross-sell" → order_items_analysis
        - Perguntas sobre "segmentação", "clientes VIP", "classificação de clientes", "perfil dos clientes" → customer_segments
        - Perguntas sobre "clientes novos", "novos vs recorrentes", "aquisição de clientes" → new_vs_returning
        - Perguntas sobre "visão geral", "overview", "resumo geral", "KPIs", "indicadores", "taxa de conversão" → store_overview
        - Perguntas sobre "comparação", "vs período anterior", "mês passado", "evolução", "crescimento" → period_comparison
        - Perguntas sobre "receita por categoria", "categoria mais lucrativa", "vendas por categoria" → revenue_by_category

        Responda APENAS com JSON válido, sem texto adicional:
        {"queries": [{"type": "nome_da_query", "params": {}}]}
        PROMPT;
    }

    /**
     * Sanitize and validate extracted queries.
     */
    private function sanitizeQueries(array $queries): array
    {
        $validTypes = [
            'top_products', 'products_catalog', 'product_search',
            'products_by_category', 'products_by_coupon', 'revenue_by_product',
            'new_products', 'discounted_products', 'product_margins',
            'stock_status', 'excess_stock', 'slow_moving_products',
            'order_status', 'recent_orders', 'order_search',
            'high_value_orders', 'cancelled_orders',
            'payment_methods', 'orders_by_region', 'shipping_analysis', 'sales_by_weekday',
            'top_customers', 'repeat_customers', 'customer_orders', 'customer_details',
            'coupon_stats', 'coupon_details', 'coupon_ranking',
            'analysis_summary', 'active_suggestions', 'knowledge_base', 'cross_domain_analysis',
            'product_abc', 'price_analysis', 'stock_summary',
            'pending_orders', 'best_selling_days', 'sales_by_hour', 'discount_impact', 'order_items_analysis',
            'customer_segments', 'new_vs_returning',
            'store_overview', 'period_comparison', 'revenue_by_category',
        ];

        $sanitized = [];
        foreach ($queries as $query) {
            if (! is_array($query) || ! isset($query['type'])) {
                continue;
            }

            if (! in_array($query['type'], $validTypes)) {
                continue;
            }

            $params = $query['params'] ?? [];
            if (! is_array($params)) {
                $params = [];
            }

            // Validate and sanitize params with allowlist per query type
            $sanitized[] = [
                'type' => $query['type'],
                'params' => $this->validateQueryParams($query['type'], $params),
            ];
        }

        return $sanitized;
    }

    /**
     * Validate and sanitize query parameters using allowlists per query type.
     */
    private function validateQueryParams(string $type, array $params): array
    {
        return match ($type) {
            'products_by_coupon', 'coupon_details' => [
                'codes' => $this->validateCouponCodes($params['codes'] ?? []),
            ],
            'customer_orders' => [
                'name_or_email' => $this->validateSearchString($params['name_or_email'] ?? ''),
            ],
            'revenue_by_product' => [
                'product_name' => $this->validateSearchString($params['product_name'] ?? ''),
            ],
            'product_search' => [
                'search' => $this->validateSearchString($params['search'] ?? ''),
            ],
            'products_by_category' => [
                'category' => $this->validateSearchString($params['category'] ?? ''),
            ],
            'order_search' => [
                'order_number' => $this->validateOrderNumber($params['order_number'] ?? ''),
            ],
            'customer_details' => [
                'name_or_email' => $this->validateSearchString($params['name_or_email'] ?? ''),
            ],
            default => [],
        };
    }

    /**
     * Validate coupon codes: only alphanumeric, hyphens, underscores.
     */
    private function validateCouponCodes(mixed $codes): array
    {
        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_filter(
            array_map(function ($c) {
                if (! is_string($c)) {
                    return null;
                }

                return preg_replace('/[^A-Z0-9\-_]/i', '', mb_substr($c, 0, 30));
            }, array_slice($codes, 0, 10)),
            fn ($c) => $c !== null && $c !== ''
        ));
    }

    /**
     * Validate search strings: alphanumeric, accented chars, basic punctuation.
     * Escapes LIKE metacharacters to prevent wildcard amplification.
     */
    private function validateSearchString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        // Allowlist: letters (including accented), numbers, spaces, @, ., -
        $clean = preg_replace('/[^a-zA-Z0-9áéíóúâêîôûãõçÁÉÍÓÚÂÊÎÔÛÃÕÇ@.\- ]/u', '', $value);

        // Escape LIKE metacharacters
        $clean = str_replace(['%', '_'], ['\\%', '\\_'], $clean);

        return mb_substr($clean, 0, 100);
    }

    /**
     * Validate order numbers: only alphanumeric, hyphens, #.
     */
    private function validateOrderNumber(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return preg_replace('/[^A-Z0-9\-#]/i', '', mb_substr($value, 0, 30));
    }

    private function detectRequestedPeriod(string $message): array
    {
        $days = self::DEFAULT_PERIOD_DAYS;
        $lower = mb_strtolower($message);

        // All-time patterns (check first — most specific intent)
        $allTimePatterns = [
            'desde o come', 'desde o início', 'desde o inicio', 'desde sempre',
            'todo o período', 'todo o periodo', 'todo período', 'todo periodo',
            'todo o tempo', 'todo tempo', 'histórico completo', 'historico completo',
            'todos os tempos', 'all time', 'desde que comecei',
            'desde a primeira', 'desde o primeiro', 'desde que abri',
            'desde que inaugurei', 'desde que lancei', 'desde que criei',
            'desde que existe', 'toda a história', 'toda a historia',
        ];

        foreach ($allTimePatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return ['days' => self::ALL_TIME_DAYS];
            }
        }

        // Specific period patterns (ordered from most to least specific)
        $patterns = [
            '/(\d+)\s*anos?/i' => fn ($m) => (int) $m[1] * 365,
            '/(\d+)\s*m[eê]s(?:es)?/i' => fn ($m) => (int) $m[1] * 30,
            '/(\d+)\s*semanas?/i' => fn ($m) => (int) $m[1] * 7,
            '/(\d+)\s*dias?/i' => fn ($m) => (int) $m[1],
            '/(?:último|ultimo|esse)\s*ano/i' => fn () => 365,
            '/(?:últimos|ultimos)\s*(\d+)\s*m[eê]s(?:es)?/i' => fn ($m) => (int) $m[1] * 30,
            '/(?:último|ultimo)\s*trimestre/i' => fn () => 90,
            '/(?:último|ultimo)\s*semestre/i' => fn () => 180,
            '/(?:último|ultimo)\s*m[eê]s/i' => fn () => 30,
            '/uma?\s*semana/i' => fn () => 7,
            '/duas?\s*semanas?/i' => fn () => 14,
            '/quinze(?:na)?/i' => fn () => 15,
        ];

        foreach ($patterns as $pattern => $extractor) {
            if (preg_match($pattern, $message, $matches)) {
                $days = max(1, $extractor($matches));
                break;
            }
        }

        return ['days' => $days];
    }

    /**
     * Build a specialized prompt for suggestion discussion.
     */
    private function buildSuggestionDiscussionPrompt(?object $store, array $storeData, array $suggestion): string
    {
        $storeName = $store?->name ?? 'sua loja';
        $storeNiche = $store?->niche ?? 'não definido';
        $storeNicheSubcategory = $store?->niche_subcategory ?? '';
        $nicheContext = $storeNicheSubcategory
            ? "Segmento/Nicho: {$storeNiche} — {$storeNicheSubcategory}"
            : "Segmento/Nicho: {$storeNiche}";
        $storeDataJson = json_encode($storeData, JSON_UNESCAPED_UNICODE);
        $legendSection = $this->buildDynamicLegend($storeData);

        $categoryLabel = $this->getCategoryLabel($suggestion['category'] ?? 'geral');
        $impactLabel = $this->getImpactLabel($suggestion['expected_impact'] ?? 'medium');
        $recommendedAction = $this->formatRecommendedAction($suggestion['recommended_action'] ?? '');

        return <<<PROMPT
        Você é um assistente de marketing para e-commerce, especializado em ajudar lojistas a implementar sugestões de melhoria.
        Você trabalha para a plataforma Ecommpilot.

        CONTEXTO DA LOJA:
        Loja: {$storeName}
        {$nicheContext}
        Período de dados: {$storeData['period']['start']} a {$storeData['period']['end']}

        DADOS DA LOJA:
        {$storeDataJson}

        {$legendSection}

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

        REGRA CRÍTICA - USO OBRIGATÓRIO DOS DADOS:
        - Você TEM acesso aos dados reais da loja (produtos, vendas, clientes, estoque, etc.) fornecidos acima em "DADOS DA LOJA"
        - SEMPRE use esses dados para responder perguntas do usuário sobre vendas, produtos, clientes, etc.
        - NUNCA diga que não tem acesso aos dados, que não pode consultar dados, ou que os dados não estão disponíveis
        - NUNCA sugira ao usuário consultar outra plataforma (Nuvemshop, painel administrativo, relatórios externos, etc.)
        - NUNCA diga "verifique no seu painel", "consulte os relatórios da Nuvemshop" ou variações
        - Se os dados solicitados não estiverem presentes em "DADOS DA LOJA", diga: "No momento não tenho esses dados específicos carregados. Posso te ajudar com informações sobre [liste categorias disponíveis nos dados]."
        - Quando tiver dados parciais, use o que tem e informe que está mostrando os dados disponíveis

        RESTRIÇÃO DE ESCOPO - BLOQUEIO OBRIGATÓRIO:
        Você SÓ pode responder sobre assuntos diretamente relacionados à loja "{$storeName}", seu segmento "{$storeNiche}" e à sugestão em discussão.
        - NÃO responda sobre assuntos fora de e-commerce (receitas, carros, programação, política, etc.)
        - NÃO responda sobre como a plataforma Ecommpilot funciona internamente
        - NÃO dê estratégias para segmentos/nichos diferentes de "{$storeNiche}"
        - Se a pergunta for fora do escopo, responda: "Desculpe, só posso ajudar com assuntos relacionados à sua loja **{$storeName}** e à sugestão em discussão. Como posso te ajudar com essa sugestão?"
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

    /**
     * Build legend entries only for data keys actually present in storeData.
     * Reduces system prompt token usage by excluding irrelevant explanations.
     */
    private function buildDynamicLegend(array $storeData): string
    {
        $legendMap = [
            'summary' => 'summary: resumo (receita, pedidos, ticket médio)',
            'daily_stats' => 'daily_stats: d=data, r=receita, p=pedidos, t=ticket médio',
            'top_products' => 'top_products: n=nome, q=quantidade vendida, r=receita',
            'products_catalog' => 'products_catalog: n=nome, p=preço, e=estoque',
            'product_search' => 'product_search: n=nome, p=preço, e=estoque, sku, cat=categorias, promo=preço original, custo',
            'products_by_category' => 'products_by_category: n=nome, p=preço, e=estoque, cat=categorias',
            'products_by_coupon' => 'products_by_coupon: n=nome produto, cupom=código do cupom, q=quantidade, r=receita',
            'product_revenue' => 'product_revenue: n=nome, q=qtd, r=receita, orders=nº pedidos',
            'new_products' => 'new_products: n=nome, p=preço, e=estoque, cat=categorias, criado=data',
            'discounted_products' => 'discounted_products: n=nome, p=preço atual, de=preço original, desc=% desconto, e=estoque',
            'product_margins' => 'product_margins: n=nome, p=preço, custo, margem=%, lucro=valor, e=estoque',
            'product_abc' => 'product_abc: classificação ABC (summary com contagem A/B/C, products=[n, receita, categoria, pct])',
            'price_analysis' => 'price_analysis: distribuição de preços (min, max, media, mediana, ranges=faixas com contagem)',
            'stock' => 'stock: low_stock=produtos com estoque baixo, out_of_stock_count=esgotados',
            'excess_stock' => 'excess_stock: excesso de estoque (n=nome, e=estoque, vendidos=qtd vendida, p=preço)',
            'slow_moving' => 'slow_moving: encalhados (count=total, products=[n=nome, e=estoque, p=preço])',
            'stock_summary' => 'stock_summary: resumo (total_products, total_value, avg_price, health=distribuição, out_of_stock)',
            'order_status' => 'order_status: status dos pedidos com contagem',
            'recent_orders' => 'recent_orders: últimos pedidos (numero, cliente, email, total, status, pagamento, data, itens)',
            'order_search' => 'order_search: pedido específico (numero, cliente, email, total, desconto, frete, status, metodo, data, itens)',
            'high_value_orders' => 'high_value_orders: maiores pedidos (numero, cliente, total, itens, metodo, data)',
            'cancelled_orders' => 'cancelled_orders: cancelados/reembolsados (total, receita perdida, orders)',
            'payment_methods' => 'payment_methods: por forma de pagamento (metodo, pedidos, receita, ticket)',
            'orders_by_region' => 'orders_by_region: por estado/cidade (estado, cidade, pedidos, receita)',
            'shipping_analysis' => 'shipping_analysis: frete (avg_shipping, grátis, faixas de valor)',
            'sales_by_weekday' => 'sales_by_weekday: vendas por dia da semana (dia, pedidos, receita, ticket)',
            'pending_orders' => 'pending_orders: não pagos (total, valor_perdido, orders)',
            'best_selling_days' => 'best_selling_days: melhores dias (data, receita, pedidos, ticket)',
            'sales_by_hour' => 'sales_by_hour: por hora (hora, pedidos, receita)',
            'discount_impact' => 'discount_impact: impacto desconto (com_desconto vs sem_desconto: pedidos, receita, ticket)',
            'order_items_analysis' => 'order_items_analysis: itens/pedido (media, max, distribuição)',
            'top_customers' => 'top_customers: n=nome, p=nº pedidos, t=total gasto',
            'repeat_customers' => 'repeat_customers: recorrentes (total, rate=%, customers)',
            'customer_orders' => 'customer_orders: pedidos de cliente (client, email, total, status, date)',
            'customer_details' => 'customer_details: perfil completo (n, email, telefone, total_pedidos, total_gasto, ticket_medio)',
            'customer_segments' => 'customer_segments: segmentos (vip/regular/ocasional: count, receita, ticket, pct)',
            'new_vs_returning' => 'new_vs_returning: novos vs recorrentes (new_count, returning_count, revenue)',
            'coupons' => 'coupons: resumo + ativos (code, type, val, used, max, exp)',
            'coupon_details' => 'coupon_details: detalhes e estatísticas de cupons específicos',
            'coupon_ranking' => 'coupon_ranking: ranking por receita (code, receita, pedidos, ticket_medio)',
            'analysis_summary' => 'analysis_summary: health_score, health_status, main_insight, premium summary',
            'active_suggestions' => 'active_suggestions: t=título, cat=categoria, imp=impacto, st=status',
            'proactive_alerts' => 'proactive_alerts: alertas (type, msg). Tipos: critical_stock, expiring_coupon, revenue_trend, unused_coupons',
            'knowledge_base' => 'knowledge_base: strategies, benchmarks, relevant (resultados semânticos)',
            'store_overview' => 'store_overview: KPIs completos (receita, pedidos, ticket, conversão, clientes, crescimento)',
            'period_comparison' => 'period_comparison: comparação (current vs previous com % mudança)',
            'revenue_by_category' => 'revenue_by_category: receita por categoria (cat, receita, pedidos, pct)',
        ];

        $lines = [];
        foreach ($legendMap as $key => $description) {
            if (array_key_exists($key, $storeData)) {
                $lines[] = "- {$description}";
            }
        }

        if (empty($lines)) {
            return 'LEGENDA: summary=resumo, daily_stats: d=data, r=receita, p=pedidos, t=ticket';
        }

        return "LEGENDA DOS DADOS:\n".implode("\n", $lines);
    }

    private function buildSystemPrompt(?object $store, ?Analysis $analysis, array $storeData, array $queries = []): string
    {
        $storeName = $store?->name ?? 'sua loja';
        $storeNiche = $store?->niche ?? 'não definido';
        $storeNicheSubcategory = $store?->niche_subcategory ?? '';
        $nicheContext = $storeNicheSubcategory
            ? "Segmento/Nicho: {$storeNiche} — {$storeNicheSubcategory}"
            : "Segmento/Nicho: {$storeNiche}";

        // Build cross-domain correlation instruction if detected
        $crossDomainInstruction = '';
        $queryTypes = array_column($queries, 'type');
        if (in_array('cross_domain_analysis', $queryTypes)) {
            $crossDomainInstruction = <<<'CROSS'


        INSTRUÇÃO DE CORRELAÇÃO CROSS-DOMAIN:
        Os dados acima vêm de múltiplos domínios. Você DEVE:
        1. Identificar relações entre os datasets (ex: produto mais vendido + cupom mais usado)
        2. Sugerir combinações estratégicas (ex: "aplique o cupom X no produto Y para amplificar vendas")
        3. Quantificar o potencial de cada correlação usando os dados disponíveis
        4. Propor uma campanha ou ação concreta que combine os dados correlacionados
        CROSS;
        }

        $analysisContext = '';
        if ($analysis && $analysis->isCompleted()) {
            // Only include summary to reduce token usage
            $analysisContext = "\n\nÚLTIMA ANÁLISE (resumo): ".json_encode($analysis->summary ?? [], JSON_UNESCAPED_UNICODE);
        }

        $storeDataJson = json_encode($storeData, JSON_UNESCAPED_UNICODE);
        $legendSection = $this->buildDynamicLegend($storeData);

        return <<<PROMPT
        Você é um assistente de marketing para e-commerce, especializado em ajudar lojistas a aumentar suas vendas.
        Você trabalha para a plataforma Ecommpilot.

        CONTEXTO:
        Loja: {$storeName}
        {$nicheContext}
        Período: {$storeData['period']['start']} a {$storeData['period']['end']}
        {$analysisContext}

        DADOS DA LOJA (últimos {$storeData['period']['days']} dias):
        {$storeDataJson}

        {$legendSection}

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
        - Use os dados reais fornecidos acima para criar tabelas e análises com NOMES REAIS dos produtos
        - SEMPRE inclua uma tabela com os dados diários quando perguntado sobre vendas/faturamento
        - Quando dados específicos estiverem presentes (top_products, products_by_coupon, coupons, etc.), USE-OS obrigatoriamente na resposta
        - Se products_by_coupon estiver presente, mostre uma tabela com os produtos vendidos com cada cupom
        - Forneça insights baseados nos dados (tendências, anomalias, padrões)
        - Sugira ações concretas baseadas nos dados
        - NÃO use emojis excessivamente
        - Quando sugerir ações, seja específico para os dados da loja
        - Se proactive_alerts estiver presente e a pergunta for genérica (ex: "como vai minha loja?", "alguma novidade?"), mencione os alertas proativos naturalmente na resposta
        - NÃO force alertas proativos quando a pergunta é específica e não tem relação com os alertas
        - Se knowledge_base estiver presente, use benchmarks e estratégias para enriquecer suas recomendações com referências do setor
        - Quando tiver benchmarks, compare métricas da loja com médias do setor (ex: "seu ticket médio está acima da média do setor")

        REGRA CRÍTICA - USO OBRIGATÓRIO DOS DADOS:
        - Você TEM acesso aos dados reais da loja fornecidos acima em "DADOS DA LOJA"
        - SEMPRE use esses dados para responder perguntas do usuário
        - NUNCA diga que não tem acesso aos dados, que não pode consultar dados, ou que os dados não estão disponíveis
        - NUNCA sugira ao usuário consultar outra plataforma (Nuvemshop, painel administrativo, relatórios externos, etc.)
        - NUNCA diga "verifique no seu painel", "consulte os relatórios da Nuvemshop" ou variações
        - Se os dados solicitados não estiverem presentes em "DADOS DA LOJA", diga: "No momento não tenho esses dados específicos carregados. Posso te ajudar com informações sobre [liste categorias disponíveis nos dados]."
        - Quando tiver dados parciais, use o que tem e informe que está mostrando os dados disponíveis

        RESTRIÇÃO DE ESCOPO - BLOQUEIO OBRIGATÓRIO:
        Você SÓ pode responder sobre assuntos diretamente relacionados à loja "{$storeName}" e seu segmento "{$storeNiche}".

        PERMITIDO (responda normalmente):
        - Vendas, receita, faturamento, pedidos, ticket médio da loja
        - Produtos, catálogo, estoque, preços da loja
        - Cupons, descontos, promoções da loja
        - Clientes, compradores da loja
        - Estratégias de marketing e vendas PARA O SEGMENTO da loja ({$storeNiche})
        - Métricas, KPIs, tendências de e-commerce relevantes ao segmento
        - Dicas de precificação, conversão, retenção para o nicho da loja

        BLOQUEADO (recuse educadamente):
        - Assuntos que NÃO são sobre e-commerce (receitas, carros, programação, política, etc.)
        - Perguntas sobre como a plataforma Ecommpilot funciona internamente (código, telas, arquitetura)
        - Estratégias para segmentos/nichos DIFERENTES do segmento da loja ativa
          Exemplo: se a loja é de roupas, NÃO responda sobre estratégias de venda de shampoos ou cosméticos
        - Qualquer conteúdo que não ajude o lojista a operar/melhorar SUA loja específica

        Quando a pergunta for BLOQUEADA, responda com:
        "Desculpe, só posso ajudar com assuntos relacionados à sua loja **{$storeName}** e ao segmento de **{$storeNiche}**. Posso te ajudar com vendas, produtos, cupons, estoque, clientes ou estratégias de marketing para o seu nicho. Como posso te ajudar?"

        CAPACIDADES:
        PRODUTOS: Ranking de mais vendidos, busca por nome/SKU/categoria, produtos novos no catálogo, produtos em promoção, margem de lucro, receita por produto, classificação ABC (curva 80/20), análise de distribuição de preços
        ESTOQUE: Estoque baixo/esgotado, excesso de estoque vs vendas, produtos encalhados sem vendas, resumo geral com valor do estoque e saúde
        PEDIDOS: Vendas diárias, últimos pedidos, busca por nº do pedido, maiores pedidos, cancelamentos/reembolsos, por forma de pagamento, por região, análise de frete, vendas por dia da semana, pedidos pendentes/abandonados, melhores dias de vendas, vendas por horário, impacto de descontos, análise de itens por pedido
        CLIENTES: Top compradores, clientes recorrentes e taxa de recompra, perfil completo de cliente, pedidos de cliente específico, segmentação (VIP/regular/ocasional), novos vs recorrentes
        CUPONS: Estatísticas gerais, detalhes de cupom específico, ranking de cupons por receita
        ANÁLISE AI: Health score, sugestões ativas, benchmarks do setor, alertas proativos (estoque crítico, cupons expirando, tendência de receita), visão geral com KPIs e taxa de conversão, comparação de períodos, receita por categoria
        CORRELAÇÃO: Combinar dados de múltiplas áreas (produto + cupom + cliente) para insights cruzados
        {$crossDomainInstruction}

        Agora, responda à mensagem do usuário de forma útil, personalizada e bem formatada usando Markdown.
        PROMPT;
    }

    /**
     * Get store data with caching to reduce DB queries.
     * Queries with search params bypass cache (user-specific results).
     * TTL: 5 minutes (matching DashboardService pattern).
     */
    private function getCachedStoreData(object $store, array $queries, int $days, ?string $message): array
    {
        // Queries with non-empty params should NOT be cached (user-specific)
        foreach ($queries as $query) {
            $params = $query['params'] ?? [];
            if (! empty(array_filter($params, fn ($v) => $v !== '' && $v !== [] && $v !== null))) {
                return $this->contextBuilder->build($store, $queries, $days, $message);
            }
        }

        // Sort query types for consistent cache keys
        $queryTypes = array_column($queries, 'type');
        sort($queryTypes);
        $queriesHash = md5(json_encode($queryTypes));

        $cacheKey = "chat_context:{$store->id}:{$queriesHash}:{$days}";

        return Cache::remember($cacheKey, 300, function () use ($store, $queries, $days, $message) {
            return $this->contextBuilder->build($store, $queries, $days, $message);
        });
    }

    private function getEmptyStoreData(?int $days = null): array
    {
        $days = $days ?? self::DEFAULT_PERIOD_DAYS;
        $endDate = now();
        $startDate = now()->subDays($days);

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
        ];
    }

    private function getLatestAnalysis(User $user): ?Analysis
    {
        return Analysis::where('user_id', $user->id)
            ->completed()
            ->latest()
            ->first();
    }

    private function getChatHistory(ChatConversation $conversation, int $limit = 5): array
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

    /**
     * Get the model name used for the main response call.
     */
    private function getResponseModel(): string
    {
        try {
            return $this->aiManager->provider()->getName() === 'gemini'
                ? (app(\App\Models\SystemSetting::class)::get('ai.gemini.model') ?? 'gemini-2.5-flash')
                : $this->aiManager->provider()->getName();
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
