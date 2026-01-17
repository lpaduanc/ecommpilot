<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\StrategistAgentPrompt;
use Illuminate\Support\Facades\Log;

class StrategistAgentService
{
    private string $logChannel = 'analysis';

    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the strategist agent.
     */
    public function execute(array $context): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── STRATEGIST AGENT ─────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Gerando sugestoes estrategicas baseadas na analise          │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (sem dados reais)
        Log::channel($this->logChannel)->info('    [STRATEGIST] Variaveis do contexto:', [
            'collector_context_keys' => array_keys($context['collector_context'] ?? []),
            'analysis_keys' => array_keys($context['analysis'] ?? []),
            'previous_suggestions_count' => count($context['previous_suggestions'] ?? []),
            'rag_strategies_count' => count($context['rag_strategies'] ?? []),
        ]);

        // Log do template do prompt (sem dados do banco)
        Log::channel($this->logChannel)->info('    [STRATEGIST] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(StrategistAgentPrompt::getTemplate());

        $prompt = StrategistAgentPrompt::get($context);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.7,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.7, // Higher temperature for creative suggestions
        ]);
        $apiTime = round((microtime(true) - $apiStart) * 1000, 2);

        Log::channel($this->logChannel)->info('    <<< Resposta recebida da AI', [
            'response_chars' => strlen($response),
            'api_time_ms' => $apiTime,
        ]);

        // Log da resposta completa da AI
        Log::channel($this->logChannel)->info('    [STRATEGIST] RESPOSTA AI:');
        Log::channel($this->logChannel)->info($response);

        $result = $this->parseResponse($response);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [STRATEGIST] Concluido', [
            'suggestions_generated' => count($result['suggestions'] ?? []),
            'has_observations' => ! empty($result['general_observations']),
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
            Log::channel($this->logChannel)->warning('    [STRATEGIST] ERRO: Nao foi possivel extrair JSON da resposta');

            return [
                'suggestions' => [],
                'general_observations' => 'Could not generate suggestions',
            ];
        }

        Log::channel($this->logChannel)->info('    [STRATEGIST] JSON extraido com sucesso', [
            'keys' => array_keys($json),
            'has_suggestions' => isset($json['suggestions']),
        ]);

        // Validate suggestions structure
        $suggestions = $json['suggestions'] ?? [];
        $validatedSuggestions = [];

        Log::channel($this->logChannel)->info('    [STRATEGIST] Validando sugestoes', [
            'total_encontradas' => count($suggestions),
        ]);

        foreach ($suggestions as $index => $suggestion) {
            if ($this->isValidSuggestion($suggestion)) {
                $validatedSuggestions[] = $this->normalizeSuggestion($suggestion);
            } else {
                Log::channel($this->logChannel)->warning("    [STRATEGIST] Sugestao {$index} falhou na validacao", [
                    'has_category' => ! empty($suggestion['category']),
                    'has_title' => ! empty($suggestion['title']),
                    'has_description' => ! empty($suggestion['description']),
                    'has_recommended_action' => ! empty($suggestion['recommended_action']),
                    'has_expected_impact' => ! empty($suggestion['expected_impact']),
                ]);
            }
        }

        Log::channel($this->logChannel)->info('    [STRATEGIST] Validacao concluida', [
            'total_validas' => count($validatedSuggestions),
            'total_invalidas' => count($suggestions) - count($validatedSuggestions),
        ]);

        return [
            'suggestions' => $validatedSuggestions,
            'general_observations' => $json['general_observations'] ?? '',
        ];
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Strategist');
    }

    /**
     * Check if a suggestion has required fields.
     */
    private function isValidSuggestion(array $suggestion): bool
    {
        $required = ['category', 'title', 'description', 'recommended_action', 'expected_impact'];

        foreach ($required as $field) {
            if (empty($suggestion[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize suggestion structure.
     */
    private function normalizeSuggestion(array $suggestion): array
    {
        return [
            'category' => $suggestion['category'],
            'title' => substr($suggestion['title'], 0, 255),
            'description' => $suggestion['description'],
            'recommended_action' => $suggestion['recommended_action'],
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact']),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
            'implementation_time' => $suggestion['implementation_time'] ?? 'immediate',
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
}
