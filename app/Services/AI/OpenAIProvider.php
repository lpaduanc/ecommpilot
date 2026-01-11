<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    private string $defaultModel;

    private float $defaultTemperature;

    private int $defaultMaxTokens;

    public function __construct()
    {
        // Try database settings first, then fall back to config
        $this->defaultModel = SystemSetting::get('ai.openai.model', config('services.ai.openai.model', 'gpt-4o'));
        $this->defaultTemperature = (float) SystemSetting::get('ai.openai.temperature', config('services.ai.openai.temperature', 0.7));
        $this->defaultMaxTokens = (int) SystemSetting::get('ai.openai.max_tokens', config('services.ai.openai.max_tokens', 8192));
    }

    public function chat(array $messages, array $options = []): string
    {
        $response = OpenAI::chat()->create([
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->defaultTemperature,
            'max_tokens' => $options['max_tokens'] ?? $this->defaultMaxTokens,
        ]);

        return $response->choices[0]->message->content;
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
