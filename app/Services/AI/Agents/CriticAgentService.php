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
     * Execute the critic agent.
     */
    public function execute(array $data): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── CRITIC AGENT ──────────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Revisando e validando sugestoes geradas                      │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (sem dados reais)
        Log::channel($this->logChannel)->info('    [CRITIC] Variaveis do contexto:', [
            'suggestions_count' => count($data['suggestions'] ?? []),
            'previous_suggestions_count' => count($data['previous_suggestions'] ?? []),
            'store_context_keys' => array_keys($data['store_context'] ?? []),
        ]);

        // Log do template do prompt (sem dados do banco)
        Log::channel($this->logChannel)->info('    [CRITIC] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(CriticAgentPrompt::getTemplate());

        $prompt = CriticAgentPrompt::get($data);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.3,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.3, // Lower temperature for consistent evaluation
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
     */
    private function normalizeResponse(array $response): array
    {
        $approvedSuggestions = [];

        $rawApproved = $response['approved_suggestions'] ?? [];
        Log::channel($this->logChannel)->info('    [CRITIC] Processando sugestoes aprovadas', [
            'total_no_json' => count($rawApproved),
        ]);

        foreach ($rawApproved as $index => $approved) {
            // Usar final_version se disponível, senão fallback para original (compatibilidade)
            $finalVersion = $approved['final_version'] ?? $approved['original'] ?? [];

            if (! empty($finalVersion)) {
                // Extrair quality_score de múltiplos locais possíveis
                $qualityScore = $approved['review']['quality_score']
                    ?? $approved['quality_score']
                    ?? $approved['review']['score']
                    ?? 5.0;

                // Formato simplificado: apenas campos necessários para persistência
                $approvedSuggestions[] = [
                    'final_version' => $this->normalizeFinalVersion($finalVersion),
                    'quality_score' => floatval($qualityScore),
                    'final_priority' => intval($approved['review']['final_priority'] ?? $approved['final_priority'] ?? ($index + 1)),
                ];
            } else {
                Log::channel($this->logChannel)->warning("    [CRITIC] Sugestao {$index} com final_version vazio", [
                    'has_final_version' => isset($approved['final_version']),
                    'has_original' => isset($approved['original']),
                ]);
            }
        }

        Log::channel($this->logChannel)->info('    [CRITIC] Normalizacao concluida', [
            'total_aprovadas' => count($approvedSuggestions),
            'total_removidas' => count($response['removed_suggestions'] ?? []),
        ]);

        // Enforce 3-3-3 distribution
        $approvedSuggestions = $this->enforceDistribution($approvedSuggestions);

        return [
            'approved_suggestions' => $approvedSuggestions,
            'removed_suggestions' => $response['removed_suggestions'] ?? [],
            'general_analysis' => array_merge([
                'total_received' => 0,
                'total_approved' => count($approvedSuggestions),
                'total_removed' => count($response['removed_suggestions'] ?? []),
                'average_quality' => $this->calculateAverageQuality($approvedSuggestions),
                'observations' => '',
            ], $response['general_analysis'] ?? []),
        ];
    }

    /**
     * Normalize the final version of a suggestion.
     */
    private function normalizeFinalVersion(array $suggestion): array
    {
        return [
            'category' => $suggestion['category'] ?? 'general',
            'title' => substr($suggestion['title'] ?? '', 0, 255),
            'description' => $suggestion['description'] ?? '',
            'recommended_action' => $suggestion['recommended_action'] ?? '',
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact'] ?? 'medium'),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
            'specific_data' => $suggestion['specific_data'] ?? [],
            'data_justification' => $suggestion['data_justification'] ?? '',
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
     * Enforce 3-3-3 distribution of suggestions.
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

        // Rebalance if needed
        $result = $this->rebalanceDistribution($high, $medium, $low);

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
     * Rebalance arrays to achieve 3-3-3 distribution.
     */
    private function rebalanceDistribution(array $high, array $medium, array $low): array
    {
        $targetPerCategory = 3;

        // If we have excess in high, redistribute to medium or low
        while (count($high) > $targetPerCategory && (count($medium) < $targetPerCategory || count($low) < $targetPerCategory)) {
            $item = array_pop($high);
            if (count($medium) < $targetPerCategory) {
                $item['final_version']['expected_impact'] = 'medium';
                $medium[] = $item;
            } else {
                $item['final_version']['expected_impact'] = 'low';
                $low[] = $item;
            }
        }

        // If we have excess in medium, redistribute to low
        while (count($medium) > $targetPerCategory && count($low) < $targetPerCategory) {
            $item = array_pop($medium);
            $item['final_version']['expected_impact'] = 'low';
            $low[] = $item;
        }

        // If we have shortages in high, promote from medium
        while (count($high) < $targetPerCategory && count($medium) > $targetPerCategory) {
            $item = array_shift($medium);
            $item['final_version']['expected_impact'] = 'high';
            $high[] = $item;
        }

        // If we have shortages in medium, promote from low
        while (count($medium) < $targetPerCategory && count($low) > $targetPerCategory) {
            $item = array_shift($low);
            $item['final_version']['expected_impact'] = 'medium';
            $medium[] = $item;
        }

        // Take exactly 3 from each (or all if less than 3)
        $result = array_merge(
            array_slice($high, 0, $targetPerCategory),
            array_slice($medium, 0, $targetPerCategory),
            array_slice($low, 0, $targetPerCategory)
        );

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
