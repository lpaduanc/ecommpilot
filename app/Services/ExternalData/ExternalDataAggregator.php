<?php

namespace App\Services\ExternalData;

use App\Models\Store;
use Illuminate\Support\Facades\Log;

class ExternalDataAggregator
{
    private string $logChannel = 'analysis';

    public function __construct(
        private GoogleTrendsService $trendsService,
        private MarketDataService $marketService,
        private CompetitorAnalysisService $competitorService
    ) {}

    /**
     * Check if external data collection is enabled.
     * Reads directly from database to avoid cache issues with queue workers.
     */
    public function isEnabled(): bool
    {
        // Force fresh read from database to avoid cache issues
        $setting = \App\Models\SystemSetting::where('key', 'external_data.enabled')->first();

        return $setting ? (bool) $setting->getCastedValue() : false;
    }

    /**
     * Collect all external data for a store analysis.
     *
     * @param  Store  $store  Store to collect data for
     * @param  string  $niche  Detected niche
     * @param  string  $subcategory  Detected subcategory
     * @param  array  $topProducts  Top product names for search
     * @return array Aggregated external data
     */
    public function collect(Store $store, string $niche, string $subcategory, array $topProducts = []): array
    {
        $startTime = microtime(true);

        if (! $this->isEnabled()) {
            Log::channel($this->logChannel)->info('ExternalDataAggregator: Disabled, skipping');

            return $this->emptyResponse('Coleta de dados externos desabilitada');
        }

        Log::channel($this->logChannel)->info('┌─────────────────────────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('│ ETAPA 3.1/9: Coletando dados externos de mercado                │');
        Log::channel($this->logChannel)->info('└─────────────────────────────────────────────────────────────────┘');

        // Build keywords for trends from niche, subcategory, and top products
        $keywords = $this->buildKeywords($niche, $subcategory, $topProducts);

        // Collect data from all services
        $trendsData = $this->trendsService->getTrends($keywords, $niche);
        $marketData = $this->marketService->getMarketData($store->platform, $niche, $subcategory, $topProducts);
        $competitorData = $this->competitorService->analyze($store);

        $elapsedTime = round((microtime(true) - $startTime) * 1000, 2);

        // Log summary
        Log::channel($this->logChannel)->info('<<< Dados externos coletados', [
            'trends_sucesso' => $trendsData['sucesso'],
            'trends_tendencia' => $trendsData['tendencia'],
            'market_sucesso' => $marketData['sucesso'],
            'market_fonte' => $marketData['fonte'],
            'market_preco_medio' => $marketData['faixa_preco']['media'] ?? 0,
            'concorrentes_informados' => $competitorData['concorrentes_informados'],
            'concorrentes_analisados' => $competitorData['concorrentes_analisados'],
            'time_ms' => $elapsedTime,
        ]);

        return [
            'dados_mercado' => [
                'google_trends' => $trendsData,
                'precos_mercado' => $marketData,
            ],
            'concorrentes' => $competitorData['concorrentes'],
            'tem_concorrentes' => $competitorData['tem_concorrentes'],
            'resumo_concorrentes' => $competitorData['resumo'],
            'tempo_coleta_ms' => $elapsedTime,
        ];
    }

    /**
     * Build keywords for trends search.
     */
    private function buildKeywords(string $niche, string $subcategory, array $topProducts = []): array
    {
        $keywords = [];

        // Add niche-based keyword
        $nicheLabels = [
            'beauty' => 'cosméticos beleza',
            'moda' => 'moda roupas',
            'eletronicos' => 'eletrônicos',
            'casa_decoracao' => 'decoração casa',
            'alimentos' => 'alimentos orgânicos',
            'pet' => 'pet shop',
            'saude' => 'saúde bem-estar',
            'esportes' => 'esportes fitness',
        ];

        $keywords[] = $nicheLabels[$niche] ?? $niche;

        // Add subcategory
        if ($subcategory && $subcategory !== 'geral') {
            $keywords[] = $subcategory;
        }

        // Add top products (max 3)
        if (! empty($topProducts)) {
            $keywords = array_merge($keywords, array_slice($topProducts, 0, 3));
        }

        return array_slice($keywords, 0, 5);
    }

    /**
     * Return empty response when disabled.
     */
    private function emptyResponse(string $reason): array
    {
        return [
            'dados_mercado' => [
                'google_trends' => [
                    'interesse_busca' => 0,
                    'tendencia' => 'nao_disponivel',
                    'sazonalidade' => [],
                    'termos_relacionados' => [],
                    'sucesso' => false,
                    'motivo_falha' => $reason,
                ],
                'precos_mercado' => [
                    'fonte' => 'google_shopping',
                    'faixa_preco' => ['min' => 0, 'max' => 0, 'media' => 0, 'mediana' => 0],
                    'produtos_referencia' => [],
                    'sucesso' => false,
                    'motivo_falha' => $reason,
                ],
            ],
            'concorrentes' => [],
            'tem_concorrentes' => false,
            'resumo_concorrentes' => 'Coleta de dados externos desabilitada.',
            'tempo_coleta_ms' => 0,
        ];
    }

    /**
     * Get a summary of external data for prompts.
     */
    public function getSummaryForPrompts(array $externalData): array
    {
        $trends = $externalData['dados_mercado']['google_trends'] ?? [];
        $market = $externalData['dados_mercado']['precos_mercado'] ?? [];
        $competitors = $externalData['concorrentes'] ?? [];

        return [
            'tendencia_mercado' => $trends['tendencia'] ?? 'nao_disponivel',
            'interesse_busca' => $trends['interesse_busca'] ?? 0,
            'preco_medio_mercado' => $market['faixa_preco']['media'] ?? 0,
            'preco_min_mercado' => $market['faixa_preco']['min'] ?? 0,
            'preco_max_mercado' => $market['faixa_preco']['max'] ?? 0,
            'fonte_precos' => $market['fonte'] ?? 'nao_disponivel',
            'tem_concorrentes' => $externalData['tem_concorrentes'] ?? false,
            'total_concorrentes' => count($competitors),
            'concorrentes_sucesso' => count(array_filter($competitors, fn ($c) => $c['sucesso'] ?? false)),
            'diferenciais_concorrentes' => $this->aggregateCompetitorFeatures($competitors),
        ];
    }

    /**
     * Aggregate competitor features.
     */
    private function aggregateCompetitorFeatures(array $competitors): array
    {
        $features = [];

        foreach ($competitors as $competitor) {
            if (isset($competitor['diferenciais']) && is_array($competitor['diferenciais'])) {
                $features = array_merge($features, $competitor['diferenciais']);
            }
        }

        // Count occurrences and sort by frequency
        $featureCounts = array_count_values($features);
        arsort($featureCounts);

        return array_keys($featureCounts);
    }
}
