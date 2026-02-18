<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
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
        $store = $user->activeStore;
        $latestAnalysis = $this->getLatestAnalysis($user);
        $chatHistory = $conversation ? $this->getChatHistory($conversation) : [];

        // Check if this is a suggestion discussion context
        $isSuggestionContext = isset($context['type']) && $context['type'] === 'suggestion';

        // Detect if user requested a specific period
        $periodInfo = $this->detectRequestedPeriod($message);

        // Step 1: AI-powered intent extraction to determine what data to fetch
        $queries = $store ? $this->extractQueryIntents($message, $chatHistory) : [];

        // Step 2: Fetch enriched store data based on extracted intents
        $storeData = $store
            ? $this->contextBuilder->build($store, $queries, $periodInfo['days'], $message)
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
        if ($isSuggestionContext) {
            $message = $this->formatSuggestionUserMessage($context['suggestion'], $message);
        } elseif ($context) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $message = "Contexto adicional: {$contextStr}\n\nMensagem do usuário: {$message}";
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            return $this->aiManager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]);
        } catch (\Exception $e) {
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

        LEGENDA DOS DADOS:
        - summary: resumo (receita, pedidos, ticket médio) | daily_stats: d, r, p, t
        - top_products: n, q, r | products_catalog: n, p, e | product_search: n, p, e, sku, cat, promo, custo
        - products_by_category: n, p, e, cat | products_by_coupon: n, cupom, q, r | product_revenue: n, q, r, orders
        - new_products: n, p, e, cat, criado | discounted_products: n, p, de, desc, e | product_margins: n, p, custo, margem, lucro, e
        - stock: low_stock, out_of_stock_count | excess_stock: n, e, vendidos, p | slow_moving: count, products
        - order_status | recent_orders: numero, cliente, total, status, data | order_search: detalhes completos do pedido
        - high_value_orders: numero, cliente, total, itens, data | cancelled_orders: total, receita perdida, orders
        - payment_methods: metodo, pedidos, receita, ticket | orders_by_region: estado, cidade, pedidos, receita
        - shipping_analysis: frete médio, grátis, faixas | sales_by_weekday: dia, pedidos, receita, ticket
        - top_customers: n, p, t | repeat_customers: total, rate, customers | customer_details: perfil completo
        - coupons: resumo + ativos | coupon_details: detalhes | coupon_ranking: ranking por receita
        - analysis_summary | active_suggestions | knowledge_base | proactive_alerts

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

        LEGENDA DOS DADOS:
        PRODUTOS:
        - top_products: n=nome, q=quantidade vendida, r=receita
        - products_catalog: n=nome, p=preço, e=estoque
        - product_search: busca de produto (n=nome, p=preço, e=estoque, sku, cat=categorias, promo=preço original, custo)
        - products_by_category: produtos por categoria (n=nome, p=preço, e=estoque, cat=categorias)
        - products_by_coupon: n=nome produto, cupom=código do cupom, q=quantidade, r=receita
        - product_revenue: receita de produto (n=nome, q=qtd, r=receita, orders=nº pedidos)
        - new_products: produtos recém-adicionados (n=nome, p=preço, e=estoque, cat=categorias, criado=data)
        - discounted_products: em promoção (n=nome, p=preço atual, de=preço original, desc=% desconto, e=estoque)
        - product_margins: margem de lucro (n=nome, p=preço, custo, margem=%, lucro=valor, e=estoque)
        - product_abc: classificação ABC (summary com contagem A/B/C, products=[n, receita, categoria, pct])
        - price_analysis: distribuição de preços (min, max, media, mediana, ranges=faixas com contagem)

        ESTOQUE:
        - stock: low_stock=produtos com estoque baixo, out_of_stock_count=esgotados
        - excess_stock: excesso de estoque (n=nome, e=estoque, vendidos=qtd vendida no período, p=preço)
        - slow_moving: encalhados (count=total, products=[n=nome, e=estoque, p=preço])
        - stock_summary: resumo (total_products, total_value, avg_price, health=distribuição por saúde, out_of_stock)

        PEDIDOS:
        - summary: resumo do período (receita, pedidos, ticket médio)
        - daily_stats: d=data, r=receita, p=pedidos, t=ticket médio
        - order_status: status dos pedidos com contagem
        - recent_orders: últimos pedidos (numero, cliente, email, total, status, pagamento, data, itens)
        - order_search: pedido específico (numero, cliente, email, total, desconto, frete, status, pagamento, metodo, data, itens=[nome, qtd, preço])
        - high_value_orders: maiores pedidos (numero, cliente, total, itens_count, metodo, data)
        - cancelled_orders: cancelados/reembolsados (total_cancelled, total_refunded, lost_revenue, orders=[numero, cliente, total, status, data])
        - payment_methods: por forma de pagamento (metodo, pedidos, receita, ticket)
        - orders_by_region: por estado/cidade (estado, cidade, pedidos, receita)
        - shipping_analysis: frete (avg_shipping, free_shipping_count, free_shipping_pct, total_shipping, ranges=faixas de valor)
        - sales_by_weekday: vendas por dia da semana (dia, pedidos, receita, ticket)
        - pending_orders: não pagos (total, valor_perdido, orders=[numero, cliente, total, status, data])
        - best_selling_days: melhores dias (data, receita, pedidos, ticket)
        - sales_by_hour: por hora (hora, pedidos, receita)
        - discount_impact: impacto desconto (com_desconto vs sem_desconto: pedidos, receita, ticket, pct_desconto)
        - order_items_analysis: itens/pedido (media, max, distribuição, top_combos)

        CLIENTES:
        - top_customers: n=nome, p=nº pedidos, t=total gasto
        - repeat_customers: recorrentes (total_customers, repeat_count, repeat_rate=%, customers=[n, email, pedidos, total])
        - customer_orders: pedidos de cliente (client, email, total, status, date, items)
        - customer_details: perfil completo (n=nome, email, telefone, total_pedidos, total_gasto, ticket_medio, cliente_desde)
        - customer_segments: segmentos (vip/regular/ocasional: count, receita, ticket, pct)
        - new_vs_returning: novos vs recorrentes (new_count, returning_count, new_revenue, returning_revenue)

        CUPONS:
        - coupons: resumo + ativos (code, type, val, used, max, exp)
        - coupon_details: detalhes e estatísticas de cupons específicos
        - coupon_ranking: ranking por receita (code, receita, pedidos, ticket_medio, total_desconto, uses)

        ANÁLISE AI:
        - analysis_summary: health_score, health_status, main_insight, premium summary
        - active_suggestions: t=título, cat=categoria, imp=impacto, st=status
        - proactive_alerts: alertas (type, msg). Tipos: critical_stock, expiring_coupon, revenue_trend, unused_coupons
        - knowledge_base: strategies, benchmarks, relevant (resultados semânticos)
        - store_overview: KPIs completos (receita, pedidos, ticket, conversão, clientes, crescimento vs anterior)
        - period_comparison: comparação (current vs previous: receita, pedidos, ticket, clientes com % mudança)
        - revenue_by_category: receita por categoria (cat=nome, receita, pedidos, pct)

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
