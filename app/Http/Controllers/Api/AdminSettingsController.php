<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * Get AI settings.
     */
    public function getAISettings(): JsonResponse
    {
        $settings = $this->settingsService->getAISettingsForDisplay();

        return response()->json([
            'settings' => $settings,
            'available_providers' => $this->getAvailableProviders(),
            'available_models' => $this->getAvailableModels(),
        ]);
    }

    /**
     * Update AI settings.
     */
    public function updateAISettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['sometimes', 'string', 'in:openai,gemini,anthropic'],
            'openai.api_key' => ['sometimes', 'nullable', 'string'],
            'openai.model' => ['sometimes', 'string'],
            'openai.temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'openai.max_tokens' => ['sometimes', 'integer', 'min:100', 'max:128000'],
            'gemini.api_key' => ['sometimes', 'nullable', 'string'],
            'gemini.model' => ['sometimes', 'string'],
            'gemini.temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'gemini.max_tokens' => ['sometimes', 'integer', 'min:100', 'max:32000'],
            'anthropic.api_key' => ['sometimes', 'nullable', 'string'],
            'anthropic.model' => ['sometimes', 'string'],
            'anthropic.temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'anthropic.max_tokens' => ['sometimes', 'integer', 'min:100', 'max:128000'],
        ]);

        $this->settingsService->updateAISettings($validated);

        ActivityLog::log('admin.ai_settings_updated', null, [
            'admin_id' => $request->user()->id,
            'provider' => $validated['provider'] ?? null,
        ]);

        return response()->json([
            'message' => 'Configurações de IA atualizadas com sucesso.',
            'settings' => $this->settingsService->getAISettingsForDisplay(),
        ]);
    }

    /**
     * Test AI provider connection.
     */
    public function testAIProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:openai,gemini,anthropic'],
        ]);

        $result = $this->settingsService->testAIProvider($validated['provider']);

        ActivityLog::log('admin.ai_provider_tested', null, [
            'admin_id' => $request->user()->id,
            'provider' => $validated['provider'],
            'success' => $result['success'],
        ]);

        return response()->json($result);
    }

    /**
     * Get available AI providers.
     */
    private function getAvailableProviders(): array
    {
        return [
            [
                'id' => 'openai',
                'name' => 'OpenAI',
                'description' => 'GPT-4o e outros modelos OpenAI',
                'icon' => 'openai',
            ],
            [
                'id' => 'gemini',
                'name' => 'Google Gemini',
                'description' => 'Gemini Pro e Flash',
                'icon' => 'google',
            ],
            [
                'id' => 'anthropic',
                'name' => 'Anthropic Claude',
                'description' => 'Claude Sonnet, Opus e Haiku',
                'icon' => 'anthropic',
            ],
        ];
    }

    /**
     * Get available models for each provider.
     */
    private function getAvailableModels(): array
    {
        return [
            'openai' => [
                ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'Mais recente e poderoso'],
                ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'description' => 'Mais rápido e econômico'],
                ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => 'Alta performance'],
                ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'description' => 'Básico e econômico'],
            ],
            'gemini' => [
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'description' => 'Mais recente e recomendado'],
                ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'description' => 'Melhor qualidade'],
                ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'description' => 'Mais rápido'],
                ['id' => 'gemini-1.0-pro', 'name' => 'Gemini 1.0 Pro', 'description' => 'Versão estável'],
            ],
            'anthropic' => [
                ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'description' => 'Mais recente e recomendado'],
                ['id' => 'claude-opus-4-20250514', 'name' => 'Claude Opus 4', 'description' => 'Mais poderoso'],
                ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'description' => 'Excelente custo-benefício'],
                ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'description' => 'Mais rápido e econômico'],
            ],
        ];
    }
}
