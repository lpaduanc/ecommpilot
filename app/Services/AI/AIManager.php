<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AIManager
{
    private array $providers = [];

    private array $providerClasses = [
        'openai' => OpenAIProvider::class,
        'gemini' => GeminiProvider::class,
        'anthropic' => AnthropicProvider::class,
    ];

    private string $defaultProvider;

    public function __construct()
    {
        $this->defaultProvider = $this->getDefaultProviderFromSettings();
    }

    /**
     * Get the default provider from database settings or config.
     */
    private function getDefaultProviderFromSettings(): string
    {
        return SystemSetting::get('ai.provider', config('services.ai.default', 'gemini'));
    }

    /**
     * Get or create a provider instance (lazy loading).
     */
    private function getProviderInstance(string $name): AIProviderInterface
    {
        if (! isset($this->providers[$name])) {
            if (! isset($this->providerClasses[$name])) {
                throw new InvalidArgumentException("AI provider [{$name}] is not supported.");
            }

            $this->providers[$name] = new $this->providerClasses[$name];
        }

        return $this->providers[$name];
    }

    /**
     * Get the default AI provider.
     */
    public function provider(?string $name = null): AIProviderInterface
    {
        $name = $name ?? $this->defaultProvider;

        $provider = $this->getProviderInstance($name);

        if (! $provider->isConfigured()) {
            throw new InvalidArgumentException("AI provider [{$name}] is not properly configured.");
        }

        return $provider;
    }

    /**
     * Send a chat completion using the default provider with automatic fallback.
     */
    public function chat(array $messages, array $options = []): string
    {
        $providerName = $options['provider'] ?? null;
        $disableFallback = $options['disable_fallback'] ?? false;
        unset($options['provider'], $options['disable_fallback']);

        $primaryProvider = $providerName ?? $this->defaultProvider;
        $availableProviders = $this->getAvailableProviders();

        // Try the primary provider first
        try {
            return $this->provider($primaryProvider)->chat($messages, $options);
        } catch (\RuntimeException $e) {
            // Check if fallback is disabled or if it's not a retryable error
            if ($disableFallback || ! $this->isRetryableError($e)) {
                throw $e;
            }

            Log::warning("Primary AI provider [{$primaryProvider}] failed, attempting fallback", [
                'error' => $e->getMessage(),
            ]);

            // Try fallback providers
            foreach ($availableProviders as $fallbackProvider) {
                if ($fallbackProvider === $primaryProvider) {
                    continue;
                }

                try {
                    Log::info("Attempting fallback to AI provider [{$fallbackProvider}]");

                    return $this->provider($fallbackProvider)->chat($messages, $options);
                } catch (\RuntimeException $fallbackException) {
                    Log::warning("Fallback provider [{$fallbackProvider}] also failed", [
                        'error' => $fallbackException->getMessage(),
                    ]);
                    // Continue to next fallback
                }
            }

            // All providers failed - throw the original exception
            throw new \RuntimeException(
                "All AI providers failed. Primary error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Check if an exception is retryable (server errors, rate limits, overload).
     */
    private function isRetryableError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'HTTP 5')
            || str_contains($message, 'HTTP 429')
            || str_contains($message, 'overloaded')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'token limit')
            || str_contains($message, 'connection')
            || str_contains($message, 'timeout');
    }

    /**
     * Get all available provider names.
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach (array_keys($this->providerClasses) as $name) {
            try {
                $provider = $this->getProviderInstance($name);
                if ($provider->isConfigured()) {
                    $available[] = $name;
                }
            } catch (\Throwable) {
                // Provider failed to instantiate, skip it
            }
        }

        return $available;
    }

    /**
     * Check if a specific provider is available.
     */
    public function hasProvider(string $name): bool
    {
        if (! isset($this->providerClasses[$name])) {
            return false;
        }

        try {
            return $this->getProviderInstance($name)->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }
}
