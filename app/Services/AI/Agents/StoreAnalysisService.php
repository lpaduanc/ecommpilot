<?php

namespace App\Services\AI\Agents;

use App\Enums\PaymentStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Models\SystemSetting;
use App\Services\AI\AnalysisRouter;
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
    use FeedbackLoopTrait;
    use HistoricalMetricsTrait;
    use SuggestionDeduplicationTrait;

    private string $logChannel = 'analysis';

    private array $collectedPrompts = [];

    private array $collectedEmbeddings = [];

    private ?array $premiumSummary = null;

    private array $rawAgentOutputs = [];

    /**
     * Maximum retries per stage before failing permanently.
     */
    private const MAX_STAGE_RETRIES = 3;

    /**
     * Delay between stage retries in seconds.
     */
    private const STAGE_RETRY_DELAYS = [30, 60, 120];

    public function __construct(
        private ProfileSynthesizerService $profileSynthesizer,
        private CollectorAgentService $collector,
        private AnalystAgentService $analyst,
        private StrategistAgentService $strategist,
        private CriticAgentService $critic,
        private KnowledgeBaseService $knowledgeBase,
        private HistoryService $history,
        private EmbeddingService $embedding,
        private PlatformContextService $platformContext,
        private ExternalDataAggregator $externalData,
        private AnalysisLogService $logService,
        private AnalysisRouter $analysisRouter
    ) {}

    /**
     * Log full data as formatted JSON without truncation.
     *
     * @param  string  $title  Title/header for the log section
     * @param  mixed  $data  Data to log (array, object, scalar, or null)
     * @param  string  $level  Log level (info, debug, warning)
     */
    private function logFullData(string $title, mixed $data, string $level = 'info'): void
    {
        $separator = '═══════════════════════════════════════════════════════════════════';

        // Normalize data to be JSON-serializable
        if (is_null($data)) {
            $data = [];
        } elseif (is_scalar($data)) {
            // Convert scalar (int, string, bool, float) to array with value
            $data = ['value' => $data];
        } elseif (is_object($data)) {
            // Convert object to array
            $data = (array) $data;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::channel($this->logChannel)->$level($separator);
        Log::channel($this->logChannel)->$level("█ {$title}");
        Log::channel($this->logChannel)->$level($separator);
        Log::channel($this->logChannel)->$level($json);
        Log::channel($this->logChannel)->$level($separator);
    }

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

        // Resolver configuração do módulo de análise (financial, conversion, etc.)
        $moduleConfig = $this->analysisRouter->resolve(
            $analysis->analysis_type?->value ?? 'general'
        );

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     STORE ANALYSIS PIPELINE - INICIO                            ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Configuracao do pipeline', [
            'analysis_id' => $analysis->id,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'analysis_type' => $analysis->analysis_type?->value ?? 'general',
            'module_specialized' => $moduleConfig->isSpecialized,
            'module_type' => $moduleConfig->analysisType,
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

            // V5: Buscar categorias bloqueadas por múltiplas rejeições
            $blockedCategories = $this->getBlockedCategoriesByRejection($store->id);

            Log::channel($this->logChannel)->info('<<< Contexto historico carregado (V5)', [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
                'accepted_count' => count($previousSuggestions['accepted_titles']),
                'rejected_count' => count($previousSuggestions['rejected_titles']),
                'saturated_themes' => $saturatedThemes,
            ]);

            // V5: Buscar dados de feedback para aprendizado
            $successCases = $this->getSuccessCasesForStore($store, 5);
            $failureCases = $this->getFailureCasesForStore($store, 5);
            $categorySuccessRates = $this->getCategorySuccessRates();

            // Agrupar sugestões por status para contexto detalhado
            $suggestionsByStatus = $this->groupSuggestionsByStatus($previousSuggestions['all']);

            $learningContext = [
                'success_cases' => $successCases,
                'failure_cases' => $failureCases,
                'category_success_rates' => $categorySuccessRates,
                'suggestions_by_status' => $suggestionsByStatus,
                'blocked_categories' => $blockedCategories,
            ];

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Contexto de Aprendizado (feedback system)
            // ═══════════════════════════════════════════════════════════════════
            $this->logFullData('LEARNING CONTEXT COMPLETO', $learningContext);
            $this->logFullData('SUGESTÕES ANTERIORES (todas)', $previousSuggestions['all']);
            $this->logFullData('TEMAS SATURADOS', $saturatedThemes);

            Log::channel($this->logChannel)->info('<<< Contexto historico carregado', [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
                'success_cases_count' => count($successCases),
                'failure_cases_count' => count($failureCases),
                'blocked_categories' => array_keys($blockedCategories),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            $this->logService->completeStage($analysis, 2, [
                'previous_analyses_count' => count($previousAnalyses),
                'previous_suggestions_count' => count($previousSuggestions['all']),
                'has_feedback_data' => ! empty($successCases) || ! empty($failureCases),
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

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Benchmarks e Estratégias do RAG
            // ═══════════════════════════════════════════════════════════════════
            if (! empty($benchmarks)) {
                $this->logFullData('RAG - BENCHMARKS DO NICHO', $benchmarks);
            }
            if (! empty($nicheStrategies)) {
                $this->logFullData('RAG - ESTRATÉGIAS DO NICHO', $nicheStrategies);
            }

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
            $topProductNames = $store->analysisProducts()
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
                if (! empty($concorrente['dados_ricos']['categorias']) ||
                    ! empty($concorrente['dados_ricos']['promocoes']) ||
                    ! empty($concorrente['dados_ricos']['produtos'])) {
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

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Dados de Concorrentes (para debugging de qualidade)
            // ═══════════════════════════════════════════════════════════════════
            if (! empty($externalMarketData['concorrentes'])) {
                $this->logFullData('DADOS COMPLETOS DOS CONCORRENTES', $externalMarketData['concorrentes']);
            }

            // Log dados de mercado (Google Trends e preços)
            if (! empty($externalMarketData['dados_mercado'])) {
                $this->logFullData('DADOS DE MERCADO (Trends + Preços)', $externalMarketData['dados_mercado']);
            }

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

        // Pre-compute store stats (needed by ProfileSynthesizer and Collector)
        $storeStats = $this->getStoreStats($store);
        $platform = $store->platform?->value ?? 'nuvemshop';
        $platformName = $store->platform?->label() ?? 'Nuvemshop';

        // =====================================================
        // ETAPA 4.5: Sintetizar perfil da loja (ProfileSynthesizer)
        // =====================================================
        $stepStart = microtime(true);
        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 4.5: Executando PROFILE SYNTHESIZER                       │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        $storeProfile = [];
        try {
            $storeProfile = $this->profileSynthesizer->execute([
                'store_name' => $store->name,
                'platform' => $platform,
                'platform_name' => $platformName,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'store_url' => $store->url ?? '',
                'store_stats' => $storeStats,
                'benchmarks' => $benchmarks ?? [],
                'structured_benchmarks' => $structuredBenchmarks ?? [],
                'store_goals' => $storeGoals,
            ]);

            // Remove internal prompt tracking key
            unset($storeProfile['_prompt_used']);

            Log::channel($this->logChannel)->info('<<< ProfileSynthesizer concluido', [
                'profile_keys' => array_keys($storeProfile['store_profile'] ?? []),
                'porte' => $storeProfile['store_profile']['porte_estimado'] ?? 'N/A',
                'maturidade' => $storeProfile['store_profile']['maturidade_digital'] ?? 'N/A',
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
        } catch (\Exception $e) {
            // Graceful fallback: pipeline continues without store profile
            Log::channel($this->logChannel)->warning('ProfileSynthesizer falhou, continuando sem perfil', [
                'error' => $e->getMessage(),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            $storeProfile = [];
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
            Log::channel($this->logChannel)->info('>>> Dados da loja coletados', [
                'total_orders' => $storeStats['total_orders'],
                'total_products' => $storeStats['total_products'],
                'total_customers' => $storeStats['total_customers'],
                'total_revenue' => $storeStats['total_revenue'],
            ]);

            $collectorContext = $this->collector->execute([
                'store_name' => $store->name,
                'store_profile' => $storeProfile,
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
                // V5: Learning context para feedback de análises anteriores
                'learning_context' => $learningContext,
                // V6: Module config para análises especializadas
                'module_config' => $moduleConfig,
            ]);

            // Coletar prompt para logging no final
            $this->collectedPrompts['collector'] = $collectorContext['_prompt_used'] ?? null;
            unset($collectorContext['_prompt_used']);

            // Salvar output bruto para debug
            $this->rawAgentOutputs['collector'] = $collectorContext;

            // Truncar data_not_available para reduzir noise nos agentes downstream
            if (isset($collectorContext['data_not_available']) && count($collectorContext['data_not_available']) > 10) {
                $totalUnavailable = count($collectorContext['data_not_available']);
                $collectorContext['data_not_available'] = array_slice($collectorContext['data_not_available'], 0, 10);
                $collectorContext['data_not_available'][] = '... e mais '.($totalUnavailable - 10).' itens indisponíveis';
            }
            if (isset($collectorContext['data_quality']['missing_data']) && is_array($collectorContext['data_quality']['missing_data']) && count($collectorContext['data_quality']['missing_data']) > 10) {
                $totalMissing = count($collectorContext['data_quality']['missing_data']);
                $collectorContext['data_quality']['missing_data'] = array_slice($collectorContext['data_quality']['missing_data'], 0, 10);
                $collectorContext['data_quality']['missing_data'][] = '... e mais '.($totalMissing - 10).' itens';
            }

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

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Dados da Loja (para debugging de qualidade)
            // ═══════════════════════════════════════════════════════════════════
            $this->logFullData('BEST SELLERS (Top 10)', $storeData['products']['best_sellers'] ?? []);
            $this->logFullData('PRODUTOS SEM ESTOQUE', $storeData['products']['out_of_stock_list'] ?? []);
            // no_sales_period é um contador (int), não array - logar como info simples
            Log::channel($this->logChannel)->info('PRODUTOS SEM VENDAS NO PERIODO: '.($storeData['products']['no_sales_period'] ?? 0).' produtos');
            $this->logFullData('DADOS DE CUPONS', $storeData['coupons'] ?? []);
            $this->logFullData('PEDIDOS POR DIA', $storeData['orders']['by_day'] ?? []);
            $this->logFullData('PEDIDOS POR STATUS DE PAGAMENTO', $storeData['orders']['by_payment_status'] ?? []);

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
                'store_profile' => $storeProfile,
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
                'previous_suggestions' => $previousSuggestions, // V6: para anti-repetição de diagnósticos
                // V6: Module config para análises especializadas
                'module_config' => $moduleConfig,
            ]);

            // Coletar prompt para logging no final
            $this->collectedPrompts['analyst'] = $analysisResult['_prompt_used'] ?? null;
            unset($analysisResult['_prompt_used']);

            // Salvar output bruto para debug
            $this->rawAgentOutputs['analyst'] = $analysisResult;

            Log::channel($this->logChannel)->info('<<< Analyst Agent concluido', [
                'health_score' => $analysisResult['overall_health']['score'] ?? 'N/A',
                'health_classification' => $analysisResult['overall_health']['classification'] ?? 'N/A',
                'anomalies_count' => count($analysisResult['anomalies'] ?? []),
                'patterns_count' => count($analysisResult['identified_patterns'] ?? []),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Resultado do Analyst (anomalias, patterns, health)
            // ═══════════════════════════════════════════════════════════════════
            $this->logFullData('ANALYST - OVERALL HEALTH', $analysisResult['overall_health'] ?? []);
            $this->logFullData('ANALYST - ANOMALIAS DETECTADAS', $analysisResult['anomalies'] ?? []);
            $this->logFullData('ANALYST - PATTERNS IDENTIFICADOS', $analysisResult['identified_patterns'] ?? []);
            if (isset($analysisResult['comparativo_concorrentes'])) {
                $this->logFullData('ANALYST - COMPARATIVO LOJA x CONCORRENTES', $analysisResult['comparativo_concorrentes']);
            }

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
                'store_profile' => $storeProfile,
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
                'analysis_count' => count($previousAnalyses ?? []),

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
                'out_of_stock_list' => $storeData['products']['out_of_stock_list'] ?? [],
                'best_sellers' => $storeData['products']['best_sellers'] ?? [],
                'coupon_rate' => $storeData['coupons']['usage_rate'] ?? 0,
                'coupon_impact' => $storeData['coupons']['ticket_impact'] ?? 0,
                'top_coupons' => $storeData['coupons']['top_coupons'] ?? [],
                'anomalies_list' => $this->formatAnomaliesList($analysisResult['anomalies'] ?? []),
                'anomalies' => $this->formatAnomaliesArray($analysisResult['anomalies'] ?? []),
                'patterns_list' => $this->formatPatternsList($analysisResult['identified_patterns'] ?? []),
                'patterns' => $analysisResult['identified_patterns'] ?? [],

                // V5: Dados de feedback/aprendizado
                'learning_context' => $learningContext ?? [],
                // V6: Module config para análises especializadas
                'module_config' => $moduleConfig,
            ]);

            // Coletar prompt para logging no final
            $this->collectedPrompts['strategist'] = $generatedSuggestions['_prompt_used'] ?? null;
            unset($generatedSuggestions['_prompt_used']);

            // Salvar output bruto para debug
            $this->rawAgentOutputs['strategist'] = $generatedSuggestions;

            // Extrair premium_summary
            $this->premiumSummary = $generatedSuggestions['premium_summary'] ?? null;

            Log::channel($this->logChannel)->info('<<< Strategist Agent concluido', [
                'suggestions_generated' => count($generatedSuggestions['suggestions'] ?? []),
                'has_premium_summary' => $this->premiumSummary !== null,
                'premium_sections' => $this->premiumSummary ? array_keys($this->premiumSummary) : [],
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Sugestões do Strategist (antes do Critic)
            // ═══════════════════════════════════════════════════════════════════
            $this->logFullData('STRATEGIST - SUGESTÕES GERADAS', $generatedSuggestions['suggestions'] ?? []);

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
                'store_profile' => $storeProfile,
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
                // V7: Dados detalhados para verificação numérica pelo Critic
                'orders_summary' => $storeData['orders'],
                'products_summary' => $storeData['products'],
                'inventory_summary' => $storeData['inventory'],
                'coupons_summary' => $storeData['coupons'],
                'external_data' => $externalMarketData,
                'analyst_briefing' => $analysisResult['alertas_para_strategist'] ?? $analysisResult['briefing_strategist'] ?? [],
                'anomalies' => $analysisResult['anomalies'] ?? [],
                'store_context' => [
                    'niche' => $niche,
                    'subcategory' => $subcategory,
                    'platform' => $platform,
                    'platform_name' => $platformName,
                    'metrics' => $analysisResult['metrics'] ?? [],
                ],
                // V6: Module config para análises especializadas
                'module_config' => $moduleConfig,
                'store_goals' => $storeGoals,
            ]);

            // Coletar prompt para logging no final
            $this->collectedPrompts['critic'] = $criticizedSuggestions['_prompt_used'] ?? null;
            unset($criticizedSuggestions['_prompt_used']);

            // Salvar output bruto para debug
            $this->rawAgentOutputs['critic'] = $criticizedSuggestions;

            Log::channel($this->logChannel)->info('<<< Critic Agent concluido', [
                'suggestions_approved' => count($criticizedSuggestions['approved_suggestions'] ?? []),
                'suggestions_removed' => count($criticizedSuggestions['removed_suggestions'] ?? []),
                'average_quality' => $criticizedSuggestions['general_analysis']['average_quality'] ?? 'N/A',
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // LOG COMPLETO: Sugestões do Critic (finais, após revisão)
            // ═══════════════════════════════════════════════════════════════════
            $this->logFullData('CRITIC - SUGESTÕES APROVADAS (FINAIS)', $criticizedSuggestions['approved_suggestions'] ?? []);
            if (! empty($criticizedSuggestions['removed_suggestions'])) {
                $this->logFullData('CRITIC - SUGESTÕES REMOVIDAS', $criticizedSuggestions['removed_suggestions']);
            }
            if (isset($criticizedSuggestions['general_analysis'])) {
                $this->logFullData('CRITIC - ANÁLISE GERAL', $criticizedSuggestions['general_analysis']);
            }

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

            // V6: Programmatic enforcement of saturated theme blocking
            $filteredSuggestions = $this->filterSaturatedThemes($filteredSuggestions, $saturatedThemes ?? []);

            // SAFETY NET: Garantir minimo de 6 sugestoes (2 por nivel de prioridade)
            // Isso é CRITICO - o cliente deve receber pelo menos 2 sugestoes por nivel
            $minimumRequired = 6;

            if (empty($filteredSuggestions) && ! empty($criticizedSuggestions['approved_suggestions'])) {
                // Full bypass - filtro removeu TUDO
                Log::channel($this->logChannel)->warning('>>> SAFETY NET ETAPA 9: Filtro removeu TODAS as sugestoes - fazendo bypass', [
                    'analysis_id' => $analysis->id,
                    'critic_approved' => count($criticizedSuggestions['approved_suggestions']),
                    'action' => 'Usando sugestoes do Critic diretamente (bypass de similaridade + temas)',
                ]);

                $filteredSuggestions = $criticizedSuggestions['approved_suggestions'];

                Log::channel($this->logChannel)->info('>>> SAFETY NET ETAPA 9: Bypass concluido', [
                    'suggestions_restored' => count($filteredSuggestions),
                ]);
            } elseif (count($filteredSuggestions) < $minimumRequired && ! empty($criticizedSuggestions['approved_suggestions'])) {
                // Partial recovery - recuperar melhores candidatos ate atingir minimo
                Log::channel($this->logChannel)->warning('>>> SAFETY NET ETAPA 9: Sugestoes abaixo do minimo de '.$minimumRequired.' - recuperando', [
                    'analysis_id' => $analysis->id,
                    'current_count' => count($filteredSuggestions),
                    'minimum_required' => $minimumRequired,
                ]);

                // Usar titulos normalizados para evitar duplicatas semanticas na mesma analise
                $includedNormalizedTitles = array_map(
                    fn ($s) => $this->normalizeTitle($s['final_version']['title'] ?? ''),
                    $filteredSuggestions
                );

                // Ordenar por quality_score para recuperar os melhores primeiro
                $candidates = $criticizedSuggestions['approved_suggestions'];
                usort($candidates, fn ($a, $b) => ($b['quality_score'] ?? 0) <=> ($a['quality_score'] ?? 0));

                foreach ($candidates as $candidate) {
                    if (count($filteredSuggestions) >= $minimumRequired) {
                        break;
                    }

                    $candidateNormalized = $this->normalizeTitle($candidate['final_version']['title'] ?? '');

                    // Verificar similaridade com todas as sugestoes ja incluidas
                    $isSimilar = false;
                    foreach ($includedNormalizedTitles as $existingNormalized) {
                        if ($this->calculateTitleSimilarity($candidateNormalized, $existingNormalized) >= 0.80) {
                            $isSimilar = true;
                            break;
                        }
                    }

                    if (! $isSimilar) {
                        $candidate['recovered_by_minimum_guarantee'] = true;
                        $filteredSuggestions[] = $candidate;
                        $includedNormalizedTitles[] = $candidateNormalized;

                        Log::channel($this->logChannel)->info('>>> SAFETY NET MINIMO: Recuperando sugestao para atingir '.$minimumRequired, [
                            'title' => $candidate['final_version']['title'] ?? 'N/A',
                            'quality_score' => $candidate['quality_score'] ?? 0,
                        ]);
                    }
                }

                Log::channel($this->logChannel)->info('>>> SAFETY NET MINIMO CONCLUIDO', [
                    'recovered_to' => count($filteredSuggestions),
                    'minimum_required' => $minimumRequired,
                ]);
            } elseif (count($filteredSuggestions) < 9) {
                Log::channel($this->logChannel)->warning('>>> ALERTA: Filtro removeu sugestoes abaixo do ideal de 9', [
                    'analysis_id' => $analysis->id,
                    'input' => count($criticizedSuggestions['approved_suggestions'] ?? []),
                    'output' => count($filteredSuggestions),
                    'removed' => count($criticizedSuggestions['approved_suggestions'] ?? []) - count($filteredSuggestions),
                ]);
            }

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

        // =====================================================
        // V5: Pré-validar sugestões do Strategist contra histórico ANTES de recuperar
        // Isso garante que sugestões recuperadas não sejam similares ao histórico
        // =====================================================
        $validatedStrategistSuggestions = $this->preValidateStrategistSuggestions(
            $generatedSuggestions['suggestions'] ?? [],
            $previousSuggestions['all'] ?? [],
            $store->id
        );

        // Garantir mínimo de 9 sugestões APÓS o filtro de similaridade
        // (O fallback usa apenas sugestões já validadas contra o histórico)
        $finalSuggestions = $this->ensureMinimumSuggestions(
            $filteredSuggestions,
            $validatedStrategistSuggestions,
            [
                'niche' => $niche,
                'subcategory' => $subcategory,
                'platform' => $platform,
                'platform_name' => $platformName,
                'ticket_medio' => $ticketMedio,
                'pedidos_mes' => $ordersTotal,
            ],
            $previousSuggestions['all'] ?? [],
            $store->id
        );

        $afterRecovery = count($finalSuggestions);

        // Enforcement programático: max 2 sugestões por categoria
        $finalSuggestions = $this->enforceCategoryDiversification($finalSuggestions, $validatedStrategistSuggestions);

        // V6: Activate historical dedup (was dead code before)
        $finalSuggestions = $this->validateSuggestionUniqueness(
            $finalSuggestions,
            $previousSuggestions['all'] ?? []
        );

        // =====================================================
        // V7: GARANTIA ABSOLUTA - Mínimo de 6 sugestões (2 por nível de prioridade)
        // Este é o ÚLTIMO safety net antes de salvar. Se todos os filtros anteriores
        // removeram demais, recupera do Critic ignorando filtros de tema/similaridade.
        // =====================================================
        $absoluteMinimum = 6;
        if (count($finalSuggestions) < $absoluteMinimum && ! empty($criticizedSuggestions['approved_suggestions'])) {
            Log::channel($this->logChannel)->warning('>>> GARANTIA ABSOLUTA: Abaixo do minimo de '.$absoluteMinimum.' apos todos os filtros', [
                'analysis_id' => $analysis->id,
                'current_count' => count($finalSuggestions),
                'critic_approved' => count($criticizedSuggestions['approved_suggestions']),
            ]);

            // Contar distribuição atual por nível de prioridade
            $currentDist = ['high' => 0, 'medium' => 0, 'low' => 0];
            foreach ($finalSuggestions as $s) {
                $impact = $s['final_version']['expected_impact'] ?? 'medium';
                if (isset($currentDist[$impact])) {
                    $currentDist[$impact]++;
                }
            }

            $existingNormalizedTitles = array_map(
                fn ($s) => $this->normalizeTitle($s['final_version']['title'] ?? ''),
                $finalSuggestions
            );

            // Ordenar candidatos por quality_score
            $candidates = $criticizedSuggestions['approved_suggestions'];
            usort($candidates, fn ($a, $b) => ($b['quality_score'] ?? 0) <=> ($a['quality_score'] ?? 0));

            // Priorizar níveis de prioridade que tem menos de 2 sugestões
            $priority = count($finalSuggestions) + 1;
            foreach ($candidates as $candidate) {
                // Parar quando atingir 2 por nível ou o mínimo absoluto
                $allLevelsHaveMinimum = $currentDist['high'] >= 2 && $currentDist['medium'] >= 2 && $currentDist['low'] >= 2;
                if ($allLevelsHaveMinimum && count($finalSuggestions) >= $absoluteMinimum) {
                    break;
                }

                $candidateImpact = $candidate['final_version']['expected_impact'] ?? 'medium';
                if (! isset($currentDist[$candidateImpact])) {
                    $candidateImpact = 'medium';
                }

                // Pular se este nível já tem 2+ e outros ainda precisam
                if ($currentDist[$candidateImpact] >= 2) {
                    $levelsNeedingMore = array_filter($currentDist, fn ($c) => $c < 2);
                    if (! empty($levelsNeedingMore)) {
                        continue;
                    }
                }

                // Verificar unicidade por titulo normalizado (anti-duplicação)
                $candidateNormalized = $this->normalizeTitle($candidate['final_version']['title'] ?? '');
                $isSimilar = false;
                foreach ($existingNormalizedTitles as $existingNormalized) {
                    if ($this->calculateTitleSimilarity($candidateNormalized, $existingNormalized) >= 0.80) {
                        $isSimilar = true;
                        break;
                    }
                }

                if ($isSimilar) {
                    continue;
                }

                $candidate['final_priority'] = $priority++;
                $candidate['recovered_by_absolute_guarantee'] = true;
                $finalSuggestions[] = $candidate;
                $existingNormalizedTitles[] = $candidateNormalized;
                $currentDist[$candidateImpact]++;

                Log::channel($this->logChannel)->info('>>> GARANTIA ABSOLUTA: Recuperando sugestao', [
                    'title' => $candidate['final_version']['title'] ?? 'N/A',
                    'impact' => $candidateImpact,
                    'distribution' => $currentDist,
                ]);
            }

            Log::channel($this->logChannel)->info('>>> GARANTIA ABSOLUTA CONCLUIDA', [
                'final_count' => count($finalSuggestions),
                'distribution' => $currentDist,
            ]);
        }

        // Logar estatísticas de deduplicação
        $this->logDeduplicationStats($analysis->id, [
            'from_strategist' => count($generatedSuggestions['suggestions'] ?? []),
            'from_critic' => count($criticizedSuggestions['approved_suggestions'] ?? []),
            'after_similarity_filter' => count($filteredSuggestions),
            'after_recovery' => $afterRecovery,
            'after_category_diversification' => count($finalSuggestions),
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

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Sugestões Finais Salvas no Banco
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('SUGESTÕES FINAIS SALVAS NO BANCO', $finalSuggestions);

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
        $categories = $store->analysisProducts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles (active products, most recent)
        $productTitles = $store->analysisProducts()
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
        $categories = $store->analysisProducts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles (active products, most recent)
        $productTitles = $store->analysisProducts()
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
        $categories = $store->analysisProducts()
            ->whereNotNull('categories')
            ->pluck('categories')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->toArray();

        // Get top product titles
        $productTitles = $store->analysisProducts()
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

        // Products (applying analysis config exclusions)
        $allProductsCount = $store->products()->count();
        $products = $store->analysisProducts()->get();
        $excludedCount = $allProductsCount - $products->count();
        $activeProducts = $products->filter(fn ($p) => $p->is_active);

        if ($excludedCount > 0) {
            Log::channel($this->logChannel)->info(">>> {$excludedCount} produto(s) excluído(s) da análise via config");
        }

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
                'out_of_stock_list' => $this->getOutOfStockProducts($products),
                'best_sellers' => $this->getBestSellers($store, $paidOrders),
                'no_sales_period' => $this->getNoSalesProducts($store, $periodDays),
                'excluded_count' => $excludedCount,
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
     * Get best selling products with details.
     */
    private function getBestSellers(Store $store, $paidOrders, int $limit = 10): array
    {
        $productStats = [];

        foreach ($paidOrders as $order) {
            $items = is_array($order->items) ? $order->items : [];
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $itemPrice = $item['price'] ?? 0;
                if ($productId && $itemPrice > 0) {
                    if (! isset($productStats[$productId])) {
                        $productStats[$productId] = [
                            'quantity' => 0,
                            'revenue' => 0,
                        ];
                    }
                    $productStats[$productId]['quantity'] += ($item['quantity'] ?? 1);
                    $productStats[$productId]['revenue'] += $itemPrice * ($item['quantity'] ?? 1);
                }
            }
        }

        // Sort by quantity sold
        uasort($productStats, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);
        $topProductIds = array_slice(array_keys($productStats), 0, $limit);

        // Get product details (excluding gifts/brindes)
        $products = $store->analysisProducts()
            ->whereIn('external_id', $topProductIds)
            ->get()
            ->keyBy('external_id');

        $result = [];
        foreach ($topProductIds as $productId) {
            $product = $products->get($productId);
            if ($product) {
                $result[] = [
                    'id' => $productId,
                    'name' => $product->name,
                    'quantity_sold' => $productStats[$productId]['quantity'],
                    'revenue' => round($productStats[$productId]['revenue'], 2),
                    'current_stock' => $product->stock_quantity ?? 0,
                    'price' => $product->price ?? 0,
                ];
            }
        }

        return $result;
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

        return $store->analysisProducts()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('external_id', $soldProductIds)
            ->count();
    }

    /**
     * Get out of stock products with details.
     */
    private function getOutOfStockProducts($products, int $limit = 10): array
    {
        return $products
            ->filter(fn ($p) => $p->isOutOfStock())
            ->take($limit)
            ->map(fn ($p) => [
                'id' => $p->external_id,
                'name' => $p->name,
                'price' => $p->price ?? 0,
                'last_updated' => $p->external_updated_at?->format('Y-m-d') ?? null,
                'sku' => $p->sku ?? null,
            ])
            ->values()
            ->toArray();
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
     * Uses adaptive threshold based on existing suggestions count.
     */
    private function filterBySimilarity(array $suggestions, int $storeId): array
    {
        // First, filter out intra-batch duplicates (suggestions similar to each other)
        $suggestions = $this->filterIntraBatchDuplicates($suggestions);

        if (! $this->embedding->isConfigured()) {
            // Skip embedding-based similarity check if not configured
            return $suggestions;
        }

        // Count existing pending suggestions to determine adaptive threshold
        $pendingSuggestionsCount = Suggestion::where('store_id', $storeId)
            ->whereIn('status', ['new', 'accepted', 'in_progress'])
            ->count();

        // Adaptive threshold based on existing suggestions count
        $threshold = match (true) {
            $pendingSuggestionsCount <= 10 => 0.85,  // Original threshold for stores with few suggestions
            $pendingSuggestionsCount <= 25 => 0.90,  // Slightly higher for moderate history
            $pendingSuggestionsCount <= 50 => 0.93,  // Higher for large history
            default => 0.95,                          // Very high for stores with 50+ suggestions
        };

        Log::channel($this->logChannel)->info('>>> Threshold adaptativo de similaridade', [
            'pending_suggestions_count' => $pendingSuggestionsCount,
            'threshold_used' => $threshold,
            'reasoning' => $pendingSuggestionsCount <= 10 ? 'low_history' : ($pendingSuggestionsCount <= 25 ? 'moderate_history' : ($pendingSuggestionsCount <= 50 ? 'large_history' : 'very_large_history')),
        ]);

        $filteredSuggestions = [];
        $similarityData = []; // Track similarity scores for safety net

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
                // Exclui rejected/ignored/completed e considera apenas últimos 90 dias
                $isTooSimilar = $this->embedding->isTooSimilarFiltered($embedding, $storeId, $threshold, ['rejected', 'ignored', 'completed'], 90);

                if (! $isTooSimilar) {
                    $suggestion['embedding'] = $embedding;
                    $filteredSuggestions[] = $suggestion;
                } else {
                    // Store similarity data for potential safety net recovery
                    $similarityData[] = [
                        'suggestion' => $suggestion,
                        'embedding' => $embedding,
                        'title' => $finalVersion['title'] ?? 'N/A',
                    ];
                    Log::channel($this->logChannel)->info('>>> Sugestao filtrada por similaridade', [
                        'title' => $finalVersion['title'],
                        'threshold' => $threshold,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not check similarity: '.$e->getMessage());
                // Include suggestion anyway if similarity check fails
                $filteredSuggestions[] = $suggestion;
            }
        }

        // SAFETY NET: If ALL suggestions were filtered but we had approved suggestions from Critic,
        // allow the least similar ones to pass (up to 9)
        if (empty($filteredSuggestions) && ! empty($similarityData)) {
            Log::channel($this->logChannel)->warning('>>> SAFETY NET ATIVADO: Todas as sugestoes foram filtradas por similaridade', [
                'original_count' => count($suggestions),
                'filtered_count' => count($similarityData),
                'action' => 'Permitindo passar as menos similares (ate 9)',
            ]);

            // Sort by quality_score DESC to recover the best ones
            usort($similarityData, function ($a, $b) {
                $qualityA = $a['suggestion']['quality_score'] ?? 0;
                $qualityB = $b['suggestion']['quality_score'] ?? 0;

                return $qualityB <=> $qualityA;
            });

            // Allow up to 9 best suggestions to pass
            $recoveredCount = 0;
            foreach (array_slice($similarityData, 0, 9) as $item) {
                $item['suggestion']['embedding'] = $item['embedding'];
                $filteredSuggestions[] = $item['suggestion'];
                $recoveredCount++;

                Log::channel($this->logChannel)->info('>>> SAFETY NET: Recuperando sugestao', [
                    'title' => $item['title'],
                    'quality_score' => $item['suggestion']['quality_score'] ?? 0,
                ]);
            }

            Log::channel($this->logChannel)->info('>>> SAFETY NET CONCLUIDO', [
                'recovered_count' => $recoveredCount,
            ]);
        }

        return $filteredSuggestions;
    }

    /**
     * Programmatically filter suggestions about saturated themes (3+ occurrences).
     * This enforces theme blocking - the prompt tells the AI to avoid these, but this ensures compliance.
     */
    private function filterSaturatedThemes(array $suggestions, array $saturatedThemes): array
    {
        if (empty($saturatedThemes)) {
            return $suggestions;
        }

        // Only enforce for themes with 3+ occurrences
        $blockedThemes = array_filter($saturatedThemes, fn ($count) => $count >= 3);

        if (empty($blockedThemes)) {
            return $suggestions;
        }

        $keywords = \App\Services\Analysis\ThemeKeywords::all();
        $filtered = [];

        foreach ($suggestions as $suggestion) {
            $title = mb_strtolower($suggestion['final_version']['title'] ?? '');
            $description = mb_strtolower($suggestion['final_version']['description'] ?? '');
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title.' '.$description) ?: ($title.' '.$description);

            $matchedTheme = null;
            foreach ($blockedThemes as $theme => $count) {
                $themeKeywords = $keywords[$theme] ?? [];
                foreach ($themeKeywords as $kw) {
                    $kwNormalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $kw) ?: $kw;
                    if (str_contains($text, $kwNormalized)) {
                        $matchedTheme = $theme;
                        break 2;
                    }
                }
            }

            if ($matchedTheme) {
                Log::channel($this->logChannel)->info('Suggestion filtered by saturated theme enforcement', [
                    'title' => $suggestion['final_version']['title'] ?? '',
                    'theme' => $matchedTheme,
                    'theme_count' => $blockedThemes[$matchedTheme],
                ]);

                continue;
            }

            $filtered[] = $suggestion;
        }

        return $filtered;
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
                if ($similarity >= 0.85) { // 85% similarity threshold for titles
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
                'premium_summary' => $this->premiumSummary,
            ],
            'suggestions' => [], // Legacy field - suggestions are now in separate table
            'alerts' => $this->extractAlerts($analysisResult),
            'opportunities' => $this->extractOpportunities($analysisResult),
        ]);

        // Salvar outputs brutos dos agentes para debug (admin only)
        if (! empty($this->rawAgentOutputs)) {
            $analysis->update(['raw_agent_outputs' => $this->rawAgentOutputs]);
        }
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
     * Format anomalies list for Strategist prompt (legacy string format).
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
     * Format anomalies as structured array for Strategist prompt.
     */
    private function formatAnomaliesArray(array $anomalies): array
    {
        if (empty($anomalies)) {
            return [];
        }

        return collect($anomalies)
            ->map(function ($a) {
                // Mapear campos do formato do Analyst para o formato esperado
                $metrica = $a['metric'] ?? $a['metrica'] ?? null;
                $atual = $a['actual'] ?? $a['atual'] ?? null;
                $historico = $a['expected'] ?? $a['historico'] ?? null;
                $variacao = $a['variation_percent'] ?? $a['variacao'] ?? null;
                $tipo = $a['type'] ?? $a['tipo'] ?? 'general';
                $explicacao = $a['explicacao_sazonal'] ?? null;

                // Construir descrição se não existir
                $description = $a['description'] ?? $a['descricao'] ?? null;
                if (! $description && $metrica) {
                    $description = $metrica;
                    if ($atual !== null && $historico !== null) {
                        $description .= " (Atual: {$atual}, Histórico: {$historico})";
                    }
                    if ($variacao) {
                        $description .= " - Variação: {$variacao}";
                    }
                }

                // Inferir severidade baseado no tipo e variação
                $severity = $a['severity'] ?? null;
                if (! $severity) {
                    $variacaoNum = abs((float) str_replace(['%', '+', '-'], '', (string) $variacao));
                    if ($tipo === 'negativa' && $variacaoNum > 50) {
                        $severity = 'high';
                    } elseif ($tipo === 'negativa') {
                        $severity = 'medium';
                    } else {
                        $severity = 'low';
                    }
                }

                return [
                    'type' => $tipo,
                    'description' => $description ?? 'Anomalia detectada',
                    'severity' => $severity,
                    'metric' => $metrica,
                    'expected' => $historico,
                    'actual' => $atual,
                    'affected_items' => $a['affected_items'] ?? $a['items'] ?? [],
                    'variation_percent' => $variacao,
                    'explicacao_sazonal' => $explicacao,
                ];
            })
            ->toArray();
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
     * Group suggestions by status for detailed learning context.
     */
    private function groupSuggestionsByStatus(array $suggestions): array
    {
        $grouped = [
            'accepted_successful' => [],
            'accepted_failed' => [],
            'rejected' => [],
            'in_progress' => [],
            'pending' => [],
        ];

        foreach ($suggestions as $s) {
            $status = $s['status'] ?? 'pending';
            $wasSuccessful = $s['was_successful'] ?? null;

            $item = [
                'title' => $s['title'] ?? 'Sem título',
                'category' => $s['category'] ?? 'general',
                'expected_impact' => $s['expected_impact'] ?? 'medium',
            ];

            if ($status === 'completed') {
                if ($wasSuccessful === true) {
                    $item['impact'] = $s['metrics_impact'] ?? null;
                    $grouped['accepted_successful'][] = $item;
                } else {
                    $item['failure_reason'] = $s['feedback'] ?? 'Não informado';
                    $grouped['accepted_failed'][] = $item;
                }
            } elseif ($status === 'rejected') {
                $grouped['rejected'][] = $item;
            } elseif ($status === 'in_progress') {
                $grouped['in_progress'][] = $item;
            } else {
                $grouped['pending'][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Save suggestions to database.
     */
    private function saveSuggestions(Analysis $analysis, array $suggestions): void
    {
        // V7: Final dedup guard - prevent exact AND semantically similar duplicates in the same analysis
        $seenTitles = [];
        $seenNormalizedTitles = [];
        $dedupedSuggestions = [];

        foreach ($suggestions as $suggestion) {
            $title = mb_strtolower(trim($suggestion['final_version']['title'] ?? ''));
            if (empty($title) || $title === 'untitled suggestion') {
                $dedupedSuggestions[] = $suggestion;

                continue;
            }

            // Check exact duplicate
            if (in_array($title, $seenTitles)) {
                Log::channel($this->logChannel)->warning('saveSuggestions: Duplicate title blocked (exact)', [
                    'title' => $title,
                    'analysis_id' => $analysis->id,
                ]);

                continue;
            }

            // Check semantically similar duplicate via normalized title
            $normalizedTitle = $this->normalizeTitle($title);
            $isSimilar = false;
            foreach ($seenNormalizedTitles as $seenNormalized) {
                if ($this->calculateTitleSimilarity($normalizedTitle, $seenNormalized) >= 0.80) {
                    Log::channel($this->logChannel)->warning('saveSuggestions: Similar title blocked (semantic)', [
                        'title' => $title,
                        'similarity_with' => 'normalized match',
                        'analysis_id' => $analysis->id,
                    ]);
                    $isSimilar = true;
                    break;
                }
            }

            if ($isSimilar) {
                continue;
            }

            $seenTitles[] = $title;
            $seenNormalizedTitles[] = $normalizedTitle;
            $dedupedSuggestions[] = $suggestion;
        }

        // Re-number priorities after dedup
        foreach ($dedupedSuggestions as $index => &$s) {
            $s['final_priority'] = $index + 1;
        }
        unset($s);

        $suggestions = $dedupedSuggestions;

        foreach ($suggestions as $index => $suggestion) {
            $finalVersion = $suggestion['final_version'] ?? [];

            // Merge competitor_reference and implementation into specific_data
            $specificData = $finalVersion['specific_data'] ?? [];
            if (! is_array($specificData)) {
                $specificData = [];
            }
            if (! empty($finalVersion['competitor_reference'])) {
                $specificData['competitor_reference'] = $finalVersion['competitor_reference'];
            }
            if (! empty($finalVersion['implementation'])) {
                $specificData['implementation'] = $finalVersion['implementation'];
            }
            // Phase 5: Traceability and verification metadata
            if (! empty($finalVersion['insight_origem'])) {
                $specificData['insight_origem'] = $finalVersion['insight_origem'];
            }
            if (! empty($finalVersion['nivel_confianca'])) {
                $specificData['nivel_confianca'] = $finalVersion['nivel_confianca'];
            }
            if (! empty($suggestion['verificacao_status'])) {
                $specificData['verificacao_status'] = $suggestion['verificacao_status'];
            }
            if (isset($suggestion['quality_score'])) {
                $specificData['score_qualidade'] = $suggestion['quality_score'];
            }

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
                'specific_data' => ! empty($specificData) ? $specificData : null,
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
     * V5: Pre-validate Strategist suggestions against historical suggestions.
     * This ensures that suggestions used for recovery are not similar to past suggestions.
     */
    private function preValidateStrategistSuggestions(array $strategistSuggestions, array $previousSuggestions, int $storeId = 0): array
    {
        if (empty($previousSuggestions)) {
            return $strategistSuggestions;
        }

        // Build list of historical titles (normalized)
        $historicalTitles = [];
        foreach ($previousSuggestions as $prev) {
            $title = $prev['title'] ?? '';
            if (! empty($title)) {
                $historicalTitles[] = $this->normalizeTitle($title);
            }
        }

        $validated = [];
        foreach ($strategistSuggestions as $suggestion) {
            $title = $suggestion['title'] ?? '';
            $normalizedTitle = $this->normalizeTitle($title);

            // Check similarity against historical suggestions
            $isSimilarToHistory = false;
            foreach ($historicalTitles as $histTitle) {
                $similarity = $this->calculateTitleSimilarity($normalizedTitle, $histTitle);
                if ($similarity >= 0.85) {
                    Log::channel($this->logChannel)->info('Strategist suggestion pre-filtered (similar to history)', [
                        'title' => $title,
                        'similarity' => round($similarity * 100, 1).'%',
                    ]);
                    $isSimilarToHistory = true;
                    break;
                }
            }

            // V6: Also check embeddings for suggestions that pass Jaccard
            // V7: Threshold relaxado de 0.85 para 0.93 — pool de recovery precisa ser menos restritivo
            // para garantir candidatos disponíveis ao ensureMinimumSuggestions()
            if (! $isSimilarToHistory && $storeId > 0 && $this->embedding->isConfigured()) {
                try {
                    $textToEmbed = $title.' '.($suggestion['description'] ?? '');
                    $embedding = $this->embedding->generate($textToEmbed);
                    if ($this->embedding->isTooSimilarFiltered($embedding, $storeId, 0.93, ['rejected', 'ignored', 'completed'], 90)) {
                        $isSimilarToHistory = true;
                        Log::channel($this->logChannel)->info('Strategist suggestion pre-filtered by EMBEDDING (passed Jaccard)', [
                            'title' => $title,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Continue without embedding check on failure
                }
            }

            if (! $isSimilarToHistory) {
                $validated[] = $suggestion;
            }
        }

        Log::channel($this->logChannel)->info('Pre-validation of Strategist suggestions', [
            'input' => count($strategistSuggestions),
            'output' => count($validated),
            'filtered_by_history' => count($strategistSuggestions) - count($validated),
        ]);

        return $validated;
    }

    /**
     * Enforce category diversification: max 2 suggestions per category.
     * If a category has 3+ suggestions, remove excess and try to replace from pool.
     */
    private function enforceCategoryDiversification(array $suggestions, array $pool = []): array
    {
        $maxPerCategory = 2;

        // Count by category
        $categoryCounts = [];
        foreach ($suggestions as $i => $s) {
            $cat = $s['final_version']['category'] ?? 'general';
            $categoryCounts[$cat][] = $i;
        }

        // Find categories over limit
        $overLimit = array_filter($categoryCounts, fn ($indices) => count($indices) > $maxPerCategory);

        if (empty($overLimit)) {
            Log::channel($this->logChannel)->info('[DIVERSIFICATION] Todas categorias dentro do limite de 2');

            return $suggestions;
        }

        Log::channel($this->logChannel)->warning('[DIVERSIFICATION] Categorias acima do limite de 2:', array_map('count', $overLimit));

        // Build pool of candidates from different categories (not already in suggestions)
        $usedTitles = array_map(fn ($s) => mb_strtolower($s['final_version']['title'] ?? ''), $suggestions);
        $usedCategories = array_keys($categoryCounts);

        $replacementPool = [];
        foreach ($pool as $candidate) {
            $candidateTitle = mb_strtolower($candidate['title'] ?? '');
            $candidateCategory = $candidate['category'] ?? 'general';

            // Only consider candidates from categories NOT over limit
            if (isset($overLimit[$candidateCategory])) {
                continue;
            }
            // Don't use if title is too similar to existing
            $isDuplicate = false;
            foreach ($usedTitles as $usedTitle) {
                similar_text($candidateTitle, $usedTitle, $percent);
                if ($percent >= 75) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (! $isDuplicate) {
                $replacementPool[] = $candidate;
            }
        }

        // Remove excess from over-limit categories (keep first 2, remove rest)
        $indicesToRemove = [];
        foreach ($overLimit as $cat => $indices) {
            $excess = array_slice($indices, $maxPerCategory);
            $indicesToRemove = array_merge($indicesToRemove, $excess);
            Log::channel($this->logChannel)->info("[DIVERSIFICATION] Categoria '{$cat}': ".count($indices).' sugestoes → removendo '.count($excess).' excedente(s)');
        }

        // Sort indices descending so we can remove without shifting
        rsort($indicesToRemove);

        $removed = [];
        foreach ($indicesToRemove as $idx) {
            $removed[] = $suggestions[$idx];
            array_splice($suggestions, $idx, 1);
        }

        // Try to fill gaps with pool candidates
        $poolIdx = 0;
        while (count($suggestions) < 9 && $poolIdx < count($replacementPool)) {
            $candidate = $replacementPool[$poolIdx++];

            // Determine needed impact level
            $currentDistribution = ['high' => 0, 'medium' => 0, 'low' => 0];
            foreach ($suggestions as $s) {
                $impact = $s['final_version']['expected_impact'] ?? 'medium';
                $currentDistribution[$impact] = ($currentDistribution[$impact] ?? 0) + 1;
            }

            $neededImpact = 'medium';
            if ($currentDistribution['high'] < 3) {
                $neededImpact = 'high';
            } elseif ($currentDistribution['low'] < 3) {
                $neededImpact = 'low';
            }

            // Convert pool candidate to approved suggestion format
            $suggestions[] = [
                'final_version' => [
                    'category' => $candidate['category'] ?? 'general',
                    'title' => $candidate['title'] ?? '',
                    'description' => $candidate['description'] ?? $candidate['problem'] ?? '',
                    'recommended_action' => $candidate['recommended_action'] ?? $candidate['action'] ?? '',
                    'expected_impact' => $neededImpact,
                    'target_metrics' => $candidate['target_metrics'] ?? [],
                    'specific_data' => $candidate['specific_data'] ?? [],
                    'data_justification' => $candidate['data_justification'] ?? $candidate['data_source'] ?? '',
                    'implementation' => $candidate['implementation'] ?? [],
                    'competitor_reference' => $candidate['competitor_reference'] ?? null,
                ],
                'quality_score' => 6.0,
                'final_priority' => count($suggestions) + 1,
            ];

            Log::channel($this->logChannel)->info('[DIVERSIFICATION] Substituicao adicionada', [
                'category' => $candidate['category'] ?? 'general',
                'title' => $candidate['title'] ?? '',
                'impact' => $neededImpact,
            ]);
        }

        // Re-number priorities
        foreach ($suggestions as $i => &$s) {
            $s['final_priority'] = $i + 1;
        }

        // Final category distribution log
        $finalCounts = [];
        foreach ($suggestions as $s) {
            $cat = $s['final_version']['category'] ?? 'general';
            $finalCounts[$cat] = ($finalCounts[$cat] ?? 0) + 1;
        }

        Log::channel($this->logChannel)->info('[DIVERSIFICATION] Distribuicao final por categoria', [
            'categories' => $finalCounts,
            'unique_categories' => count($finalCounts),
            'total' => count($suggestions),
            'removed' => count($removed),
            'replaced' => count($suggestions) - (9 - count($removed)),
        ]);

        return $suggestions;
    }

    /**
     * Ensure we ALWAYS have exactly 9 suggestions with 3-3-3 distribution.
     * This is a CRITICAL business requirement - the client must always receive 9 suggestions.
     *
     * Priority: Use Strategist suggestions that were filtered by similarity before generic fallbacks.
     *
     * V5: Now also validates against historical suggestions to prevent duplicates.
     */
    private function ensureMinimumSuggestions(array $approved, array $allGenerated, array $context, array $previousSuggestions = [], int $storeId = 0): array
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

        // Extrair títulos rejeitados do histórico
        $rejectedTitles = array_values(array_map(
            fn ($s) => $s['title'] ?? '',
            array_filter($previousSuggestions, fn ($s) => in_array($s['status'] ?? '', ['rejected', 'ignored']))
        ));

        // Normalizar títulos rejeitados para filtragem
        $rejectedNormalized = array_map(
            fn ($t) => $this->normalizeTitle($t),
            $rejectedTitles
        );

        if (count($rejectedTitles) > 0) {
            Log::channel($this->logChannel)->info('Titulos rejeitados para filtrar no recovery', [
                'count' => count($rejectedTitles),
            ]);
        }

        // Agrupar sugestões do Strategist por impact para preencher lacunas
        $strategistByImpact = ['high' => [], 'medium' => [], 'low' => []];
        $rejectedInRecovery = 0;
        foreach ($allGenerated as $suggestion) {
            $impact = $suggestion['expected_impact'] ?? 'medium';
            if (! isset($strategistByImpact[$impact])) {
                $impact = 'medium';
            }
            $title = strtolower(trim($suggestion['title'] ?? ''));
            $normalizedTitle = $this->normalizeTitle($suggestion['title'] ?? '');

            // Check for exact duplicate with approved
            if (in_array($title, $approvedTitles)) {
                continue;
            }

            // Filter out previously rejected suggestions
            $isRejected = false;
            foreach ($rejectedNormalized as $rejNormalized) {
                if ($this->calculateTitleSimilarity($normalizedTitle, $rejNormalized) >= 0.75) {
                    $isRejected = true;
                    break;
                }
            }

            if ($isRejected) {
                $rejectedInRecovery++;
                Log::channel($this->logChannel)->info('Sugestao filtrada no recovery (similar a rejeitada anteriormente)', [
                    'title' => $suggestion['title'] ?? 'N/A',
                ]);

                continue;
            }

            // Check for semantically similar title with approved
            $isSimilar = false;
            foreach ($approvedNormalizedTitles as $approvedNormalized) {
                if ($this->calculateTitleSimilarity($normalizedTitle, $approvedNormalized) >= 0.85) {
                    $isSimilar = true;
                    break;
                }
            }

            if (! $isSimilar) {
                $strategistByImpact[$impact][] = $suggestion;
            }
        }

        if ($rejectedInRecovery > 0) {
            Log::channel($this->logChannel)->info('Total de sugestoes filtradas no recovery por serem similares a rejeitadas', [
                'count' => $rejectedInRecovery,
            ]);
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

                // V7: Check embedding similarity before recovering (threshold relaxado para recovery)
                if ($storeId > 0 && $this->embedding->isConfigured()) {
                    $textToEmbed = ($suggestion['title'] ?? '').' '.($suggestion['description'] ?? '');
                    try {
                        $embedding = $this->embedding->generate($textToEmbed);
                        if ($this->embedding->isTooSimilarFiltered($embedding, $storeId, 0.93, ['rejected', 'ignored', 'completed'], 90)) {
                            Log::channel($this->logChannel)->info('Recovery candidate rejected by embedding check', [
                                'title' => $suggestion['title'] ?? 'N/A',
                            ]);

                            continue;
                        }
                        $converted['embedding'] = $embedding;
                    } catch (\Exception $e) {
                        // On failure, proceed without embedding check
                    }
                }

                $distribution[$impact][] = $converted;
                $approvedTitles[] = strtolower(trim($suggestion['title'] ?? ''));
                $approvedNormalizedTitles[] = $this->normalizeTitle($suggestion['title'] ?? '');
                $needed--;

                Log::channel($this->logChannel)->info('Sugestao recuperada do Strategist para '.$impact, [
                    'title' => $suggestion['title'] ?? 'N/A',
                ]);
            }

            // Se ainda falta e não tem do Strategist no mesmo nível, promover do nível adjacente
            if ($needed > 0) {
                $adjacentLevels = match ($impact) {
                    'high' => ['medium', 'low'],
                    'medium' => ['high', 'low'],
                    'low' => ['medium', 'high'],
                };

                foreach ($adjacentLevels as $adjLevel) {
                    if ($needed <= 0) {
                        break;
                    }
                    foreach ($strategistByImpact[$adjLevel] as $key => $suggestion) {
                        if ($needed <= 0) {
                            break;
                        }

                        $converted = [
                            'final_version' => [
                                'category' => $suggestion['category'] ?? 'general',
                                'title' => $suggestion['title'] ?? '',
                                'description' => $suggestion['description'] ?? '',
                                'recommended_action' => $suggestion['recommended_action'] ?? '',
                                'expected_impact' => $impact, // Forcar o nivel correto
                                'target_metrics' => $suggestion['target_metrics'] ?? [],
                                'specific_data' => $suggestion['specific_data'] ?? $suggestion['roi_estimate'] ?? [],
                                'data_justification' => $suggestion['data_justification'] ?? '',
                            ],
                            'quality_score' => 5.5,
                            'final_priority' => $priority++,
                            'recovered_from_filter' => true,
                            'promoted_from' => $adjLevel,
                        ];

                        // V7: Check embedding similarity before recovering (threshold relaxado para recovery)
                        if ($storeId > 0 && $this->embedding->isConfigured()) {
                            $textToEmbed = ($suggestion['title'] ?? '').' '.($suggestion['description'] ?? '');
                            try {
                                $embedding = $this->embedding->generate($textToEmbed);
                                if ($this->embedding->isTooSimilarFiltered($embedding, $storeId, 0.93, ['rejected', 'ignored', 'completed'], 90)) {
                                    Log::channel($this->logChannel)->info('Recovery candidate (promoted) rejected by embedding check', [
                                        'title' => $suggestion['title'] ?? 'N/A',
                                    ]);

                                    continue;
                                }
                                $converted['embedding'] = $embedding;
                            } catch (\Exception $e) {
                                // On failure, proceed without embedding check
                            }
                        }

                        $distribution[$impact][] = $converted;
                        $approvedTitles[] = strtolower(trim($suggestion['title'] ?? ''));
                        $approvedNormalizedTitles[] = $this->normalizeTitle($suggestion['title'] ?? '');
                        unset($strategistByImpact[$adjLevel][$key]);
                        $needed--;

                        Log::channel($this->logChannel)->info('Sugestao promovida de '.$adjLevel.' para '.$impact, [
                            'title' => $suggestion['title'] ?? 'N/A',
                        ]);
                    }
                }
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
        Log::channel($this->logChannel)->warning('Fallback suggestions requested but disabled in V6', [
            'count_requested' => $count,
        ]);

        return [];
    }
}
