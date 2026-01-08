<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Send a chat completion request to the AI provider.
     *
     * @param  array  $messages  Array of messages with 'role' and 'content' keys
     * @param  array  $options  Additional options (model, temperature, max_tokens, etc.)
     * @return string The AI response content
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;
}
