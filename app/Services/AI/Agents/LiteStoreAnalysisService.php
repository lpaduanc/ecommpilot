<?php

namespace App\Services\AI\Agents;

use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\LiteAnalystAgentPrompt;
use App\Services\AI\Prompts\LiteStrategistAgentPrompt;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline de análise lite otimizado para Anthropic com limite de 30k tokens/minuto.
 *
 * Diferenças do pipeline completo:
 * - 2 etapas (Analyst + Strategist) em vez de 4
 * - Dados compactos (7 dias, top 20 produtos)
 * - 6 sugestões (2 high, 2 medium, 2 low) em vez de 9
 * - Prompts mais curtos
 * - Token total estimado: 18-27k (vs 45-105k do pipeline completo)
 */
class LiteStoreAnalysisService
{
    private const ANALYSIS_PERIOD_DAYS = 7; // Reduced from 15

    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the lite analysis pipeline for a store.
     */
    public function execute(Store $store, Analysis $analysis): array
    {
        Log::info("Starting LITE store analysis pipeline for store {$store->id}");

        // 1. Identify store niche
        $niche = $this->identifyNiche($store);
        Log::info("Lite Pipeline: Store niche identified as '{$niche}'");

        // 2. Prepare compact store data
        $storeData = $this->prepareCompactData($store);

        // 3. Execute Lite Analyst
        Log::info('Lite Pipeline: Executing Analyst');
        $analysisResult = $this->executeAnalyst($storeData);

        // 4. Execute Lite Strategist
        Log::info('Lite Pipeline: Executing Strategist');
        $suggestions = $this->executeStrategist($analysisResult, $niche);

        Log::info('Lite Pipeline: Strategist generated '.count($suggestions).' suggestions');

        // 5. Basic validation (inline, no Critic agent)
        $validatedSuggestions = $this->validateSuggestions($suggestions);

        Log::info('Lite Pipeline: After validation: '.count($validatedSuggestions).' suggestions');

        // 6. Save analysis and suggestions
        Log::info('Lite Pipeline: Saving analysis and suggestions');
        $this->saveAnalysis($analysis, $analysisResult, $niche);
        $this->saveSuggestions($analysis, $validatedSuggestions);

        Log::info('Lite Pipeline: Analysis completed with '.count($validatedSuggestions).' suggestions');

        return [
            'analysis_id' => $analysis->id,
            'overall_health' => $analysisResult['overall_health'] ?? null,
            'metrics' => $analysisResult['metrics'] ?? null,
            'suggestions_count' => count($validatedSuggestions),
            'niche' => $niche,
            'pipeline' => 'lite',
        ];
    }

    /**
     * Prepare compact store data for lite analysis.
     */
    private function prepareCompactData(Store $store): array
    {
        $periodDays = self::ANALYSIS_PERIOD_DAYS;

        // Orders from the analysis period (7 days)
        $orders = $store->orders()
            ->where('external_created_at', '>=', now()->subDays($periodDays))
            ->get();

        $paidOrders = $orders->filter(fn ($o) => $o->isPaid());

        // Products (top 20 best sellers + basic counts)
        $allProductsCount = $store->products()->count();
        $products = $store->products()->excludeGifts()->get();
        $giftsFiltered = $allProductsCount - $products->count();
        $bestSellers = $this->getBestSellers($paidOrders, 20);

        // Compact coupon summary (no detailed list)
        $ordersWithCoupon = $paidOrders->filter(fn ($o) => ! empty($o->coupon));
        $usageRate = $paidOrders->count() > 0
            ? round(($ordersWithCoupon->count() / $paidOrders->count()) * 100, 2)
            : 0;

        $avgTicketWithCoupon = $ordersWithCoupon->count() > 0 ? $ordersWithCoupon->avg('total') : 0;
        $avgTicketWithoutCoupon = $paidOrders->filter(fn ($o) => empty($o->coupon))->avg('total') ?? 0;
        $ticketImpact = $avgTicketWithoutCoupon > 0
            ? round((($avgTicketWithCoupon - $avgTicketWithoutCoupon) / $avgTicketWithoutCoupon) * 100, 2)
            : 0;

        return [
            'period_days' => $periodDays,
            'orders' => [
                'total' => $orders->count(),
                'total_revenue' => round($paidOrders->sum('total'), 2),
                'average_order_value' => round($paidOrders->avg('total') ?? 0, 2),
                'cancellation_rate' => $orders->count() > 0
                    ? round(($orders->filter(fn ($o) => $o->isCancelled())->count() / $orders->count()) * 100, 2)
                    : 0,
            ],
            'products' => [
                'total' => $products->count(),
                'active' => $products->filter(fn ($p) => $p->is_active)->count(),
                'out_of_stock' => $products->filter(fn ($p) => $p->isOutOfStock())->count(),
                'low_stock' => $products->filter(fn ($p) => $p->hasLowStock())->count(),
                'best_sellers_count' => count($bestSellers),
                'gifts_filtered' => $giftsFiltered,
            ],
            'coupons' => [
                'usage_rate' => $usageRate,
                'ticket_impact' => $ticketImpact,
                'total_discount' => round($ordersWithCoupon->sum('discount'), 2),
            ],
        ];
    }

    /**
     * Execute the lite analyst agent.
     */
    private function executeAnalyst(array $storeData): array
    {
        $prompt = LiteAnalystAgentPrompt::get(['store_data' => $storeData]);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'max_tokens' => 4096, // Reduced from 8192
        ]);

        Log::debug('Lite Analyst raw response (first 1000 chars): '.substr($response, 0, 1000));

        $json = JsonExtractor::extract($response, 'LiteAnalyst');

        if ($json === null) {
            Log::warning('Lite Analyst: Could not extract JSON from response');

            return $this->getDefaultAnalysis();
        }

        Log::info('Lite Analyst: Successfully parsed analysis with health score: '.($json['overall_health']['score'] ?? 'N/A'));

        return $this->normalizeAnalysis($json);
    }

    /**
     * Execute the lite strategist agent.
     */
    private function executeStrategist(array $analysisResult, string $niche): array
    {
        $prompt = LiteStrategistAgentPrompt::get([
            'analysis' => $analysisResult,
            'niche' => $niche,
        ]);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'max_tokens' => 6144, // Reduced from 8192
        ]);

        Log::debug('Lite Strategist raw response (first 1000 chars): '.substr($response, 0, 1000));

        $json = JsonExtractor::extract($response, 'LiteStrategist');

        if ($json === null || empty($json['suggestions'])) {
            Log::warning('Lite Strategist: Could not extract suggestions from response');

            return [];
        }

        Log::info('Lite Strategist: Found '.count($json['suggestions']).' suggestions');

        return $json['suggestions'];
    }

    /**
     * Basic validation of suggestions (no Critic agent).
     */
    private function validateSuggestions(array $suggestions): array
    {
        $validated = [];
        $requiredFields = ['category', 'title', 'description', 'expected_impact'];

        foreach ($suggestions as $index => $suggestion) {
            // Check required fields
            $hasAllFields = true;
            foreach ($requiredFields as $field) {
                if (empty($suggestion[$field])) {
                    $hasAllFields = false;
                    break;
                }
            }

            if (! $hasAllFields) {
                Log::warning("Lite validation: Suggestion {$index} missing required fields");

                continue;
            }

            // Normalize expected_impact
            $impact = strtolower($suggestion['expected_impact']);
            if (! in_array($impact, ['high', 'medium', 'low'])) {
                $impact = 'medium';
            }
            $suggestion['expected_impact'] = $impact;

            // Add final_version wrapper for compatibility with saveSuggestions
            $validated[] = [
                'final_version' => $suggestion,
                'final_priority' => $index + 1,
            ];
        }

        return $validated;
    }

    /**
     * Get best selling product IDs from orders.
     */
    private function getBestSellers($paidOrders, int $limit = 20): array
    {
        $productCounts = [];

        foreach ($paidOrders as $order) {
            $items = is_array($order->items) ? $order->items : [];
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                if ($productId) {
                    $productCounts[$productId] = ($productCounts[$productId] ?? 0) + ($item['quantity'] ?? 1);
                }
            }
        }

        arsort($productCounts);

        return array_slice($productCounts, 0, $limit, true);
    }

    /**
     * Identify the store niche based on products.
     */
    private function identifyNiche(Store $store): string
    {
        $categories = $store->products()
            ->excludeGifts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(3)
            ->keys()
            ->toArray();

        $nicheMap = [
            // Fashion
            'roupas' => 'fashion', 'camisetas' => 'fashion', 'vestidos' => 'fashion',
            'calças' => 'fashion', 'moda' => 'fashion', 'fashion' => 'fashion',
            // Electronics
            'celulares' => 'electronics', 'smartphones' => 'electronics',
            'computadores' => 'electronics', 'eletrônicos' => 'electronics',
            // Food
            'alimentos' => 'food', 'bebidas' => 'food', 'comida' => 'food',
            // Beauty & Hair Care
            'beleza' => 'beauty', 'cosméticos' => 'beauty', 'maquiagem' => 'beauty',
            'cabelo' => 'beauty', 'cabelos' => 'beauty', 'hair' => 'beauty',
            'shampoo' => 'beauty', 'shampoos' => 'beauty', 'condicionador' => 'beauty',
            'hidratação' => 'beauty', 'tratamento' => 'beauty', 'capilar' => 'beauty',
            'lisos' => 'beauty', 'cacheados' => 'beauty', 'loiros' => 'beauty',
            'coloração' => 'beauty', 'tintura' => 'beauty', 'perfumaria' => 'beauty',
            'skincare' => 'beauty', 'cuidados' => 'beauty',
            // Home
            'casa' => 'home', 'decoração' => 'home', 'móveis' => 'home',
            // Sports
            'esportes' => 'sports', 'fitness' => 'sports',
        ];

        foreach ($categories as $category) {
            $categoryLower = strtolower($category);
            foreach ($nicheMap as $key => $niche) {
                if (str_contains($categoryLower, $key)) {
                    return $niche;
                }
            }
        }

        return 'general';
    }

    /**
     * Normalize the analysis structure.
     */
    private function normalizeAnalysis(array $analysis): array
    {
        $default = $this->getDefaultAnalysis();

        return [
            'metrics' => array_merge($default['metrics'], $analysis['metrics'] ?? []),
            'anomalies' => $analysis['anomalies'] ?? [],
            'overall_health' => array_merge(
                $default['overall_health'],
                $analysis['overall_health'] ?? []
            ),
        ];
    }

    /**
     * Get default analysis structure.
     */
    private function getDefaultAnalysis(): array
    {
        return [
            'metrics' => [
                'sales' => [
                    'total' => 0,
                    'daily_average' => 0,
                    'trend' => 'stable',
                ],
                'average_order_value' => [
                    'value' => 0,
                    'benchmark' => 150,
                ],
                'cancellation_rate' => 0,
                'inventory' => [
                    'out_of_stock_products' => 0,
                    'critical_stock_products' => 0,
                ],
                'coupons' => [
                    'usage_rate' => 0,
                    'ticket_impact' => 0,
                ],
            ],
            'anomalies' => [],
            'overall_health' => [
                'score' => 50,
                'classification' => 'attention',
                'main_points' => ['Análise não pôde ser completada'],
            ],
        ];
    }

    /**
     * Save analysis results.
     */
    private function saveAnalysis(Analysis $analysis, array $analysisResult, string $niche): void
    {
        $health = $analysisResult['overall_health'] ?? [];

        $analysis->markAsCompleted([
            'summary' => [
                'health_score' => $health['score'] ?? 50,
                'health_status' => $health['classification'] ?? 'attention',
                'main_insight' => $health['main_points'][0] ?? 'Análise lite concluída',
            ],
            'suggestions' => [],
            'alerts' => $this->extractAlerts($analysisResult),
            'opportunities' => [],
            'pipeline' => 'lite',
        ]);
    }

    /**
     * Extract alerts from analysis.
     */
    private function extractAlerts(array $analysis): array
    {
        $alerts = [];

        foreach ($analysis['anomalies'] ?? [] as $anomaly) {
            $alerts[] = [
                'type' => match ($anomaly['severity'] ?? 'medium') {
                    'high' => 'danger',
                    'medium' => 'warning',
                    default => 'info',
                },
                'title' => $this->mapAlertTypeToLabel($anomaly['type'] ?? 'alert'),
                'message' => $anomaly['description'] ?? '',
            ];
        }

        return $alerts;
    }

    /**
     * Map alert type to Portuguese label.
     */
    private function mapAlertTypeToLabel(string $type): string
    {
        $labels = [
            'concentracao_vendas' => 'Concentração de Vendas',
            'estoque_critico' => 'Estoque Crítico',
            'queda_vendas_recente' => 'Queda de Vendas Recente',
            'cupons_excessivos' => 'Cupons Excessivos',
            'sazonalidade_inicio_ano' => 'Sazonalidade de Início de Ano',
            'dependencia_cupons' => 'Dependência de Cupons',
            'produtos_estrela' => 'Produtos Estrela',
            'gestao_estoque' => 'Gestão de Estoque',
            'cancellation_rate' => 'Taxa de Cancelamento',
            'refund_rate' => 'Taxa de Reembolso',
            'inventory_critical' => 'Estoque Crítico',
            'low_conversion' => 'Baixa Conversão',
            'high_abandonment' => 'Alto Abandono',
            'revenue_decline' => 'Queda de Receita',
            'order_decline' => 'Queda de Pedidos',
            'ticket_decline' => 'Queda de Ticket Médio',
            'customer_churn' => 'Perda de Clientes',
            'stock_out' => 'Ruptura de Estoque',
            'alert' => 'Alerta',
        ];

        return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Save suggestions to database.
     */
    private function saveSuggestions(Analysis $analysis, array $suggestions): void
    {
        foreach ($suggestions as $index => $suggestion) {
            $finalVersion = $suggestion['final_version'] ?? $suggestion;

            Suggestion::create([
                'analysis_id' => $analysis->id,
                'store_id' => $analysis->store_id,
                'category' => $finalVersion['category'] ?? 'general',
                'title' => $finalVersion['title'] ?? 'Sugestão sem título',
                'description' => $finalVersion['description'] ?? '',
                'recommended_action' => $finalVersion['recommended_action'] ?? '',
                'expected_impact' => $finalVersion['expected_impact'] ?? 'medium',
                'priority' => $suggestion['final_priority'] ?? ($index + 1),
                'status' => 'pending',
                'target_metrics' => $finalVersion['target_metrics'] ?? null,
                'specific_data' => $finalVersion['specific_data'] ?? null,
                'data_justification' => $finalVersion['data_justification'] ?? null,
            ]);
        }
    }
}
