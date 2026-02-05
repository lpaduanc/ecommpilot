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
     * Log full data as formatted JSON without truncation.
     */
    private function logFullData(string $title, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $separator = '═══════════════════════════════════════════════════════════════════';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::channel($this->logChannel)->info($separator);
        Log::channel($this->logChannel)->info("█ {$title}");
        Log::channel($this->logChannel)->info($separator);
        Log::channel($this->logChannel)->info($json);
        Log::channel($this->logChannel)->info($separator);
    }

    /**
     * Execute the analyst agent.
     */
    public function execute(array $data): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── ANALYST AGENT ────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Analisando metricas, anomalias e padroes                     │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (resumo)
        Log::channel($this->logChannel)->info('    [ANALYST] Variaveis do contexto:', [
            'orders_summary_keys' => array_keys($data['orders_summary'] ?? []),
            'products_summary_keys' => array_keys($data['products_summary'] ?? []),
            'inventory_summary_keys' => array_keys($data['inventory_summary'] ?? []),
            'coupons_summary_keys' => array_keys($data['coupons_summary'] ?? []),
            'benchmarks_count' => count($data['benchmarks'] ?? []),
            'has_external_data' => isset($data['external_data']),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Contexto recebido pelo Analyst
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('ANALYST INPUT - Orders Summary', $data['orders_summary'] ?? []);
        $this->logFullData('ANALYST INPUT - Products Summary', $data['products_summary'] ?? []);
        $this->logFullData('ANALYST INPUT - Inventory Summary', $data['inventory_summary'] ?? []);
        $this->logFullData('ANALYST INPUT - Coupons Summary', $data['coupons_summary'] ?? []);
        $this->logFullData('ANALYST INPUT - External Data (Concorrentes)', $data['external_data'] ?? []);
        $this->logFullData('ANALYST INPUT - Benchmarks', $data['benchmarks'] ?? []);
        $this->logFullData('ANALYST INPUT - Historical Metrics', $data['historical_metrics'] ?? []);

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
     * Supports V4 format and V5 format with simplified field names.
     */
    private function normalizeAnalysis(array $analysis): array
    {
        $default = $this->getDefaultAnalysis();

        // Extract health score from V5/V4 format (health_score object) or legacy (overall_health)
        $healthScore = $analysis['health_score'] ?? [];
        $healthLegacy = $analysis['overall_health'] ?? [];

        $overallHealth = [
            // V5/V4 format uses score_final (after override), fallback to score for legacy
            'score' => $healthScore['score_final'] ?? $healthScore['score'] ?? $healthLegacy['score'] ?? $default['overall_health']['score'],
            'classification' => $healthScore['classificacao'] ?? $healthLegacy['classification'] ?? $default['overall_health']['classification'],
            'main_points' => [$analysis['resumo_executivo'] ?? ($healthLegacy['main_points'][0] ?? $default['overall_health']['main_points'][0])],
        ];

        // V5 uses 'posicionamento', V4 uses 'posicionamento_mercado'
        $posicionamento = $analysis['posicionamento_mercado'] ?? $analysis['posicionamento'] ?? [];

        // V5 uses 'briefing_strategist', V4 uses 'alertas_para_strategist'
        $alertasStrategist = $analysis['alertas_para_strategist'] ?? $analysis['briefing_strategist'] ?? [];

        // V5 uses flat 'alertas' array with 'severidade' field
        // V4 uses 'alertas' with criticos/atencao/monitoramento structure
        $alertas = $this->normalizeAlertas($analysis['alertas'] ?? []);

        // V5 uses 'anomalias', V4 uses 'anomalies'
        $anomalias = $analysis['anomalies'] ?? $analysis['anomalias'] ?? [];

        return [
            'metrics' => array_merge($default['metrics'], $analysis['metricas_detalhadas'] ?? $analysis['metrics'] ?? []),
            'anomalies' => $anomalias,
            'identified_patterns' => $analysis['identified_patterns'] ?? [],
            // V5/V4 fields passthrough
            'oportunidades' => $analysis['oportunidades'] ?? [],
            'alertas' => $alertas,
            'posicionamento_mercado' => $posicionamento,
            'contexto_mercado' => $analysis['contexto_mercado'] ?? [],
            'alertas_para_strategist' => $alertasStrategist,
            'resumo_executivo' => $analysis['resumo_executivo'] ?? null,
            'health_score' => $healthScore,
            'overall_health' => $overallHealth,
        ];
    }

    /**
     * Normalize alertas structure.
     * V5 uses flat array with 'severidade' field.
     * V4 uses nested structure with criticos/atencao/monitoramento.
     */
    private function normalizeAlertas(array $alertas): array
    {
        // If it's already V4 format (has criticos key), return as-is
        if (isset($alertas['criticos']) || isset($alertas['atencao']) || isset($alertas['monitoramento'])) {
            return $alertas;
        }

        // V5 format: flat array with 'severidade' field
        // Convert to V4 format for compatibility
        $normalized = [
            'criticos' => [],
            'atencao' => [],
            'monitoramento' => [],
        ];

        foreach ($alertas as $alerta) {
            $severidade = $alerta['severidade'] ?? 'monitorar';

            // Map V5 severidade to V4 keys
            $key = match ($severidade) {
                'critico' => 'criticos',
                'atencao' => 'atencao',
                default => 'monitoramento',
            };

            $normalized[$key][] = [
                'tipo' => $alerta['tipo'] ?? '',
                'titulo' => $alerta['titulo'] ?? '',
                'descricao' => $alerta['dados'] ?? $alerta['descricao'] ?? '',
                'impacto_estimado' => $alerta['impacto'] ?? '',
                'acao' => $alerta['acao'] ?? '',
            ];
        }

        return $normalized;
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
