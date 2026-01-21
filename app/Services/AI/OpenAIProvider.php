<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    private string $defaultModel;

    private float $defaultTemperature;

    private int $defaultMaxTokens;

    private int $maxRetries = 3;

    private array $retryDelays = [5, 15, 30]; // seconds

    public function __construct()
    {
        // Try database settings first, then fall back to config
        $this->defaultModel = SystemSetting::get('ai.openai.model', config('services.ai.openai.model', 'gpt-4o')) ?? 'gpt-4o';
        $this->defaultTemperature = (float) (SystemSetting::get('ai.openai.temperature', config('services.ai.openai.temperature', 0.7)) ?? 0.7);
        $this->defaultMaxTokens = (int) (SystemSetting::get('ai.openai.max_tokens', config('services.ai.openai.max_tokens', 8192)) ?? 8192);
    }

    public function chat(array $messages, array $options = []): string
    {
        $lastException = null;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Log::channel('ai')->info("        [OPENAI] Chamada API - Tentativa {$attempt}/{$this->maxRetries}", [
                    'model' => $options['model'] ?? $this->defaultModel,
                    'max_tokens' => $maxTokens,
                ]);

                $response = OpenAI::chat()->create([
                    'model' => $options['model'] ?? $this->defaultModel,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? $this->defaultTemperature,
                    'max_tokens' => $maxTokens,
                ]);

                // Extract token usage from response
                $inputTokens = $response->usage->promptTokens ?? 0;
                $outputTokens = $response->usage->completionTokens ?? 0;
                $totalTokens = $response->usage->totalTokens ?? 0;
                $finishReason = $response->choices[0]->finishReason ?? 'unknown';

                Log::channel('ai')->info('        [OPENAI] Requisicao concluida', [
                    'attempt' => $attempt,
                    'model' => $options['model'] ?? $this->defaultModel,
                    'finish_reason' => $finishReason,
                    'response_length' => strlen($response->choices[0]->message->content ?? ''),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $totalTokens,
                ]);

                // Check for truncation (finish_reason: 'length')
                if ($finishReason === 'length' && $attempt < $this->maxRetries) {
                    Log::channel('ai')->warning('        [OPENAI] Resposta truncada (length), retry com mais tokens');
                    $maxTokens = min($maxTokens * 2, 16384); // OpenAI max is 16k for most models

                    continue;
                }

                return $response->choices[0]->message->content;

            } catch (TransporterException $e) {
                // Network/connection errors - retry
                $lastException = $e;
                Log::warning("OpenAI connection error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 30;
                    Log::info("Retrying OpenAI API in {$delay} seconds...");
                    sleep($delay);
                }
            } catch (ErrorException $e) {
                // API errors - check if retryable
                $errorCode = $e->getCode();

                // Retry on server errors (5xx) or rate limits (429)
                if ($errorCode >= 500 || $errorCode === 429) {
                    $lastException = $e;
                    Log::warning("OpenAI server error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt - 1] ?? 30;
                        Log::info("Retrying OpenAI API in {$delay} seconds...");
                        sleep($delay);
                    }
                } else {
                    // Non-retryable error (4xx except 429)
                    throw $e;
                }
            }
        }

        // All retries exhausted
        Log::error('OpenAI API request failed after all retries', [
            'attempts' => $this->maxRetries,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw new \RuntimeException(
            "OpenAI API request failed after {$this->maxRetries} attempts: ".($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('openai.api_key'));
    }
}
