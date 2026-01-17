<?php

namespace App\Services\AI\Agents;

use App\Models\SystemSetting;
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

        // Detectar versão do formato
        $formatVersion = $data['format_version'] ?? SystemSetting::get('analysis.format_version', 'v1');
        $useV2 = $formatVersion === 'v2';

        // Log das variáveis usadas (sem dados reais)
        Log::channel($this->logChannel)->info('    [ANALYST] Variaveis do contexto:', [
            'format_version' => $formatVersion,
            'use_v2' => $useV2,
            'orders_summary_keys' => array_keys($data['orders_summary'] ?? []),
            'products_summary_keys' => array_keys($data['products_summary'] ?? []),
            'inventory_summary_keys' => array_keys($data['inventory_summary'] ?? []),
            'coupons_summary_keys' => array_keys($data['coupons_summary'] ?? []),
            'benchmarks_count' => count($data['benchmarks'] ?? []),
        ]);

        // Log do template do prompt (sem dados do banco)
        if ($useV2) {
            Log::channel($this->logChannel)->info('    [ANALYST] PROMPT TEMPLATE (V2):');
            Log::channel($this->logChannel)->info(AnalystAgentPrompt::getTemplateV2());
        } else {
            Log::channel($this->logChannel)->info('    [ANALYST] PROMPT TEMPLATE (V1):');
            Log::channel($this->logChannel)->info(AnalystAgentPrompt::getTemplate());
        }

        // Escolher prompt baseado na versão
        if ($useV2) {
            $useMarkdownTables = (bool) SystemSetting::get('analysis.v2.use_markdown_tables', true);
            $prompt = AnalystAgentPrompt::getV2($data, $useMarkdownTables);
        } else {
            $prompt = AnalystAgentPrompt::get($data);
        }

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

        // Se v2, logar avisos de validação de limites (não bloqueia)
        if ($useV2 && SystemSetting::get('analysis.v2.validate_field_lengths', true)) {
            $this->logFieldLengthWarnings($result);
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [ANALYST] Concluido', [
            'keys_returned' => array_keys($result),
            'health_score' => $result['overall_health']['score'] ?? 'N/A',
            'anomalies_count' => count($result['anomalies'] ?? []),
            'patterns_count' => count($result['identified_patterns'] ?? []),
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
            'health_score' => $json['overall_health']['score'] ?? 'N/A',
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
     */
    private function normalizeAnalysis(array $analysis): array
    {
        $default = $this->getDefaultAnalysis();

        return [
            'metrics' => array_merge($default['metrics'], $analysis['metrics'] ?? []),
            'anomalies' => $analysis['anomalies'] ?? [],
            'identified_patterns' => $analysis['identified_patterns'] ?? [],
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
                'main_points' => ['Could not complete full analysis'],
            ],
        ];
    }

    /**
     * Log warnings for fields that exceed character limits (v2 only).
     * Does not block - just logs for monitoring.
     */
    private function logFieldLengthWarnings(array $data): void
    {
        $warnings = [];

        // Verificar main_points (limite 150 chars cada)
        $mainPoints = $data['overall_health']['main_points'] ?? [];
        foreach ($mainPoints as $i => $point) {
            if (strlen($point) > 150) {
                $warnings[] = "main_points[{$i}]: ".strlen($point).' chars (limite 150)';
            }
        }

        // Verificar anomalias (descrição limite 200 chars)
        foreach ($data['anomalies'] ?? [] as $i => $anomaly) {
            $desc = $anomaly['description'] ?? '';
            if (strlen($desc) > 200) {
                $warnings[] = "anomaly[{$i}].description: ".strlen($desc).' chars (limite 200)';
            }
        }

        // Verificar patterns (descrição e opportunity limite 200 chars)
        foreach ($data['identified_patterns'] ?? [] as $i => $pattern) {
            $desc = $pattern['description'] ?? '';
            if (strlen($desc) > 200) {
                $warnings[] = "pattern[{$i}].description: ".strlen($desc).' chars (limite 200)';
            }
            $opp = $pattern['opportunity'] ?? '';
            if (strlen($opp) > 200) {
                $warnings[] = "pattern[{$i}].opportunity: ".strlen($opp).' chars (limite 200)';
            }
        }

        if (! empty($warnings)) {
            Log::channel($this->logChannel)->warning('    [ANALYST] V2: Avisos de tamanho de campos', [
                'warning_count' => count($warnings),
                'warnings' => $warnings,
            ]);
        }
    }
}
