<?php

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Memory\HistorySummaryService;
use App\Services\AI\ProductTableFormatter;
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

    /**
     * Check if rate limit should be skipped (local/dev environment).
     */
    private function shouldSkipRateLimit(): bool
    {
        $isDebug = config('app.debug');
        $env = config('app.env');
        $isLocal = app()->isLocal();

        // Debug log para verificar valores
        Log::debug('Rate limit check', [
            'app.debug' => $isDebug,
            'app.env' => $env,
            'isLocal' => $isLocal,
        ]);

        // Skip rate limit if debug mode is on and not production
        if ($isDebug && $env !== 'production') {
            return true;
        }

        return $isLocal || app()->environment('testing', 'dev', 'development');
    }

    public function canRequestAnalysis(User $user, ?Store $store = null): bool
    {
        // Skip rate limit in local/dev environment
        if ($this->shouldSkipRateLimit()) {
            return true;
        }

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
        // No cooldown in local/dev environment
        if ($this->shouldSkipRateLimit()) {
            return null;
        }

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

        // First, check for pending or processing analyses
        $pendingOrProcessing = Analysis::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->whereIn('status', [AnalysisStatus::Pending, AnalysisStatus::Processing])
            ->latest()
            ->first();

        if ($pendingOrProcessing) {
            return $pendingOrProcessing;
        }

        // If no pending/processing, check for recently failed analyses (last 10 minutes)
        // This allows the frontend to show the error message
        return Analysis::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->where('status', AnalysisStatus::Failed)
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->latest()
            ->first();
    }

    public function processAnalysis(Analysis $analysis): void
    {
        $analysis->markAsProcessing();

        try {
            $store = $analysis->store;
            $storeData = $this->prepareStoreData($store, $analysis->period_start, $analysis->period_end);

            // Adicionar store_id aos dados para o HistorySummaryService
            $storeData['store']['id'] = $store->id;

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

            // Aumentado max_tokens para suportar resposta completa
            $content = $this->aiManager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 16384,
            ]);

            Log::info('AI response received', ['length' => strlen($content)]);
            $data = $this->parseResponse($content);

            // Validar limites de tamanho dos campos
            $this->validateFieldLengths($data);

            $analysis->markAsCompleted($data);
        } catch (\Exception $e) {
            Log::error('Analysis failed', ['error' => $e->getMessage()]);
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
    /**
     * Analyze order patterns and behaviors
     *
     * IMPORTANTE: Diferença entre status e payment_status:
     * - status = fluxo do pedido (pending, processing, shipped, delivered, cancelled)
     * - payment_status = situação do pagamento (pending, paid, refunded, failed)
     *
     * Para análise de saúde financeira, o foco deve ser em payment_status.
     * Pedidos com status='pending' mas payment_status='paid' estão em processamento normal.
     */
    private function getOrderPatterns($orders): array
    {
        $totalOrders = $orders->count();

        if ($totalOrders === 0) {
            return [
                'total_orders' => 0,
                'by_payment_status' => [],
                'payment_pending_count' => 0,
                'payment_pending_rate' => 0,
                'payment_confirmed_count' => 0,
                'payment_confirmed_rate' => 0,
                'cancellation_rate' => 0,
                'refund_rate' => 0,
                'average_shipping' => 0,
                'discount_usage_rate' => 0,
                'peak_day' => null,
                'orders_by_day' => [],
            ];
        }

        // Orders by day of week
        $ordersByDay = $orders->groupBy(fn ($o) => $o->external_created_at?->dayOfWeek ?? 0)
            ->map->count()
            ->toArray();

        $peakDay = ! empty($ordersByDay) ? array_search(max($ordersByDay), $ordersByDay) : null;
        $dayNames = ['Domingo', 'Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

        // Status do pagamento - ÚNICA fonte de verdade para status de pedidos
        $byPaymentStatus = $orders->groupBy(fn ($o) => $o->payment_status?->value ?? $o->payment_status ?? 'unknown')
            ->map->count()
            ->toArray();

        // Cancelamento baseado em payment_status (cancelled ou voided)
        $cancelledCount = $orders->filter(fn ($o) => $o->isCancelled())->count();

        // Reembolso baseado em payment_status
        $refundedCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'refunded')->count();

        // Pagamentos pendentes (não confirmados) - MÉTRICA IMPORTANTE
        $paymentPendingCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'pending')->count();

        // Pagamentos confirmados
        $paymentConfirmedCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'paid')->count();

        $ordersWithDiscount = $orders->filter(fn ($o) => ($o->discount ?? 0) > 0)->count();

        return [
            'total_orders' => $totalOrders,
            'by_payment_status' => $byPaymentStatus,
            'payment_pending_count' => $paymentPendingCount,
            'payment_pending_rate' => round(($paymentPendingCount / $totalOrders) * 100, 1),
            'payment_confirmed_count' => $paymentConfirmedCount,
            'payment_confirmed_rate' => round(($paymentConfirmedCount / $totalOrders) * 100, 1),
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
        // Usar helpers para formato compacto
        $historyService = new HistorySummaryService;
        $periodDays = $startDate->diffInDays($endDate);

        // Dados básicos
        $storeName = $storeData['store']['name'] ?? 'Loja';
        $revenue = number_format($storeData['metrics']['total_revenue'] ?? 0, 0, ',', '.');
        $paidOrders = $storeData['metrics']['paid_orders'] ?? 0;
        $totalOrders = $storeData['metrics']['total_orders'] ?? 0;
        $storeId = $storeData['store']['id'] ?? 0;

        // Resumo de histórico (OTIMIZADO - reduz tokens)
        $historySummary = $historyService->generateSummary($storeId);
        $successRate = $historySummary['success_rate'];
        $repeatedTopics = implode(', ', array_keys($historySummary['repeated_topics']));
        $categoriesToAvoid = implode(', ', array_keys($historySummary['categories_to_avoid']));

        // Produtos em tabela Markdown (OTIMIZADO)
        $topSellersTable = ProductTableFormatter::formatTopSellers(
            $storeData['product_performance']['top_sellers'] ?? []
        );
        $noSalesTable = ProductTableFormatter::formatNoSalesProducts(
            $storeData['product_performance']['no_sales_products'] ?? []
        );

        // Inventário resumido
        $inventorySummary = ProductTableFormatter::formatInventorySummary(
            $storeData['inventory_alerts'] ?? []
        );

        // Insights de clientes
        $customerInsights = $storeData['customer_insights'] ?? [];
        $repeatRate = $customerInsights['repeat_purchase_rate'] ?? 0;
        $vipCount = $customerInsights['segments']['vip'] ?? 0;
        $totalCustomers = $customerInsights['total_customers'] ?? 0;

        // Padrões de pedidos
        $orderPatterns = $storeData['order_patterns'] ?? [];
        $cancellationRate = $orderPatterns['cancellation_rate'] ?? 0;
        $refundRate = $orderPatterns['refund_rate'] ?? 0;
        $paymentPendingRate = $orderPatterns['payment_pending_rate'] ?? 0;
        $paymentConfirmedRate = $orderPatterns['payment_confirmed_rate'] ?? 0;

        // Tendências
        $trends = $storeData['trends'] ?? [];
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

        // PROMPT OTIMIZADO (Muito mais compacto)
        return <<<PROMPT
# ANALISE: {$storeName}
**Periodo:** {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')} ({$periodDays} dias)
**Faturamento:** R\$ {$revenue} | **Pagamentos Confirmados:** {$paymentConfirmedRate}%

## RESUMO FINANCEIRO
- **Faturamento Realizado:** R\$ {$revenue}
- **Pedidos com Pagamento:** {$paidOrders} / {$totalOrders}
- **Porte:** {$this->getStoreSize($storeData['metrics']['total_revenue'] ?? 0)}
- **Tendencia:** {$trendDescription}

## HISTORICO DE SUGESTOES
- **Taxa de Sucesso:** {$successRate}
- **Topicos Ja Sugeridos (EVITAR):** {$repeatedTopics}
- **Categorias que Loja Ignora:** {$categoriesToAvoid}

## TOP 10 PRODUTOS COM VENDAS
{$topSellersTable}

## TOP 10 PRODUTOS SEM VENDAS (com estoque)
{$noSalesTable}

## ESTOQUE
{$inventorySummary}

## CLIENTES
- **Taxa de Recompra:** {$repeatRate}%
- **Clientes VIP (>R\$1000):** {$vipCount} de {$totalCustomers}
- **Cancelamentos:** {$cancellationRate}%
- **Reembolsos:** {$refundRate}%
- **Pagamentos Pendentes:** {$paymentPendingRate}%

{$strategiesContext}

## INSTRUCAO
Analise os dados acima e gere EXATAMENTE 9 sugestoes para aumentar vendas.

**RESTRICOES (CRITICAS):**
- Titulo: maximo 80 caracteres
- Descricao: maximo 200 caracteres
- Expected Impact: maximo 80 caracteres (formato: "R\$ XXXX" ou "+X%")
- Action Steps: SEMPRE 3, maximo 150 chars cada

**EVITE:**
- Titulos com contexto: "Reduzir cancelamentos de 32% para 10% com..." (muito longo)
- Descricoes genericas: "Melhore seu marketing"
- Expected impact vago: "Aumento nas vendas"
- Menos ou mais de 3 action steps

**FACA:**
- Titulos diretos: "Reduzir cancelamentos de 32% para 10%"
- Descricoes com numeros: "32% de 1.336 = R\$ 73.950/mes perdidos"
- Expected impact com valor: "R\$ 73.950/mes" ou "+45% em recompras"
- 3 passos claros: Diagnostico -> Implementacao -> Validacao

**CRUCIAL:**
- Evite repetir topicos: {$repeatedTopics}
- Nao sugira para: {$categoriesToAvoid}
- Cada sugestao deve mencionar produtos/numeros especificos
- Priorize HIGH apenas se problema real nos dados
- Responda APENAS em JSON: {summary, suggestions[], alerts[], opportunities[]}
PROMPT;
    }

    private function getStoreSize(float $revenue): string
    {
        return match (true) {
            $revenue > 50000 => 'Grande (>R$50k)',
            $revenue > 10000 => 'Medio (R$10-50k)',
            default => 'Pequeno (<R$10k)',
        };
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Voce e um consultor senior de e-commerce analisando dados de vendas brasileiras.

## RESTRICAO CRITICA: LIMITES DE CARACTERES

TODOS os campos tem limites ESTRITOS. NAO ultrapasse ou saida sera rejeitada:

```
main_insight:           <=150 chars (1-2 linhas)
alert.title:            <=80 chars
alert.message:          <=200 chars
opportunity.title:      <=80 chars
opportunity.description:<=200 chars
suggestion.title:       <=80 chars (maximo!)
suggestion.description: <=200 chars
suggestion.expected_impact: <=80 chars
action_step:            <=150 chars (SEMPRE 3 passos)
```

Se seu conteudo ultrapassa limites:
1. Corte o menos importante
2. Mude pra forma mais compacta
3. Priorize: NUMEROS > ESPECIFICOS > CONTEXTO

## DIFERENCA: STATUS vs PAYMENT_STATUS

**status** = Fluxo/Processamento do pedido
- 'pending': Em processamento (NORMAL - nao e anomalia!)
- 'processing', 'shipped', 'delivered', 'cancelled'

**payment_status** = Confirmacao de pagamento
- 'pending': Pagamento nao confirmado (ALERTA real)
- 'paid': Pagamento CONFIRMADO
- 'refunded': Reembolsado
- 'failed': Falha na transacao

REGRA: Pedidos com status='pending' + payment_status='paid' = NORMAL
ANOMALIA: payment_status='pending' em alta taxa (>5%)

## PROCESSO DE ANALISE

1. DIAGNOSTICO: Identifique padroes reais nos dados
   - Tendencias em "trends" (receita crescendo ou caindo?)
   - Produtos que realmente vendem (top_sellers)
   - Produtos parados com estoque (no_sales_products)
   - Taxa de recompra de clientes
   - Produtos fora/com baixo estoque
   - Pagamentos: qual % tem payment_status='paid'?

2. PRIORIZACAO: O que e mais urgente?
   - Queda forte de receita: HIGH PRIORITY
   - Produtos top sem estoque: HIGH PRIORITY
   - Taxa recompra baixa: HIGH PRIORITY
   - Muitos pagamentos pendentes: HIGH PRIORITY
   - Produtos parados com estoque: MEDIUM PRIORITY

3. RECOMENDACOES: Crie sugestoes ESPECIFICAS
   - SEMPRE mencione produtos, numeros, nomes reais
   - NUNCA generico ("melhore marketing")
   - Cada sugestao = unica para ESTA loja
   - Use dados dos 3 ultimos periodos quando houver

## FORMATO EXATO DE RESPOSTA (JSON)

{
  "summary": {
    "health_score": <numero 0-100>,
    "health_status": "<Critico|Precisa Atencao|Bom|Excelente>",
    "main_insight": "<Uma frase de impacto maximo 150 chars>"
  },
  "alerts": [
    {
      "type": "<danger|warning|info>",
      "title": "<Titulo maximo 80 chars>",
      "message": "<Mensagem maximo 200 chars>"
    }
  ],
  "opportunities": [
    {
      "title": "<Titulo maximo 80 chars>",
      "potential_revenue": "<R$ X.XXX>",
      "description": "<Descricao maximo 200 chars>"
    }
  ],
  "suggestions": [
    {
      "id": "sug1",
      "category": "<marketing|pricing|inventory|product|customer|conversion|operational>",
      "priority": "<high|medium|low>",
      "title": "<Maximo 80 CHARS - comece com verbo>",
      "description": "<Maximo 200 chars - POR QUE? responda>",
      "expected_impact": "<Maximo 80 chars - formato: R$ XXXX ou +X%>",
      "target_metrics": ["metrica1", "metrica2"],
      "action_steps": [
        "<Maximo 150 chars - Passo 1: O que fazer>",
        "<Maximo 150 chars - Passo 2: Como fazer>",
        "<Maximo 150 chars - Passo 3: Como validar>"
      ],
      "is_done": false
    }
  ]
}

## EXEMPLO DE SUGESTAO CORRETA

BAD (nao faca isso):
{
  "title": "Reduzir taxa de cancelamento de 32% para 10% com melhorias operacionais" (92 chars X),
  "description": "A taxa de cancelamento atual de 32% esta perdendo...[200+ chars de contexto]" X,
  "expected_impact": "Reduzir a taxa de cancelamento para 10%" (sem R$ ou %) X
}

GOOD (faca assim):
{
  "title": "Reduzir cancelamentos de 32% para 10%" (38 chars OK),
  "description": "32% dos 1.336 pedidos sao cancelados. Isso sao ~427 pedidos perdidos = R$ 73.950/mes em receita. Investigar motivos: pagamento recusado? Frete alto?" (194 chars OK),
  "expected_impact": "R$ 73.950/mes recuperados" (26 chars OK),
  "action_steps": [
    "Auditar ultimos 100 cancelamentos: encontrar padrao comum (gateway, frete, produto?)" (87 chars OK),
    "Contatar clientes que cancelaram para entender frustracoes (amostra de 20)" (75 chars OK),
    "Implementar 1 mudanca piloto: ex - se for frete, testar desconto para pedidos <R$50" (83 chars OK)
  ]
}

## REGRAS CRITICAS

1. **EXATAMENTE 9 sugestoes**: 3 high, 3 medium, 3 low (ou menos se houver so 2 problemas high reais)

2. **Sugestoes HIGH**: So se houver problema real nos dados
   - Queda forte de receita
   - Produto top sem estoque
   - Taxa recompra <15%
   - Pagamentos pendentes >5%

3. **Titulos**: SEM contexto, SEM justificacao
   - X "Reduzir taxa de cancelamento de 32% para 10% com melhorias operacionais"
   - OK "Reduzir cancelamentos de 32% para 10%"
   - OK "Aumentar estoque de Camiseta Azul (+150 unidades)"

4. **Descricoes**: POR QUE? responda (nao COMO)
   - X "Implemente um programa de fidelidade para reter clientes"
   - OK "Taxa de recompra e 8% (abaixo da meta 20%). Significa perder R$ 45k/mes em clientes unicos"

5. **Expected Impact**: Numero + unidade, sem prosa
   - X "Impacto esperado: Aumento significativo nas vendas"
   - OK "R$ 2.500/mes" ou "+15% de ticket medio" ou "200 unidades/mes"

6. **Action Steps**: SEMPRE 3, cada um uma acao clara
   - OK Passo 1: DIAGNOSTICAR (investigar o problema)
   - OK Passo 2: IMPLEMENTAR (fazer a mudanca)
   - OK Passo 3: VALIDAR (medir o resultado)

7. **Responda APENAS em JSON valido**
   - Sem texto antes ou depois
   - Sem markdown (sem ```)
   - Sem comentarios
   - JSON bem formatado e fechado corretamente

8. **Cada sugestao deve referenciar dados especificos**
   - Nomes de produtos (nao "um produto")
   - Numeros exatos (nao "alguns" ou "muitos")
   - Metricas do periodo (nao generico)

Responda APENAS com JSON valido.
PROMPT;
    }

    /**
     * Valida limites de tamanho dos campos da resposta AI
     * Lanca excecao se campos excedem limites (bloqueia saida invalida)
     */
    private function validateFieldLengths(array $data): void
    {
        $errors = [];

        // Main insight
        $mainInsight = $data['summary']['main_insight'] ?? '';
        if (strlen($mainInsight) > 150) {
            $errors[] = 'main_insight: '.strlen($mainInsight).' chars (maximo 150)';
        }

        // Alerts
        foreach ($data['alerts'] ?? [] as $i => $alert) {
            if (strlen($alert['title'] ?? '') > 80) {
                $errors[] = "alert[{$i}].title: ".strlen($alert['title']).' chars (maximo 80)';
            }
            if (strlen($alert['message'] ?? '') > 200) {
                $errors[] = "alert[{$i}].message: ".strlen($alert['message']).' chars (maximo 200)';
            }
        }

        // Opportunities
        foreach ($data['opportunities'] ?? [] as $i => $opp) {
            if (strlen($opp['title'] ?? '') > 80) {
                $errors[] = "opportunity[{$i}].title: ".strlen($opp['title']).' chars (maximo 80)';
            }
            if (strlen($opp['description'] ?? '') > 200) {
                $errors[] = "opportunity[{$i}].description: ".strlen($opp['description']).' chars (maximo 200)';
            }
        }

        // Suggestions
        foreach ($data['suggestions'] ?? [] as $i => $sug) {
            // Titulo
            if (strlen($sug['title'] ?? '') > 80) {
                $errors[] = "suggestion[{$i}].title: ".strlen($sug['title']).' chars (maximo 80)';
            }

            // Descricao
            if (strlen($sug['description'] ?? '') > 200) {
                $errors[] = "suggestion[{$i}].description: ".strlen($sug['description']).' chars (maximo 200)';
            }

            // Expected Impact
            if (strlen($sug['expected_impact'] ?? '') > 80) {
                $errors[] = "suggestion[{$i}].expected_impact: ".strlen($sug['expected_impact']).' chars (maximo 80)';
            }

            // Action Steps - exatamente 3
            $stepCount = count($sug['action_steps'] ?? []);
            if ($stepCount !== 3) {
                $errors[] = "suggestion[{$i}].action_steps: tem {$stepCount} passos (deve ser exatamente 3)";
            }

            // Cada passo maximo 150 chars
            foreach (($sug['action_steps'] ?? []) as $j => $step) {
                if (strlen($step) > 150) {
                    $errors[] = "suggestion[{$i}].action_steps[{$j}]: ".strlen($step).' chars (maximo 150)';
                }
            }
        }

        if (! empty($errors)) {
            Log::error('Field length validation failed', [
                'error_count' => count($errors),
                'errors' => $errors,
            ]);

            throw new \RuntimeException(
                "Saida violou limites de tamanho:\n".
                implode("\n", $errors).
                "\nAI deve respeitar todos os limites."
            );
        }
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
