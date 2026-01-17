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
                // Formato simplificado: apenas campos necessários para persistência
                $approvedSuggestions[] = [
                    'final_version' => $this->normalizeFinalVersion($finalVersion),
                    'quality_score' => floatval($approved['quality_score'] ?? 5.0),
                    'final_priority' => intval($approved['final_priority'] ?? ($index + 1)),
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
