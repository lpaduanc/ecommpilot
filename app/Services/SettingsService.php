<?php

namespace App\Services;

use App\Models\SystemSetting;

class SettingsService
{
    /**
     * Get AI configuration settings.
     */
    public function getAISettings(): array
    {
        return [
            'provider' => SystemSetting::get('ai.provider', config('services.ai.default', 'openai')),
            'openai' => [
                'api_key' => SystemSetting::get('ai.openai.api_key', config('openai.api_key', '')),
                'model' => SystemSetting::get('ai.openai.model', config('services.ai.openai.model', 'gpt-4o')),
                'temperature' => SystemSetting::get('ai.openai.temperature', config('services.ai.openai.temperature', 0.7)),
                'max_tokens' => SystemSetting::get('ai.openai.max_tokens', config('services.ai.openai.max_tokens', 4000)),
            ],
            'gemini' => [
                'api_key' => SystemSetting::get('ai.gemini.api_key', config('services.ai.gemini.api_key', '')),
                'model' => SystemSetting::get('ai.gemini.model', config('services.ai.gemini.model', 'gemini-1.5-pro')),
                'temperature' => SystemSetting::get('ai.gemini.temperature', config('services.ai.gemini.temperature', 0.7)),
                'max_tokens' => SystemSetting::get('ai.gemini.max_tokens', config('services.ai.gemini.max_tokens', 4000)),
            ],
        ];
    }

    /**
     * Get AI settings for display (with masked sensitive values).
     */
    public function getAISettingsForDisplay(): array
    {
        $settings = $this->getAISettings();

        // Mask API keys
        $settings['openai']['api_key'] = $this->maskApiKey($settings['openai']['api_key']);
        $settings['gemini']['api_key'] = $this->maskApiKey($settings['gemini']['api_key']);

        // Add configured status
        $settings['openai']['is_configured'] = ! empty(SystemSetting::get('ai.openai.api_key', config('openai.api_key')));
        $settings['gemini']['is_configured'] = ! empty(SystemSetting::get('ai.gemini.api_key', config('services.ai.gemini.api_key')));

        return $settings;
    }

    /**
     * Update AI settings.
     */
    public function updateAISettings(array $data): void
    {
        // Update provider
        if (isset($data['provider'])) {
            SystemSetting::set('ai.provider', $data['provider'], [
                'type' => 'string',
                'group' => 'ai',
                'label' => 'Provedor de IA',
                'description' => 'Provedor de IA padrão para análises e chat',
            ]);
        }

        // Update OpenAI settings
        if (isset($data['openai'])) {
            $this->updateOpenAISettings($data['openai']);
        }

        // Update Gemini settings
        if (isset($data['gemini'])) {
            $this->updateGeminiSettings($data['gemini']);
        }

        // Clear settings cache
        SystemSetting::clearCache();
    }

    /**
     * Update OpenAI settings.
     */
    private function updateOpenAISettings(array $data): void
    {
        if (isset($data['api_key']) && ! $this->isMaskedValue($data['api_key'])) {
            SystemSetting::set('ai.openai.api_key', $data['api_key'], [
                'type' => 'string',
                'group' => 'ai',
                'label' => 'OpenAI API Key',
                'is_sensitive' => true,
            ]);
        }

        if (isset($data['model'])) {
            SystemSetting::set('ai.openai.model', $data['model'], [
                'type' => 'string',
                'group' => 'ai',
                'label' => 'Modelo OpenAI',
            ]);
        }

        if (isset($data['temperature'])) {
            SystemSetting::set('ai.openai.temperature', (float) $data['temperature'], [
                'type' => 'float',
                'group' => 'ai',
                'label' => 'Temperatura OpenAI',
            ]);
        }

        if (isset($data['max_tokens'])) {
            SystemSetting::set('ai.openai.max_tokens', (int) $data['max_tokens'], [
                'type' => 'integer',
                'group' => 'ai',
                'label' => 'Max Tokens OpenAI',
            ]);
        }
    }

    /**
     * Update Gemini settings.
     */
    private function updateGeminiSettings(array $data): void
    {
        if (isset($data['api_key']) && ! $this->isMaskedValue($data['api_key'])) {
            SystemSetting::set('ai.gemini.api_key', $data['api_key'], [
                'type' => 'string',
                'group' => 'ai',
                'label' => 'Gemini API Key',
                'is_sensitive' => true,
            ]);
        }

        if (isset($data['model'])) {
            SystemSetting::set('ai.gemini.model', $data['model'], [
                'type' => 'string',
                'group' => 'ai',
                'label' => 'Modelo Gemini',
            ]);
        }

        if (isset($data['temperature'])) {
            SystemSetting::set('ai.gemini.temperature', (float) $data['temperature'], [
                'type' => 'float',
                'group' => 'ai',
                'label' => 'Temperatura Gemini',
            ]);
        }

        if (isset($data['max_tokens'])) {
            SystemSetting::set('ai.gemini.max_tokens', (int) $data['max_tokens'], [
                'type' => 'integer',
                'group' => 'ai',
                'label' => 'Max Tokens Gemini',
            ]);
        }
    }

    /**
     * Test AI provider connection.
     */
    public function testAIProvider(string $provider): array
    {
        try {
            $aiManager = app(\App\Services\AI\AIManager::class);

            $response = $aiManager->chat([
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Say "Connection successful!" in Portuguese.'],
            ], [
                'provider' => $provider,
                'max_tokens' => 50,
            ]);

            return [
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso!',
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao conectar: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Mask an API key for display.
     */
    private function maskApiKey(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        if (strlen($key) <= 8) {
            return '********';
        }

        return substr($key, 0, 4).str_repeat('*', strlen($key) - 8).substr($key, -4);
    }

    /**
     * Check if value is a masked placeholder.
     */
    private function isMaskedValue(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return str_contains($value, '****') || $value === '********';
    }
}
