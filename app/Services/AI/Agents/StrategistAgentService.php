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
     * Execute the strategist agent.
     */
    public function execute(array $context): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── STRATEGIST AGENT ─────────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Gerando sugestoes estrategicas baseadas na analise          │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (resumo)
        Log::channel($this->logChannel)->info('    [STRATEGIST] Variaveis do contexto:', [
            'collector_context_keys' => array_keys($context['collector_context'] ?? []),
            'analysis_keys' => array_keys($context['analysis'] ?? []),
            'previous_suggestions_count' => count($context['previous_suggestions'] ?? []),
            'rag_strategies_count' => count($context['rag_strategies'] ?? []),
            'has_external_data' => ! empty($context['external_data']),
            'has_learning_context' => ! empty($context['learning_context']),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Contexto recebido pelo Strategist
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('STRATEGIST INPUT - External Data (Concorrentes)', $context['external_data'] ?? []);
        $this->logFullData('STRATEGIST INPUT - Learning Context', $context['learning_context'] ?? []);
        $this->logFullData('STRATEGIST INPUT - Best Sellers', $context['best_sellers'] ?? []);
        $this->logFullData('STRATEGIST INPUT - Out of Stock List', $context['out_of_stock_list'] ?? []);
        $this->logFullData('STRATEGIST INPUT - Anomalies', $context['anomalies'] ?? []);
        $this->logFullData('STRATEGIST INPUT - Store Goals', $context['store_goals'] ?? []);

        // Log do template do prompt
        Log::channel($this->logChannel)->info('    [STRATEGIST] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(StrategistAgentPrompt::getTemplate());

        $prompt = StrategistAgentPrompt::get($context);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 'from_settings',
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
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

        // Validar qualidade das sugestões HIGH (rebaixar se não tiverem dados específicos)
        $finalSuggestions = $this->validateHighPrioritySuggestions($validatedSuggestions);

        return [
            'suggestions' => $finalSuggestions,
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
     * Supports both V4 (description, recommended_action) and V5 (problem, action) formats.
     */
    private function isValidSuggestion(array $suggestion): bool
    {
        // V5 format uses 'problem' and 'action'
        // V4 format uses 'description' and 'recommended_action'
        $hasDescription = ! empty($suggestion['description']) || ! empty($suggestion['problem']);
        $hasAction = ! empty($suggestion['recommended_action']) || ! empty($suggestion['action']);

        return ! empty($suggestion['category'])
            && ! empty($suggestion['title'])
            && $hasDescription
            && $hasAction
            && ! empty($suggestion['expected_impact']);
    }

    /**
     * Normalize suggestion structure.
     * Supports both V4 and V5 formats.
     */
    private function normalizeSuggestion(array $suggestion): array
    {
        // V5 uses 'problem', V4 uses 'description'
        $description = $suggestion['description'] ?? $suggestion['problem'] ?? '';

        // V5 uses 'action', V4 uses 'recommended_action'
        $recommendedAction = $suggestion['recommended_action'] ?? $suggestion['action'] ?? '';

        // V5 uses 'expected_result', V4 uses 'specific_data'
        $specificData = $suggestion['specific_data'] ?? [];
        if (! empty($suggestion['expected_result'])) {
            $specificData['expected_result'] = $suggestion['expected_result'];
        }

        // V5 uses 'data_source', V4 uses 'data_justification'
        $dataJustification = $suggestion['data_justification'] ?? $suggestion['data_source'] ?? '';

        // V5 has 'implementation' object
        $implementation = $suggestion['implementation'] ?? [];
        $implementationTime = $suggestion['implementation_time'] ?? $implementation['complexity'] ?? 'immediate';

        return [
            'category' => $suggestion['category'],
            'title' => substr($suggestion['title'], 0, 255),
            'description' => $description,
            'recommended_action' => $recommendedAction,
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact']),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
            'implementation_time' => $implementationTime,
            'specific_data' => $specificData,
            'data_justification' => $dataJustification,
            // V5 additional fields
            'implementation' => $implementation,
            'competitor_reference' => $suggestion['competitor_reference'] ?? null,
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
     * Validate HIGH priority suggestions have specific data.
     *
     * Suggestions marked as HIGH must contain specific data like:
     * - Monetary values (R$ X.XXX)
     * - Percentages (X%)
     * - Product/SKU/order counts
     *
     * If a HIGH suggestion lacks specific data, it's downgraded to MEDIUM.
     */
    private function validateHighPrioritySuggestions(array $suggestions): array
    {
        $downgraded = 0;

        $validated = collect($suggestions)->map(function ($suggestion) use (&$downgraded) {
            if (($suggestion['expected_impact'] ?? '') !== 'high') {
                return $suggestion;
            }

            // Combine problem/description + action/recommended_action for validation
            $textToCheck = ($suggestion['description'] ?? '')
                .' '.($suggestion['recommended_action'] ?? '');

            // Check for specific data patterns:
            // - R$ values: R$ 1.234 or R$ 1.234,56 or R$1234
            // - Percentages: 15% or 15,5%
            // - Counts: 10 produtos, 5 SKUs, 100 pedidos
            $hasSpecificData = preg_match(
                '/R\$\s*[\d.,]+|\d+[,.]?\d*\s*%|\d+\s*(produtos?|SKUs?|pedidos?|itens?|clientes?|unidades?)/iu',
                $textToCheck
            );

            if (! $hasSpecificData) {
                $suggestion['expected_impact'] = 'medium';
                $suggestion['_downgraded_from'] = 'high';
                $downgraded++;

                Log::channel($this->logChannel)->warning('    [STRATEGIST] Sugestao HIGH rebaixada para MEDIUM (falta dados especificos)', [
                    'title' => $suggestion['title'] ?? 'N/A',
                ]);
            }

            return $suggestion;
        })->toArray();

        if ($downgraded > 0) {
            Log::channel($this->logChannel)->info('    [STRATEGIST] Validacao de qualidade HIGH concluida', [
                'total_downgraded' => $downgraded,
            ]);
        }

        return $validated;
    }
}
