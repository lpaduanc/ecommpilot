<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\AnalystAgentPrompt;
use Illuminate\Support\Facades\Log;

class AnalystAgentService
{
    private string $logChannel = 'analysis';

    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the analyst agent.
     */
    public function execute(array $data): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── ANALYST AGENT ────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Analisando metricas, anomalias e padroes                     │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (sem dados reais)
        Log::channel($this->logChannel)->info('    [ANALYST] Variaveis do contexto:', [
            'orders_summary_keys' => array_keys($data['orders_summary'] ?? []),
            'products_summary_keys' => array_keys($data['products_summary'] ?? []),
            'inventory_summary_keys' => array_keys($data['inventory_summary'] ?? []),
            'coupons_summary_keys' => array_keys($data['coupons_summary'] ?? []),
            'benchmarks_count' => count($data['benchmarks'] ?? []),
            'has_external_data' => isset($data['external_data']),
        ]);

        // Log do template do prompt
        Log::channel($this->logChannel)->info('    [ANALYST] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(AnalystAgentPrompt::getTemplate());

        // Gerar prompt V3
        $prompt = AnalystAgentPrompt::get($data);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.2,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.2, // Very low temperature for analytical accuracy
        ]);
        $apiTime = round((microtime(true) - $apiStart) * 1000, 2);

        Log::channel($this->logChannel)->info('    <<< Resposta recebida da AI', [
            'response_chars' => strlen($response),
            'api_time_ms' => $apiTime,
        ]);

        // Log da resposta completa da AI
        Log::channel($this->logChannel)->info('    [ANALYST] RESPOSTA AI:');
        Log::channel($this->logChannel)->info($response);

        $result = $this->parseResponse($response);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [ANALYST] Concluido', [
            'keys_returned' => array_keys($result),
            'health_score' => $result['overall_health']['score'] ?? 'N/A',
            'anomalies_count' => count($result['anomalies'] ?? []),
            'patterns_count' => count($result['identified_patterns'] ?? []),
            'oportunidades_count' => count($result['oportunidades'] ?? []),
            'total_time_ms' => $totalTime,
        ]);

        // Adicionar prompt usado para logging no final do pipeline
        $result['_prompt_used'] = $prompt;

        return $result;
    }

    /**
     * Parse the AI response into structured data.
     */
    private function parseResponse(string $response): array
    {
        $json = $this->extractJson($response);

        if ($json === null) {
            Log::channel($this->logChannel)->warning('    [ANALYST] ERRO: Nao foi possivel extrair JSON da resposta');

            return $this->getDefaultAnalysis();
        }

        Log::channel($this->logChannel)->info('    [ANALYST] JSON extraido com sucesso', [
            'keys' => array_keys($json),
            'health_score' => $json['health_score']['score'] ?? $json['overall_health']['score'] ?? 'N/A',
        ]);

        // Validate and normalize the structure
        return $this->normalizeAnalysis($json);
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Analyst');
    }

    /**
     * Normalize the analysis structure.
     * Supports V3 format with Portuguese field names.
     */
    private function normalizeAnalysis(array $analysis): array
    {
        $default = $this->getDefaultAnalysis();

        // Extract health score from V3 format (health_score object) or legacy (overall_health)
        $healthV3 = $analysis['health_score'] ?? [];
        $healthLegacy = $analysis['overall_health'] ?? [];

        $overallHealth = [
            'score' => $healthV3['score'] ?? $healthLegacy['score'] ?? $default['overall_health']['score'],
            'classification' => $healthV3['classificacao'] ?? $healthLegacy['classification'] ?? $default['overall_health']['classification'],
            'main_points' => [$analysis['resumo_executivo'] ?? ($healthLegacy['main_points'][0] ?? $default['overall_health']['main_points'][0])],
        ];

        return [
            'metrics' => array_merge($default['metrics'], $analysis['metricas_detalhadas'] ?? $analysis['metrics'] ?? []),
            'anomalies' => $analysis['anomalies'] ?? [],
            'identified_patterns' => $analysis['identified_patterns'] ?? [],
            // V3 fields passthrough
            'oportunidades' => $analysis['oportunidades'] ?? [],
            'alertas' => $analysis['alertas'] ?? [],
            'posicionamento_mercado' => $analysis['posicionamento_mercado'] ?? [],
            'contexto_mercado' => $analysis['contexto_mercado'] ?? [],
            'alertas_para_strategist' => $analysis['alertas_para_strategist'] ?? [],
            'resumo_executivo' => $analysis['resumo_executivo'] ?? null,
            'health_score' => $healthV3,
            'overall_health' => $overallHealth,
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
                    'previous_period_variation' => 0,
                ],
                'average_order_value' => [
                    'value' => 0,
                    'benchmark' => 0,
                    'percentage_difference' => 0,
                ],
                'conversion' => [
                    'rate' => 0,
                    'benchmark' => 0,
                ],
                'cancellation' => [
                    'rate' => 0,
                    'main_reasons' => [],
                ],
                'inventory' => [
                    'out_of_stock_products' => 0,
                    'critical_stock_products' => 0,
                    'stagnant_inventory_value' => 0,
                ],
                'coupons' => [
                    'usage_rate' => 0,
                    'ticket_impact' => 0,
                ],
            ],
            'anomalies' => [],
            'identified_patterns' => [],
            'overall_health' => [
                'score' => 50,
                'classification' => 'attention',
                'main_points' => ['Análise concluída com dados parciais'],
            ],
        ];
    }
}
