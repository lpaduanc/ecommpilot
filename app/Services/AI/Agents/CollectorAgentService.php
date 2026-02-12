<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\CollectorAgentPrompt;
use Illuminate\Support\Facades\Log;

class CollectorAgentService
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
     * Execute the collector agent.
     */
    public function execute(array $context): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── COLLECTOR AGENT ───────────────────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Gerando contexto historico e benchmarks                   │');
        Log::channel($this->logChannel)->info('    └────────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (resumo)
        Log::channel($this->logChannel)->info('    [COLLECTOR] Variaveis do contexto:', [
            'platform' => $context['platform'] ?? 'desconhecida',
            'niche' => $context['niche'] ?? 'geral',
            'store_stats_keys' => array_keys($context['store_stats'] ?? []),
            'previous_analyses_count' => count($context['previous_analyses'] ?? []),
            'previous_suggestions_count' => count($context['previous_suggestions'] ?? []),
            'benchmarks_count' => count($context['benchmarks'] ?? []),
            'has_external_data' => ! empty($context['external_data']),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Contexto recebido pelo Collector
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('COLLECTOR INPUT - Store Stats', $context['store_stats'] ?? []);
        $this->logFullData('COLLECTOR INPUT - External Data (Concorrentes)', $context['external_data'] ?? []);
        $this->logFullData('COLLECTOR INPUT - Benchmarks', $context['benchmarks'] ?? []);
        $this->logFullData('COLLECTOR INPUT - Previous Analyses', $context['previous_analyses'] ?? []);
        $this->logFullData('COLLECTOR INPUT - Store Goals', $context['store_goals'] ?? []);

        // Log do template do prompt
        Log::channel($this->logChannel)->info('    [COLLECTOR] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(CollectorAgentPrompt::getTemplate());

        $prompt = CollectorAgentPrompt::get($context);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.2,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.2, // Baixa: coleta de dados exige precisão, não criatividade
        ]);
        $apiTime = round((microtime(true) - $apiStart) * 1000, 2);

        Log::channel($this->logChannel)->info('    <<< Resposta recebida da AI', [
            'response_chars' => strlen($response),
            'api_time_ms' => $apiTime,
        ]);

        // Log da resposta completa da AI
        Log::channel($this->logChannel)->info('    [COLLECTOR] RESPOSTA AI:');
        Log::channel($this->logChannel)->info($response);

        $result = $this->parseResponse($response);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [COLLECTOR] Concluido', [
            'keys_returned' => array_keys($result),
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
            Log::channel($this->logChannel)->warning('    [COLLECTOR] ERRO: Nao foi possivel extrair JSON da resposta');

            return [
                'historical_summary' => [],
                'success_patterns' => [],
                'suggestions_to_avoid' => [],
                'relevant_benchmarks' => [],
                'identified_gaps' => [],
                'special_context' => 'Could not parse collector response',
            ];
        }

        Log::channel($this->logChannel)->info('    [COLLECTOR] JSON extraido com sucesso', [
            'keys' => array_keys($json),
        ]);

        return $json;
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Collector');
    }
}
