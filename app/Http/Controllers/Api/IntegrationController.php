<?php

namespace App\Http\Controllers\Api;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Jobs\SyncStoreDataJob;
use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Services\Integration\NuvemshopService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    public function __construct(
        private NuvemshopService $nuvemshopService,
        private PlanLimitService $planLimitService
    ) {}

    public function stores(Request $request): JsonResponse
    {
        $stores = $request->user()
            ->stores()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(StoreResource::collection($stores));
    }

    public function connectNuvemshop(Request $request): JsonResponse|RedirectResponse
    {
        $storeUrl = $request->input('store_url');

        // Validate store_url if provided
        if ($storeUrl) {
            $storeUrl = $this->normalizeStoreUrl($storeUrl);

            if (! $this->isValidNuvemshopUrl($storeUrl)) {
                return response()->json([
                    'message' => 'URL da loja inválida.',
                ], 422);
            }
        }

        $authUrl = $this->nuvemshopService->getAuthorizationUrl(
            $request->user()->id,
            $storeUrl
        );

        // Return JSON for API calls, redirect for direct access
        if ($request->expectsJson()) {
            return response()->json([
                'redirect_url' => $authUrl,
            ]);
        }

        return redirect($authUrl);
    }

    /**
     * Normalize the store URL to a consistent format.
     */
    private function normalizeStoreUrl(string $url): string
    {
        // Remove protocol if present
        $url = preg_replace('#^https?://#', '', $url);

        // Remove trailing slash
        $url = rtrim($url, '/');

        // Remove www. if present
        $url = preg_replace('#^www\.#', '', $url);

        return $url;
    }

    /**
     * Validate if the URL is a valid store URL format.
     * Accepts any valid domain - Nuvemshop will validate if it's a real store.
     */
    private function isValidNuvemshopUrl(string $url): bool
    {
        // Accept any non-empty URL - Nuvemshop will validate if it's a real store
        return ! empty($url) && strlen($url) >= 3;
    }

    public function callbackNuvemshop(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (! $code || ! $state) {
            return redirect('/integrations?error=invalid_callback');
        }

        try {
            // Decode state to get userId and storeUrl
            $stateData = $this->nuvemshopService->decodeState($state);
            $userId = $stateData['user_id'];
            $storeUrl = $stateData['store_url'];

            if (! $userId) {
                return redirect('/integrations?error=invalid_state');
            }

            $store = $this->nuvemshopService->handleCallback($code, (int) $userId, $storeUrl);

            ActivityLog::log('store.connected', $store);

            // Start initial sync
            SyncStoreDataJob::dispatch($store);

            return redirect('/integrations?success=connected');
        } catch (\Exception $e) {
            Log::error('Nuvemshop callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect('/integrations?error='.urlencode($e->getMessage()));
        }
    }

    /**
     * Authorize Nuvemshop - Exchange OAuth code for access token.
     * This is called by the frontend after receiving the code from Nuvemshop OAuth.
     */
    public function authorizeNuvemshop(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = $request->input('code');

        // Get client credentials from .env
        $clientId = env('NUVEMSHOP_CLIENT_ID');
        $clientSecret = env('NUVEMSHOP_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            return response()->json([
                'message' => 'NUVEMSHOP_CLIENT_ID e NUVEMSHOP_CLIENT_SECRET não configurados no .env',
            ], 400);
        }

        try {
            // Log request data for debugging (not secrets) - ONLY in local/dev
            if (app()->isLocal() || app()->environment('development', 'testing')) {
                Log::info('Attempting Nuvemshop token exchange', [
                    'client_id_set' => ! empty($clientId),
                    'client_secret_set' => ! empty($clientSecret),
                    'code_length' => strlen($code),
                ]);
            }

            // Exchange code for access token
            // Nuvemshop API expects form-urlencoded data, not JSON
            // Using Brazilian URL (nuvemshop.com.br) - for Argentina use tiendanube.com
            $response = Http::timeout(30)
                ->asForm()
                ->post('https://www.nuvemshop.com.br/apps/authorize/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                ]);

            if (! $response->successful()) {
                Log::error('Nuvemshop token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $errorMessage = 'Falha ao conectar com a Nuvemshop.';
                $errorData = $response->json();

                if (isset($errorData['error_description'])) {
                    $errorMessage = $errorData['error_description'];
                } elseif (isset($errorData['error'])) {
                    $errorMessage = $errorData['error'];
                }

                return response()->json([
                    'message' => $errorMessage,
                ], 400);
            }

            $data = $response->json();

            // Log response for debugging - ONLY in local/dev
            if (app()->isLocal() || app()->environment('development', 'testing')) {
                Log::info('Nuvemshop token response', [
                    'status' => $response->status(),
                    'has_access_token' => isset($data['access_token']),
                    'keys' => array_keys($data ?? []),
                ]);
            }

            // Validate that we received the expected access_token
            if (! isset($data['access_token'])) {
                Log::error('Nuvemshop response missing access_token', [
                    'response_body' => $response->body(),
                    'response_data' => $data,
                ]);

                $errorMessage = 'Resposta inválida da Nuvemshop. Token de acesso não recebido.';

                if (isset($data['error_description'])) {
                    $errorMessage = $data['error_description'];
                } elseif (isset($data['error'])) {
                    $errorMessage = $data['error'];
                } elseif (isset($data['message'])) {
                    $errorMessage = $data['message'];
                }

                return response()->json([
                    'message' => $errorMessage,
                    'debug' => config('app.debug') ? [
                        'response_keys' => array_keys($data ?? []),
                    ] : null,
                ], 400);
            }

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
                ]);
            }

            if (isset($data['scope'])) {
                SystemSetting::set('nuvemshop.scope', $data['scope'], [
                    'type' => 'string',
                    'group' => 'nuvemshop',
                    'label' => 'Scope',
                ]);
            }

            if (isset($data['user_id'])) {
                SystemSetting::set('nuvemshop.user_id', (string) $data['user_id'], [
                    'type' => 'string',
                    'group' => 'nuvemshop',
                    'label' => 'User ID',
                ]);
            }

            // Also create/update a Store record for this user
            // Use platform + external_store_id as unique key (matches database constraint)
            $user = $request->user();

            // Check if this would be a NEW store (not reconnecting an existing one)
            $existingStore = Store::where('platform', Platform::Nuvemshop)
                ->where('external_store_id', (string) ($data['user_id'] ?? ''))
                ->first();

            // SECURITY: Prevent store takeover - verify existing store belongs to current user
            if ($existingStore && $existingStore->user_id !== $user->id) {
                Log::warning('Store takeover attempt blocked', [
                    'attacker_user_id' => $user->id,
                    'attacker_email' => $user->email,
                    'original_owner_id' => $existingStore->user_id,
                    'external_store_id' => $data['user_id'] ?? '',
                    'store_name' => $existingStore->name,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return response()->json([
                    'message' => 'Esta loja já está vinculada a outra conta.',
                ], 403);
            }

            // Skip plan limits in local/dev environment
            $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

            // If it's a new store, check plan limits
            if (! $existingStore && ! $isLocalEnv && ! $this->planLimitService->canAddStore($user)) {
                $plan = $user->currentPlan();

                return response()->json([
                    'message' => 'Você atingiu o limite de lojas do seu plano.',
                    'stores_limit' => $plan?->stores_limit ?? 0,
                    'current_stores' => $user->stores()->count(),
                    'upgrade_required' => true,
                ], 403);
            }

            $store = Store::updateOrCreate(
                [
                    'platform' => Platform::Nuvemshop,
                    'external_store_id' => (string) ($data['user_id'] ?? ''),
                ],
                [
                    'user_id' => $user->id,
                    'name' => 'Loja Nuvemshop',
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'sync_status' => SyncStatus::Pending,
                    'token_requires_reconnection' => false,
                ]
            );

            // Try to get store details from Nuvemshop API
            if (isset($data['user_id']) && isset($data['access_token'])) {
                try {
                    $storeResponse = Http::withHeaders([
                        'Authentication' => 'bearer '.$data['access_token'],
                        'User-Agent' => 'Ecomm Pilot (contato@softio.com.br)',
                    ])->get("https://api.tiendanube.com/2025-03/{$data['user_id']}/store");

                    if ($storeResponse->successful()) {
                        $storeData = $storeResponse->json();
                        $store->update([
                            'name' => $storeData['name']['pt'] ?? $storeData['name']['es'] ?? $storeData['name']['en'] ?? 'Loja Nuvemshop',
                            'domain' => $storeData['original_domain'] ?? $storeData['domains'][0] ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch store details from Nuvemshop', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Set newly connected store as the active store
            // This ensures the user is immediately switched to the dashboard of the new store
            $user->update(['active_store_id' => $store->id]);

            // Start initial sync
            SyncStoreDataJob::dispatch($store);

            ActivityLog::log('store.connected', $store);

            Log::info('Nuvemshop OAuth completed successfully', [
                'user_id' => $user->id,
                'store_id' => $store->id,
                'nuvemshop_user_id' => $data['user_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Loja conectada com sucesso!',
                'store' => new StoreResource($store),
                'config' => [
                    'userId' => $data['user_id'] ?? null,
                    'scope' => $data['scope'] ?? null,
                    'tokenType' => $data['token_type'] ?? null,
                    'isConnected' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during Nuvemshop authorization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a autorização. Tente novamente.',
            ], 500);
        }
    }

    public function sync(Request $request, int $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        if ($store->requiresReconnection()) {
            return response()->json([
                'message' => 'O token de acesso da loja está inválido. Por favor, reconecte a loja.',
                'requires_reconnection' => true,
            ], 401);
        }

        if ($store->isSyncing()) {
            return response()->json([
                'message' => 'A sincronização já está em andamento.',
            ], 409);
        }

        SyncStoreDataJob::dispatch($store);

        ActivityLog::log('store.sync_started', $store);

        return response()->json([
            'message' => 'Sincronização iniciada.',
            'store' => new StoreResource($store->fresh()),
        ]);
    }

    public function disconnect(Request $request, int $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $storeName = $store->name;

        ActivityLog::log('store.disconnected', $store);

        // Delete related data first
        $store->products()->delete();
        $store->orders()->delete();
        $store->customers()->delete();
        $store->analyses()->delete();

        // Permanently delete the store (not soft delete)
        $store->forceDelete();

        // Update user's active store if this was the active one
        $user = $request->user();
        if ($user->active_store_id === $storeId) {
            $newActiveStore = $user->stores()->first();
            $user->update(['active_store_id' => $newActiveStore?->id]);
        }

        Log::info('Store disconnected and deleted', [
            'store_id' => $storeId,
            'store_name' => $storeName,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Loja desconectada com sucesso.',
        ]);
    }

    public function myStores(Request $request): JsonResponse
    {
        $user = $request->user();
        $stores = $user->stores()
            ->select('id', 'name', 'platform', 'domain', 'sync_status', 'last_sync_at')
            ->orderBy('name')
            ->get();

        $activeStoreId = $user->activeStore?->id;

        return response()->json([
            'stores' => $stores->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'platform' => $s->platform,
                'domain' => $s->domain,
                'sync_status' => $s->sync_status,
                'last_sync_at' => $s->last_sync_at?->toISOString(),
                'is_active' => $s->id === $activeStoreId,
            ]),
            'active_store_id' => $activeStoreId,
        ]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeStore = $user->activeStore;

        if (! $activeStore) {
            return response()->json([
                'has_store' => false,
                'sync_status' => null,
                'is_syncing' => false,
            ]);
        }

        return response()->json([
            'has_store' => true,
            'store_id' => $activeStore->id,
            'store_name' => $activeStore->name,
            'sync_status' => $activeStore->sync_status->value,
            'is_syncing' => $activeStore->isSyncing(),
            'last_sync_at' => $activeStore->last_sync_at?->toISOString(),
        ]);
    }

    public function selectStore(Request $request, int $storeId): JsonResponse
    {
        $user = $request->user();

        $store = Store::where('id', $storeId)
            ->where('user_id', $user->id)
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Update user's active store
        $user->update(['active_store_id' => $store->id]);

        ActivityLog::log('store.selected', $store);

        return response()->json([
            'message' => 'Loja selecionada com sucesso.',
            'store' => new StoreResource($store),
        ]);
    }
}
