<?php

namespace App\Services\AI\Agents;

use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Models\SystemSetting;
use App\Services\AI\EmbeddingService;
use App\Services\AI\Memory\HistoryService;
use App\Services\AI\Memory\HistorySummaryService;
use App\Services\AI\RAG\KnowledgeBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAnalysisService
{
    private string $logChannel = 'analysis';

    private array $collectedPrompts = [];

    private array $collectedEmbeddings = [];

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
        $pipelineStart = microtime(true);

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     STORE ANALYSIS PIPELINE - INICIO                            ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Configuracao do pipeline', [
            'analysis_id' => $analysis->id,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Detectar versão do formato de análise
        $formatVersion = SystemSetting::get('analysis.format_version', 'v1');
        Log::channel($this->logChannel)->info('Versao do formato de analise', [
            'format_version' => $formatVersion,
        ]);

        // =====================================================
        // ETAPA 1: Identificar nicho da loja
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 1/9: Identificando nicho da loja                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        // Prefer configured niche/subcategory over auto-detection
        if ($store->niche && $store->niche_subcategory) {
            // Both configured - use as is
            $niche = $store->niche;
            $subcategory = $store->niche_subcategory;
        } elseif ($store->niche) {
            // Only niche configured - detect subcategory
            $niche = $store->niche;
            $subcategory = $this->detectSubcategoryForNiche($store, $niche);
        } else {
            // Nothing configured - detect both
            $detected = $this->detectNicheAndSubcategory($store);
            $niche = $detected['niche'];
            $subcategory = $detected['subcategory'];
        }

        // Get store goals and configuration
        $storeGoals = $store->getFormattedGoals();
        $hasConfiguredNiche = $store->hasConfiguredNiche();

        // Get structured benchmarks for the niche/subcategory
        $structuredBenchmarks = $this->knowledgeBase->getStructuredBenchmarks($niche, $subcategory);

        Log::channel($this->logChannel)->info('<<< Nicho identificado', [
            'niche' => $niche,
            'subcategory' => $subcategory,
            'niche_configured' => $hasConfiguredNiche,
            'has_goals' => ! empty(array_filter($storeGoals)),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 2: Buscar contexto historico
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 2/9: Buscando contexto historico                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $previousAnalyses = $this->history->getPreviousAnalyses($store->id, 5);
        $previousSuggestions = $this->history->getSuggestionsWithStatus($store->id);

        Log::channel($this->logChannel)->info('<<< Contexto historico carregado', [
            'previous_analyses_count' => count($previousAnalyses),
            'previous_suggestions_count' => count($previousSuggestions),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 3: Buscar benchmarks via RAG
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 3/9: Buscando benchmarks via RAG                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $benchmarks = $this->knowledgeBase->searchBenchmarks($niche);
        $nicheStrategies = $this->knowledgeBase->searchStrategies($niche);

        Log::channel($this->logChannel)->info('<<< Benchmarks e estrategias carregados', [
            'benchmarks_count' => count($benchmarks),
            'strategies_count' => count($nicheStrategies),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 4: Executar Collector Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 4/9: Executando COLLECTOR AGENT                           │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $storeStats = $this->getStoreStats($store);

        Log::channel($this->logChannel)->info('>>> Dados da loja coletados', [
            'total_orders' => $storeStats['total_orders'],
            'total_products' => $storeStats['total_products'],
            'total_customers' => $storeStats['total_customers'],
            'total_revenue' => $storeStats['total_revenue'],
        ]);

        $collectorContext = $this->collector->execute([
            'platform' => $store->platform?->value ?? 'nuvemshop',
            'niche' => $niche,
            'niche_subcategory' => $subcategory,
            'store_stats' => $storeStats,
            'previous_analyses' => $previousAnalyses,
            'previous_suggestions' => $previousSuggestions,
            'benchmarks' => $benchmarks,
            'structured_benchmarks' => $structuredBenchmarks,
            'store_goals' => $storeGoals,
        ]);

        // Coletar prompt para logging no final
        $this->collectedPrompts['collector'] = $collectorContext['_prompt_used'] ?? null;
        unset($collectorContext['_prompt_used']);

        Log::channel($this->logChannel)->info('<<< Collector Agent concluido', [
            'keys_returned' => array_keys($collectorContext),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 5: Executar Analyst Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 5/9: Executando ANALYST AGENT                             │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $storeData = $this->prepareStoreData($store);

        Log::channel($this->logChannel)->info('>>> Dados preparados para analise', [
            'orders_total' => $storeData['orders']['total'],
            'orders_revenue' => $storeData['orders']['total_revenue'],
            'products_total' => $storeData['products']['total'],
            'products_active' => $storeData['products']['active'],
            'products_out_of_stock' => $storeData['products']['out_of_stock'],
        ]);

        // Se v2, usar HistorySummaryService para resumo de histórico otimizado
        $historySummary = null;
        if ($formatVersion === 'v2' && SystemSetting::get('analysis.v2.use_history_summary', true)) {
            $historySummary = app(HistorySummaryService::class)->generateSummary($store->id);
            Log::channel($this->logChannel)->info('>>> V2: Usando resumo de historico otimizado', [
                'total_suggestions' => $historySummary['total_suggestions'],
                'success_rate' => $historySummary['success_rate'],
            ]);
        }

        $analysisResult = $this->analyst->execute([
            'orders_summary' => $storeData['orders'],
            'products_summary' => $storeData['products'],
            'inventory_summary' => $storeData['inventory'],
            'coupons_summary' => $storeData['coupons'],
            'benchmarks' => $benchmarks,
            'structured_benchmarks' => $structuredBenchmarks,
            'store_goals' => $storeGoals,
            'niche' => $niche,
            'niche_subcategory' => $subcategory,
            'format_version' => $formatVersion,
            'history_summary' => $historySummary,
        ]);

        // Coletar prompt para logging no final
        $this->collectedPrompts['analyst'] = $analysisResult['_prompt_used'] ?? null;
        unset($analysisResult['_prompt_used']);

        Log::channel($this->logChannel)->info('<<< Analyst Agent concluido', [
            'health_score' => $analysisResult['overall_health']['score'] ?? 'N/A',
            'health_classification' => $analysisResult['overall_health']['classification'] ?? 'N/A',
            'anomalies_count' => count($analysisResult['anomalies'] ?? []),
            'patterns_count' => count($analysisResult['identified_patterns'] ?? []),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 6: Executar Strategist Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 6/9: Executando STRATEGIST AGENT                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $generatedSuggestions = $this->strategist->execute([
            'collector_context' => $collectorContext,
            'analysis' => $analysisResult,
            'previous_suggestions' => $previousSuggestions,
            'rag_strategies' => $nicheStrategies,
            'structured_benchmarks' => $structuredBenchmarks,
            'store_goals' => $storeGoals,
            'niche' => $niche,
            'niche_subcategory' => $subcategory,

            // Métricas diretas para o Strategist
            'platform' => $store->platform?->value ?? 'nuvemshop',
            'operation_time' => $storeStats['operation_time'] ?? 'não informado',
            'orders_total' => $storeData['orders']['total'] ?? 0,
            'ticket_medio' => round($storeData['orders']['average_order_value'] ?? 0, 2),
            'ticket_benchmark' => $structuredBenchmarks['ticket_medio']['media'] ?? 0,
            'health_score' => $analysisResult['overall_health']['score'] ?? 0,
            'health_classification' => $analysisResult['overall_health']['classification'] ?? 'não calculado',
            'active_products' => $storeData['products']['active'] ?? 0,
            'out_of_stock' => $storeData['products']['out_of_stock'] ?? 0,
            'out_of_stock_pct' => $storeData['products']['total'] > 0
                ? round(($storeData['products']['out_of_stock'] / $storeData['products']['total']) * 100, 2)
                : 0,
            'anomalies_list' => $this->formatAnomaliesList($analysisResult['anomalies'] ?? []),
            'patterns_list' => $this->formatPatternsList($analysisResult['identified_patterns'] ?? []),
        ]);

        // Coletar prompt para logging no final
        $this->collectedPrompts['strategist'] = $generatedSuggestions['_prompt_used'] ?? null;
        unset($generatedSuggestions['_prompt_used']);

        Log::channel($this->logChannel)->info('<<< Strategist Agent concluido', [
            'suggestions_generated' => count($generatedSuggestions['suggestions'] ?? []),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 7: Executar Critic Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 7/9: Executando CRITIC AGENT                              │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $criticizedSuggestions = $this->critic->execute([
            'suggestions' => $generatedSuggestions['suggestions'],
            'previous_suggestions' => $previousSuggestions,
            'store_context' => [
                'niche' => $niche,
                'platform' => $store->platform?->value ?? 'nuvemshop',
                'metrics' => $analysisResult['metrics'] ?? [],
            ],
        ]);

        // Coletar prompt para logging no final
        $this->collectedPrompts['critic'] = $criticizedSuggestions['_prompt_used'] ?? null;
        unset($criticizedSuggestions['_prompt_used']);

        Log::channel($this->logChannel)->info('<<< Critic Agent concluido', [
            'suggestions_approved' => count($criticizedSuggestions['approved_suggestions'] ?? []),
            'suggestions_removed' => count($criticizedSuggestions['removed_suggestions'] ?? []),
            'average_quality' => $criticizedSuggestions['general_analysis']['average_quality'] ?? 'N/A',
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 8: Filtrar por similaridade
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 8/9: Filtrando por similaridade (embeddings)              │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        Log::channel($this->logChannel)->info('>>> Configuracao de embeddings', [
            'embedding_configured' => $this->embedding->isConfigured(),
            'provider' => $this->embedding->getProvider(),
            'model' => $this->embedding->getModel(),
        ]);

        $finalSuggestions = $this->filterBySimilarity(
            $criticizedSuggestions['approved_suggestions'],
            $store->id
        );

        Log::channel($this->logChannel)->info('<<< Filtro de similaridade concluido', [
            'input_count' => count($criticizedSuggestions['approved_suggestions'] ?? []),
            'output_count' => count($finalSuggestions),
            'filtered_out' => count($criticizedSuggestions['approved_suggestions'] ?? []) - count($finalSuggestions),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // ETAPA 9: Salvar analise e sugestoes
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 9/9: Salvando analise e sugestoes                         │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->saveAnalysis($analysis, $analysisResult, $niche);
        $this->saveSuggestions($analysis, $finalSuggestions);

        Log::channel($this->logChannel)->info('<<< Dados salvos no banco', [
            'analysis_id' => $analysis->id,
            'suggestions_saved' => count($finalSuggestions),
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        // =====================================================
        // CONCLUSAO DO PIPELINE
        // =====================================================
        $totalTime = round((microtime(true) - $pipelineStart), 2);

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     STORE ANALYSIS PIPELINE - CONCLUIDO                         ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Estatisticas finais do pipeline', [
            'analysis_id' => $analysis->id,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'niche' => $niche,
            'niche_subcategory' => $subcategory,
            'niche_configured' => $hasConfiguredNiche,
            'health_score' => $analysisResult['overall_health']['score'] ?? 'N/A',
            'suggestions_final' => count($finalSuggestions),
            'total_time_seconds' => $totalTime,
            'timestamp_end' => now()->toIso8601String(),
        ]);

        // =====================================================
        // LOGGING: PROMPTS COMPLETOS ENVIADOS PARA IA
        // =====================================================
        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     PROMPTS COMPLETOS ENVIADOS PARA IA                          ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');

        foreach ($this->collectedPrompts as $agent => $prompt) {
            if ($prompt) {
                Log::channel($this->logChannel)->info('┌─── PROMPT: '.strtoupper($agent).' AGENT ───────────────────────────────────────┐');
                Log::channel($this->logChannel)->info($prompt);
                Log::channel($this->logChannel)->info('└─── FIM PROMPT: '.strtoupper($agent).' ─────────────────────────────────────────┘');
            }
        }

        // =====================================================
        // LOGGING: EMBEDDINGS COMPLETOS GERADOS
        // =====================================================
        if (! empty($this->collectedEmbeddings)) {
            Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
            Log::channel($this->logChannel)->info('║     EMBEDDINGS COMPLETOS GERADOS                                ║');
            Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');

            foreach ($this->collectedEmbeddings as $index => $embData) {
                Log::channel($this->logChannel)->info('┌─── EMBEDDING #'.($index + 1).' ─────────────────────────────────────────────────┐');
                Log::channel($this->logChannel)->info('Titulo: '.$embData['title']);
                Log::channel($this->logChannel)->info('Texto embedado: '.$embData['text_embedded']);
                Log::channel($this->logChannel)->info('Embedding completo ('.$embData['dimensions'].' dimensoes):');
                Log::channel($this->logChannel)->info(json_encode($embData['embedding']));
                Log::channel($this->logChannel)->info('└─── FIM EMBEDDING #'.($index + 1).' ───────────────────────────────────────────┘');
            }
        }

        // Limpar para próxima execução
        $this->collectedPrompts = [];
        $this->collectedEmbeddings = [];

        return [
            'analysis_id' => $analysis->id,
            'overall_health' => $analysisResult['overall_health'] ?? null,
            'metrics' => $analysisResult['metrics'] ?? null,
            'suggestions_count' => count($finalSuggestions),
            'niche' => $niche,
            'niche_subcategory' => $subcategory,
            'pipeline' => 'full',
        ];
    }

    /**
     * Get store statistics to provide real context about the store operation.
     */
    private function getStoreStats(Store $store): array
    {
        $totalOrders = $store->orders()->count();
        $totalCustomers = $store->customers()->count();
        $totalProducts = $store->products()->count();
        $activeProducts = $store->products()->where('is_active', true)->count();

        // Get first order date to estimate real operation time
        $firstOrder = $store->orders()->orderBy('external_created_at', 'asc')->first();
        $operationTime = $firstOrder
            ? $firstOrder->external_created_at->diffForHumans()
            : $store->created_at->diffForHumans();

        // Recent orders (last 15 days including today)
        $recentOrders = $store->orders()
            ->where('external_created_at', '>=', now()->subDays(15)->startOfDay())
            ->count();

        // Total revenue (paid orders)
        $totalRevenue = $store->orders()
            ->whereIn('payment_status', ['paid', 'pago'])
            ->sum('total');

        return [
            'operation_time' => $operationTime,
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'recent_orders_15d' => $recentOrders,
            'total_revenue' => round($totalRevenue, 2),
            'connected_at' => $store->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Identify the store niche using RAG semantic search.
     * Falls back to keyword matching if RAG is not available.
     */
    private function identifyNiche(Store $store): string
    {
        // Get product categories
        $categories = $store->products()
            ->excludeGifts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles (active products, most recent)
        $productTitles = $store->products()
            ->excludeGifts()
            ->where('is_active', true)
            ->orderByDesc('external_updated_at')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        // Try RAG-based identification first
        $ragNiche = $this->knowledgeBase->identifyNiche(
            $store->name ?? '',
            $categories,
            $productTitles
        );

        if ($ragNiche !== 'general') {
            Log::channel($this->logChannel)->info('Nicho identificado via RAG', [
                'niche' => $ragNiche,
                'method' => 'rag',
            ]);

            return $ragNiche;
        }

        // Fallback to keyword matching
        return $this->identifyNicheByKeywords($store->name ?? '', $categories);
    }

    /**
     * Fallback: Identify niche using keyword matching.
     */
    private function identifyNicheByKeywords(string $storeName, array $categories): string
    {
        // Mapping of keywords to niches
        $nicheMap = [
            // Fashion
            'roupas' => 'fashion',
            'camisetas' => 'fashion',
            'vestidos' => 'fashion',
            'calças' => 'fashion',
            'moda' => 'fashion',
            'fashion' => 'fashion',
            // Electronics
            'celulares' => 'electronics',
            'smartphones' => 'electronics',
            'computadores' => 'electronics',
            'eletrônicos' => 'electronics',
            'electronics' => 'electronics',
            // Food
            'alimentos' => 'food',
            'bebidas' => 'food',
            'comida' => 'food',
            'food' => 'food',
            // Beauty & Hair Care
            'beleza' => 'beauty',
            'cosméticos' => 'beauty',
            'cosmético' => 'beauty',
            'cosmeticos' => 'beauty',
            'maquiagem' => 'beauty',
            'beauty' => 'beauty',
            'cabelo' => 'beauty',
            'cabelos' => 'beauty',
            'hair' => 'beauty',
            'shampoo' => 'beauty',
            'shampoos' => 'beauty',
            'condicionador' => 'beauty',
            'hidratação' => 'beauty',
            'tratamento' => 'beauty',
            'capilar' => 'beauty',
            'lisos' => 'beauty',
            'cacheados' => 'beauty',
            'loiros' => 'beauty',
            'coloração' => 'beauty',
            'tintura' => 'beauty',
            'perfumaria' => 'beauty',
            'skincare' => 'beauty',
            'cuidados' => 'beauty',
            // Home
            'casa' => 'home',
            'decoração' => 'home',
            'móveis' => 'home',
            'home' => 'home',
            // Sports
            'esportes' => 'sports',
            'fitness' => 'sports',
            'sports' => 'sports',
        ];

        // Try to identify niche from store name
        $storeNameLower = strtolower($storeName);
        foreach ($nicheMap as $key => $niche) {
            if (str_contains($storeNameLower, $key)) {
                Log::channel($this->logChannel)->info('Nicho identificado via keywords (nome da loja)', [
                    'niche' => $niche,
                    'method' => 'keyword_store_name',
                    'matched_keyword' => $key,
                ]);

                return $niche;
            }
        }

        // Try to identify niche from categories
        foreach ($categories as $category) {
            $categoryLower = strtolower($category);
            foreach ($nicheMap as $key => $niche) {
                if (str_contains($categoryLower, $key)) {
                    Log::channel($this->logChannel)->info('Nicho identificado via keywords (categoria)', [
                        'niche' => $niche,
                        'method' => 'keyword_category',
                        'matched_keyword' => $key,
                        'category' => $category,
                    ]);

                    return $niche;
                }
            }
        }

        Log::channel($this->logChannel)->info('Nicho nao identificado, usando general', [
            'method' => 'fallback',
        ]);

        return 'general';
    }

    /**
     * Detect both niche and subcategory using RAG semantic analysis.
     */
    private function detectNicheAndSubcategory(Store $store): array
    {
        // Get product categories
        $categories = $store->products()
            ->excludeGifts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles (active products, most recent)
        $productTitles = $store->products()
            ->excludeGifts()
            ->where('is_active', true)
            ->orderByDesc('external_updated_at')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        // Try RAG-based identification for both niche and subcategory
        $detected = $this->knowledgeBase->identifyNicheAndSubcategory(
            $store->name ?? '',
            $categories,
            $productTitles
        );

        Log::channel($this->logChannel)->info('Nicho e subcategoria detectados via RAG', [
            'niche' => $detected['niche'],
            'subcategory' => $detected['subcategory'],
            'method' => 'rag_auto_detect',
        ]);

        // If RAG returned general, try keyword fallback for niche
        if ($detected['niche'] === 'general') {
            $keywordNiche = $this->identifyNicheByKeywords($store->name ?? '', $categories);
            if ($keywordNiche !== 'general') {
                $detected['niche'] = $keywordNiche;
                // Re-detect subcategory for the keyword-identified niche
                $detected['subcategory'] = $this->detectSubcategoryForNiche($store, $keywordNiche);

                Log::channel($this->logChannel)->info('Nicho via keywords, subcategoria re-detectada', [
                    'niche' => $detected['niche'],
                    'subcategory' => $detected['subcategory'],
                    'method' => 'keyword_fallback',
                ]);
            }
        }

        return $detected;
    }

    /**
     * Detect subcategory for a given niche using semantic analysis.
     */
    private function detectSubcategoryForNiche(Store $store, string $niche): string
    {
        // Get product categories
        $categories = $store->products()
            ->excludeGifts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles
        $productTitles = $store->products()
            ->excludeGifts()
            ->where('is_active', true)
            ->orderByDesc('external_updated_at')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        // Use KnowledgeBaseService to identify subcategory
        $detected = $this->knowledgeBase->identifyNicheAndSubcategory(
            $store->name ?? '',
            $categories,
            $productTitles
        );

        // If the detected niche matches, use its subcategory
        // Otherwise, fall back to 'geral'
        if ($detected['niche'] === $niche) {
            Log::channel($this->logChannel)->info('Subcategoria detectada para nicho configurado', [
                'niche' => $niche,
                'subcategory' => $detected['subcategory'],
                'method' => 'rag_subcategory',
            ]);

            return $detected['subcategory'];
        }

        Log::channel($this->logChannel)->info('Subcategoria fallback para nicho configurado', [
            'niche' => $niche,
            'subcategory' => 'geral',
            'method' => 'fallback',
        ]);

        return 'geral';
    }

    /**
     * Analysis period in days.
     */
    private const ANALYSIS_PERIOD_DAYS = 15;

    /**
     * Prepare store data for analysis.
     */
    private function prepareStoreData(Store $store): array
    {
        $periodDays = self::ANALYSIS_PERIOD_DAYS;

        // Orders from the analysis period (15 days including today)
        $orders = $store->orders()
            ->where('external_created_at', '>=', now()->subDays($periodDays)->startOfDay())
            ->get();

        $paidOrders = $orders->filter(fn ($o) => $o->isPaid());

        // Products (excluding gifts/brindes)
        $products = $store->products()->excludeGifts()->get();
        $activeProducts = $products->filter(fn ($p) => $p->is_active && ! $p->isGift());

        return [
            'orders' => [
                'total' => $orders->count(),
                'period_days' => $periodDays,
                'by_payment_status' => $orders->groupBy(fn ($o) => $o->payment_status?->value ?? 'unknown')->map->count()->toArray(),
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
                'no_sales_period' => $this->getNoSalesProducts($store, $periodDays),
            ],
            'inventory' => [
                'total_value' => $products->sum(fn ($p) => ($p->stock_quantity ?? 0) * ($p->cost ?? 0)),
                'low_stock_products' => $products->filter(fn ($p) => $p->hasLowStock())->count(),
                'excess_stock_products' => $products->filter(fn ($p) => ($p->stock_quantity ?? 0) > 100)->count(),
            ],
            'coupons' => $this->getCouponsData($store, $paidOrders),
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
        $soldProductIds = $store->orders()
            ->where('external_created_at', '>=', now()->subDays($days)->startOfDay())
            ->get()
            ->flatMap(function ($order) {
                $items = is_array($order->items) ? $order->items : [];

                return collect($items)->pluck('product_id');
            })
            ->filter()
            ->unique()
            ->toArray();

        return $store->products()
            ->excludeGifts()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('external_id', $soldProductIds)
            ->count();
    }

    /**
     * Get coupons data for analysis.
     */
    private function getCouponsData(Store $store, $paidOrders): array
    {
        // Get registered coupons
        $allCoupons = $store->coupons()->get();
        $activeCoupons = $allCoupons->filter(fn ($c) => $c->isActive());

        // Analyze coupon usage in paid orders
        $ordersWithCoupon = $paidOrders->filter(fn ($o) => ! empty($o->coupon));
        $couponUsage = [];
        $totalDiscountFromCoupons = 0;

        foreach ($ordersWithCoupon as $order) {
            $couponData = $order->coupon;
            $couponCode = $couponData['code'] ?? ($couponData['codes'][0]['code'] ?? 'unknown');

            if (! isset($couponUsage[$couponCode])) {
                $couponUsage[$couponCode] = [
                    'code' => $couponCode,
                    'times_used' => 0,
                    'total_discount' => 0,
                    'orders_value' => 0,
                ];
            }

            $couponUsage[$couponCode]['times_used']++;
            $couponUsage[$couponCode]['total_discount'] += (float) ($order->discount ?? 0);
            $couponUsage[$couponCode]['orders_value'] += (float) $order->total;
            $totalDiscountFromCoupons += (float) ($order->discount ?? 0);
        }

        // Sort by usage
        usort($couponUsage, fn ($a, $b) => $b['times_used'] <=> $a['times_used']);

        // Calculate usage rate
        $totalPaidOrders = $paidOrders->count();
        $usageRate = $totalPaidOrders > 0
            ? round(($ordersWithCoupon->count() / $totalPaidOrders) * 100, 2)
            : 0;

        // Calculate average ticket impact
        $avgTicketWithCoupon = $ordersWithCoupon->count() > 0
            ? $ordersWithCoupon->avg('total')
            : 0;
        $avgTicketWithoutCoupon = $paidOrders->filter(fn ($o) => empty($o->coupon))->avg('total') ?? 0;
        $ticketImpact = $avgTicketWithoutCoupon > 0
            ? round((($avgTicketWithCoupon - $avgTicketWithoutCoupon) / $avgTicketWithoutCoupon) * 100, 2)
            : 0;

        return [
            'registered_total' => $allCoupons->count(),
            'registered_active' => $activeCoupons->count(),
            'registered_expired' => $allCoupons->filter(fn ($c) => $c->isExpired())->count(),
            'period_orders_with_coupon' => $ordersWithCoupon->count(),
            'period_orders_total' => $totalPaidOrders,
            'usage_rate_percent' => $usageRate,
            'total_discount_given' => round($totalDiscountFromCoupons, 2),
            'average_discount_per_order' => $ordersWithCoupon->count() > 0
                ? round($totalDiscountFromCoupons / $ordersWithCoupon->count(), 2)
                : 0,
            'ticket_impact_percent' => $ticketImpact,
            'most_used_coupons' => array_slice($couponUsage, 0, 5),
        ];
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

                // Coletar embedding para logging no final
                $this->collectedEmbeddings[] = [
                    'title' => $finalVersion['title'] ?? 'N/A',
                    'text_embedded' => $textToEmbed,
                    'embedding' => $embedding,
                    'dimensions' => count($embedding),
                ];

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
                'title' => $this->mapOpportunityTypeToLabel($pattern['type'] ?? 'opportunity'),
                'description' => $pattern['opportunity'] ?? $pattern['description'] ?? '',
                'potential_revenue' => $pattern['potential_revenue'] ?? null,
            ];
        }

        return $opportunities;
    }

    /**
     * Map opportunity type to Portuguese label.
     */
    private function mapOpportunityTypeToLabel(string $type): string
    {
        $labels = [
            'coupon_dependency' => 'Dependência de Cupons',
            'bestseller_dominance' => 'Dominância de Bestsellers',
            'inventory_imbalance' => 'Desequilíbrio de Estoque',
            'ticket_medio_positivo' => 'Ticket Médio Positivo',
            'cross_sell' => 'Venda Cruzada',
            'upsell' => 'Upsell',
            'seasonal_trend' => 'Tendência Sazonal',
            'customer_retention' => 'Retenção de Clientes',
            'price_optimization' => 'Otimização de Preços',
            'bundle_opportunity' => 'Oportunidade de Combo',
            'reactivation' => 'Reativação de Clientes',
            'high_margin' => 'Alta Margem',
            'growth_potential' => 'Potencial de Crescimento',
            'market_expansion' => 'Expansão de Mercado',
            'repeat_purchase' => 'Compra Recorrente',
            'opportunity' => 'Oportunidade',
        ];

        return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Format anomalies list for Strategist prompt.
     */
    private function formatAnomaliesList(array $anomalies): string
    {
        if (empty($anomalies)) {
            return 'Nenhuma anomalia identificada.';
        }

        return collect($anomalies)
            ->map(fn ($a) => '- '.($a['description'] ?? 'Anomalia não especificada').' (Severidade: '.($a['severity'] ?? 'medium').')')
            ->implode("\n");
    }

    /**
     * Format patterns list for Strategist prompt.
     */
    private function formatPatternsList(array $patterns): string
    {
        if (empty($patterns)) {
            return 'Nenhum padrão identificado.';
        }

        return collect($patterns)
            ->map(fn ($p) => '- '.($p['type'] ?? 'pattern').': '.($p['description'] ?? $p['opportunity'] ?? 'Padrão não especificado'))
            ->implode("\n");
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
