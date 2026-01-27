<?php

namespace App\Services\AI\Agents;

use App\Enums\PaymentStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Models\SystemSetting;
use App\Services\AI\EmbeddingService;
use App\Services\AI\Memory\HistoryService;
use App\Services\AI\Memory\HistorySummaryService;
use App\Services\AI\PlatformContextService;
use App\Services\AI\RAG\KnowledgeBaseService;
use App\Services\Analysis\FeedbackLoopTrait;
use App\Services\Analysis\HistoricalMetricsTrait;
use App\Services\Analysis\SuggestionDeduplicationTrait;
use App\Services\AnalysisLogService;
use App\Services\ExternalData\ExternalDataAggregator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAnalysisService
{
    use SuggestionDeduplicationTrait;
    use FeedbackLoopTrait;
    use HistoricalMetricsTrait;
    private string $logChannel = 'analysis';

    private array $collectedPrompts = [];

    private array $collectedEmbeddings = [];

    /**
     * Maximum retries per stage before failing permanently.
     */
    private const MAX_STAGE_RETRIES = 3;

    /**
     * Delay between stage retries in seconds.
     */
    private const STAGE_RETRY_DELAYS = [30, 60, 120];

    public function __construct(
        private CollectorAgentService $collector,
        private AnalystAgentService $analyst,
        private StrategistAgentService $strategist,
        private CriticAgentService $critic,
        private KnowledgeBaseService $knowledgeBase,
        private HistoryService $history,
        private EmbeddingService $embedding,
        private PlatformContextService $platformContext,
        private ExternalDataAggregator $externalData,
        private AnalysisLogService $logService
    ) {}

    /**
     * Execute a stage with retry logic and progress saving.
     *
     * @param  callable  $stageCallback  The callback to execute for this stage
     * @param  string  $errorMessage  Custom error message on failure
     * @return mixed The result of the stage execution
     *
     * @throws \RuntimeException When all retries are exhausted
     */
    private function executeStageWithRetry(
        Analysis $analysis,
        int $stageNumber,
        string $stageName,
        callable $stageCallback,
        string $errorMessage = 'Erro no estágio'
    ): mixed {
        $maxRetries = self::MAX_STAGE_RETRIES;
        $currentAttempt = 0;
        $lastException = null;

        while ($currentAttempt < $maxRetries) {
            try {
                $result = $stageCallback();

                // Save progress on success
                $analysis->saveStageProgress($stageNumber, $stageName, [
                    'completed_at' => now()->toIso8601String(),
                    'result_summary' => is_array($result) ? array_keys($result) : 'scalar',
                ]);

                return $result;
            } catch (\Throwable $e) {
                $currentAttempt++;
                $lastException = $e;

                // Check if it's a retryable error (503, timeout, rate limit)
                if (! $this->isRetryableError($e)) {
                    Log::channel($this->logChannel)->error("Stage {$stageName}: Non-retryable error", [
                        'analysis_id' => $analysis->id,
                        'stage' => $stageNumber,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                    ]);
                    throw $e;
                }

                // Log the retry attempt
                Log::channel($this->logChannel)->warning("Stage {$stageName}: Retrying after error", [
                    'analysis_id' => $analysis->id,
                    'stage' => $stageNumber,
                    'attempt' => $currentAttempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                // Mark stage as failed for tracking
                $analysis->markStageFailed($stageNumber, $e->getMessage());

                // Wait before retry with exponential backoff
                if ($currentAttempt < $maxRetries) {
                    $delay = self::STAGE_RETRY_DELAYS[$currentAttempt - 1] ?? 120;
                    sleep($delay);
                }
            }
        }

        // All retries exhausted
        throw new \RuntimeException(
            "{$errorMessage}: {$lastException->getMessage()} (após {$maxRetries} tentativas)",
            0,
            $lastException
        );
    }

    /**
     * Check if an error is retryable (503, timeout, rate limit).
     */
    private function isRetryableError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        // HTTP 503 Service Unavailable / Model overloaded
        if (str_contains($message, '503') || str_contains($message, 'overloaded')) {
            return true;
        }

        // HTTP 429 Rate Limit
        if (str_contains($message, '429') || str_contains($message, 'rate limit') || str_contains($message, 'quota')) {
            return true;
        }

        // Timeout errors
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return true;
        }

        // Connection errors
        if (str_contains($message, 'connection') || str_contains($message, 'network')) {
            return true;
        }

        // Generic 5xx errors
        if (preg_match('/http\s*5\d\d/i', $message)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a stage can be skipped because it's already completed.
     */
    private function shouldSkipStage(Analysis $analysis, int $stageNumber): bool
    {
        return $analysis->isStageCompleted($stageNumber) && $analysis->is_resuming;
    }

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

        $this->logService->startStage($analysis, 1, 'niche_detection');

        try {
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

            $this->logService->completeStage($analysis, 1, [
                'niche' => $niche,
                'subcategory' => $subcategory,
                'niche_configured' => $hasConfiguredNiche,
                'has_goals' => ! empty(array_filter($storeGoals)),
            ]);
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 1, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 2: Buscar contexto historico
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 2/9: Buscando contexto historico                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 2, 'historical_context');

        try {
            $previousAnalyses = $this->history->getPreviousAnalyses($store->id, 5);
            // V4: Usar método detalhado da trait para melhor deduplicação
            $previousSuggestions = $this->getPreviousSuggestionsDetailed($store->id, 50);
            $saturatedThemes = $this->identifySaturatedThemes($previousSuggestions['all']);

            Log::channel($this->logChannel)->info('<<< Contexto historico carregado (V4)', [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
                'accepted_count' => count($previousSuggestions['accepted_titles']),
                'rejected_count' => count($previousSuggestions['rejected_titles']),
                'saturated_themes' => $saturatedThemes,
            ]);

            Log::channel($this->logChannel)->info('<<< Contexto historico carregado', [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 2, [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
            ]);
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 2, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 3: Buscar benchmarks via RAG
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 3/9: Buscando benchmarks via RAG                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 3, 'rag_benchmarks');

        try {
            $benchmarks = $this->knowledgeBase->searchBenchmarks($niche, $subcategory);
            $nicheStrategies = $this->knowledgeBase->searchStrategies($niche, $subcategory);

            Log::channel($this->logChannel)->info('<<< Benchmarks e estrategias carregados', [
                'benchmarks_count' => count($benchmarks),
                'strategies_count' => count($nicheStrategies),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 3, [
                'benchmarks_count' => count($benchmarks),
                'strategies_count' => count($nicheStrategies),
            ]);
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 3, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 3.1: Coletar dados externos de mercado
        // =====================================================
        $stepStart = microtime(true);

        $this->logService->startStage($analysis, 4, 'external_data');

        try {
            // Obter top produtos para usar nas buscas de mercado
            $topProductNames = $store->products()
                ->excludeGifts()
                ->where('is_active', true)
                ->orderByDesc('external_updated_at')
                ->limit(5)
                ->pluck('name')
                ->toArray();

            $externalMarketData = $this->externalData->collect(
                $store,
                $niche,
                $subcategory,
                $topProductNames
            );

            // Log dados ricos dos concorrentes para debugging
            $concorrentesComDadosRicos = 0;
            foreach ($externalMarketData['concorrentes'] ?? [] as $concorrente) {
                if (!empty($concorrente['dados_ricos']['categorias']) ||
                    !empty($concorrente['dados_ricos']['promocoes']) ||
                    !empty($concorrente['dados_ricos']['produtos'])) {
                    $concorrentesComDadosRicos++;
                }
            }

            Log::channel($this->logChannel)->info('<<< Dados externos coletados', [
                'trends_sucesso' => $externalMarketData['dados_mercado']['google_trends']['sucesso'] ?? false,
                'market_sucesso' => $externalMarketData['dados_mercado']['precos_mercado']['sucesso'] ?? false,
                'tem_concorrentes' => $externalMarketData['tem_concorrentes'] ?? false,
                'concorrentes_com_dados_ricos' => $concorrentesComDadosRicos,
                'total_concorrentes' => count($externalMarketData['concorrentes'] ?? []),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 4, [
                'trends_sucesso' => $externalMarketData['dados_mercado']['google_trends']['sucesso'] ?? false,
                'market_sucesso' => $externalMarketData['dados_mercado']['precos_mercado']['sucesso'] ?? false,
                'tem_concorrentes' => $externalMarketData['tem_concorrentes'] ?? false,
            ]);
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 4, $e->getMessage());
            // Não falhar o pipeline por erro em dados externos
            $externalMarketData = [];
        }

        // =====================================================
        // ETAPA 5: Executar Collector Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 5/9: Executando COLLECTOR AGENT                           │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 5, 'collector_agent');

        try {
            $storeStats = $this->getStoreStats($store);

            Log::channel($this->logChannel)->info('>>> Dados da loja coletados', [
                'total_orders' => $storeStats['total_orders'],
                'total_products' => $storeStats['total_products'],
                'total_customers' => $storeStats['total_customers'],
                'total_revenue' => $storeStats['total_revenue'],
            ]);

            $platform = $store->platform?->value ?? 'nuvemshop';
            $platformName = $store->platform?->label() ?? 'Nuvemshop';

            $collectorContext = $this->collector->execute([
                'store_name' => $store->name,
                'platform' => $platform,
                'platform_name' => $platformName,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'niche_subcategory' => $subcategory, // backward compatibility
                'store_stats' => $storeStats,
                'previous_analyses' => $previousAnalyses,
                'previous_suggestions' => $previousSuggestions,
                'benchmarks' => $benchmarks,
                'structured_benchmarks' => $structuredBenchmarks,
                'store_goals' => $storeGoals,
                'external_data' => $externalMarketData,
            ]);

            // Coletar prompt para logging no final
            $this->collectedPrompts['collector'] = $collectorContext['_prompt_used'] ?? null;
            unset($collectorContext['_prompt_used']);

            Log::channel($this->logChannel)->info('<<< Collector Agent concluido', [
                'keys_returned' => array_keys($collectorContext),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 5, [
                'keys_returned' => array_keys($collectorContext),
            ], 'collector');
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 5, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 6: Executar Analyst Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 6/9: Executando ANALYST AGENT                             │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 6, 'analyst_agent');

        try {
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

            // V4: Buscar métricas históricas da própria loja para detectar anomalias
            $historicalMetrics = $this->getHistoricalMetrics($store, 3);
            Log::channel($this->logChannel)->info('>>> V4: Metricas historicas carregadas', [
                'available' => $historicalMetrics['available'] ?? false,
                'analyses_count' => $historicalMetrics['analyses_count'] ?? 0,
            ]);

            $analysisResult = $this->analyst->execute([
                'store_name' => $store->name,
                'platform' => $platform,
                'platform_name' => $platformName,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'niche_subcategory' => $subcategory, // backward compatibility
                'ticket_medio' => round($storeData['orders']['average_order_value'] ?? 0, 2),
                'pedidos_mes' => $storeData['orders']['total'] ?? 0,
                'orders_summary' => $storeData['orders'],
                'products_summary' => $storeData['products'],
                'inventory_summary' => $storeData['inventory'],
                'coupons_summary' => $storeData['coupons'],
                'benchmarks' => $benchmarks,
                'structured_benchmarks' => $structuredBenchmarks,
                'store_goals' => $storeGoals,
                'format_version' => $formatVersion,
                'history_summary' => $historySummary,
                'historical_metrics' => $historicalMetrics, // V4: métricas históricas
                'external_data' => $externalMarketData,
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

            $this->logService->completeStage($analysis, 6, [
                'health_score' => $analysisResult['overall_health']['score'] ?? null,
                'health_classification' => $analysisResult['overall_health']['classification'] ?? null,
                'anomalies_count' => count($analysisResult['anomalies'] ?? []),
                'patterns_count' => count($analysisResult['identified_patterns'] ?? []),
            ], 'analyst');
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 6, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 7: Executar Strategist Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 7/9: Executando STRATEGIST AGENT                          │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 7, 'strategist_agent');

        try {
            // Calculate metrics for the Strategist
            $outOfStockPct = $storeData['products']['total'] > 0
                ? round(($storeData['products']['out_of_stock'] / $storeData['products']['total']) * 100, 2)
                : 0;
            $ticketMedio = round($storeData['orders']['average_order_value'] ?? 0, 2);
            $ordersTotal = $storeData['orders']['total'] ?? 0;
            $faturamentoEstimado = round($ticketMedio * $ordersTotal, 2);

            // Get platform resources for the Strategist prompt
            $platformResources = $this->platformContext->formatResourcesForPrompt($platform);

            $generatedSuggestions = $this->strategist->execute([
                'store_name' => $store->name,
                'platform' => $platform,
                'platform_name' => $platformName,
                'platform_resources' => $platformResources,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'niche_subcategory' => $subcategory, // backward compatibility
                'collector_context' => $collectorContext,
                'analysis' => $analysisResult,
                'previous_suggestions' => $previousSuggestions,
                'rag_strategies' => $nicheStrategies,
                'structured_benchmarks' => $structuredBenchmarks,
                'store_goals' => $storeGoals,
                'external_data' => $externalMarketData,

                // Métricas diretas para o Strategist
                'operation_time' => $storeStats['operation_time'] ?? 'não informado',
                'orders_total' => $ordersTotal,
                'ticket_medio' => $ticketMedio,
                'pedidos_mes' => $ordersTotal,
                'faturamento_estimado' => $faturamentoEstimado,
                'ticket_benchmark' => $structuredBenchmarks['ticket_medio']['media'] ?? 0,
                'benchmark_ticket_min' => $structuredBenchmarks['ticket_medio']['min'] ?? 0,
                'benchmark_ticket_max' => $structuredBenchmarks['ticket_medio']['max'] ?? 0,
                'health_score' => $analysisResult['overall_health']['score'] ?? 0,
                'health_classification' => $analysisResult['overall_health']['classification'] ?? 'não calculado',
                'active_products' => $storeData['products']['active'] ?? 0,
                'out_of_stock' => $storeData['products']['out_of_stock'] ?? 0,
                'out_of_stock_pct' => $outOfStockPct,
                'coupon_rate' => $storeData['coupons']['usage_rate'] ?? 0,
                'coupon_impact' => $storeData['coupons']['ticket_impact'] ?? 0,
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

            $this->logService->completeStage($analysis, 7, [
                'suggestions_generated' => count($generatedSuggestions['suggestions'] ?? []),
            ], 'strategist');
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 7, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 8: Executar Critic Agent
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 8/9: Executando CRITIC AGENT                              │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 8, 'critic_agent');

        try {
            $criticizedSuggestions = $this->critic->execute([
                'store_name' => $store->name,
                'platform' => $platform,
                'platform_name' => $platformName,
                'platform_resources' => $platformResources,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'suggestions' => $generatedSuggestions['suggestions'],
                'previous_suggestions' => $previousSuggestions,
                'ticket_medio' => $ticketMedio,
                'pedidos_mes' => $ordersTotal,
                'faturamento_estimado' => $faturamentoEstimado,
                'out_of_stock_pct' => $outOfStockPct,
                'external_data' => $externalMarketData,
                'store_context' => [
                    'niche' => $niche,
                    'subcategory' => $subcategory,
                    'platform' => $platform,
                    'platform_name' => $platformName,
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

            $this->logService->completeStage($analysis, 8, [
                'suggestions_approved' => count($criticizedSuggestions['approved_suggestions'] ?? []),
                'suggestions_removed' => count($criticizedSuggestions['removed_suggestions'] ?? []),
                'average_quality' => $criticizedSuggestions['general_analysis']['average_quality'] ?? null,
            ], 'critic');
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 8, $e->getMessage());
            throw $e;
        }

        // =====================================================
        // ETAPA 9: Filtrar por similaridade
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 9/9: Filtrando por similaridade (embeddings)              │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $this->logService->startStage($analysis, 9, 'similarity_filtering');

        try {
            Log::channel($this->logChannel)->info('>>> Configuracao de embeddings', [
                'embedding_configured' => $this->embedding->isConfigured(),
                'provider' => $this->embedding->getProvider(),
                'model' => $this->embedding->getModel(),
            ]);

            $filteredSuggestions = $this->filterBySimilarity(
                $criticizedSuggestions['approved_suggestions'],
                $store->id
            );

            Log::channel($this->logChannel)->info('<<< Filtro de similaridade concluido', [
                'input_count' => count($criticizedSuggestions['approved_suggestions'] ?? []),
                'output_count' => count($filteredSuggestions),
                'filtered_out' => count($criticizedSuggestions['approved_suggestions'] ?? []) - count($filteredSuggestions),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 9, [
                'input_count' => count($criticizedSuggestions['approved_suggestions'] ?? []),
                'output_count' => count($filteredSuggestions),
                'filtered_out' => count($criticizedSuggestions['approved_suggestions'] ?? []) - count($filteredSuggestions),
                'embedding_configured' => $this->embedding->isConfigured(),
            ]);
        } catch (\Exception $e) {
            $this->logService->failStage($analysis, 9, $e->getMessage());
            throw $e;
        }

        // Garantir mínimo de 5 sugestões APÓS o filtro de similaridade
        // (O fallback precisa acontecer DEPOIS do filtro para compensar remoções)
        $finalSuggestions = $this->ensureMinimumSuggestions(
            $filteredSuggestions,
            $generatedSuggestions['suggestions'] ?? [],
            [
                'niche' => $niche,
                'subcategory' => $subcategory,
                'platform' => $platform,
                'platform_name' => $platformName,
                'ticket_medio' => $ticketMedio,
                'pedidos_mes' => $ordersTotal,
            ]
        );

        // =====================================================
        // V4: Validação final de unicidade antes de salvar
        // =====================================================
        $beforeValidation = count($finalSuggestions);
        $finalSuggestions = $this->validateSuggestionUniqueness($finalSuggestions, $previousSuggestions['all']);

        // Logar estatísticas de deduplicação
        $this->logDeduplicationStats($analysis->id, [
            'from_strategist' => count($generatedSuggestions['suggestions'] ?? []),
            'from_critic' => count($criticizedSuggestions['approved_suggestions'] ?? []),
            'after_similarity' => $beforeValidation,
            'final_count' => count($finalSuggestions),
            'saturated_themes' => $saturatedThemes ?? [],
        ]);

        // =====================================================
        // Salvar analise e sugestoes
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ Salvando analise e sugestoes                                     │');
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
            ->where('payment_status', PaymentStatus::Paid)
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
     * Checks both against existing suggestions in DB and within the current batch.
     */
    private function filterBySimilarity(array $suggestions, int $storeId): array
    {
        // First, filter out intra-batch duplicates (suggestions similar to each other)
        $suggestions = $this->filterIntraBatchDuplicates($suggestions);

        if (! $this->embedding->isConfigured()) {
            // Skip embedding-based similarity check if not configured
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

                // Check similarity with existing suggestions in DB
                // Threshold de 0.90 = apenas sugestões muito similares (90%+) são filtradas
                // Isso permite mais variação entre análises consecutivas
                if (! $this->embedding->isTooSimilar($embedding, $storeId, 0.90)) {
                    $suggestion['embedding'] = $embedding;
                    $filteredSuggestions[] = $suggestion;
                } else {
                    Log::info('Suggestion filtered due to DB similarity: '.$finalVersion['title']);
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
     * Filter out duplicate suggestions within the same batch.
     * Uses title normalization and semantic similarity to detect duplicates.
     */
    private function filterIntraBatchDuplicates(array $suggestions): array
    {
        $filtered = [];
        $seenTitles = [];
        $seenNormalizedTitles = [];

        foreach ($suggestions as $suggestion) {
            $finalVersion = $suggestion['final_version'] ?? [];
            $title = $finalVersion['title'] ?? '';
            $normalizedTitle = $this->normalizeTitle($title);

            // Check for exact duplicate
            if (in_array(strtolower(trim($title)), $seenTitles)) {
                Log::channel($this->logChannel)->info('Intra-batch: Duplicate title filtered', [
                    'title' => $title,
                    'reason' => 'exact_match',
                ]);

                continue;
            }

            // Check for semantically similar title
            $isDuplicate = false;
            foreach ($seenNormalizedTitles as $seenNormalized => $seenOriginal) {
                $similarity = $this->calculateTitleSimilarity($normalizedTitle, $seenNormalized);
                if ($similarity >= 0.75) { // 75% similarity threshold for titles
                    Log::channel($this->logChannel)->info('Intra-batch: Similar title filtered', [
                        'title' => $title,
                        'similar_to' => $seenOriginal,
                        'similarity' => round($similarity * 100, 1).'%',
                        'reason' => 'semantic_similarity',
                    ]);
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                continue;
            }

            // Not a duplicate - add to filtered list
            $seenTitles[] = strtolower(trim($title));
            $seenNormalizedTitles[$normalizedTitle] = $title;
            $filtered[] = $suggestion;
        }

        $filteredCount = count($suggestions) - count($filtered);
        if ($filteredCount > 0) {
            Log::channel($this->logChannel)->info('Intra-batch deduplication result', [
                'input' => count($suggestions),
                'output' => count($filtered),
                'filtered' => $filteredCount,
            ]);
        }

        return $filtered;
    }

    /**
     * Normalize a title for comparison.
     * Removes common words, normalizes whitespace, and extracts key concepts.
     */
    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));

        // Remove common stopwords and filler words
        $stopwords = [
            'de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'a', 'o', 'as', 'os',
            'e', 'ou', 'um', 'uma', 'uns', 'umas', 'no', 'na', 'nos', 'nas',
            'pelo', 'pela', 'pelos', 'pelas', 'ao', 'aos', 'à', 'às',
            'implementar', 'otimizar', 'criar', 'desenvolver', 'lançar', 'ativar',
            'configurar', 'melhorar', 'aprimorar', 'revisar', 'analisar', 'oferecer',
            'estratégia', 'estratégico', 'estratégica', 'estratégicos', 'estratégicas',
            'gestão', 'gerenciamento', 'sistema', 'programa', 'plano',
            'produtos', 'produto', 'clientes', 'cliente', 'loja', 'lojas',
            'haircare', 'beleza', 'cosmético', 'cosméticos', 'capilar', 'cabelo', 'cabelos',
            'nuvemshop', 'ecommerce', 'e-commerce',
            'priorizar', 'proativo', 'proativa', 'funcionalidade',
        ];

        $words = preg_split('/\s+/', $title);
        $filteredWords = array_filter($words, fn ($w) => ! in_array($w, $stopwords) && strlen($w) > 2);

        // Sort to make comparison order-independent
        sort($filteredWords);

        return implode(' ', $filteredWords);
    }

    /**
     * Calculate similarity between two normalized titles using Jaccard similarity.
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $words1 = array_unique(preg_split('/\s+/', $title1));
        $words2 = array_unique(preg_split('/\s+/', $title2));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    /**
     * Save analysis results.
     * Supports both V3 format and legacy format.
     */
    private function saveAnalysis(Analysis $analysis, array $analysisResult, string $niche): void
    {
        // V3 format: health_score is an object with score and classificacao
        // Legacy format: overall_health is an object with score and classification
        $healthV3 = $analysisResult['health_score'] ?? [];
        $healthLegacy = $analysisResult['overall_health'] ?? [];

        // Determine score (V3: health_score.score_final after override, Legacy: overall_health.score)
        $score = $healthV3['score_final'] ?? $healthV3['score'] ?? $healthLegacy['score'] ?? 50;

        // Determine status (V3: health_score.classificacao, Legacy: overall_health.classification)
        $status = $healthV3['classificacao'] ?? $healthLegacy['classification'] ?? 'attention';

        // Determine insight (V3: resumo_executivo string, Legacy: overall_health.main_points[0])
        $insight = $analysisResult['resumo_executivo']
            ?? $healthLegacy['main_points'][0]
            ?? $healthV3['explicacao']
            ?? 'Análise concluída com sucesso';

        $analysis->markAsCompleted([
            'summary' => [
                'health_score' => $score,
                'health_status' => $status,
                'main_insight' => $insight,
            ],
            'suggestions' => [], // Legacy field - suggestions are now in separate table
            'alerts' => $this->extractAlerts($analysisResult),
            'opportunities' => $this->extractOpportunities($analysisResult),
        ]);
    }

    /**
     * Extract alerts from analysis.
     * Returns maximum 3 alerts with warm colors only (danger/warning).
     */
    private function extractAlerts(array $analysis): array
    {
        $alerts = [];

        // V4 format: alertas.criticos, alertas.atencao, alertas.monitoramento
        $alertasV4 = $analysis['alertas'] ?? [];

        // Críticos primeiro (danger)
        foreach ($alertasV4['criticos'] ?? [] as $alerta) {
            $alerts[] = [
                'type' => 'danger',
                'title' => $alerta['titulo'] ?? $this->mapAlertTypeToLabel($alerta['tipo'] ?? 'alert'),
                'message' => $alerta['descricao'] ?? '',
            ];
        }

        // Atenção em segundo (warning)
        foreach ($alertasV4['atencao'] ?? [] as $alerta) {
            $alerts[] = [
                'type' => 'warning',
                'title' => $alerta['titulo'] ?? $this->mapAlertTypeToLabel($alerta['tipo'] ?? 'alert'),
                'message' => $alerta['descricao'] ?? '',
            ];
        }

        // Se tiver alertas V4, usar eles; senão, fallback para formato legado (anomalies)
        if (! empty($alerts)) {
            return array_slice($alerts, 0, 3);
        }

        // Fallback: formato legado com anomalies
        $anomalies = $analysis['anomalies'] ?? [];

        usort($anomalies, function ($a, $b) {
            $severityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $severityA = $severityOrder[$a['severity'] ?? 'medium'] ?? 3;
            $severityB = $severityOrder[$b['severity'] ?? 'medium'] ?? 3;

            return $severityA <=> $severityB;
        });

        $anomalies = array_slice($anomalies, 0, 3);

        foreach ($anomalies as $anomaly) {
            $alerts[] = [
                'type' => $this->mapSeverityToWarmAlertType($anomaly['severity'] ?? 'medium'),
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
            // Tipos V3 (AnalystAgentPrompt)
            'inventory_management' => 'Gestão de Estoque',
            'sales_performance' => 'Desempenho de Vendas',
            'pricing_strategy' => 'Estratégia de Preços',
            'customer_behavior' => 'Comportamento do Cliente',
            'order_management' => 'Gestão de Pedidos',
            // Tipos legados
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
     * Map severity to warm alert type only (no blue/info - only danger/warning).
     */
    private function mapSeverityToWarmAlertType(string $severity): string
    {
        return $severity === 'high' ? 'danger' : 'warning';
    }

    /**
     * Extract opportunities from analysis.
     * Supports both V3 format (oportunidades) and legacy format (identified_patterns).
     */
    private function extractOpportunities(array $analysis): array
    {
        $opportunities = [];

        // V3 format: oportunidades array
        $rawOpportunities = $analysis['oportunidades'] ?? $analysis['identified_patterns'] ?? [];

        foreach ($rawOpportunities as $opp) {
            $opportunities[] = [
                'title' => $opp['titulo'] ?? $this->mapOpportunityTypeToLabel($opp['type'] ?? $opp['tipo'] ?? 'opportunity'),
                'description' => $opp['descricao'] ?? $opp['description'] ?? $opp['opportunity'] ?? '',
                'potential_revenue' => $opp['potencial_receita'] ?? $opp['potential_revenue'] ?? null,
                'type' => $opp['tipo'] ?? $opp['type'] ?? 'opportunity',
                'base_dados' => $opp['base_dados'] ?? null,
                'calculo_roi' => $opp['calculo_roi'] ?? null,
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

    /**
     * Ensure we ALWAYS have exactly 9 suggestions with 3-3-3 distribution.
     * This is a CRITICAL business requirement - the client must always receive 9 suggestions.
     *
     * Priority: Use Strategist suggestions that were filtered by similarity before generic fallbacks.
     */
    private function ensureMinimumSuggestions(array $approved, array $allGenerated, array $context): array
    {
        // REGRA DE NEGÓCIO CRÍTICA: SEMPRE 9 sugestões com distribuição 3-3-3
        $required = 9;
        $perCategory = 3;

        // Contar distribuição atual
        $distribution = ['high' => [], 'medium' => [], 'low' => []];
        foreach ($approved as $suggestion) {
            $impact = $suggestion['final_version']['expected_impact'] ?? 'medium';
            if (! isset($distribution[$impact])) {
                $impact = 'medium';
            }
            $distribution[$impact][] = $suggestion;
        }

        Log::channel($this->logChannel)->info('Distribuicao atual de sugestoes aprovadas', [
            'high' => count($distribution['high']),
            'medium' => count($distribution['medium']),
            'low' => count($distribution['low']),
            'total' => count($approved),
        ]);

        // Identificar títulos já aprovados para não duplicar (usando normalização)
        $approvedTitles = array_map(
            fn ($s) => strtolower(trim($s['final_version']['title'] ?? '')),
            $approved
        );
        $approvedNormalizedTitles = array_map(
            fn ($s) => $this->normalizeTitle($s['final_version']['title'] ?? ''),
            $approved
        );

        // Agrupar sugestões do Strategist por impact para preencher lacunas
        $strategistByImpact = ['high' => [], 'medium' => [], 'low' => []];
        foreach ($allGenerated as $suggestion) {
            $impact = $suggestion['expected_impact'] ?? 'medium';
            if (! isset($strategistByImpact[$impact])) {
                $impact = 'medium';
            }
            $title = strtolower(trim($suggestion['title'] ?? ''));
            $normalizedTitle = $this->normalizeTitle($suggestion['title'] ?? '');

            // Check for exact duplicate
            if (in_array($title, $approvedTitles)) {
                continue;
            }

            // Check for semantically similar title
            $isSimilar = false;
            foreach ($approvedNormalizedTitles as $approvedNormalized) {
                if ($this->calculateTitleSimilarity($normalizedTitle, $approvedNormalized) >= 0.75) {
                    $isSimilar = true;
                    break;
                }
            }

            if (! $isSimilar) {
                $strategistByImpact[$impact][] = $suggestion;
            }
        }

        // Preencher cada categoria para atingir exatamente 3
        $priority = count($approved) + 1;
        foreach (['high', 'medium', 'low'] as $impact) {
            $needed = $perCategory - count($distribution[$impact]);
            if ($needed <= 0) {
                continue;
            }

            // Primeiro tentar do Strategist
            foreach ($strategistByImpact[$impact] as $suggestion) {
                if ($needed <= 0) {
                    break;
                }

                $converted = [
                    'final_version' => [
                        'category' => $suggestion['category'] ?? 'general',
                        'title' => $suggestion['title'] ?? '',
                        'description' => $suggestion['description'] ?? '',
                        'recommended_action' => $suggestion['recommended_action'] ?? '',
                        'expected_impact' => $impact,
                        'target_metrics' => $suggestion['target_metrics'] ?? [],
                        'specific_data' => $suggestion['specific_data'] ?? $suggestion['roi_estimate'] ?? [],
                        'data_justification' => $suggestion['data_justification'] ?? '',
                    ],
                    'quality_score' => 6.0,
                    'final_priority' => $priority++,
                    'recovered_from_filter' => true,
                ];

                $distribution[$impact][] = $converted;
                $approvedTitles[] = strtolower(trim($suggestion['title'] ?? ''));
                $approvedNormalizedTitles[] = $this->normalizeTitle($suggestion['title'] ?? '');
                $needed--;

                Log::channel($this->logChannel)->info('Sugestao recuperada do Strategist para '.$impact, [
                    'title' => $suggestion['title'] ?? 'N/A',
                ]);
            }

            // Se ainda falta, usar fallbacks
            if ($needed > 0) {
                $fallbacks = $this->generateFallbackSuggestionsForImpact($needed, $impact, $context, $priority);
                foreach ($fallbacks as $fallback) {
                    $distribution[$impact][] = $fallback;
                    $priority++;
                }
            }
        }

        // Combinar e reassinar prioridades
        $result = [];
        $priority = 1;
        foreach (['high', 'medium', 'low'] as $impact) {
            foreach (array_slice($distribution[$impact], 0, $perCategory) as $suggestion) {
                $suggestion['final_priority'] = $priority++;
                $result[] = $suggestion;
            }
        }

        Log::channel($this->logChannel)->info('Distribuicao final de sugestoes', [
            'high' => count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'high')),
            'medium' => count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'medium')),
            'low' => count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'low')),
            'total' => count($result),
        ]);

        return $result;
    }

    /**
     * Generate fallback suggestions for a specific impact level.
     */
    private function generateFallbackSuggestionsForImpact(int $count, string $impact, array $context, int $startPriority): array
    {
        $fallbacks = $this->generateFallbackSuggestions($count * 3, $context, $startPriority);

        // Filtrar pelo impact desejado e pegar apenas o necessário
        $filtered = array_filter($fallbacks, fn ($s) => ($s['final_version']['expected_impact'] ?? 'medium') === $impact);

        // Se não tiver suficientes do impact desejado, forçar o impact
        if (count($filtered) < $count) {
            $filtered = array_slice($fallbacks, 0, $count);
            foreach ($filtered as &$f) {
                $f['final_version']['expected_impact'] = $impact;
            }
        }

        return array_slice($filtered, 0, $count);
    }

    /**
     * Generate fallback suggestions when not enough were generated/approved.
     */
    private function generateFallbackSuggestions(int $count, array $context, int $startPriority): array
    {
        $niche = $context['niche'] ?? 'general';
        $subcategory = $context['subcategory'] ?? 'geral';
        $platformName = $context['platform_name'] ?? 'Nuvemshop';
        $ticketMedio = $context['ticket_medio'] ?? 100;
        $pedidosMes = $context['pedidos_mes'] ?? 50;

        // 9 sugestões fallback universais (funcionam para qualquer nicho)
        // IMPORTANTE: Manter pelo menos 9 templates para garantir o mínimo exigido
        $fallbackTemplates = [
            [
                'category' => 'customer',
                'title' => 'Criar programa de indicação com benefício duplo',
                'description' => 'Implementar um sistema onde clientes indicam amigos e ambos ganham desconto. Isso gera novos clientes com custo de aquisição muito baixo.',
                'recommended_action' => "1. Definir desconto de 10-15% para indicador e indicado\n2. Criar cupom único por cliente para rastreamento\n3. Comunicar programa em emails pós-compra\n4. Limitar a 1 indicação por mês para evitar abuso",
                'expected_impact' => 'high',
                'target_metrics' => ['novos_clientes', 'cac'],
            ],
            [
                'category' => 'marketing',
                'title' => 'Implementar automação de email por comportamento',
                'description' => 'Criar sequência automatizada de emails baseada no comportamento do cliente: boas-vindas, carrinho abandonado, pós-compra e reativação.',
                'recommended_action' => "1. Configurar email de boas-vindas com cupom de primeira compra\n2. Criar sequência de carrinho abandonado (1h, 24h, 72h)\n3. Implementar email pós-compra pedindo avaliação\n4. Segmentar base por data da última compra",
                'expected_impact' => 'high',
                'target_metrics' => ['conversao', 'recompra', 'ticket_medio'],
            ],
            [
                'category' => 'marketing',
                'title' => 'Otimizar páginas de produto para buscadores',
                'description' => 'Melhorar títulos, descrições e imagens dos produtos para aumentar tráfego orgânico e reduzir dependência de mídia paga.',
                'recommended_action' => "1. Revisar títulos incluindo palavras-chave relevantes\n2. Expandir descrições com benefícios e especificações\n3. Adicionar alt-text em todas as imagens\n4. Criar FAQ nos produtos mais vendidos",
                'expected_impact' => 'medium',
                'target_metrics' => ['trafego_organico', 'conversao'],
            ],
            [
                'category' => 'product',
                'title' => 'Revisar e otimizar catálogo de produtos',
                'description' => 'Revisar catálogo para identificar produtos de baixo giro que ocupam estoque e capital. Focar em produtos com melhor margem e velocidade de venda.',
                'recommended_action' => "1. Listar produtos sem venda nos últimos 90 dias\n2. Calcular margem líquida por produto\n3. Definir estratégia para produtos encalhados (promoção ou descontinuação)\n4. Reforçar estoque dos 20% que geram 80% das vendas",
                'expected_impact' => 'medium',
                'target_metrics' => ['margem', 'giro_estoque'],
            ],
            [
                'category' => 'conversion',
                'title' => 'Reduzir fricção no processo de compra',
                'description' => 'Reduzir fricção no checkout para diminuir abandono de carrinho. Cada campo removido aumenta a taxa de conversão.',
                'recommended_action' => "1. Remover campos não essenciais do formulário\n2. Oferecer opção de checkout como visitante\n3. Mostrar resumo do pedido sempre visível\n4. Garantir que frete seja calculado antes do checkout",
                'expected_impact' => 'high',
                'target_metrics' => ['conversao', 'abandono_carrinho'],
            ],
            [
                'category' => 'pricing',
                'title' => 'Criar estratégia de kits e combos',
                'description' => 'Agrupar produtos complementares em kits com desconto para aumentar o ticket médio e facilitar a decisão de compra do cliente.',
                'recommended_action' => "1. Identificar produtos frequentemente comprados juntos\n2. Criar 3-5 kits com desconto de 10-15% vs compra separada\n3. Destacar economia na página do kit\n4. Posicionar kits em destaque na home e categorias",
                'expected_impact' => 'high',
                'target_metrics' => ['ticket_medio', 'itens_por_pedido'],
            ],
            [
                'category' => 'customer',
                'title' => 'Implementar programa de fidelidade simples',
                'description' => 'Criar sistema de pontos ou cashback para incentivar recompra e aumentar o lifetime value dos clientes.',
                'recommended_action' => "1. Definir mecânica simples (ex: 5% de cashback em pontos)\n2. Criar níveis de benefício por volume de compras\n3. Comunicar saldo em todos os emails transacionais\n4. Oferecer bônus de pontos em datas especiais",
                'expected_impact' => 'medium',
                'target_metrics' => ['recompra', 'ltv', 'retencao'],
            ],
            [
                'category' => 'inventory',
                'title' => 'Ativar notificação de produto disponível',
                'description' => 'Implementar funcionalidade "Avise-me quando chegar" para produtos sem estoque, capturando demanda e recuperando vendas perdidas.',
                'recommended_action' => "1. Ativar botão de avise-me em produtos sem estoque\n2. Configurar email automático quando produto voltar\n3. Oferecer cupom exclusivo no email de disponibilidade\n4. Monitorar taxa de conversão dos alertas",
                'expected_impact' => 'medium',
                'target_metrics' => ['vendas_recuperadas', 'conversao'],
            ],
            [
                'category' => 'marketing',
                'title' => 'Criar conteúdo educativo sobre os produtos',
                'description' => 'Desenvolver guias, tutoriais e conteúdo que eduque o cliente sobre como usar e escolher os produtos, aumentando confiança e conversão.',
                'recommended_action' => "1. Criar guia de escolha para principais categorias\n2. Produzir vídeos curtos de demonstração de uso\n3. Publicar artigos respondendo dúvidas frequentes\n4. Adicionar seção de dicas nas páginas de produto",
                'expected_impact' => 'medium',
                'target_metrics' => ['conversao', 'tempo_no_site', 'autoridade'],
            ],
        ];

        $fallbacks = [];
        $priority = $startPriority;

        for ($i = 0; $i < $count && $i < count($fallbackTemplates); $i++) {
            $template = $fallbackTemplates[$i];

            // Calcular ROI estimado conservador
            $roiBase = round($ticketMedio * $pedidosMes * 0.03, 2); // 3% de impacto conservador

            $fallbacks[] = [
                'final_version' => [
                    'category' => $template['category'],
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'recommended_action' => $template['recommended_action'],
                    'expected_impact' => $template['expected_impact'],
                    'target_metrics' => $template['target_metrics'],
                    'specific_data' => [
                        'roi_estimado' => 'R$ '.number_format($roiBase, 2, ',', '.').'/mês (conservador)',
                        'base_calculo' => "Ticket médio R$ {$ticketMedio} × {$pedidosMes} pedidos × 3%",
                    ],
                    'data_justification' => "Sugestão baseada em melhores práticas de e-commerce para o nicho {$niche}/{$subcategory} na {$platformName}.",
                ],
                'quality_score' => 5.0,
                'final_priority' => $priority++,
                'forced_approval' => true,
                'is_generic_fallback' => true,
            ];

            Log::channel($this->logChannel)->info('Sugestao generica de fallback gerada', [
                'title' => $template['title'],
                'priority' => $priority - 1,
            ]);
        }

        return $fallbacks;
    }
}
