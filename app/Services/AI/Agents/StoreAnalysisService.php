<?php

namespace App\Services\AI\Agents;

use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Services\AI\EmbeddingService;
use App\Services\AI\Memory\HistoryService;
use App\Services\AI\RAG\KnowledgeBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAnalysisService
{
    public function __construct(
        private CollectorAgentService $collector,
        private AnalystAgentService $analyst,
        private StrategistAgentService $strategist,
        private CriticAgentService $critic,
        private KnowledgeBaseService $knowledgeBase,
        private HistoryService $history,
        private EmbeddingService $embedding
    ) {}

    /**
     * Execute the full analysis pipeline for a store.
     */
    public function execute(Store $store, Analysis $analysis): array
    {
        Log::info("Starting store analysis pipeline for store {$store->id}");

        // 1. Identify store niche
        $niche = $this->identifyNiche($store);

        // 2. Get historical context
        $previousAnalyses = $this->history->getPreviousAnalyses($store->id, 5);
        $previousSuggestions = $this->history->getSuggestionsWithStatus($store->id);

        // 3. Get benchmarks via RAG
        $benchmarks = $this->knowledgeBase->searchBenchmarks($niche);
        $nicheStrategies = $this->knowledgeBase->searchStrategies($niche);

        // 4. Execute Collector Agent
        Log::info('Executing Collector Agent');
        $collectorContext = $this->collector->execute([
            'platform' => $store->platform ?? 'nuvemshop',
            'niche' => $niche,
            'operation_time' => $store->created_at->diffForHumans(),
            'previous_analyses' => $previousAnalyses,
            'previous_suggestions' => $previousSuggestions,
            'benchmarks' => $benchmarks,
        ]);

        // 5. Execute Analyst Agent
        Log::info('Executing Analyst Agent');
        $storeData = $this->prepareStoreData($store);
        $analysisResult = $this->analyst->execute([
            'orders_summary' => $storeData['orders'],
            'products_summary' => $storeData['products'],
            'inventory_summary' => $storeData['inventory'],
            'coupons_summary' => $storeData['coupons'],
            'benchmarks' => $benchmarks,
        ]);

        // 6. Execute Strategist Agent
        Log::info('Executing Strategist Agent');
        $generatedSuggestions = $this->strategist->execute([
            'collector_context' => $collectorContext,
            'analysis' => $analysisResult,
            'previous_suggestions' => $previousSuggestions,
            'rag_strategies' => $nicheStrategies,
        ]);

        // 7. Execute Critic Agent
        Log::info('Executing Critic Agent');
        $criticizedSuggestions = $this->critic->execute([
            'suggestions' => $generatedSuggestions['suggestions'],
            'previous_suggestions' => $previousSuggestions,
            'store_context' => [
                'niche' => $niche,
                'platform' => $store->platform ?? 'nuvemshop',
                'metrics' => $analysisResult['metrics'] ?? [],
            ],
        ]);

        // 8. Filter by similarity (avoid repetitions)
        Log::info('Filtering suggestions by similarity');
        $finalSuggestions = $this->filterBySimilarity(
            $criticizedSuggestions['approved_suggestions'],
            $store->id
        );

        // 9. Save analysis and suggestions
        Log::info('Saving analysis and suggestions');
        $this->saveAnalysis($analysis, $analysisResult, $niche);
        $this->saveSuggestions($analysis, $finalSuggestions);

        Log::info('Store analysis completed with '.count($finalSuggestions).' suggestions');

        return [
            'analysis_id' => $analysis->id,
            'overall_health' => $analysisResult['overall_health'] ?? null,
            'metrics' => $analysisResult['metrics'] ?? null,
            'suggestions_count' => count($finalSuggestions),
            'niche' => $niche,
        ];
    }

    /**
     * Identify the store niche based on products.
     */
    private function identifyNiche(Store $store): string
    {
        // Get most common categories
        $categories = $store->syncedProducts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(3)
            ->keys()
            ->toArray();

        // Mapping of categories to niches
        $nicheMap = [
            'roupas' => 'fashion',
            'camisetas' => 'fashion',
            'vestidos' => 'fashion',
            'calças' => 'fashion',
            'moda' => 'fashion',
            'fashion' => 'fashion',
            'celulares' => 'electronics',
            'smartphones' => 'electronics',
            'computadores' => 'electronics',
            'eletrônicos' => 'electronics',
            'electronics' => 'electronics',
            'alimentos' => 'food',
            'bebidas' => 'food',
            'comida' => 'food',
            'food' => 'food',
            'beleza' => 'beauty',
            'cosméticos' => 'beauty',
            'maquiagem' => 'beauty',
            'beauty' => 'beauty',
            'casa' => 'home',
            'decoração' => 'home',
            'móveis' => 'home',
            'home' => 'home',
            'esportes' => 'sports',
            'fitness' => 'sports',
            'sports' => 'sports',
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
     * Prepare store data for analysis.
     */
    private function prepareStoreData(Store $store): array
    {
        // Orders from last 90 days
        $orders = $store->syncedOrders()
            ->where('external_created_at', '>=', now()->subDays(90))
            ->get();

        $paidOrders = $orders->filter(fn ($o) => $o->isPaid());

        // Products
        $products = $store->syncedProducts()->get();
        $activeProducts = $products->filter(fn ($p) => $p->is_active);

        return [
            'orders' => [
                'total' => $orders->count(),
                'by_status' => $orders->groupBy('status')->map->count()->toArray(),
                'total_revenue' => $paidOrders->sum('total'),
                'average_order_value' => $paidOrders->avg('total') ?? 0,
                'by_day' => $orders->groupBy(fn ($o) => $o->external_created_at->format('Y-m-d'))->map->count()->toArray(),
                'cancellation_rate' => $orders->count() > 0
                    ? round(($orders->filter(fn ($o) => $o->isCancelled())->count() / $orders->count()) * 100, 2)
                    : 0,
            ],
            'products' => [
                'total' => $products->count(),
                'active' => $activeProducts->count(),
                'out_of_stock' => $products->filter(fn ($p) => $p->isOutOfStock())->count(),
                'best_sellers' => $this->getBestSellers($store, $paidOrders),
                'no_sales_30_days' => $this->getNoSalesProducts($store, 30),
            ],
            'inventory' => [
                'total_value' => $products->sum(fn ($p) => ($p->stock_quantity ?? 0) * ($p->cost ?? 0)),
                'low_stock_products' => $products->filter(fn ($p) => $p->hasLowStock())->count(),
                'excess_stock_products' => $products->filter(fn ($p) => ($p->stock_quantity ?? 0) > 100)->count(),
            ],
            'coupons' => [
                'active' => 0, // TODO: Implement if coupons are tracked
                'last_30_days_usage' => [],
            ],
        ];
    }

    /**
     * Get best selling products.
     */
    private function getBestSellers(Store $store, $paidOrders, int $limit = 10): array
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
     * Get products with no sales in period.
     */
    private function getNoSalesProducts(Store $store, int $days): int
    {
        $soldProductIds = $store->syncedOrders()
            ->where('external_created_at', '>=', now()->subDays($days))
            ->get()
            ->flatMap(function ($order) {
                $items = is_array($order->items) ? $order->items : [];

                return collect($items)->pluck('product_id');
            })
            ->filter()
            ->unique()
            ->toArray();

        return $store->syncedProducts()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('external_id', $soldProductIds)
            ->count();
    }

    /**
     * Filter suggestions by similarity to avoid repetitions.
     */
    private function filterBySimilarity(array $suggestions, int $storeId): array
    {
        if (! $this->embedding->isConfigured()) {
            // Skip similarity check if embeddings not configured
            return $suggestions;
        }

        $filteredSuggestions = [];

        foreach ($suggestions as $suggestion) {
            $finalVersion = $suggestion['final_version'] ?? [];
            $textToEmbed = ($finalVersion['title'] ?? '').' '.($finalVersion['description'] ?? '');

            try {
                $embedding = $this->embedding->generate($textToEmbed);

                // Check similarity with existing suggestions
                if (! $this->embedding->isTooSimilar($embedding, $storeId, 0.85)) {
                    $suggestion['embedding'] = $embedding;
                    $filteredSuggestions[] = $suggestion;
                } else {
                    Log::info('Suggestion filtered due to similarity: '.$finalVersion['title']);
                }
            } catch (\Exception $e) {
                Log::warning('Could not check similarity: '.$e->getMessage());
                // Include suggestion anyway if similarity check fails
                $filteredSuggestions[] = $suggestion;
            }
        }

        return $filteredSuggestions;
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
                'main_insight' => $health['main_points'][0] ?? 'Analysis completed',
            ],
            'suggestions' => [], // Legacy field - suggestions are now in separate table
            'alerts' => $this->extractAlerts($analysisResult),
            'opportunities' => $this->extractOpportunities($analysisResult),
        ]);
    }

    /**
     * Extract alerts from analysis.
     */
    private function extractAlerts(array $analysis): array
    {
        $alerts = [];

        // Add anomalies as alerts
        foreach ($analysis['anomalies'] ?? [] as $anomaly) {
            $alerts[] = [
                'type' => $this->mapSeverityToAlertType($anomaly['severity'] ?? 'medium'),
                'title' => $anomaly['type'] ?? 'Alert',
                'message' => $anomaly['description'] ?? '',
            ];
        }

        return $alerts;
    }

    /**
     * Map severity to alert type.
     */
    private function mapSeverityToAlertType(string $severity): string
    {
        return match ($severity) {
            'high' => 'danger',
            'medium' => 'warning',
            default => 'info',
        };
    }

    /**
     * Extract opportunities from analysis.
     */
    private function extractOpportunities(array $analysis): array
    {
        $opportunities = [];

        foreach ($analysis['identified_patterns'] ?? [] as $pattern) {
            $opportunities[] = [
                'title' => $pattern['type'] ?? 'Opportunity',
                'description' => $pattern['opportunity'] ?? $pattern['description'] ?? '',
                'potential_revenue' => $pattern['potential_revenue'] ?? null,
            ];
        }

        return $opportunities;
    }

    /**
     * Save suggestions to database.
     */
    private function saveSuggestions(Analysis $analysis, array $suggestions): void
    {
        foreach ($suggestions as $index => $suggestion) {
            $finalVersion = $suggestion['final_version'] ?? [];

            $suggestionModel = Suggestion::create([
                'analysis_id' => $analysis->id,
                'store_id' => $analysis->store_id,
                'category' => $finalVersion['category'] ?? 'general',
                'title' => $finalVersion['title'] ?? 'Untitled Suggestion',
                'description' => $finalVersion['description'] ?? '',
                'recommended_action' => $finalVersion['recommended_action'] ?? '',
                'expected_impact' => $finalVersion['expected_impact'] ?? 'medium',
                'priority' => $suggestion['final_priority'] ?? ($index + 1),
                'status' => 'pending',
                'target_metrics' => $finalVersion['target_metrics'] ?? null,
                'specific_data' => $finalVersion['specific_data'] ?? null,
                'data_justification' => $finalVersion['data_justification'] ?? null,
            ]);

            // Save embedding if available
            if (isset($suggestion['embedding']) && config('database.default') === 'pgsql') {
                $embeddingStr = $this->embedding->formatForStorage($suggestion['embedding']);
                DB::statement("UPDATE suggestions SET embedding = '{$embeddingStr}'::vector WHERE id = ?", [$suggestionModel->id]);
            }
        }
    }
}
