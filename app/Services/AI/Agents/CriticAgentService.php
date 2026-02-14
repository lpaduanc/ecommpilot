<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\CriticAgentPrompt;
use Illuminate\Support\Facades\Log;

class CriticAgentService
{
    private string $logChannel = 'analysis';

    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Log full data as formatted JSON without truncation.
     */
    private function logFullData(string $title, mixed $data): void
    {
        // Normalize data to be JSON-serializable
        if (is_null($data) || (is_array($data) && empty($data))) {
            return;
        } elseif (is_scalar($data)) {
            $data = ['value' => $data];
        } elseif (is_object($data)) {
            $data = (array) $data;
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
     * Execute the critic agent.
     */
    public function execute(array $data): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── CRITIC AGENT ──────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Revisando e validando sugestoes geradas                      │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (resumo)
        Log::channel($this->logChannel)->info('    [CRITIC] Variaveis do contexto:', [
            'suggestions_count' => count($data['suggestions'] ?? []),
            'previous_suggestions_count' => count($data['previous_suggestions'] ?? []),
            'store_context_keys' => array_keys($data['store_context'] ?? []),
            'has_external_data' => ! empty($data['external_data']),
            'has_analyst_briefing' => ! empty($data['analyst_briefing']),
            'has_anomalies' => ! empty($data['anomalies']),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Contexto recebido pelo Critic
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('CRITIC INPUT - Sugestões para Revisar', $data['suggestions'] ?? []);
        $this->logFullData('CRITIC INPUT - Sugestões Anteriores', $data['previous_suggestions'] ?? []);
        $this->logFullData('CRITIC INPUT - External Data (Concorrentes)', $data['external_data'] ?? []);
        $this->logFullData('CRITIC INPUT - Store Context', $data['store_context'] ?? []);

        // Log do template do prompt
        Log::channel($this->logChannel)->info('    [CRITIC] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(CriticAgentPrompt::getTemplate());

        $prompt = CriticAgentPrompt::get($data);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.1,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.1,
            'max_tokens' => 32768, // Increased to prevent JSON truncation
        ]);
        $apiTime = round((microtime(true) - $apiStart) * 1000, 2);

        Log::channel($this->logChannel)->info('    <<< Resposta recebida da AI', [
            'response_chars' => strlen($response),
            'api_time_ms' => $apiTime,
        ]);

        // Log da resposta completa da AI
        Log::channel($this->logChannel)->info('    [CRITIC] RESPOSTA AI:');
        Log::channel($this->logChannel)->info($response);

        $result = $this->parseResponse($response);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [CRITIC] Concluido', [
            'approved_count' => count($result['approved_suggestions'] ?? []),
            'removed_count' => count($result['removed_suggestions'] ?? []),
            'average_quality' => $result['general_analysis']['average_quality'] ?? 0,
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
            Log::channel($this->logChannel)->warning('    [CRITIC] ERRO: Nao foi possivel extrair JSON da resposta');

            return $this->getDefaultResponse();
        }

        Log::channel($this->logChannel)->info('    [CRITIC] JSON extraido com sucesso', [
            'keys' => array_keys($json),
        ]);

        return $this->normalizeResponse($json);
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Critic');
    }

    /**
     * Normalize the response structure.
     * Supports both V4 (approved_suggestions, final_version) and V5 (suggestions, final) formats.
     */
    private function normalizeResponse(array $response): array
    {
        $approvedSuggestions = [];
        $removedSuggestions = [];

        // V5 format uses 'suggestions' array with 'status' field
        // V4 format uses 'approved_suggestions' array
        $rawSuggestions = $response['suggestions'] ?? $response['approved_suggestions'] ?? [];

        Log::channel($this->logChannel)->info('    [CRITIC] Processando sugestoes', [
            'total_no_json' => count($rawSuggestions),
            'format' => isset($response['suggestions']) ? 'V5' : 'V4',
        ]);

        foreach ($rawSuggestions as $index => $item) {
            // Check if this is V5 format (has 'final' and 'status')
            $isV5 = isset($item['final']) || isset($item['status']);

            if ($isV5) {
                // V5 format
                $status = $item['status'] ?? 'approved';
                $finalVersion = $item['final'] ?? [];

                if ($status === 'replaced' && empty($finalVersion)) {
                    // Rejected without replacement
                    $removedSuggestions[] = [
                        'original_title' => $item['original_title'] ?? '',
                        'reason' => $item['changes_made'] ?? 'Rejeitada',
                    ];

                    continue;
                }

                if (! empty($finalVersion)) {
                    $approvedSuggestions[] = [
                        'final_version' => $this->normalizeFinalVersion($finalVersion),
                        'quality_score' => floatval($item['score_qualidade'] ?? 7.0),
                        'final_priority' => intval($finalVersion['priority'] ?? ($index + 1)),
                        'status' => $status,
                        'changes_made' => $item['changes_made'] ?? null,
                        'dados_verificados' => $item['dados_verificados'] ?? null,
                        'numeros_corretos' => $item['numeros_corretos'] ?? null,
                        'verificacao_status' => $item['verificacao_status'] ?? null,
                    ];
                }
            } else {
                // V4 format
                $finalVersion = $item['final_version'] ?? $item['original'] ?? [];

                if (! empty($finalVersion)) {
                    $qualityScore = $item['review']['quality_score']
                        ?? $item['quality_score']
                        ?? $item['review']['score']
                        ?? 5.0;

                    $approvedSuggestions[] = [
                        'final_version' => $this->normalizeFinalVersion($finalVersion),
                        'quality_score' => floatval($qualityScore),
                        'final_priority' => intval($item['review']['final_priority'] ?? $item['final_priority'] ?? ($index + 1)),
                    ];
                } else {
                    Log::channel($this->logChannel)->warning("    [CRITIC] Sugestao {$index} com final_version vazio");
                }
            }
        }

        // V4 format has separate removed_suggestions
        if (isset($response['removed_suggestions'])) {
            $removedSuggestions = array_merge($removedSuggestions, $response['removed_suggestions']);
        }

        // V5 format has rejected_suggestions
        if (isset($response['rejected_suggestions'])) {
            $removedSuggestions = array_merge($removedSuggestions, $response['rejected_suggestions']);
        }

        Log::channel($this->logChannel)->info('    [CRITIC] Normalizacao concluida', [
            'total_aprovadas' => count($approvedSuggestions),
            'total_removidas' => count($removedSuggestions),
        ]);

        // Enforce flexible distribution (max 3 per category, total 5-9)
        $approvedSuggestions = $this->enforceDistribution($approvedSuggestions);

        // V5 has review_summary, V4 has general_analysis
        $reviewSummary = $response['review_summary'] ?? $response['general_analysis'] ?? [];

        return [
            'approved_suggestions' => $approvedSuggestions,
            'removed_suggestions' => $removedSuggestions,
            'general_analysis' => array_merge([
                'total_received' => $reviewSummary['approved'] ?? 0 + ($reviewSummary['improved'] ?? 0) + ($reviewSummary['rejected'] ?? 0),
                'total_approved' => count($approvedSuggestions),
                'total_removed' => count($removedSuggestions),
                'average_quality' => $this->calculateAverageQuality($approvedSuggestions),
                'observations' => '',
            ], $reviewSummary),
        ];
    }

    /**
     * Normalize the final version of a suggestion.
     * Supports both V4 and V5 formats.
     */
    private function normalizeFinalVersion(array $suggestion): array
    {
        // V5 uses 'problem', V4 uses 'description'
        $description = $suggestion['description'] ?? $suggestion['problem'] ?? '';

        // V5 uses 'action', V4 uses 'recommended_action'
        $recommendedAction = $suggestion['recommended_action'] ?? $suggestion['action'] ?? '';

        // V5 uses 'data_source', V4 uses 'data_justification'
        $dataJustification = $suggestion['data_justification'] ?? $suggestion['data_source'] ?? '';

        // V5 uses 'expected_result' in specific_data
        $specificData = $suggestion['specific_data'] ?? [];
        if (! empty($suggestion['expected_result'])) {
            $specificData['expected_result'] = $suggestion['expected_result'];
        }

        // V5 has 'implementation' object
        $implementation = $suggestion['implementation'] ?? [];

        return [
            'category' => $suggestion['category'] ?? 'general',
            'title' => substr($suggestion['title'] ?? '', 0, 255),
            'description' => $description,
            'recommended_action' => $recommendedAction,
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact'] ?? 'medium'),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
            'specific_data' => $specificData,
            'data_justification' => $dataJustification,
            // V5 additional fields
            'implementation' => $implementation,
            'competitor_reference' => $suggestion['competitor_reference'] ?? null,
            // Phase 5: Traceability fields
            'insight_origem' => $suggestion['insight_origem'] ?? 'best_practice',
            'nivel_confianca' => $suggestion['nivel_confianca'] ?? 'medio',
        ];
    }

    /**
     * Normalize impact value.
     */
    private function normalizeImpact(string $impact): string
    {
        $impact = strtolower(trim($impact));

        if (in_array($impact, ['high', 'alto'])) {
            return 'high';
        }
        if (in_array($impact, ['medium', 'medio', 'médio'])) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Calculate average quality score.
     */
    private function calculateAverageQuality(array $suggestions): float
    {
        if (empty($suggestions)) {
            return 0;
        }

        $total = array_sum(array_column($suggestions, 'quality_score'));

        return round($total / count($suggestions), 1);
    }

    /**
     * Enforce strict 3-3-3 distribution of suggestions (exactly 3 per impact level, 9 total).
     */
    private function enforceDistribution(array $suggestions): array
    {
        $high = [];
        $medium = [];
        $low = [];

        // Separate by impact
        foreach ($suggestions as $suggestion) {
            $impact = $suggestion['final_version']['expected_impact'] ?? 'medium';
            switch ($impact) {
                case 'high':
                    $high[] = $suggestion;
                    break;
                case 'low':
                    $low[] = $suggestion;
                    break;
                default:
                    $medium[] = $suggestion;
                    break;
            }
        }

        Log::channel($this->logChannel)->info('    [CRITIC] Distribuicao antes do rebalanceamento', [
            'high' => count($high),
            'medium' => count($medium),
            'low' => count($low),
        ]);

        // Cap each category at max 3 (strict 3-3-3 = 9 total)
        $high = array_slice($high, 0, 3);
        $medium = array_slice($medium, 0, 3);
        $low = array_slice($low, 0, 3);

        // Ensure at least 1 HIGH and 1 LOW if we have enough suggestions
        $total = count($high) + count($medium) + count($low);
        if ($total >= 3) {
            if (empty($high) && count($medium) > 1) {
                $item = array_shift($medium);
                $item['final_version']['expected_impact'] = 'high';
                $high[] = $item;
            }
            if (empty($low) && count($medium) > 1) {
                $item = array_pop($medium);
                $item['final_version']['expected_impact'] = 'low';
                $low[] = $item;
            }
        }

        $result = array_merge($high, $medium, $low);

        // Reassign priorities
        $priority = 1;
        foreach ($result as &$suggestion) {
            $suggestion['final_priority'] = $priority++;
        }

        $finalHigh = count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'high'));
        $finalMedium = count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'medium'));
        $finalLow = count(array_filter($result, fn ($s) => $s['final_version']['expected_impact'] === 'low'));

        Log::channel($this->logChannel)->info('    [CRITIC] Distribuicao apos rebalanceamento', [
            'high' => $finalHigh,
            'medium' => $finalMedium,
            'low' => $finalLow,
            'total' => count($result),
        ]);

        return $result;
    }

    /**
     * Get default response when parsing fails.
     */
    private function getDefaultResponse(): array
    {
        return [
            'approved_suggestions' => [],
            'removed_suggestions' => [],
            'general_analysis' => [
                'total_received' => 0,
                'total_approved' => 0,
                'total_removed' => 0,
                'average_quality' => 0,
                'observations' => 'Could not parse critic response',
            ],
        ];
    }
}
