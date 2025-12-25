<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use InvalidArgumentException;

class AIManager
{
    private array $providers = [];
    private string $defaultProvider;

    public function __construct()
    {
        $this->defaultProvider = $this->getDefaultProviderFromSettings();
        $this->registerProviders();
    }

    /**
     * Get the default provider from database settings or config.
     */
    private function getDefaultProviderFromSettings(): string
    {
        return SystemSetting::get('ai.provider', config('services.ai.default', 'openai'));
    }

    /**
     * Register all available AI providers.
     */
    private function registerProviders(): void
    {
        $this->providers = [
            'openai' => new OpenAIProvider(),
            'gemini' => new GeminiProvider(),
        ];
    }

    /**
     * Get the default AI provider.
     */
    public function provider(?string $name = null): AIProviderInterface
    {
        $name = $name ?? $this->defaultProvider;

        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException("AI provider [{$name}] is not supported.");
        }

        $provider = $this->providers[$name];

        if (!$provider->isConfigured()) {
            throw new InvalidArgumentException("AI provider [{$name}] is not properly configured.");
        }

        return $provider;
    }

    /**
     * Send a chat completion using the default provider.
     */
    public function chat(array $messages, array $options = []): string
    {
        $providerName = $options['provider'] ?? null;
        unset($options['provider']);

        return $this->provider($providerName)->chat($messages, $options);
    }

    /**
     * Get all available provider names.
     */
    public function getAvailableProviders(): array
    {
        return array_keys(array_filter(
            $this->providers,
            fn (AIProviderInterface $provider) => $provider->isConfigured()
        ));
    }

    /**
     * Check if a specific provider is available.
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]) && $this->providers[$name]->isConfigured();
    }

    /**
     * Get the default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }
}

