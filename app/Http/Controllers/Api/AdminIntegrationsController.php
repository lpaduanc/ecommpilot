<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminIntegrationsController extends Controller
{
    /**
     * Get external data integration settings.
     */
    public function getExternalData(): JsonResponse
    {
        $settings = [
            'enabled' => SystemSetting::get('external_data.enabled', false),
            'serpapi_key' => $this->getMaskedApiKey('external_data.serpapi_key'),
            'serpapi_key_configured' => ! empty(SystemSetting::get('external_data.serpapi_key')),
            'trends' => [
                'enabled' => SystemSetting::get('external_data.trends.enabled', true),
            ],
            'market' => [
                'enabled' => SystemSetting::get('external_data.market.enabled', true),
            ],
            'competitors' => [
                'enabled' => SystemSetting::get('external_data.competitors.enabled', true),
                'max_per_store' => SystemSetting::get('external_data.competitors.max_per_store', 5),
                'scrape_timeout' => SystemSetting::get('external_data.competitors.scrape_timeout', 15),
            ],
            'decodo' => [
                'enabled' => SystemSetting::get('external_data.decodo.enabled', false),
                'username' => $this->getMaskedApiKey('external_data.decodo.username'),
                'username_configured' => ! empty(SystemSetting::get('external_data.decodo.username')),
                'password_configured' => ! empty(SystemSetting::get('external_data.decodo.password')),
                'headless' => SystemSetting::get('external_data.decodo.headless', 'html'),
                'js_rendering' => SystemSetting::get('external_data.decodo.js_rendering', false),
                'timeout' => SystemSetting::get('external_data.decodo.timeout', 30),
            ],
        ];

        return response()->json(['data' => $settings]);
    }

    /**
     * Update external data integration settings.
     */
    public function updateExternalData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'serpapi_key' => ['nullable', 'string', 'max:100'],
            'trends.enabled' => ['required', 'boolean'],
            'market.enabled' => ['required', 'boolean'],
            'competitors.enabled' => ['required', 'boolean'],
            'competitors.max_per_store' => ['required', 'integer', 'min:1', 'max:10'],
            'competitors.scrape_timeout' => ['required', 'integer', 'min:5', 'max:60'],
            'decodo.enabled' => ['required', 'boolean'],
            'decodo.username' => ['nullable', 'string', 'max:100'],
            'decodo.password' => ['nullable', 'string', 'max:100'],
            'decodo.headless' => ['nullable', 'string', 'in:html,true'],
            'decodo.js_rendering' => ['sometimes', 'boolean'],
            'decodo.timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
        ], [
            'enabled.required' => 'O campo habilitado é obrigatório.',
            'competitors.max_per_store.max' => 'O máximo de concorrentes por loja é 10.',
            'competitors.scrape_timeout.max' => 'O timeout de scraping não pode exceder 60 segundos.',
        ]);

        // Save main toggle
        SystemSetting::set('external_data.enabled', $validated['enabled'], [
            'type' => 'boolean',
            'group' => 'external_data',
            'label' => 'Habilitar dados externos',
            'description' => 'Ativa ou desativa a coleta de dados externos (Google Trends, preços de mercado, concorrentes)',
        ]);

        // Save API key only if provided (not empty and not masked)
        if (! empty($validated['serpapi_key']) && ! $this->isMaskedValue($validated['serpapi_key'])) {
            SystemSetting::set('external_data.serpapi_key', $validated['serpapi_key'], [
                'type' => 'string',
                'group' => 'external_data',
                'label' => 'SerpAPI Key',
                'description' => 'Chave da API SerpAPI para buscar dados do Google',
                'is_sensitive' => true,
            ]);
        }

        // Save trends settings
        SystemSetting::set('external_data.trends.enabled', $validated['trends']['enabled'], [
            'type' => 'boolean',
            'group' => 'external_data',
            'label' => 'Google Trends',
            'description' => 'Habilitar busca de tendências no Google Trends',
        ]);

        // Save market data settings
        SystemSetting::set('external_data.market.enabled', $validated['market']['enabled'], [
            'type' => 'boolean',
            'group' => 'external_data',
            'label' => 'Preços de mercado',
            'description' => 'Habilitar busca de preços no Google Shopping',
        ]);

        // Save competitors settings
        SystemSetting::set('external_data.competitors.enabled', $validated['competitors']['enabled'], [
            'type' => 'boolean',
            'group' => 'external_data',
            'label' => 'Análise de concorrentes',
            'description' => 'Habilitar análise de sites concorrentes',
        ]);

        SystemSetting::set('external_data.competitors.max_per_store', $validated['competitors']['max_per_store'], [
            'type' => 'integer',
            'group' => 'external_data',
            'label' => 'Máximo de concorrentes',
            'description' => 'Número máximo de concorrentes a analisar por loja',
        ]);

        SystemSetting::set('external_data.competitors.scrape_timeout', $validated['competitors']['scrape_timeout'], [
            'type' => 'integer',
            'group' => 'external_data',
            'label' => 'Timeout de scraping',
            'description' => 'Tempo máximo em segundos para analisar cada concorrente',
        ]);

        // Save Decodo scraping API settings
        // Always save the enabled state
        SystemSetting::set('external_data.decodo.enabled', $validated['decodo']['enabled'], [
            'type' => 'boolean',
            'group' => 'external_data',
            'label' => 'Habilitar Decodo API',
            'description' => 'Ativa a API de Web Scraping Decodo para análise de concorrentes',
        ]);

        // Save Decodo username only if provided and not masked
        if (! empty($validated['decodo']['username']) && ! $this->isMaskedValue($validated['decodo']['username'])) {
            SystemSetting::set('external_data.decodo.username', $validated['decodo']['username'], [
                'type' => 'string',
                'group' => 'external_data',
                'label' => 'Decodo Username',
                'description' => 'Usuário da API Decodo',
                'is_sensitive' => true,
            ]);
        }

        // Save Decodo password only if provided and not masked
        if (! empty($validated['decodo']['password']) && ! $this->isMaskedValue($validated['decodo']['password'])) {
            SystemSetting::set('external_data.decodo.password', $validated['decodo']['password'], [
                'type' => 'string',
                'group' => 'external_data',
                'label' => 'Decodo Password',
                'description' => 'Senha da API Decodo',
                'is_sensitive' => true,
            ]);
        }

        // Save Decodo headless mode
        if (isset($validated['decodo']['headless'])) {
            SystemSetting::set('external_data.decodo.headless', $validated['decodo']['headless'], [
                'type' => 'string',
                'group' => 'external_data',
                'label' => 'Decodo Headless Mode',
                'description' => 'Modo headless: html (mais rápido) ou true (renderiza JS)',
            ]);
        }

        // Save Decodo JS rendering
        if (isset($validated['decodo']['js_rendering'])) {
            SystemSetting::set('external_data.decodo.js_rendering', $validated['decodo']['js_rendering'], [
                'type' => 'boolean',
                'group' => 'external_data',
                'label' => 'Decodo JS Rendering',
                'description' => 'Habilita renderização de JavaScript no scraping',
            ]);
        }

        // Save Decodo timeout if provided
        if (! empty($validated['decodo']['timeout'])) {
            SystemSetting::set('external_data.decodo.timeout', $validated['decodo']['timeout'], [
                'type' => 'integer',
                'group' => 'external_data',
                'label' => 'Decodo Timeout',
                'description' => 'Timeout em segundos para requisições via Decodo API',
            ]);
        }

        Log::info('External data settings updated', [
            'enabled' => $validated['enabled'],
            'trends_enabled' => $validated['trends']['enabled'],
            'market_enabled' => $validated['market']['enabled'],
            'competitors_enabled' => $validated['competitors']['enabled'],
            'decodo_enabled' => $validated['decodo']['enabled'],
        ]);

        return response()->json([
            'message' => 'Configurações salvas com sucesso.',
        ]);
    }

    /**
     * Test SerpAPI connection.
     */
    public function testExternalData(Request $request): JsonResponse
    {
        $apiKey = $request->input('serpapi_key');

        // If no key provided in request, use the saved one
        if (empty($apiKey)) {
            $apiKey = SystemSetting::get('external_data.serpapi_key');
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma chave de API configurada.',
            ], 400);
        }

        try {
            // Test with a simple Google Trends query
            $response = Http::timeout(15)->get('https://serpapi.com/search', [
                'api_key' => $apiKey,
                'engine' => 'google_trends',
                'q' => 'test',
                'data_type' => 'TIMESERIES',
                'geo' => 'BR',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if we got valid data
                if (isset($data['interest_over_time']) || isset($data['search_metadata'])) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Conexão com SerpAPI estabelecida com sucesso.',
                        'credits_remaining' => $data['search_metadata']['total_time_taken'] ?? null,
                    ]);
                }

                // Check for error in response
                if (isset($data['error'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erro da API: '.$data['error'],
                    ], 400);
                }
            }

            // Handle HTTP errors
            if ($response->status() === 401) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chave de API inválida ou expirada.',
                ], 401);
            }

            if ($response->status() === 429) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de requisições excedido. Tente novamente mais tarde.',
                ], 429);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar com a API. Status: '.$response->status(),
            ], 400);

        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('SerpAPI test failed', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Test Decodo proxy connection.
     */
    public function testDecodo(Request $request): JsonResponse
    {
        // Get credentials from request or saved settings
        $username = $request->input('username') ?: SystemSetting::get('external_data.decodo.username');
        $password = $request->input('password') ?: SystemSetting::get('external_data.decodo.password');

        // Check if we have credentials to test
        if (empty($username) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais do Decodo não configuradas. Informe usuário e senha.',
            ], 400);
        }

        try {
            // Make direct test request to Decodo API
            $credentials = $username.':'.$password;
            $authHeader = 'Basic '.base64_encode($credentials);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://scraper-api.decodo.com/v2/scrape', [
                    'url' => 'https://ip.decodo.com/json',
                    'headless' => 'html',
                    'output' => 'raw',
                ]);

            $responseData = $response->json();

            // Check for API errors
            if (isset($responseData['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro da API Decodo: '.$responseData['error'],
                ], 400);
            }

            if ($response->successful()) {
                // Try to extract IP info from response
                $content = $responseData['content'] ?? $responseData['body'] ?? '';
                $ipData = json_decode($content, true);

                // If new credentials provided, save them
                if ($request->filled('username')) {
                    SystemSetting::set('external_data.decodo.username', $request->input('username'), [
                        'type' => 'string',
                        'group' => 'external_data',
                        'label' => 'Decodo Username',
                        'description' => 'Usuário da API Decodo',
                        'is_sensitive' => true,
                    ]);
                }
                if ($request->filled('password')) {
                    SystemSetting::set('external_data.decodo.password', $request->input('password'), [
                        'type' => 'string',
                        'group' => 'external_data',
                        'label' => 'Decodo Password',
                        'description' => 'Senha da API Decodo',
                        'is_sensitive' => true,
                    ]);
                }

                // Auto-enable Decodo if test is successful and credentials were just saved
                if ($request->filled('username') || $request->filled('password')) {
                    SystemSetting::set('external_data.decodo.enabled', true, [
                        'type' => 'boolean',
                        'group' => 'external_data',
                        'label' => 'Habilitar Decodo API',
                        'description' => 'Ativa a API de Web Scraping Decodo para análise de concorrentes',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Conexão com Decodo estabelecida com sucesso.',
                    'ip' => $ipData['ip'] ?? 'Proxy ativo',
                    'country' => $ipData['country'] ?? $ipData['geo'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha na conexão. Status: '.$response->status(),
            ], 400);

        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Decodo test failed', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Get masked API key for display.
     */
    private function getMaskedApiKey(string $key): ?string
    {
        $value = SystemSetting::get($key);

        if (empty($value)) {
            return null;
        }

        // Return masked version (first 4 and last 4 characters)
        if (strlen($value) > 8) {
            return substr($value, 0, 4).'****'.substr($value, -4);
        }

        return '********';
    }

    /**
     * Check if a value is masked (contains **** or ••••).
     */
    private function isMaskedValue(string $value): bool
    {
        // Check for asterisks (from getMaskedApiKey)
        if (str_contains($value, '****') || str_contains($value, '********')) {
            return true;
        }

        // Check for bullet points (from frontend placeholder)
        if (str_contains($value, '••••')) {
            return true;
        }

        // Check if the entire string is only asterisks or bullet points
        if (preg_match('/^[*•]+$/', $value)) {
            return true;
        }

        return false;
    }
}
