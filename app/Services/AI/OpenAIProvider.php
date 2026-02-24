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
    private int $maxRetries = 3;

    private array $retryDelays = [5, 15, 30]; // seconds

    /**
     * Get API key from database settings only.
     */
    private function getApiKey(): string
    {
        return SystemSetting::get('ai.openai.api_key') ?? '';
    }

    /**
     * Get model from database settings only.
     */
    private function getModel(): string
    {
        return SystemSetting::get('ai.openai.model') ?? 'gpt-4o';
    }

    /**
     * Get temperature from database settings only.
     */
    private function getTemperature(): float
    {
        return (float) (SystemSetting::get('ai.openai.temperature') ?? 0.7);
    }

    /**
     * Get max tokens from database settings only.
     */
    private function getMaxTokens(): int
    {
        return (int) (SystemSetting::get('ai.openai.max_tokens') ?? 8192);
    }

    public function chat(array $messages, array $options = []): string
    {
        $lastException = null;
        $model = $options['model'] ?? $this->getModel();
        $temperature = $options['temperature'] ?? $this->getTemperature();
        $maxTokens = $options['max_tokens'] ?? $this->getMaxTokens();

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->safeLog('info', "        [OPENAI] Chamada API - Tentativa {$attempt}/{$this->maxRetries}", [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                ]);

                $response = OpenAI::chat()->create([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

                // Extract token usage from response
                $inputTokens = $response->usage->promptTokens ?? 0;
                $outputTokens = $response->usage->completionTokens ?? 0;
                $totalTokens = $response->usage->totalTokens ?? 0;
                $finishReason = $response->choices[0]->finishReason ?? 'unknown';

                $this->safeLog('info', '        [OPENAI] Requisicao concluida', [
                    'attempt' => $attempt,
                    'model' => $model,
                    'finish_reason' => $finishReason,
                    'response_length' => strlen($response->choices[0]->message->content ?? ''),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $totalTokens,
                ]);

                // Check for truncation (finish_reason: 'length')
                if ($finishReason === 'length' && $attempt < $this->maxRetries) {
                    $this->safeLog('warning', '        [OPENAI] Resposta truncada (length), retry com mais tokens');
                    $maxTokens = min($maxTokens * 2, 16384); // OpenAI max is 16k for most models

                    continue;
                }

                return $response->choices[0]->message->content;

            } catch (TransporterException $e) {
                // Network/connection errors - retry
                $lastException = $e;
                $this->safeLog('warning', "OpenAI connection error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 30;
                    $this->safeLog('info', "Retrying OpenAI API in {$delay} seconds...");
                    sleep($delay);
                }
            } catch (ErrorException $e) {
                // API errors - check if retryable
                $errorCode = $e->getCode();

                // Retry on server errors (5xx) or rate limits (429)
                if ($errorCode >= 500 || $errorCode === 429) {
                    $lastException = $e;
                    $this->safeLog('warning', "OpenAI server error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt - 1] ?? 30;
                        $this->safeLog('info', "Retrying OpenAI API in {$delay} seconds...");
                        sleep($delay);
                    }
                } else {
                    // Non-retryable error (4xx except 429)
                    throw $e;
                }
            }
        }

        // All retries exhausted
        $this->safeLog('error', 'OpenAI API request failed after all retries', [
            'attempts' => $this->maxRetries,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw new \RuntimeException(
            "OpenAI API request failed after {$this->maxRetries} attempts: ".($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Safe logging that never throws exceptions.
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('ai')->{$level}($message, $context);
        } catch (\Throwable) {
            // Logging should never crash the AI call
        }
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }
}
