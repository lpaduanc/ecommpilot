<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\ProfileSynthesizerPrompt;
use Illuminate\Support\Facades\Log;

class ProfileSynthesizerService
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
     * Execute the profile synthesizer agent.
     */
    public function execute(array $context): array
    {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('    ┌─── PROFILE SYNTHESIZER AGENT ───────────────────────────┐');
        Log::channel($this->logChannel)->info('    │ Gerando perfil sintetizado da loja                      │');
        Log::channel($this->logChannel)->info('    └──────────────────────────────────────────────────────────┘');

        // Log das variáveis usadas (resumo)
        Log::channel($this->logChannel)->info('    [PROFILE_SYNTHESIZER] Variaveis do contexto:', [
            'store_name' => $context['store_name'] ?? 'desconhecida',
            'platform' => $context['platform'] ?? 'desconhecida',
            'niche' => $context['niche'] ?? 'geral',
            'subcategory' => $context['subcategory'] ?? 'geral',
            'store_stats_keys' => array_keys($context['store_stats'] ?? []),
            'benchmarks_count' => count($context['benchmarks'] ?? []),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // LOG COMPLETO: Contexto recebido pelo ProfileSynthesizer
        // ═══════════════════════════════════════════════════════════════════
        $this->logFullData('PROFILE_SYNTHESIZER INPUT - Store Stats', $context['store_stats'] ?? []);
        $this->logFullData('PROFILE_SYNTHESIZER INPUT - Benchmarks', $context['benchmarks'] ?? []);

        // Log do template do prompt
        Log::channel($this->logChannel)->info('    [PROFILE_SYNTHESIZER] PROMPT TEMPLATE:');
        Log::channel($this->logChannel)->info(ProfileSynthesizerPrompt::getTemplate());

        $prompt = ProfileSynthesizerPrompt::get($context);

        Log::channel($this->logChannel)->info('    >>> Chamando AI Provider', [
            'temperature' => 0.1,
            'prompt_chars' => strlen($prompt),
        ]);

        $apiStart = microtime(true);
        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.1,
        ]);
        $apiTime = round((microtime(true) - $apiStart) * 1000, 2);

        Log::channel($this->logChannel)->info('    <<< Resposta recebida da AI', [
            'response_chars' => strlen($response),
            'api_time_ms' => $apiTime,
        ]);

        // Log da resposta completa da AI
        Log::channel($this->logChannel)->info('    [PROFILE_SYNTHESIZER] RESPOSTA AI:');
        Log::channel($this->logChannel)->info($response);

        $result = $this->parseResponse($response, $context);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('    [PROFILE_SYNTHESIZER] Concluido', [
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
    private function parseResponse(string $response, array $context): array
    {
        $json = $this->extractJson($response);

        if ($json === null) {
            Log::channel($this->logChannel)->warning('    [PROFILE_SYNTHESIZER] ERRO: Nao foi possivel extrair JSON da resposta. Usando perfil padrao.');

            return [
                'store_profile' => [
                    'nome' => $context['store_name'] ?? 'Loja',
                    'url' => $context['store_url'] ?? 'N/D',
                    'plataforma' => $context['platform'] ?? 'nuvemshop',
                    'nicho' => $context['niche'] ?? 'geral',
                    'nicho_detalhado' => $context['subcategory'] ?? 'geral',
                    'porte_estimado' => 'nao_determinado',
                    'maturidade_digital' => 'nao_determinado',
                    'publico_alvo_estimado' => 'nao_determinado',
                    'diferenciais_visiveis' => [],
                    'sazonalidade_relevante' => 'nao_determinado',
                ],
                'contexto_analise' => [
                    'data_analise' => now()->toDateString(),
                    'eventos_sazonais_proximos' => [],
                    'observacoes_iniciais' => 'Perfil gerado com valores padrão devido a falha na análise.',
                ],
            ];
        }

        Log::channel($this->logChannel)->info('    [PROFILE_SYNTHESIZER] JSON extraido com sucesso', [
            'keys' => array_keys($json),
        ]);

        return $json;
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'ProfileSynthesizer');
    }
}
