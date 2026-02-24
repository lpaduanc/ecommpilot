<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicProvider implements AIProviderInterface
{
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    private string $anthropicVersion = '2023-06-01';

    private int $maxRetries = 3;

    // Delays for rate limits need to be longer (60s+ for token limits)
    private array $retryDelays = [30, 60, 90];

    private string $logChannel = 'ai';

    /**
     * Get API key from database settings only.
     */
    private function getApiKey(): string
    {
        return SystemSetting::get('ai.anthropic.api_key') ?? '';
    }

    /**
     * Get model from database settings only.
     */
    private function getModel(): string
    {
        return SystemSetting::get('ai.anthropic.model') ?? 'claude-sonnet-4-20250514';
    }

    /**
     * Get temperature from database settings only.
     */
    private function getTemperature(): float
    {
        return (float) (SystemSetting::get('ai.anthropic.temperature') ?? 0.7);
    }

    /**
     * Get max tokens from database settings only.
     */
    private function getMaxTokens(): int
    {
        return (int) (SystemSetting::get('ai.anthropic.max_tokens') ?? 8192);
    }

    public function chat(array $messages, array $options = []): string
    {
        // Extract system message if present
        $systemMessage = null;
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $chatMessages[] = [
                    'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $message['content'],
                ];
            }
        }

        $apiKey = $this->getApiKey();
        $model = $options['model'] ?? $this->getModel();
        $maxTokens = $options['max_tokens'] ?? $this->getMaxTokens();
        $temperature = $options['temperature'] ?? $this->getTemperature();

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $chatMessages,
        ];

        // Add system message if present
        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }
        if ($temperature !== 1.0) {
            $payload['temperature'] = $temperature;
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->safeLog('info', "        [ANTHROPIC] Chamada API - Tentativa {$attempt}/{$this->maxRetries}", [
                    'model' => $payload['model'],
                    'max_tokens' => $payload['max_tokens'],
                    'temperature' => $temperature,
                ]);

                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $this->anthropicVersion,
                    'Content-Type' => 'application/json',
                ])->timeout(180)->connectTimeout(30)->post($this->baseUrl, $payload);

                if (! $response->successful()) {
                    $statusCode = $response->status();
                    $error = $response->json('error.message', $response->body());

                    // Rate limit (429) - check if it's a per-request limit or time-based
                    if ($statusCode === 429) {
                        // If the request itself exceeds the token limit, don't retry - fallback immediately
                        if (str_contains($error, 'would exceed') || str_contains($error, 'input tokens')) {
                            $this->safeLog('warning', '        [ANTHROPIC] Token limit excedido - fallback para outro provider', [
                                'error' => $error,
                            ]);
                            // Throw immediately to trigger fallback - waiting won't help
                            throw new RuntimeException("Anthropic API token limit exceeded: {$error}");
                        }

                        // For time-based rate limits, wait and retry
                        $retryAfter = $this->parseRetryAfter($response);
                        $delay = $retryAfter ?? ($this->retryDelays[$attempt - 1] ?? 60);

                        $this->safeLog('warning', "        [ANTHROPIC] Rate limit na tentativa {$attempt}/{$this->maxRetries}", [
                            'error' => $error,
                            'retry_after' => $delay,
                        ]);

                        if ($attempt < $this->maxRetries) {
                            $this->safeLog('info', "        [ANTHROPIC] Aguardando {$delay}s antes de retry...");
                            sleep($delay);

                            continue;
                        }

                        throw new RuntimeException("Anthropic API rate limit exceeded: {$error}");
                    }

                    // Server errors (5xx) - retry with backoff
                    if ($statusCode >= 500) {
                        throw new RuntimeException("Anthropic API server error (HTTP {$statusCode}): {$error}");
                    }

                    // Client errors (4xx except 429) - don't retry
                    throw new RuntimeException("Anthropic API error: {$error}");
                }

                $content = $response->json('content');

                if (empty($content)) {
                    throw new RuntimeException('Anthropic API returned empty content');
                }

                // Extract text from content blocks
                $text = '';
                foreach ($content as $block) {
                    if ($block['type'] === 'text') {
                        $text .= $block['text'];
                    }
                }

                if (empty($text)) {
                    throw new RuntimeException('Anthropic API returned no text content');
                }

                // Extract token usage from response
                $usage = $response->json('usage', []);
                $inputTokens = $usage['input_tokens'] ?? 0;
                $outputTokens = $usage['output_tokens'] ?? 0;
                $totalTokens = $inputTokens + $outputTokens;
                $stopReason = $response->json('stop_reason', 'end_turn');

                $this->safeLog('info', '        [ANTHROPIC] Requisicao concluida', [
                    'attempt' => $attempt,
                    'stop_reason' => $stopReason,
                    'response_length' => strlen($text),
                    'model' => $payload['model'],
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $totalTokens,
                ]);

                // Check for truncation (stop_reason: 'max_tokens')
                if ($stopReason === 'max_tokens' && $attempt < $this->maxRetries) {
                    $this->safeLog('warning', '        [ANTHROPIC] Resposta truncada (max_tokens), retry com mais tokens');
                    $payload['max_tokens'] = min($payload['max_tokens'] * 2, 16384);

                    continue;
                }

                return $text;

            } catch (ConnectionException $e) {
                $lastException = $e;
                $this->safeLog('warning', "        [ANTHROPIC] Erro de conexao na tentativa {$attempt}/{$this->maxRetries}", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 30;
                    $this->safeLog('info', "        [ANTHROPIC] Aguardando {$delay}s antes de retry...");
                    sleep($delay);
                }
            } catch (RuntimeException $e) {
                // Check if it's a retryable error
                if (str_contains($e->getMessage(), 'HTTP 5') || str_contains($e->getMessage(), 'server error')) {
                    $lastException = $e;
                    $this->safeLog('warning', "        [ANTHROPIC] Erro de servidor na tentativa {$attempt}/{$this->maxRetries}", [
                        'error' => $e->getMessage(),
                    ]);

                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt - 1] ?? 30;
                        $this->safeLog('info', "        [ANTHROPIC] Aguardando {$delay}s antes de retry...");
                        sleep($delay);
                    }
                } else {
                    // Non-retryable error, throw immediately
                    throw $e;
                }
            }
        }

        // All retries exhausted
        $this->safeLog('error', '        [ANTHROPIC] ERRO: Falha apos todas as tentativas', [
            'attempts' => $this->maxRetries,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw new RuntimeException(
            "Anthropic API request failed after {$this->maxRetries} attempts: ".($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Parse the retry-after header from the response.
     */
    private function parseRetryAfter($response): ?int
    {
        $retryAfter = $response->header('retry-after');

        if ($retryAfter && is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        return null;
    }

    /**
     * Safe logging that never throws exceptions.
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel($this->logChannel)->{$level}($message, $context);
        } catch (\Throwable) {
            // Logging should never crash the AI call
        }
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }
}
