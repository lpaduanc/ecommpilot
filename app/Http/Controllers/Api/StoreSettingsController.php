<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NuvemshopCallbackRequest;
use App\Http\Requests\StoreSettingsRequest;
use App\Http\Requests\UserStoreSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreSettingsController extends Controller
{
    private const TOKEN_URL = 'https://www.tiendanube.com/apps/authorize/token';

    private const API_BASE_URL = 'https://api.tiendanube.com/2025-03';

    /**
     * Get store settings (client_id, client_secret, grant_type).
     * Client credentials come from .env file.
     */
    public function getStoreSettings(): JsonResponse
    {
        $clientId = env('NUVEMSHOP_CLIENT_ID', '');
        $clientSecret = env('NUVEMSHOP_CLIENT_SECRET', '');
        $grantType = SystemSetting::get('nuvemshop.grant_type', 'authorization_code');

        return response()->json([
            'client_id' => ! empty($clientId) ? $this->maskValue($clientId) : null,
            'client_secret' => ! empty($clientSecret) ? $this->maskValue($clientSecret) : null,
            'grant_type' => $grantType,
            'has_client_id' => ! empty($clientId),
            'has_client_secret' => ! empty($clientSecret),
        ]);
    }

    /**
     * Mask a sensitive value for display.
     */
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('*', strlen($value) - 8).substr($value, -4);
    }

    /**
     * Update store settings (client_id, client_secret, grant_type).
     */
    public function updateStoreSettings(StoreSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['client_id'])) {
            SystemSetting::set('nuvemshop.client_id', $validated['client_id'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Client ID',
                'description' => 'Nuvemshop OAuth Client ID',
                'is_sensitive' => true,
            ]);
        }

        if (isset($validated['client_secret'])) {
            SystemSetting::set('nuvemshop.client_secret', $validated['client_secret'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Client Secret',
                'description' => 'Nuvemshop OAuth Client Secret',
                'is_sensitive' => true,
            ]);
        }

        if (isset($validated['grant_type'])) {
            SystemSetting::set('nuvemshop.grant_type', $validated['grant_type'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Grant Type',
                'description' => 'OAuth Grant Type',
                'is_sensitive' => false,
            ]);
        }

        Log::info('Nuvemshop store settings updated', [
            'updated_by' => $request->user()->id,
            'fields' => array_keys($validated),
        ]);

        // Get updated settings
        $settings = $this->getStoreSettings()->getData(true);

        // Return updated settings with masked sensitive values and success message
        return response()->json(array_merge(
            ['message' => 'Configurações da loja atualizadas com sucesso.'],
            $settings
        ));
    }

    /**
     * Get connection status and data.
     */
    public function getConnectionStatus(): JsonResponse
    {
        $accessToken = SystemSetting::where('key', 'nuvemshop.access_token')->first();
        $userId = SystemSetting::get('nuvemshop.user_id');
        $scope = SystemSetting::get('nuvemshop.scope');
        $tokenType = SystemSetting::get('nuvemshop.token_type');

        $isConnected = ! empty(SystemSetting::get('nuvemshop.access_token'));

        return response()->json([
            'is_connected' => $isConnected,
            'access_token' => $accessToken?->getDisplayValue(),
            'user_id' => $userId,
            'scope' => $scope,
            'token_type' => $tokenType,
            'has_access_token' => $isConnected,
        ]);
    }

    /**
     * Exchange OAuth code for access token.
     * This endpoint is called by the frontend after receiving the code from Nuvemshop.
     */
    public function exchangeToken(NuvemshopCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $clientId = env('NUVEMSHOP_CLIENT_ID', '');
        $clientSecret = env('NUVEMSHOP_CLIENT_SECRET', '');

        if (empty($clientId) || empty($clientSecret)) {
            return response()->json([
                'message' => 'NUVEMSHOP_CLIENT_ID e NUVEMSHOP_CLIENT_SECRET não configurados no .env',
            ], 400);
        }

        try {
            $response = Http::timeout(30)
                ->post(self::TOKEN_URL, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $validated['code'],
                ]);

            if (! $response->successful()) {
                Log::error('Nuvemshop token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Falha ao trocar código por token de acesso.',
                    'error' => $response->json()['error'] ?? 'unknown_error',
                ], $response->status());
            }

            $data = $response->json();

            // Save token data to SystemSettings
            SystemSetting::set('nuvemshop.access_token', $data['access_token'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Access Token',
                'description' => 'Nuvemshop OAuth Access Token',
                'is_sensitive' => true,
            ]);

            if (isset($data['token_type'])) {
                SystemSetting::set('nuvemshop.token_type', $data['token_type'], [
                    'type' => 'string',
                    'group' => 'nuvemshop',
                    'label' => 'Token Type',
                    'description' => 'OAuth Token Type',
                    'is_sensitive' => false,
                ]);
            }

            if (isset($data['scope'])) {
                SystemSetting::set('nuvemshop.scope', $data['scope'], [
                    'type' => 'string',
                    'group' => 'nuvemshop',
                    'label' => 'Scope',
                    'description' => 'OAuth Scope',
                    'is_sensitive' => false,
                ]);
            }

            if (isset($data['user_id'])) {
                SystemSetting::set('nuvemshop.user_id', $data['user_id'], [
                    'type' => 'string',
                    'group' => 'nuvemshop',
                    'label' => 'User ID',
                    'description' => 'Nuvemshop User/Store ID',
                    'is_sensitive' => false,
                ]);
            }

            Log::info('Nuvemshop OAuth token exchanged successfully', [
                'user_id' => $data['user_id'] ?? null,
                'scope' => $data['scope'] ?? null,
            ]);

            return response()->json([
                'message' => 'Conexão estabelecida com sucesso.',
                'data' => [
                    'user_id' => $data['user_id'] ?? null,
                    'scope' => $data['scope'] ?? null,
                    'token_type' => $data['token_type'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during Nuvemshop token exchange', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a autenticação.',
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error',
            ], 500);
        }
    }

    /**
     * Test the connection with current settings.
     * Makes a simple API call to verify the access token is valid.
     */
    public function testConnection(): JsonResponse
    {
        $accessToken = SystemSetting::get('nuvemshop.access_token');
        $userId = SystemSetting::get('nuvemshop.user_id');

        if (empty($accessToken) || empty($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Conexão não configurada. Access token ou user_id ausentes.',
            ], 400);
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'User-Agent' => 'EcommPilot (contact@ecommpilot.com)',
                ])
                ->timeout(10)
                ->get(self::API_BASE_URL.'/'.$userId.'/store');

            if ($response->successful()) {
                $storeData = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => 'Conexão testada com sucesso.',
                    'store_name' => $storeData['name'] ?? 'N/A',
                    'store_domain' => $storeData['domain'] ?? 'N/A',
                ]);
            }

            Log::warning('Nuvemshop connection test failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao testar conexão. Status: '.$response->status(),
                'error' => $response->json()['error'] ?? 'unknown_error',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Exception during Nuvemshop connection test', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão.',
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error',
            ], 500);
        }
    }

    /**
     * Disconnect (clear all Nuvemshop settings).
     */
    public function disconnect(): JsonResponse
    {
        $keys = [
            'nuvemshop.access_token',
            'nuvemshop.token_type',
            'nuvemshop.scope',
            'nuvemshop.user_id',
        ];

        foreach ($keys as $key) {
            SystemSetting::where('key', $key)->delete();
        }

        SystemSetting::clearCache();

        Log::info('Nuvemshop connection disconnected', [
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Desconectado com sucesso.',
        ]);
    }

    /**
     * Get store settings for regular users (same as admin version but accessible to all).
     * Sensitive values are partially masked.
     */
    public function getUserStoreSettings(): JsonResponse
    {
        return $this->getStoreSettings();
    }

    /**
     * Update store settings for regular users.
     */
    public function updateUserStoreSettings(UserStoreSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['client_id'])) {
            SystemSetting::set('nuvemshop.client_id', $validated['client_id'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Client ID',
                'description' => 'Nuvemshop OAuth Client ID',
                'is_sensitive' => true,
            ]);
        }

        if (isset($validated['client_secret'])) {
            SystemSetting::set('nuvemshop.client_secret', $validated['client_secret'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Client Secret',
                'description' => 'Nuvemshop OAuth Client Secret',
                'is_sensitive' => true,
            ]);
        }

        if (isset($validated['grant_type'])) {
            SystemSetting::set('nuvemshop.grant_type', $validated['grant_type'], [
                'type' => 'string',
                'group' => 'nuvemshop',
                'label' => 'Grant Type',
                'description' => 'OAuth Grant Type',
                'is_sensitive' => false,
            ]);
        }

        Log::info('Nuvemshop store settings updated by user', [
            'updated_by' => $request->user()->id,
            'fields' => array_keys($validated),
        ]);

        // Get updated settings
        $settings = $this->getStoreSettings()->getData(true);

        // Return updated settings with masked sensitive values and success message
        return response()->json(array_merge(
            ['message' => 'Configurações da loja atualizadas com sucesso.'],
            $settings
        ));
    }
}
