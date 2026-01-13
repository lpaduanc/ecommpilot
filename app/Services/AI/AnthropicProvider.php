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
    private string $apiKey;

    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    private string $defaultModel;

    private float $defaultTemperature;

    private int $defaultMaxTokens;

    private string $anthropicVersion = '2023-06-01';

    private int $maxRetries = 3;

    // Delays for rate limits need to be longer (60s+ for token limits)
    private array $retryDelays = [30, 60, 90];

    public function __construct()
    {
        $this->apiKey = SystemSetting::get('ai.anthropic.api_key', config('services.anthropic.api_key')) ?? '';
        $this->defaultModel = SystemSetting::get('ai.anthropic.model', config('services.anthropic.model', 'claude-sonnet-4-20250514')) ?? 'claude-sonnet-4-20250514';
        $this->defaultTemperature = (float) (SystemSetting::get('ai.anthropic.temperature', config('services.anthropic.temperature', 0.7)) ?? 0.7);
        $this->defaultMaxTokens = (int) (SystemSetting::get('ai.anthropic.max_tokens', config('services.anthropic.max_tokens', 8192)) ?? 8192);
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

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'max_tokens' => $options['max_tokens'] ?? $this->defaultMaxTokens,
            'messages' => $chatMessages,
        ];

        // Add system message if present
        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        // Add temperature if not using default
        $temperature = $options['temperature'] ?? $this->defaultTemperature;
        if ($temperature !== 1.0) {
            $payload['temperature'] = $temperature;
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Log::debug("Anthropic API request attempt {$attempt}/{$this->maxRetries}", [
                    'model' => $payload['model'],
                    'max_tokens' => $payload['max_tokens'],
                ]);

                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
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
                            Log::warning('Anthropic request exceeds token limit - falling back to another provider', [
                                'error' => $error,
                            ]);
                            // Throw immediately to trigger fallback - waiting won't help
                            throw new RuntimeException("Anthropic API token limit exceeded: {$error}");
                        }

                        // For time-based rate limits, wait and retry
                        $retryAfter = $this->parseRetryAfter($response);
                        $delay = $retryAfter ?? ($this->retryDelays[$attempt - 1] ?? 60);

                        Log::warning("Anthropic rate limit hit on attempt {$attempt}/{$this->maxRetries}", [
                            'error' => $error,
                            'retry_after' => $delay,
                        ]);

                        if ($attempt < $this->maxRetries) {
                            Log::info("Waiting {$delay} seconds before retry...");
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

                Log::debug('Anthropic API request successful', [
                    'attempt' => $attempt,
                    'response_length' => strlen($text),
                ]);

                return $text;

            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("Anthropic connection error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 30;
                    Log::info("Retrying Anthropic API in {$delay} seconds...");
                    sleep($delay);
                }
            } catch (RuntimeException $e) {
                // Check if it's a retryable error
                if (str_contains($e->getMessage(), 'HTTP 5') || str_contains($e->getMessage(), 'server error')) {
                    $lastException = $e;
                    Log::warning("Anthropic server error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt - 1] ?? 30;
                        Log::info("Retrying Anthropic API in {$delay} seconds...");
                        sleep($delay);
                    }
                } else {
                    // Non-retryable error, throw immediately
                    throw $e;
                }
            }
        }

        // All retries exhausted
        Log::error('Anthropic API request failed after all retries', [
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

    public function getName(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }
}
