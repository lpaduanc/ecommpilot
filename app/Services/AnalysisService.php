<?php

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;
use App\Services\AI\AIManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalysisService
{
    private const RATE_LIMIT_MINUTES = 60;

    private EcommerceKnowledgeBase $knowledgeBase;

    public function __construct(
        private AIManager $aiManager
    ) {
        $this->knowledgeBase = new EcommerceKnowledgeBase;
    }

    public function canRequestAnalysis(User $user, ?Store $store = null): bool
    {
        $storeId = $store?->id ?? $user->active_store_id;

        if (! $storeId) {
            return false;
        }

        // Get last SUCCESSFUL or PROCESSING analysis (ignore failed ones for rate limit)
        $lastAnalysis = Analysis::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->whereIn('status', [AnalysisStatus::Completed, AnalysisStatus::Processing, AnalysisStatus::Pending])
            ->latest()
            ->first();

        if (! $lastAnalysis) {
            return true;
        }

        return $lastAnalysis->created_at->addMinutes(self::RATE_LIMIT_MINUTES)->isPast();
    }

    public function getNextAvailableAt(User $user, ?Store $store = null): ?Carbon
    {
        $storeId = $store?->id ?? $user->active_store_id;

        if (! $storeId) {
            return null;
        }

        // Get last SUCCESSFUL or PROCESSING analysis (ignore failed ones)
        $lastAnalysis = Analysis::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->whereIn('status', [AnalysisStatus::Completed, AnalysisStatus::Processing, AnalysisStatus::Pending])
            ->latest()
            ->first();

        if (! $lastAnalysis) {
            return null;
        }

        $nextAvailable = $lastAnalysis->created_at->addMinutes(self::RATE_LIMIT_MINUTES);

        return $nextAvailable->isFuture() ? $nextAvailable : null;
    }

    public function getPendingAnalysis(User $user, ?Store $store = null): ?Analysis
    {
        $storeId = $store?->id ?? $user->active_store_id;

        if (! $storeId) {
            return null;
        }

        return Analysis::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->whereIn('status', [AnalysisStatus::Pending, AnalysisStatus::Processing])
            ->latest()
            ->first();
    }

    public function processAnalysis(Analysis $analysis): void
    {
        $analysis->markAsProcessing();

        try {
            $store = $analysis->store;
            $storeData = $this->prepareStoreData($store, $analysis->period_start, $analysis->period_end);

            // RAG: Get relevant strategies from knowledge base
            $relevantStrategies = $this->knowledgeBase->getRelevantStrategies($storeData);
            $strategiesContext = $this->knowledgeBase->formatForPrompt($relevantStrategies);

            Log::info('RAG strategies selected', [
                'count' => count($relevantStrategies),
                'categories' => array_column($relevantStrategies, 'category'),
            ]);

            $prompt = $this->buildAnalysisPrompt($storeData, $analysis->period_start, $analysis->period_end, $strategiesContext);

            $messages = [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ];

            $content = $this->aiManager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 8192,
            ]);

            Log::info('AI response received', ['length' => strlen($content)]);
            $data = $this->parseResponse($content);

            $analysis->markAsCompleted($data);
        } catch (\Exception $e) {
            $analysis->markAsFailed();
            throw $e;
        }
    }

    private function prepareStoreData(Store $store, Carbon $startDate, Carbon $endDate): array
    {
        $orders = $store->orders()
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->get();

        $products = $store->products()->active()->get();
        $customers = $store->customers()->get();

        $paidOrders = $orders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');
        $totalRevenue = $paidOrders->sum('total');
        $averageTicket = $paidOrders->count() > 0 ? $totalRevenue / $paidOrders->count() : 0;

        return [
            'store' => [
                'name' => $store->name,
            ],
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate),
            ],
            'metrics' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_orders' => $orders->count(),
                'paid_orders' => $paidOrders->count(),
                'average_ticket' => round($averageTicket, 2),
                'total_products' => $products->count(),
                'total_customers' => $customers->count(),
            ],
            'trends' => $this->calculateTrends($store, $startDate, $endDate),
            'product_performance' => $this->getProductPerformance($store, $orders),
            'customer_insights' => $this->getCustomerInsights($customers),
            'inventory_alerts' => $this->getInventoryAlerts($products),
            'order_patterns' => $this->getOrderPatterns($orders),
        ];
    }

    /**
     * Calculate trends comparing current period vs previous period
     */
    private function calculateTrends(Store $store, Carbon $startDate, Carbon $endDate): array
    {
        $periodDays = $startDate->diffInDays($endDate);
        $prevStart = $startDate->copy()->subDays($periodDays + 1);
        $prevEnd = $startDate->copy()->subDay();

        // Current period orders
        $currentOrders = $store->orders()
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->get();

        // Previous period orders
        $previousOrders = $store->orders()
            ->whereBetween('external_created_at', [$prevStart, $prevEnd])
            ->get();

        $currentPaid = $currentOrders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');
        $previousPaid = $previousOrders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

        $currentRevenue = $currentPaid->sum('total');
        $previousRevenue = $previousPaid->sum('total');

        $currentTicket = $currentPaid->count() > 0 ? $currentRevenue / $currentPaid->count() : 0;
        $previousTicket = $previousPaid->count() > 0 ? $previousRevenue / $previousPaid->count() : 0;

        return [
            'revenue' => [
                'current' => round($currentRevenue, 2),
                'previous' => round($previousRevenue, 2),
                'change_percent' => $this->calculateChangePercent($currentRevenue, $previousRevenue),
                'trend' => $this->getTrendLabel($currentRevenue, $previousRevenue),
            ],
            'orders' => [
                'current' => $currentOrders->count(),
                'previous' => $previousOrders->count(),
                'change_percent' => $this->calculateChangePercent($currentOrders->count(), $previousOrders->count()),
            ],
            'average_ticket' => [
                'current' => round($currentTicket, 2),
                'previous' => round($previousTicket, 2),
                'change_percent' => $this->calculateChangePercent($currentTicket, $previousTicket),
            ],
            'comparison_period' => $prevStart->format('d/m/Y').' a '.$prevEnd->format('d/m/Y'),
        ];
    }

    /**
     * Get product performance based on actual sales
     */
    private function getProductPerformance(Store $store, $orders): array
    {
        $paidOrders = $orders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

        $productSales = [];

        foreach ($paidOrders as $order) {
            $items = $order->items ?? [];
            foreach ($items as $item) {
                $productName = $item['product_name'] ?? $item['name'] ?? 'Produto';
                $quantity = $item['quantity'] ?? 1;
                $price = $item['unit_price'] ?? $item['price'] ?? 0;
                $total = $item['total'] ?? $price * $quantity;

                if (! isset($productSales[$productName])) {
                    $productSales[$productName] = [
                        'name' => $productName,
                        'units_sold' => 0,
                        'revenue' => 0,
                        'orders_count' => 0,
                    ];
                }

                $productSales[$productName]['units_sold'] += $quantity;
                $productSales[$productName]['revenue'] += $total;
                $productSales[$productName]['orders_count']++;
            }
        }

        // Sort by revenue descending
        usort($productSales, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $topSellers = array_slice($productSales, 0, 10);

        // Format revenue
        foreach ($topSellers as &$product) {
            $product['revenue'] = round($product['revenue'], 2);
        }

        // Get products with stock but no sales in period
        $soldProductNames = array_column($productSales, 'name');
        $products = $store->products()->active()->get();

        $noSalesProducts = $products->filter(function ($p) use ($soldProductNames) {
            return ! in_array($p->name, $soldProductNames) && $p->stock_quantity > 0;
        })->sortByDesc('price')->take(10)->map(fn ($p) => [
            'name' => $p->name,
            'price' => round($p->price, 2),
            'stock' => $p->stock_quantity,
            'potential_revenue' => round($p->price * $p->stock_quantity, 2),
        ])->values()->toArray();

        return [
            'top_sellers' => $topSellers,
            'no_sales_products' => $noSalesProducts,
            'total_products_sold' => count($productSales),
            'total_units_sold' => array_sum(array_column($productSales, 'units_sold')),
        ];
    }

    /**
     * Get customer behavior insights and segmentation
     */
    private function getCustomerInsights($customers): array
    {
        $totalCustomers = $customers->count();

        if ($totalCustomers === 0) {
            return [
                'total_customers' => 0,
                'repeat_purchase_rate' => 0,
                'single_order_customers' => 0,
                'segments' => ['vip' => 0, 'regular' => 0, 'occasional' => 0],
                'average_customer_value' => 0,
                'top_customers' => [],
            ];
        }

        $customersWithMultipleOrders = $customers->filter(fn ($c) => $c->total_orders > 1)->count();
        $repeatRate = round(($customersWithMultipleOrders / $totalCustomers) * 100, 1);

        // Customer segmentation by total spent
        $segments = [
            'vip' => $customers->filter(fn ($c) => $c->total_spent >= 1000)->count(),
            'regular' => $customers->filter(fn ($c) => $c->total_spent >= 200 && $c->total_spent < 1000)->count(),
            'occasional' => $customers->filter(fn ($c) => $c->total_spent < 200)->count(),
        ];

        // Top customers by value
        $topCustomers = $customers->sortByDesc('total_spent')
            ->take(5)
            ->map(fn ($c) => [
                'orders' => $c->total_orders,
                'total_spent' => round($c->total_spent, 2),
                'average_order' => $c->total_orders > 0 ? round($c->total_spent / $c->total_orders, 2) : 0,
            ])->values()->toArray();

        return [
            'total_customers' => $totalCustomers,
            'repeat_purchase_rate' => $repeatRate,
            'single_order_customers' => $totalCustomers - $customersWithMultipleOrders,
            'segments' => $segments,
            'average_customer_value' => round($customers->avg('total_spent'), 2),
            'top_customers' => $topCustomers,
        ];
    }

    /**
     * Get detailed inventory alerts with product names
     */
    private function getInventoryAlerts($products): array
    {
        $totalProducts = $products->count();

        $outOfStockProducts = $products->filter(fn ($p) => $p->stock_quantity <= 0)
            ->sortByDesc('price')
            ->take(10)
            ->map(fn ($p) => [
                'name' => $p->name,
                'price' => round($p->price, 2),
            ])->values()->toArray();

        $lowStockProducts = $products->filter(fn ($p) => $p->stock_quantity > 0 && $p->stock_quantity < 10)
            ->sortBy('stock_quantity')
            ->take(10)
            ->map(fn ($p) => [
                'name' => $p->name,
                'stock' => $p->stock_quantity,
                'price' => round($p->price, 2),
            ])->values()->toArray();

        $outOfStockCount = $products->filter(fn ($p) => $p->stock_quantity <= 0)->count();
        $lowStockCount = $products->filter(fn ($p) => $p->stock_quantity > 0 && $p->stock_quantity < 10)->count();
        $healthyStockCount = $products->filter(fn ($p) => $p->stock_quantity >= 10)->count();

        return [
            'out_of_stock' => [
                'count' => $outOfStockCount,
                'products' => $outOfStockProducts,
            ],
            'low_stock' => [
                'count' => $lowStockCount,
                'products' => $lowStockProducts,
            ],
            'healthy_stock_count' => $healthyStockCount,
            'health_rate' => $totalProducts > 0 ? round(($healthyStockCount / $totalProducts) * 100, 1) : 0,
        ];
    }

    /**
     * Analyze order patterns and behaviors
     */
    private function getOrderPatterns($orders): array
    {
        $totalOrders = $orders->count();

        if ($totalOrders === 0) {
            return [
                'total_orders' => 0,
                'by_status' => [],
                'by_payment_status' => [],
                'cancellation_rate' => 0,
                'refund_rate' => 0,
                'average_shipping' => 0,
                'discount_usage_rate' => 0,
                'peak_day' => null,
            ];
        }

        // Orders by day of week
        $ordersByDay = $orders->groupBy(fn ($o) => $o->external_created_at?->dayOfWeek ?? 0)
            ->map->count()
            ->toArray();

        $peakDay = ! empty($ordersByDay) ? array_search(max($ordersByDay), $ordersByDay) : null;
        $dayNames = ['Domingo', 'Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

        // Status distribution
        $byStatus = $orders->groupBy(fn ($o) => $o->status?->value ?? $o->status ?? 'unknown')
            ->map->count()
            ->toArray();

        $byPaymentStatus = $orders->groupBy(fn ($o) => $o->payment_status?->value ?? $o->payment_status ?? 'unknown')
            ->map->count()
            ->toArray();

        // Calculate rates
        $cancelledCount = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) === 'cancelled')->count();
        $refundedCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'refunded')->count();
        $ordersWithDiscount = $orders->filter(fn ($o) => ($o->discount ?? 0) > 0)->count();

        return [
            'total_orders' => $totalOrders,
            'by_status' => $byStatus,
            'by_payment_status' => $byPaymentStatus,
            'cancellation_rate' => round(($cancelledCount / $totalOrders) * 100, 1),
            'refund_rate' => round(($refundedCount / $totalOrders) * 100, 1),
            'average_shipping' => round($orders->avg('shipping') ?? 0, 2),
            'discount_usage_rate' => round(($ordersWithDiscount / $totalOrders) * 100, 1),
            'peak_day' => $peakDay !== null ? $dayNames[$peakDay] : null,
            'orders_by_day' => $ordersByDay,
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculateChangePercent(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get trend label based on change percentage
     */
    private function getTrendLabel(float $current, float $previous): string
    {
        $change = $this->calculateChangePercent($current, $previous);

        return match (true) {
            $change > 20 => 'strong_growth',
            $change > 5 => 'growth',
            $change > -5 => 'stable',
            $change > -20 => 'decline',
            default => 'strong_decline',
        };
    }

    private function buildAnalysisPrompt(array $storeData, Carbon $startDate, Carbon $endDate, string $strategiesContext = ''): string
    {
        $dataJson = json_encode($storeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $periodDays = $startDate->diffInDays($endDate);

        // Extrair dados para contexto resumido
        $revenue = $storeData['metrics']['total_revenue'] ?? 0;
        $products = $storeData['metrics']['total_products'] ?? 0;
        $orders = $storeData['metrics']['total_orders'] ?? 0;
        $trends = $storeData['trends'] ?? [];
        $customerInsights = $storeData['customer_insights'] ?? [];
        $inventoryAlerts = $storeData['inventory_alerts'] ?? [];

        $storeSize = match (true) {
            $revenue > 50000 => 'grande (alto faturamento)',
            $revenue > 10000 => 'medio',
            default => 'pequeno/iniciante',
        };

        // Resumo de tendencias
        $revenueTrend = $trends['revenue']['trend'] ?? 'stable';
        $revenueChange = $trends['revenue']['change_percent'] ?? 0;
        $trendDescription = match ($revenueTrend) {
            'strong_growth' => "CRESCIMENTO FORTE (+{$revenueChange}%)",
            'growth' => "crescimento (+{$revenueChange}%)",
            'stable' => 'estavel',
            'decline' => "queda ({$revenueChange}%)",
            'strong_decline' => "QUEDA FORTE ({$revenueChange}%)",
            default => 'sem dados anteriores',
        };

        // Resumo de clientes
        $repeatRate = $customerInsights['repeat_purchase_rate'] ?? 0;
        $vipCount = $customerInsights['segments']['vip'] ?? 0;

        // Resumo de estoque
        $outOfStock = $inventoryAlerts['out_of_stock']['count'] ?? 0;
        $lowStock = $inventoryAlerts['low_stock']['count'] ?? 0;

        return <<<PROMPT
## CONTEXTO DA ANALISE

**Loja:** {$storeData['store']['name']}
**Porte:** {$storeSize}
**Periodo:** {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')} ({$periodDays} dias)

### METRICAS PRINCIPAIS
- Faturamento: R$ {$revenue} ({$trendDescription} vs periodo anterior)
- Pedidos: {$orders}
- Produtos ativos: {$products}

### SITUACAO ATUAL
- Tendencia de receita: {$trendDescription}
- Taxa de recompra: {$repeatRate}% (clientes que voltam a comprar)
- Clientes VIP (>R$1000): {$vipCount}
- Produtos sem estoque: {$outOfStock}
- Produtos com estoque baixo: {$lowStock}

{$strategiesContext}

## DADOS COMPLETOS DA LOJA

{$dataJson}

## SUA TAREFA

Analise os dados acima e forneca recomendacoes ESPECIFICAS para esta loja.

FOQUE ESPECIALMENTE EM:
1. **Tendencias**: Se houver queda, identifique possiveis causas. Se houver crescimento, como acelerar.
2. **Top Sellers**: Use os dados de "product_performance.top_sellers" para sugestoes de estoque e marketing
3. **Produtos parados**: "product_performance.no_sales_products" mostra produtos com estoque mas sem vendas - sugira acoes
4. **Retencao**: Se "repeat_purchase_rate" for baixo (<20%), sugira estrategias de fidelizacao
5. **Estoque critico**: Use "inventory_alerts" para alertar sobre produtos que precisam reposicao
6. **Padroes de pedidos**: Use "order_patterns" para identificar problemas (cancelamentos, reembolsos)

IMPORTANTE:
- Use os NOMES DOS PRODUTOS que aparecem nos dados
- Mencione os NUMEROS EXATOS (receita, quantidade, porcentagens)
- Calcule impactos baseado nos valores reais
- Compare com o periodo anterior quando relevante
- Se houver ESTRATEGIAS RECOMENDADAS acima, use-as como INSPIRACAO mas ADAPTE para os dados especificos desta loja

Forneca sua analise no formato JSON especificado nas instrucoes do sistema.
PROMPT;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Voce e um consultor senior de e-commerce com 15 anos de experiencia analisando lojas online brasileiras.

## PROCESSO DE ANALISE (siga estas etapas mentalmente)

1. DIAGNOSTICO: Analise os dados e identifique padroes
   - Verifique as TENDENCIAS em "trends" - a receita esta crescendo ou caindo?
   - Analise "product_performance.top_sellers" - quais produtos realmente vendem
   - Verifique "product_performance.no_sales_products" - produtos parados com estoque
   - Analise "customer_insights" - taxa de recompra e segmentacao
   - Verifique "inventory_alerts" - produtos sem estoque ou com estoque critico
   - Analise "order_patterns" - taxas de cancelamento, reembolso, dia de pico

2. PRIORIZACAO: Determine o que e mais urgente
   - Se tendencia de queda forte: PRIORIDADE MAXIMA - identificar causa
   - Produtos sem estoque que sao top sellers: PRIORIDADE ALTA
   - Taxa de recompra baixa (<15%): PRIORIDADE ALTA - problema de retencao
   - Alto indice de cancelamentos (>5%): PRIORIDADE ALTA
   - Produtos parados com estoque: PRIORIDADE MEDIA - capital parado

3. RECOMENDACOES: Crie sugestoes ESPECIFICAS e ACIONAVEIS
   - SEMPRE mencione produtos, numeros ou metricas ESPECIFICOS dos dados fornecidos
   - NAO de conselhos genericos - cada sugestao deve ser unica para ESTA loja
   - Use os dados de tendencia para justificar urgencia
   - Calcule impactos usando os valores reais dos dados

## EXEMPLOS DE SUGESTOES

### EXEMPLO RUIM (generico - NAO FACA ISSO):
{
  "title": "Melhore suas campanhas de marketing",
  "description": "Invista em marketing digital para atrair mais clientes",
  "expected_impact": "Aumento nas vendas"
}

### EXEMPLO BOM (especifico - FACA ASSIM):
{
  "title": "Reabastecer 'Camiseta Polo Azul' - produto esgotado",
  "description": "Este produto aparece nos top 10 por preco (R$ 89,90) mas esta com estoque zerado. Reponha estoque para capturar vendas perdidas.",
  "expected_impact": "Potencial de R$ 899 em vendas se vender 10 unidades"
}

### EXEMPLO RUIM (generico):
{
  "title": "Fidelize seus clientes",
  "description": "Crie programas de fidelidade para aumentar recorrencia"
}

### EXEMPLO BOM (especifico):
{
  "title": "Reduzir taxa de pedidos cancelados (atualmente em 15%)",
  "description": "Dos 120 pedidos do periodo, 18 foram cancelados. Isso representa R$ 2.700 em vendas perdidas. Investigue os motivos: prazo de entrega? Pagamento recusado?",
  "expected_impact": "Recuperar ate R$ 1.350 reduzindo cancelamentos pela metade"
}

## FORMATO DE RESPOSTA (JSON estrito)

{
  "summary": {
    "health_score": 0-100,
    "health_status": "Critico|Precisa Atencao|Bom|Excelente",
    "main_insight": "Uma frase de 1-2 linhas com a observacao mais importante sobre a loja"
  },
  "suggestions": [
    {
      "id": "sug1",
      "category": "marketing|pricing|inventory|product|customer|conversion",
      "priority": "high|medium|low",
      "title": "Titulo claro e especifico com dados da loja (max 80 chars)",
      "description": "Descricao detalhada mencionando produtos e numeros especificos dos dados (max 200 chars)",
      "expected_impact": "Impacto estimado em R$ ou % baseado nos dados (max 100 chars)",
      "action_steps": ["Passo 1 concreto", "Passo 2 concreto", "Passo 3 concreto"],
      "is_done": false
    }
  ],
  "alerts": [
    {
      "type": "danger|warning|info",
      "title": "Titulo do alerta",
      "message": "Descricao do problema com dados especificos da loja"
    }
  ],
  "opportunities": [
    {
      "title": "Oportunidade identificada nos dados",
      "potential_revenue": "R$ X.XXX",
      "description": "Como capturar esta oportunidade baseado nos dados da loja"
    }
  ]
}

## REGRAS CRITICAS

1. Responda APENAS com JSON valido, sem texto antes ou depois
2. NAO use markdown (sem ```)
3. Forneca EXATAMENTE 9 sugestoes com distribuicao balanceada:
   - 3 sugestoes com priority: "high"
   - 3 sugestoes com priority: "medium"
   - 3 sugestoes com priority: "low"
4. Forneca entre 1 e 3 alertas (apenas se houver problemas reais nos dados)
5. Forneca entre 1 e 3 oportunidades
6. CADA sugestao DEVE referenciar dados especificos fornecidos (nomes de produtos, valores, quantidades)
7. Se nao houver dados suficientes para uma categoria, NAO invente - pule essa sugestao
8. Calcule valores de impacto baseado nos dados reais (ex: se produto custa R$50 e tem 20 em estoque, potencial = R$1000)

Categorias validas: marketing, pricing, inventory, product, customer, conversion
Prioridades validas: high, medium, low
Tipos de alerta: danger (urgente), warning (atencao), info (informativo)
PROMPT;
    }

    private function parseResponse(string $content): array
    {
        // Check for truncated response (doesn't end with })
        $trimmedContent = trim($content);
        if (! str_ends_with($trimmedContent, '}') && ! str_ends_with($trimmedContent, '```')) {
            Log::error('AI response appears truncated', [
                'length' => strlen($content),
                'last_100_chars' => substr($content, -100),
            ]);
            throw new \RuntimeException(
                'AI response was truncated. The response did not complete. '.
                'This usually means the output token limit was reached.'
            );
        }

        // Step 1: Remove markdown code blocks
        $content = preg_replace('/```json\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/i', '', $content);
        $content = preg_replace('/^```\s*\n?/i', '', $content);

        // Step 2: Extract JSON object
        if (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        // Step 3: Clean control characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Step 4: Normalize whitespace
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/,\s*([}\]])/', '$1', $content);
        $content = trim($content);

        // Try to parse
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = json_last_error_msg();
            Log::error('JSON parse error', [
                'error' => $errorMessage,
                'content_length' => strlen($content),
            ]);

            throw new \RuntimeException("Failed to parse AI response: {$errorMessage}");
        }

        // Validate structure
        if (! isset($data['summary']) || ! isset($data['suggestions'])) {
            throw new \RuntimeException('AI response missing required fields (summary or suggestions)');
        }

        return $data;
    }
}
