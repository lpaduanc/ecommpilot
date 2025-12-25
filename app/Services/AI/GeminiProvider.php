<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private string $defaultModel;
    private float $defaultTemperature;
    private int $defaultMaxTokens;

    public function __construct()
    {
        // Try database settings first, then fall back to config
        $this->apiKey = SystemSetting::get('ai.gemini.api_key', config('services.ai.gemini.api_key', ''));
        $this->defaultModel = SystemSetting::get('ai.gemini.model', config('services.ai.gemini.model', 'gemini-1.5-pro'));
        $this->defaultTemperature = (float) SystemSetting::get('ai.gemini.temperature', config('services.ai.gemini.temperature', 0.7));
        $this->defaultMaxTokens = (int) SystemSetting::get('ai.gemini.max_tokens', config('services.ai.gemini.max_tokens', 4000));
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

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown Gemini API error');
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

        $parts = $candidates[0]['content']['parts'] ?? [];
        if (empty($parts)) {
            throw new \RuntimeException('No content parts in Gemini response');
        }

        return $parts[0]['text'] ?? '';
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
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

