<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    private string $defaultModel;

    private float $defaultTemperature;

    private int $defaultMaxTokens;

    private int $maxRetries = 3;

    private array $retryDelays = [5, 15, 30]; // seconds

    public function __construct()
    {
        // Try database settings first, then fall back to config
        $this->apiKey = SystemSetting::get('ai.gemini.api_key', config('services.ai.gemini.api_key', ''));
        $this->defaultModel = SystemSetting::get('ai.gemini.model', config('services.ai.gemini.model', 'gemini-1.5-pro'));
        $this->defaultTemperature = (float) SystemSetting::get('ai.gemini.temperature', config('services.ai.gemini.temperature', 0.7));
        $this->defaultMaxTokens = (int) SystemSetting::get('ai.gemini.max_tokens', config('services.ai.gemini.max_tokens', 8192));
    }

    public function chat(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? $this->defaultTemperature;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;

        // Convert OpenAI-style messages to Gemini format
        $contents = $this->convertMessagesToGeminiFormat($messages);

        // Extract system instruction if present
        $systemInstruction = $this->extractSystemInstruction($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.95,
            ],
        ];

        // Add system instruction if present
        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Log::debug("Gemini API request attempt {$attempt}/{$this->maxRetries}", [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                ]);

                $response = Http::timeout(180) // 3 minutes timeout
                    ->connectTimeout(30) // 30 seconds to establish connection
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", $payload);

                if (! $response->successful()) {
                    $error = $response->json('error.message', 'Unknown Gemini API error');
                    $statusCode = $response->status();

                    // Retry on 5xx errors or rate limits (429)
                    if ($statusCode >= 500 || $statusCode === 429) {
                        throw new \RuntimeException("Gemini API error (HTTP {$statusCode}): {$error}");
                    }

                    // Don't retry on client errors (4xx except 429)
                    throw new \RuntimeException("Gemini API error: {$error}");
                }

                $data = $response->json();

                // Check for blocked content
                if (isset($data['promptFeedback']['blockReason'])) {
                    throw new \RuntimeException("Content blocked by Gemini: {$data['promptFeedback']['blockReason']}");
                }

                // Extract the response text
                $candidates = $data['candidates'] ?? [];
                if (empty($candidates)) {
                    throw new \RuntimeException('No response candidates from Gemini');
                }

                // Check finish reason
                $finishReason = $candidates[0]['finishReason'] ?? 'UNKNOWN';

                Log::debug('Gemini API response received', [
                    'attempt' => $attempt,
                    'finish_reason' => $finishReason,
                ]);

                // Handle truncation - MAX_TOKENS means the response was cut off
                if ($finishReason === 'MAX_TOKENS') {
                    Log::warning("Gemini response truncated (MAX_TOKENS). Consider increasing max_tokens.", [
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                    ]);
                    // Mark response as potentially truncated but don't fail - let the caller handle it
                }

                $parts = $candidates[0]['content']['parts'] ?? [];
                if (empty($parts)) {
                    throw new \RuntimeException('No content parts in Gemini response');
                }

                $responseText = $parts[0]['text'] ?? '';

                Log::debug('Gemini API request successful', [
                    'attempt' => $attempt,
                    'response_length' => strlen($responseText),
                    'finish_reason' => $finishReason,
                ]);

                // If truncated and we have room to retry with more tokens, do so
                if ($finishReason === 'MAX_TOKENS' && $attempt < $this->maxRetries) {
                    // Double the tokens for the next attempt
                    $maxTokens = min($maxTokens * 2, 32768); // Cap at 32k
                    Log::info("Retrying with increased max_tokens: {$maxTokens}");
                    $payload['generationConfig']['maxOutputTokens'] = $maxTokens;
                    continue; // Retry with more tokens
                }

                return $responseText;

            } catch (ConnectionException|ConnectException $e) {
                $lastException = $e;
                Log::warning("Gemini connection error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 30;
                    Log::info("Retrying Gemini API in {$delay} seconds...");
                    sleep($delay);
                }
            } catch (\RuntimeException $e) {
                // Check if it's a retryable error (5xx or network issues)
                if (str_contains($e->getMessage(), 'HTTP 5') || str_contains($e->getMessage(), 'HTTP 429')) {
                    $lastException = $e;
                    Log::warning("Gemini server error on attempt {$attempt}/{$this->maxRetries}: {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt - 1] ?? 30;
                        Log::info("Retrying Gemini API in {$delay} seconds...");
                        sleep($delay);
                    }
                } else {
                    // Non-retryable error, throw immediately
                    throw $e;
                }
            }
        }

        // All retries exhausted
        Log::error('Gemini API request failed after all retries', [
            'attempts' => $this->maxRetries,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw new \RuntimeException(
            "Gemini API request failed after {$this->maxRetries} attempts: ".($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Convert OpenAI-style messages to Gemini format.
     * Gemini uses 'user' and 'model' roles instead of 'user' and 'assistant'.
     */
    private function convertMessagesToGeminiFormat(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            // Skip system messages - they're handled separately
            if ($message['role'] === 'system') {
                continue;
            }

            $role = match ($message['role']) {
                'assistant' => 'model',
                default => 'user',
            };

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $message['content']],
                ],
            ];
        }

        return $contents;
    }

    /**
     * Extract the system instruction from messages.
     */
    private function extractSystemInstruction(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                return $message['content'];
            }
        }

        return null;
    }
}
