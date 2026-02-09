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
            ->accessibleStores()
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
     * SECURITY: Prevent SSRF by whitelisting allowed domains and blocking internal IPs.
     */
    private function isValidNuvemshopUrl(string $url): bool
    {
        if (empty($url) || strlen($url) < 3 || strlen($url) > 255) {
            return false;
        }

        // Whitelist of valid Nuvemshop domains
        $allowedDomains = [
            '.lojavirtualnuvem.com.br',
            '.nuvemshop.com.br',
            '.tiendanube.com',
            '.mitiendanube.com',
        ];

        // Normalize URL
        $normalizedUrl = strtolower(trim($url));

        // Check if URL ends with allowed domain
        foreach ($allowedDomains as $domain) {
            if (str_ends_with($normalizedUrl, $domain)) {
                return true;
            }
        }

        // Allow custom domains ONLY if valid hostname and NOT internal IP
        // This prevents SSRF attacks to localhost, private networks, etc.
        $blockedPatterns = [
            '/^localhost/i',
            '/^127\./',           // 127.0.0.0/8 loopback
            '/^10\./',            // 10.0.0.0/8 private
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',  // 172.16.0.0/12 private
            '/^192\.168\./',      // 192.168.0.0/16 private
            '/^0\./',             // 0.0.0.0/8 reserved
            '/^\[/',              // IPv6
            '/^::/',              // IPv6 localhost
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $normalizedUrl)) {
                return false;
            }
        }

        // Validate as hostname
        if (filter_var($normalizedUrl, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }

        return false;
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
            $errorId = 'err_'.uniqid();

            Log::error('Nuvemshop callback error', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect('/integrations?error=callback_failed&error_id='.$errorId);
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

        // Get client credentials from config (env() doesn't work with cached config)
        $clientId = config('services.nuvemshop.client_id');
        $clientSecret = config('services.nuvemshop.client_secret');

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
            // Include soft-deleted and disconnected stores to prevent unique constraint violations
            $existingStore = Store::withTrashed()
                ->where('platform', Platform::Nuvemshop)
                ->where('external_store_id', (string) ($data['user_id'] ?? ''))
                ->first();

            // SECURITY: Prevent store takeover - verify existing store belongs to current user
            $ownerUser = $user->getOwnerUser();
            if ($existingStore && $existingStore->user_id !== $ownerUser->id) {
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

            // If it's a new store (not reconnecting), check plan limits
            // Reconnecting stores (soft-deleted or disconnected) don't count against the limit
            if (! $existingStore && ! $isLocalEnv && ! $this->planLimitService->canAddStore($user)) {
                $ownerPlan = $ownerUser->currentPlan();

                return response()->json([
                    'message' => 'Você atingiu o limite de lojas do seu plano.',
                    'stores_limit' => $ownerPlan?->stores_limit ?? 0,
                    'current_stores' => $ownerUser->stores()->count(),
                    'upgrade_required' => true,
                ], 403);
            }

            // Restore soft-deleted store if reconnecting
            if ($existingStore && $existingStore->trashed()) {
                $existingStore->restore();
                Log::info('Restored soft-deleted store during reconnection', [
                    'store_id' => $existingStore->id,
                    'external_store_id' => $data['user_id'],
                ]);
            }

            $store = Store::updateOrCreate(
                [
                    'platform' => Platform::Nuvemshop,
                    'external_store_id' => (string) ($data['user_id'] ?? ''),
                ],
                [
                    'user_id' => $ownerUser->id,
                    'name' => 'Loja Nuvemshop',
                    'access_token' => $data['access_token'],
                    'authorization_code' => $code,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'sync_status' => SyncStatus::Pending,
                    'token_requires_reconnection' => false,
                ]
            );

            // Log se foi uma reconexão ou uma nova conexão
            if ($existingStore) {
                Log::info('Store reconnected successfully', [
                    'store_id' => $store->id,
                    'external_store_id' => $data['user_id'],
                    'had_products' => $store->products()->count(),
                    'had_orders' => $store->orders()->count(),
                    'had_analyses' => $store->analyses()->count(),
                ]);
            }

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
            $errorId = 'err_'.uniqid();

            Log::error('Exception during Nuvemshop authorization', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a autorização. Tente novamente.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    public function sync(Request $request, Store $store): JsonResponse
    {
        // Verify store ownership
        if (! $request->user()->hasAccessToStore($store->id)) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        if ($store->isSyncing()) {
            return response()->json([
                'message' => 'A sincronização já está em andamento.',
            ], 409);
        }

        // Se o token expirou, a reconexão automática será tentada pelo SyncStoreDataJob
        // Reset sync_status para permitir nova tentativa
        if ($store->sync_status === SyncStatus::TokenExpired) {
            $store->update(['sync_status' => SyncStatus::Pending]);
        }

        SyncStoreDataJob::dispatch($store);

        ActivityLog::log('store.sync_started', $store);

        return response()->json([
            'message' => 'Sincronização iniciada.',
            'store' => new StoreResource($store->fresh()),
        ]);
    }

    public function disconnect(Request $request, Store $store): JsonResponse
    {
        // Apenas donos podem desconectar lojas (funcionários não podem)
        if ($store->user_id !== $request->user()->getOwnerUser()->id) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Funcionários não podem desconectar lojas
        if ($request->user()->isEmployee()) {
            return response()->json(['message' => 'Apenas o proprietário pode desconectar lojas.'], 403);
        }

        $storeName = $store->name;

        ActivityLog::log('store.disconnected', $store);

        // Limpa os tokens e marca como desconectada
        // Os dados de produtos, pedidos, clientes e análises são preservados
        $store->update([
            'access_token' => null,
            'authorization_code' => null,
            'refresh_token' => null,
            'token_requires_reconnection' => false,
            'sync_status' => SyncStatus::Disconnected,
        ]);

        // Update user's active store if this was the active one
        $user = $request->user();
        if ($user->active_store_id === $store->id) {
            $newActiveStore = $user->accessibleStores()->first();
            $user->update(['active_store_id' => $newActiveStore?->id]);
        }

        Log::info('Store disconnected (data preserved)', [
            'store_id' => $store->id,
            'store_name' => $storeName,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Loja desconectada com sucesso. Seus dados foram preservados e estarão disponíveis quando reconectar.',
        ]);
    }

    public function myStores(Request $request): JsonResponse
    {
        $user = $request->user();
        $stores = $user->accessibleStores()
            ->select('id', 'uuid', 'name', 'platform', 'domain', 'email', 'sync_status', 'last_sync_at')
            ->orderBy('name')
            ->get();

        // active_store_id in users table is numeric ID, but we return UUID for frontend
        $activeStoreUuid = $user->activeStore?->uuid;

        return response()->json([
            'stores' => $stores->map(fn ($s) => [
                'id' => $s->uuid,
                'name' => $s->name,
                'platform' => $s->platform,
                'domain' => $s->domain,
                'email' => $s->email,
                'sync_status' => $s->sync_status,
                'last_sync_at' => $s->last_sync_at?->toISOString(),
                'is_active' => $s->uuid === $activeStoreUuid,
            ]),
            'active_store_id' => $activeStoreUuid,
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
            'store_id' => $activeStore->uuid,
            'store_name' => $activeStore->name,
            'sync_status' => $activeStore->sync_status->value,
            'is_syncing' => $activeStore->isSyncing(),
            'last_sync_at' => $activeStore->last_sync_at?->toISOString(),
        ]);
    }

    public function selectStore(Request $request, Store $store): JsonResponse
    {
        $user = $request->user();

        // Verify store ownership
        if (! $user->hasAccessToStore($store->id)) {
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
