<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    private string $defaultModel;

    private float $defaultTemperature;

    private int $defaultMaxTokens;

    private string $anthropicVersion = '2023-06-01';

    public function __construct()
    {
        $this->apiKey = SystemSetting::get('ai.anthropic.api_key', config('services.anthropic.api_key', ''));
        $this->defaultModel = SystemSetting::get('ai.anthropic.model', config('services.anthropic.model', 'claude-sonnet-4-20250514'));
        $this->defaultTemperature = (float) SystemSetting::get('ai.anthropic.temperature', config('services.anthropic.temperature', 0.7));
        $this->defaultMaxTokens = (int) SystemSetting::get('ai.anthropic.max_tokens', config('services.anthropic.max_tokens', 8192));
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

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->anthropicVersion,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($this->baseUrl, $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
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

        return $text;
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
