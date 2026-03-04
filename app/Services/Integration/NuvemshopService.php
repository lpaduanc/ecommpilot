<?php

namespace App\Services\Integration;

use App\Contracts\CouponAdapterInterface;
use App\Contracts\OrderAdapterInterface;
use App\Contracts\ProductAdapterInterface;
use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Exceptions\TokenExpiredException;
use App\Models\Store;
use App\Models\SyncedCoupon;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class NuvemshopService
{
    private string $clientId;

    private string $clientSecret;

    private string $redirectUri;

    private string $apiBaseUrl = 'https://api.nuvemshop.com.br/2025-03';

    private string $authUrl = 'https://www.nuvemshop.com.br/apps/authorize';

    /**
     * Número máximo de requisições por minuto por loja.
     * Nuvemshop permite 60/min, usamos 55 como limite interno.
     * Jobs paralelos (Products, Orders, Customers, Coupons) compartilham
     * este bucket, então o limite precisa acomodar picos de concorrência.
     */
    private const RATE_LIMIT_PER_MINUTE = 55;

    /**
     * Threshold de pedidos para usar sincronização paralela.
     * Lojas com mais pedidos que este valor usarão chunks paralelos.
     */
    private const PARALLEL_SYNC_THRESHOLD = 5000;

    /**
     * Número de páginas por chunk na sincronização paralela.
     */
    private const PAGES_PER_CHUNK = 50;

    /**
     * Per page para sincronização de pedidos (usado em syncOrdersRange).
     */
    private const ORDERS_PER_PAGE = 200;

    /**
     * Product adapter for transforming Nuvemshop data to SyncedProduct structure.
     */
    private ProductAdapterInterface $productAdapter;

    /**
     * Order adapter for transforming Nuvemshop data to SyncedOrder structure.
     */
    private OrderAdapterInterface $orderAdapter;

    /**
     * Coupon adapter for transforming Nuvemshop data to SyncedCoupon structure.
     */
    private CouponAdapterInterface $couponAdapter;

    public function __construct(
        ?ProductAdapterInterface $productAdapter = null,
        ?OrderAdapterInterface $orderAdapter = null,
        ?CouponAdapterInterface $couponAdapter = null
    ) {
        // Use config() instead of env() - required when config is cached
        $this->clientId = config('services.nuvemshop.client_id', '');
        $this->clientSecret = config('services.nuvemshop.client_secret', '');

        $this->redirectUri = config('services.nuvemshop.redirect_uri')
            ?? url('/api/integrations/nuvemshop/callback');

        // Use provided adapters or defaults
        $this->productAdapter = $productAdapter ?? new NuvemshopProductAdapter;
        $this->orderAdapter = $orderAdapter ?? new NuvemshopOrderAdapter;
        $this->couponAdapter = $couponAdapter ?? new NuvemshopCouponAdapter;
    }

    public function getAuthorizationUrl(int $userId, ?string $storeUrl = null): string
    {
        $state = $this->encodeState($userId, $storeUrl);

        // Nuvemshop OAuth only requires 'state' as query param.
        // redirect_uri and scope are configured in the app settings on the partner portal.
        $params = http_build_query([
            'state' => $state,
        ]);

        // If store URL is provided, use it to build the auth URL
        // Format: https://{storeUrl}/admin/apps/{clientId}/authorize
        if ($storeUrl) {
            $authUrl = "https://{$storeUrl}/admin/apps/{$this->clientId}/authorize";

            return "{$authUrl}?{$params}";
        }

        // Fallback to generic Nuvemshop auth URL
        return "{$this->authUrl}/{$this->clientId}/authorize?{$params}";
    }

    /**
     * Encode state parameter with HMAC signature for OAuth security.
     * Prevents CSRF attacks by signing state data with app key.
     */
    public function encodeState(int $userId, ?string $storeUrl): string
    {
        $stateData = [
            'user_id' => $userId,
            'store_url' => $storeUrl,
            'nonce' => bin2hex(random_bytes(16)),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];
        $payload = json_encode($stateData);
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode($payload.'|'.$signature);
    }

    /**
     * Decode state parameter from OAuth callback and validate HMAC signature.
     * Returns null if signature is invalid or state has expired.
     */
    public function decodeState(string $state): ?array
    {
        try {
            $decoded = base64_decode($state);
            if (strpos($decoded, '|') === false) {
                // Fallback para state antigo (sem assinatura) - rejeitar em produção
                if (app()->isProduction()) {
                    Log::warning('OAuth state without signature rejected in production');

                    return null;
                }
                // Em dev/local, aceita formato antigo para compatibilidade
                Log::warning('OAuth state without signature accepted in non-production environment');
                $legacyData = json_decode($decoded, true);

                return [
                    'user_id' => $legacyData['user_id'] ?? null,
                    'store_url' => $legacyData['store_url'] ?? null,
                ];
            }

            [$payload, $signature] = explode('|', $decoded, 2);
            $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));

            if (! hash_equals($expectedSignature, $signature)) {
                Log::warning('OAuth state signature mismatch', [
                    'ip' => request()->ip(),
                ]);

                return null;
            }

            $data = json_decode($payload, true);

            // Verificar expiração
            if (isset($data['expires_at']) && $data['expires_at'] < now()->timestamp) {
                Log::warning('OAuth state expired', [
                    'expired_at' => $data['expires_at'],
                    'current_time' => now()->timestamp,
                ]);

                return null;
            }

            return [
                'user_id' => $data['user_id'] ?? null,
                'store_url' => $data['store_url'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to decode OAuth state', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function handleCallback(string $code, int $userId, ?string $storeUrl = null): Store
    {
        // Exchange code for token
        // Nuvemshop API expects form-urlencoded data
        $response = Http::asForm()
            ->timeout(30)
            ->post("{$this->authUrl}/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['error_description']
                ?? $errorData['error']
                ?? $errorData['message']
                ?? 'Falha ao autenticar com Nuvemshop.';

            Log::error('Nuvemshop token exchange failed in service', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($errorMessage);
        }

        $data = $response->json();

        // Validate required fields exist
        if (! isset($data['access_token'])) {
            Log::error('Nuvemshop response missing access_token in service', [
                'response_keys' => array_keys($data ?? []),
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('Resposta inválida da Nuvemshop. Token de acesso não recebido.');
        }

        if (! isset($data['user_id'])) {
            Log::error('Nuvemshop response missing user_id in service', [
                'response_keys' => array_keys($data ?? []),
            ]);

            throw new \RuntimeException('Resposta inválida da Nuvemshop. ID da loja não recebido.');
        }

        // Get store info from Nuvemshop API
        $storeInfo = $this->getStoreInfo($data['access_token'], $data['user_id']);

        // Use provided storeUrl as primary domain (user typed URL), fallback to API domain
        $domain = $storeUrl ?? $storeInfo['domain'] ?? null;

        // Check if store is being reconnected (soft-deleted or disconnected)
        $existingStore = Store::withTrashed()
            ->where('platform', Platform::Nuvemshop)
            ->where('external_store_id', $data['user_id'])
            ->first();

        Log::info('Creating/updating store from Nuvemshop callback', [
            'user_id' => $userId,
            'external_store_id' => $data['user_id'],
            'store_url_provided' => $storeUrl,
            'domain_from_api' => $storeInfo['domain'] ?? null,
            'final_domain' => $domain,
            'is_reconnection' => $existingStore !== null,
            'was_soft_deleted' => $existingStore?->trashed() ?? false,
        ]);

        // Restore soft-deleted store if reconnecting
        if ($existingStore && $existingStore->trashed()) {
            $existingStore->restore();
            Log::info('Restored soft-deleted store during reconnection', [
                'store_id' => $existingStore->id,
                'external_store_id' => $data['user_id'],
            ]);
        }

        // Create or update store
        // When reconnecting, clear token_requires_reconnection flag and reset sync_status
        $store = Store::updateOrCreate(
            [
                'platform' => Platform::Nuvemshop,
                'external_store_id' => $data['user_id'],
            ],
            [
                'user_id' => $userId,
                'name' => $storeInfo['name'] ?? 'Loja Nuvemshop',
                'domain' => $domain,
                'email' => $storeInfo['email'] ?? null,
                'access_token' => $data['access_token'],
                'authorization_code' => $code,
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_requires_reconnection' => false,
                'sync_status' => SyncStatus::Pending,
                'metadata' => $storeInfo,
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

        return $store;
    }

    /**
     * Chunk size for bulk upsert operations.
     */
    private const BULK_CHUNK_SIZE = 500;

    /**
     * Sync products from Nuvemshop using bulk upsert for performance.
     *
     * @param  Store  $store  The store to sync
     * @param  Carbon|null  $updatedSince  If provided, only sync products updated after this date
     */
    public function syncProducts(Store $store, ?Carbon $updatedSince = null): void
    {
        $page = 1;
        $perPage = 200;
        $allProducts = [];
        $totalSynced = 0;

        do {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            if ($updatedSince) {
                $params['updated_at_min'] = $updatedSince->toIso8601String();
            }

            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/products", $params);

            foreach ($response as $product) {
                $allProducts[] = $this->prepareProductForBulkUpsert($store, $product);
            }

            // Process in chunks to avoid memory issues
            if (count($allProducts) >= self::BULK_CHUNK_SIZE) {
                $this->bulkUpsertProducts($allProducts);
                $totalSynced += count($allProducts);
                $allProducts = [];
            }

            $page++;
        } while (count($response) === $perPage);

        // Process remaining products
        if (! empty($allProducts)) {
            $this->bulkUpsertProducts($allProducts);
            $totalSynced += count($allProducts);
        }

        Log::info("Products sync completed for store {$store->id}", [
            'total_synced' => $totalSynced,
            'incremental' => $updatedSince !== null,
        ]);
    }

    /**
     * Sync orders from Nuvemshop using bulk upsert for performance.
     *
     * @param  Store  $store  The store to sync
     * @param  Carbon|null  $updatedSince  If provided, only sync orders updated after this date
     */
    public function syncOrders(Store $store, ?Carbon $updatedSince = null): void
    {
        $page = 1;
        $perPage = 200; // Increased from 50 to 200 for better performance (75% less API calls)
        $allOrders = [];
        $totalSynced = 0;

        do {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            if ($updatedSince) {
                $params['updated_at_min'] = $updatedSince->toIso8601String();
            }

            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/orders", $params);

            foreach ($response as $order) {
                $allOrders[] = $this->prepareOrderForBulkUpsert($store, $order);
            }

            // Process in chunks to avoid memory issues
            if (count($allOrders) >= self::BULK_CHUNK_SIZE) {
                $this->bulkUpsertOrders($allOrders);
                $totalSynced += count($allOrders);
                $allOrders = [];
            }

            $page++;

            // Log progress for large syncs
            if ($page % 20 === 0) {
                Log::info("Orders sync progress for store {$store->id}: page {$page}, synced {$totalSynced}");
            }
        } while (count($response) === $perPage);

        // Process remaining orders
        if (! empty($allOrders)) {
            $this->bulkUpsertOrders($allOrders);
            $totalSynced += count($allOrders);
        }

        Log::info("Orders sync completed for store {$store->id}", [
            'total_synced' => $totalSynced,
            'incremental' => $updatedSince !== null,
        ]);
    }

    /**
     * Busca uma página específica de pedidos da API.
     * Usado pelo SyncOrdersPageJob para paginação por jobs.
     *
     * @param  Store  $store  The store to fetch from
     * @param  int  $page  Page number (1-indexed)
     * @param  int  $perPage  Items per page
     * @param  string|null  $updatedSince  ISO 8601 date for incremental sync
     * @return array Raw orders data from API
     */
    public function fetchOrdersPage(Store $store, int $page, int $perPage = 200, ?string $updatedSince = null): array
    {
        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($updatedSince) {
            $params['updated_at_min'] = $updatedSince;
        }

        return $this->makeRequest($store, 'GET', "/{$store->external_store_id}/orders", $params);
    }

    /**
     * Salva uma lista de pedidos no banco de dados.
     * Usado pelo SyncOrdersPageJob para persistência.
     *
     * @param  Store  $store  The store
     * @param  array  $orders  Raw orders data from API
     */
    public function saveOrders(Store $store, array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        $preparedOrders = [];
        foreach ($orders as $order) {
            $preparedOrders[] = $this->prepareOrderForBulkUpsert($store, $order);
        }

        $this->bulkUpsertOrders($preparedOrders);

        // Upsert customers derived from this batch of orders.
        // This ensures new and existing customers (including guest checkouts) are
        // reflected in synced_customers immediately after each batch of orders is saved,
        // without waiting for the separate SyncCustomersJob to complete.
        $this->upsertCustomersFromOrders($store, $orders);
    }

    /**
     * Sincroniza um range específico de páginas de pedidos.
     * Usado para processamento paralelo em lojas grandes.
     *
     * @param  Store  $store  The store to sync
     * @param  int  $startPage  First page to sync (1-indexed)
     * @param  int  $endPage  Last page to sync (inclusive)
     * @param  string|null  $updatedSince  ISO 8601 date string for incremental sync
     * @return int Number of orders synced
     */
    public function syncOrdersRange(Store $store, int $startPage, int $endPage, ?string $updatedSince = null): int
    {
        $allOrders = [];
        $totalSynced = 0;

        for ($page = $startPage; $page <= $endPage; $page++) {
            $params = [
                'page' => $page,
                'per_page' => self::ORDERS_PER_PAGE,
            ];

            if ($updatedSince) {
                $params['updated_at_min'] = $updatedSince;
            }

            try {
                $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/orders", $params);
            } catch (\Exception $e) {
                Log::error("Erro ao buscar página {$page} de pedidos", [
                    'store_id' => $store->id,
                    'start_page' => $startPage,
                    'end_page' => $endPage,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (empty($response)) {
                break; // Não há mais pedidos
            }

            foreach ($response as $order) {
                $allOrders[] = $this->prepareOrderForBulkUpsert($store, $order);
            }

            // Process in chunks to avoid memory issues
            if (count($allOrders) >= self::BULK_CHUNK_SIZE) {
                $this->bulkUpsertOrders($allOrders);
                $totalSynced += count($allOrders);
                $allOrders = [];
            }

            // Se retornou menos que perPage, é a última página
            if (count($response) < self::ORDERS_PER_PAGE) {
                break;
            }
        }

        // Process remaining orders
        if (! empty($allOrders)) {
            $this->bulkUpsertOrders($allOrders);
            $totalSynced += count($allOrders);
        }

        Log::info("Orders range sync completed for store {$store->id}", [
            'pages' => "{$startPage}-{$endPage}",
            'total_synced' => $totalSynced,
        ]);

        return $totalSynced;
    }

    /**
     * Dispara sincronização de pedidos em paralelo usando chunks.
     * Divide o total de páginas em chunks e processa em workers separados.
     *
     * @param  Store  $store  The store to sync
     * @param  string|null  $updatedSince  ISO 8601 date string for incremental sync
     * @return Batch The batch instance for tracking progress
     */
    public function dispatchParallelOrdersSync(Store $store, ?string $updatedSince = null): Batch
    {
        // Estima total de páginas necessárias
        $estimatedOrders = $this->getOrdersCount($store);
        $totalPages = (int) ceil($estimatedOrders / self::ORDERS_PER_PAGE);

        // Garante pelo menos 1 página
        $totalPages = max($totalPages, 1);

        // Cria jobs para cada chunk de páginas
        $jobs = [];
        for ($startPage = 1; $startPage <= $totalPages; $startPage += self::PAGES_PER_CHUNK) {
            $endPage = min($startPage + self::PAGES_PER_CHUNK - 1, $totalPages);

            $jobs[] = new SyncOrdersChunkJob(
                $store,
                $startPage,
                $endPage,
                $updatedSince
            );
        }

        Log::info('Disparando sincronização paralela de pedidos', [
            'store_id' => $store->id,
            'estimated_orders' => $estimatedOrders,
            'total_pages' => $totalPages,
            'total_chunks' => count($jobs),
            'pages_per_chunk' => self::PAGES_PER_CHUNK,
        ]);

        // Dispara batch com controle de concorrência
        return Bus::batch($jobs)
            ->name("sync-orders-parallel-store-{$store->id}")
            ->allowFailures()
            ->onQueue('sync')
            ->dispatch();
    }

    /**
     * Retorna o número estimado de pedidos de uma loja.
     * Usa a contagem do banco de dados como referência.
     *
     * @param  Store  $store  The store to check
     * @return int Estimated number of orders
     */
    public function getOrdersCount(Store $store): int
    {
        // Usa contagem do banco como estimativa
        // Em sync inicial, retorna um valor alto para garantir cobertura
        $dbCount = $store->orders()->count();

        // Se não tem pedidos no banco, assume que pode ter muitos (sync inicial)
        // Usa 100k como máximo seguro para não criar chunks demais
        if ($dbCount === 0) {
            return 100000;
        }

        // Adiciona margem de 10% para novos pedidos
        return (int) ceil($dbCount * 1.1);
    }

    /**
     * Verifica se a loja deve usar sincronização paralela baseado no número de pedidos.
     *
     * @param  Store  $store  The store to check
     * @return bool True if parallel sync should be used
     */
    public function shouldUseParallelSync(Store $store): bool
    {
        return $this->getOrdersCount($store) > self::PARALLEL_SYNC_THRESHOLD;
    }

    /**
     * Sync customers from Nuvemshop using bulk upsert for performance.
     *
     * @param  Store  $store  The store to sync
     * @param  Carbon|null  $updatedSince  If provided, only sync customers updated after this date
     */
    public function syncCustomers(Store $store, ?Carbon $updatedSince = null): void
    {
        $page = 1;
        $perPage = 200;
        $allCustomers = [];
        $totalSynced = 0;

        do {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            if ($updatedSince) {
                $params['updated_at_min'] = $updatedSince->toIso8601String();
            }

            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/customers", $params);

            foreach ($response as $customer) {
                $allCustomers[] = $this->prepareCustomerForBulkUpsert($store, $customer);
            }

            // Process in chunks to avoid memory issues
            if (count($allCustomers) >= self::BULK_CHUNK_SIZE) {
                $this->bulkUpsertCustomers($allCustomers);
                $totalSynced += count($allCustomers);
                $allCustomers = [];
            }

            $page++;
        } while (count($response) === $perPage);

        // Process remaining customers
        if (! empty($allCustomers)) {
            $this->bulkUpsertCustomers($allCustomers);
            $totalSynced += count($allCustomers);
        }

        // Update first_order_at / last_order_at from synced_orders
        $this->updateCustomerOrderDates($store);

        Log::info("Customers sync completed for store {$store->id}", [
            'total_synced' => $totalSynced,
            'incremental' => $updatedSince !== null,
        ]);
    }

    /**
     * Update first_order_at and last_order_at on synced_customers
     * by aggregating MIN/MAX external_created_at from synced_orders.
     */
    private function updateCustomerOrderDates(Store $store): void
    {
        DB::statement('
            UPDATE synced_customers sc SET
                first_order_at = sub.first_order,
                last_order_at  = sub.last_order
            FROM (
                SELECT customer_email, store_id,
                       MIN(external_created_at) AS first_order,
                       MAX(external_created_at) AS last_order
                FROM synced_orders
                WHERE customer_email IS NOT NULL
                  AND store_id = ?
                GROUP BY customer_email, store_id
            ) sub
            WHERE sc.email    = sub.customer_email
              AND sc.store_id = sub.store_id
              AND sc.store_id = ?
              AND sc.deleted_at IS NULL
        ', [$store->id, $store->id]);
    }

    /**
     * Sync coupons from Nuvemshop using bulk upsert for performance.
     *
     * @param  Store  $store  The store to sync
     * @param  Carbon|null  $updatedSince  If provided, only sync coupons updated after this date
     */
    public function syncCoupons(Store $store, ?Carbon $updatedSince = null): void
    {
        $page = 1;
        $perPage = 200;
        $allCoupons = [];
        $totalSynced = 0;

        do {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            if ($updatedSince) {
                $params['updated_at_min'] = $updatedSince->toIso8601String();
            }

            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/coupons", $params);

            foreach ($response as $coupon) {
                $allCoupons[] = $this->prepareCouponForBulkUpsert($store, $coupon);
            }

            // Process in chunks to avoid memory issues
            if (count($allCoupons) >= self::BULK_CHUNK_SIZE) {
                $this->bulkUpsertCoupons($allCoupons);
                $totalSynced += count($allCoupons);
                $allCoupons = [];
            }

            $page++;
        } while (count($response) === $perPage);

        // Process remaining coupons
        if (! empty($allCoupons)) {
            $this->bulkUpsertCoupons($allCoupons);
            $totalSynced += count($allCoupons);
        }

        Log::info("Coupons sync completed for store {$store->id}", [
            'total_synced' => $totalSynced,
            'incremental' => $updatedSince !== null,
        ]);
    }

    private function getStoreInfo(string $accessToken, string $storeId): array
    {
        // Nuvemshop uses non-standard auth header: "Authentication: bearer {token}"
        $response = Http::withHeaders([
            'Authentication' => 'bearer '.$accessToken,
            'User-Agent' => 'Ecomm Pilot (contato@softio.com.br)',
        ])
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(3, 1000, function ($exception) {
                return $this->shouldRetry($exception);
            })
            ->get("{$this->apiBaseUrl}/{$storeId}/store");

        return $response->successful() ? $response->json() : [];
    }

    /**
     * Faz uma requisição à API com retry, rate limiting e token refresh automático
     */
    private function makeRequest(Store $store, string $method, string $endpoint, array $params = []): array
    {
        // Rate limiting por loja (55 req/min — Nuvemshop permite 60/min)
        $rateLimitKey = "nuvemshop_api:{$store->id}";

        // Aguarda se atingiu rate limit.
        // Em modo paralelo, múltiplos jobs (Products/Orders/Customers/Coupons) compartilham
        // o mesmo bucket. Esperar o tempo completo de reset (availableIn) não funciona porque
        // os outros jobs paralelos reenchem o bucket enquanto este espera. Por isso usamos
        // intervalos curtos de 5s para verificar se há espaço disponível.
        $rateLimitWaitStart = null;
        while (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_PER_MINUTE)) {
            if ($rateLimitWaitStart === null) {
                $rateLimitWaitStart = time();
                Log::channel('sync')->info("[RATE-LIMIT] Entrando em espera para loja {$store->id} (limite: ".self::RATE_LIMIT_PER_MINUTE.'/min)');
            }
            $waitedSeconds = time() - $rateLimitWaitStart;
            if ($waitedSeconds > 0 && $waitedSeconds % 60 === 0) {
                Log::channel('sync')->warning("[RATE-LIMIT] Aguardando há {$waitedSeconds}s para loja {$store->id} (limite: ".self::RATE_LIMIT_PER_MINUTE.'/min)');
            }
            sleep(5); // Espera intervalo curto e testa novamente
        }
        if ($rateLimitWaitStart !== null) {
            $totalWait = time() - $rateLimitWaitStart;
            Log::channel('sync')->info("[RATE-LIMIT] Saindo de espera após {$totalWait}s para loja {$store->id}");
        }

        RateLimiter::hit($rateLimitKey, 60);

        $maxAttempts = 2; // 1 tentativa inicial + 1 retry após refresh
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                // Reconecta ao banco para evitar conexões stale em jobs longos
                DB::reconnect();

                // Refresh the store model to get the latest token
                $store->refresh();

                // Nuvemshop uses non-standard auth header: "Authentication: bearer {token}"
                // NOT the standard "Authorization: Bearer {token}"
                $response = Http::withHeaders([
                    'Authentication' => 'bearer '.$store->access_token,
                    'User-Agent' => 'Ecomm Pilot (contato@softio.com.br)',
                ])
                    ->connectTimeout(15)
                    ->timeout(60)
                    ->retry(3, 1000, function ($exception) {
                        return $this->shouldRetry($exception);
                    }, throw: false)
                    ->{strtolower($method)}("{$this->apiBaseUrl}{$endpoint}", $params);

                // Se recebeu 401, tenta reconexão automática antes de falhar
                if ($response->status() === 401) {
                    if ($attempt < $maxAttempts) {
                        Log::warning("Token expirado para loja {$store->id}, tentando reconexão automática", [
                            'store_id' => $store->id,
                            'store_name' => $store->name,
                            'endpoint' => $endpoint,
                            'attempt' => $attempt,
                        ]);

                        if ($this->refreshAccessToken($store)) {
                            continue; // Retry com novo token
                        }
                    }

                    // Reconexão falhou ou última tentativa
                    Log::warning("Reconexão falhou para loja {$store->id}, marcando token como expirado", [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'endpoint' => $endpoint,
                    ]);

                    $store->update([
                        'token_requires_reconnection' => true,
                        'sync_status' => SyncStatus::TokenExpired,
                    ]);

                    throw new TokenExpiredException($store->id, $store->name);
                }

                // Para qualquer outro erro ou sucesso, processa normalmente
                if (! $response->successful()) {
                    // Handle 404 "Last page is 0" - This is Nuvemshop's way of saying "no more pages"
                    if ($response->status() === 404) {
                        $bodyData = $response->json();
                        if (isset($bodyData['description']) && str_contains($bodyData['description'], 'Last page is')) {
                            Log::channel('sync')->info('[API] Fim de paginação detectado (404 Last page)', [
                                'store_id' => $store->id,
                                'endpoint' => $endpoint,
                                'description' => $bodyData['description'],
                            ]);

                            // Return empty array - this is not an error, it's end of pagination
                            return [];
                        }
                    }

                    $this->handleApiError($response, $store, $endpoint);
                }

                return $response->json() ?? [];
            } catch (RequestException $e) {
                // Se for o último attempt ou não for erro de autenticação, lança a exceção
                if ($attempt >= $maxAttempts || $e->response->status() !== 401) {
                    Log::error('Nuvemshop API request failed', [
                        'store_id' => $store->id,
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } catch (\Exception $e) {
                Log::error('Nuvemshop API request failed', [
                    'store_id' => $store->id,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // Se chegou aqui, esgotou as tentativas
        throw new \RuntimeException("Falha ao executar requisição após {$maxAttempts} tentativas");
    }

    /**
     * Determina se deve retentar a requisição
     */
    private function shouldRetry(\Throwable $exception): bool
    {
        // Retry em erros de conexão/timeout (cURL errors)
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            Log::channel('sync')->info('[API] Retry após erro de conexão/timeout: '.$exception->getMessage());

            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        // Retry em erros transientes (429 rate limit, 5xx server errors)
        return in_array($status, [429, 500, 502, 503, 504]);
    }

    /**
     * Tenta reconectar a loja automaticamente via OAuth.
     *
     * Segue o mesmo fluxo de conexão inicial:
     * 1. Gera URL de autorização
     * 2. Segue cadeia de redirects buscando o code
     * 3. Troca o code por novo access_token
     */
    public function attemptReconnection(Store $store): bool
    {
        Log::info('[RECONNECT] Tentando reconexão automática da loja', [
            'store_id' => $store->id,
            'store_name' => $store->name,
        ]);

        return $this->refreshAccessToken($store);
    }

    /**
     * Tenta obter novo access_token usando o authorization_code permanente.
     *
     * O code retornado pela Nuvemshop durante OAuth é permanente e reutilizável.
     * Podemos trocar o mesmo code por um novo access_token múltiplas vezes.
     */
    private function refreshAccessToken(Store $store): bool
    {
        try {
            // Verifica se temos o authorization_code salvo
            if (empty($store->authorization_code)) {
                Log::warning('[RECONNECT] Store não possui authorization_code salvo', [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                ]);

                return false;
            }

            Log::info('[RECONNECT] Usando authorization_code salvo para obter novo token', [
                'store_id' => $store->id,
                'store_name' => $store->name,
            ]);

            // Troca o authorization_code por novo access_token
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://www.tiendanube.com/apps/authorize/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $store->authorization_code,
                ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error_description']
                    ?? $errorData['error']
                    ?? $errorData['message']
                    ?? 'Falha ao renovar token.';

                Log::error('[RECONNECT] Falha ao trocar authorization_code por novo token', [
                    'store_id' => $store->id,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json();

            // Validate required fields exist
            if (! isset($data['access_token'])) {
                Log::error('[RECONNECT] Resposta não contém access_token', [
                    'store_id' => $store->id,
                    'response_keys' => array_keys($data ?? []),
                ]);

                return false;
            }

            // Atualiza apenas o access_token (mantém o authorization_code!)
            $store->update([
                'access_token' => $data['access_token'],
                'token_requires_reconnection' => false,
                'sync_status' => SyncStatus::Pending,
            ]);

            Log::info('[RECONNECT] Reconexão automática bem-sucedida', [
                'store_id' => $store->id,
                'store_name' => $store->name,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[RECONNECT] Falha na reconexão automática', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Trata erros da API
     */
    private function handleApiError($response, Store $store, string $endpoint): void
    {
        $status = $response->status();
        $body = $response->body();

        Log::error('Nuvemshop API error', [
            'store_id' => $store->id,
            'endpoint' => $endpoint,
            'status' => $status,
            'body' => $body,
        ]);

        $message = match ($status) {
            401 => 'Token de acesso inválido ou expirado',
            403 => 'Acesso negado ao recurso',
            404 => 'Recurso não encontrado',
            429 => 'Limite de requisições excedido',
            default => "Erro na API Nuvemshop: {$status}",
        };

        throw new \RuntimeException($message);
    }

    /**
     * Parse datetime string from Nuvemshop API.
     *
     * Nuvemshop returns dates in ISO 8601 format with UTC timezone (+0000).
     * This method parses the date and converts it to the application timezone.
     */
    private function parseDateTime(?string $datetime): ?Carbon
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return Carbon::parse($datetime)->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert Carbon datetime to database format string.
     */
    private function formatDateTimeForDb(?Carbon $datetime): ?string
    {
        return $datetime?->format('Y-m-d H:i:s');
    }

    // ==========================================
    // BULK UPSERT METHODS - Products
    // ==========================================

    /**
     * Prepare product data for bulk upsert.
     */
    private function prepareProductForBulkUpsert(Store $store, array $data): array
    {
        $productData = $this->productAdapter->transform($data);
        $now = now()->format('Y-m-d H:i:s');

        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $store->id,
            'external_id' => $productData['external_id'],
            'name' => $productData['name'],
            'description' => $productData['description'],
            'price' => $productData['price'],
            'compare_at_price' => $productData['compare_at_price'],
            'stock_quantity' => $productData['stock_quantity'],
            'sku' => $productData['sku'],
            'images' => json_encode($productData['images']),
            'categories' => json_encode($productData['categories']),
            'variants' => json_encode($productData['variants']),
            'is_active' => $productData['is_active'],
            'external_created_at' => $this->formatDateTimeForDb($productData['external_created_at']),
            'external_updated_at' => $this->formatDateTimeForDb($productData['external_updated_at']),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Bulk upsert products using database upsert for performance.
     *
     * Deduplica produtos pelo par store_id+external_id antes do upsert
     * para evitar erro "ON CONFLICT DO UPDATE cannot affect row a second time"
     */
    private function bulkUpsertProducts(array $products): void
    {
        if (empty($products)) {
            return;
        }

        // Deduplica produtos pelo par store_id+external_id, mantendo o último
        $uniqueProducts = [];
        foreach ($products as $product) {
            $key = $product['store_id'].'_'.$product['external_id'];
            $uniqueProducts[$key] = $product;
        }
        $products = array_values($uniqueProducts);

        SyncedProduct::upsert(
            $products,
            uniqueBy: ['store_id', 'external_id'],
            update: [
                'name',
                'description',
                'price',
                'compare_at_price',
                'stock_quantity',
                'sku',
                'images',
                'categories',
                'variants',
                'is_active',
                'external_created_at',
                'external_updated_at',
                'updated_at',
            ]
        );
    }

    // ==========================================
    // BULK UPSERT METHODS - Orders
    // ==========================================

    /**
     * Prepare order data for bulk upsert.
     */
    private function prepareOrderForBulkUpsert(Store $store, array $data): array
    {
        $orderData = $this->orderAdapter->transform($data);
        $now = now()->format('Y-m-d H:i:s');

        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $store->id,
            'external_id' => $orderData['external_id'],
            'order_number' => $orderData['order_number'],
            'status' => $orderData['status'],
            'payment_status' => $orderData['payment_status'],
            'shipping_status' => $orderData['shipping_status'],
            'customer_name' => $orderData['customer_name'],
            'customer_email' => $orderData['customer_email'],
            'customer_phone' => $orderData['customer_phone'],
            'subtotal' => $orderData['subtotal'],
            'discount' => $orderData['discount'],
            'shipping' => $orderData['shipping'],
            'total' => $orderData['total'],
            'payment_method' => $orderData['payment_method'],
            'coupon' => $orderData['coupon'] ? json_encode($orderData['coupon']) : null,
            'items' => json_encode($orderData['items']),
            'shipping_address' => $orderData['shipping_address'] ? json_encode($orderData['shipping_address']) : null,
            'external_created_at' => $this->formatDateTimeForDb($orderData['external_created_at']),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Bulk upsert orders using database upsert for performance.
     *
     * Deduplica pedidos pelo par store_id+external_id antes do upsert
     * para evitar erro "ON CONFLICT DO UPDATE cannot affect row a second time"
     */
    private function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        // Deduplica pedidos pelo par store_id+external_id, mantendo o último
        $uniqueOrders = [];
        foreach ($orders as $order) {
            $key = $order['store_id'].'_'.$order['external_id'];
            $uniqueOrders[$key] = $order;
        }
        $orders = array_values($uniqueOrders);

        try {
            SyncedOrder::upsert(
                $orders,
                uniqueBy: ['store_id', 'external_id'],
                update: [
                    'order_number',
                    'status',
                    'payment_status',
                    'shipping_status',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'subtotal',
                    'discount',
                    'shipping',
                    'total',
                    'payment_method',
                    'coupon',
                    'items',
                    'shipping_address',
                    'external_created_at',
                    'updated_at',
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'ON CONFLICT DO UPDATE')) {
                Log::channel('sync')->warning('Bulk upsert de pedidos falhou por duplicata, usando fallback individual', [
                    'count' => count($orders),
                    'error' => $e->getMessage(),
                ]);

                foreach ($orders as $order) {
                    SyncedOrder::updateOrCreate(
                        ['store_id' => $order['store_id'], 'external_id' => $order['external_id']],
                        $order
                    );
                }
            } else {
                throw $e;
            }
        }
    }

    // ==========================================
    // CUSTOMER UPSERT FROM ORDERS
    // ==========================================

    /**
     * Upsert synced_customers records derived from a batch of raw Nuvemshop order data.
     *
     * This runs after every saveOrders() call so that:
     *  - New customers who placed their first order today are created immediately
     *    without waiting for the separate SyncCustomersJob to complete.
     *  - Guest checkout customers (not present in the /customers API) still get a record.
     *  - Existing customers have their first_order_at / last_order_at date range extended
     *    when the incoming orders fall outside the currently stored range.
     *
     * For existing customers, total_orders and total_spent are intentionally NOT modified
     * here because those authoritative values come from Nuvemshop's /customers API and
     * would be double-counted if incremented per batch. The full SyncCustomersJob writes
     * the correct totals from the API.
     *
     * For new customers (no record yet), totals are seeded from this order batch as a
     * best-effort value that SyncCustomersJob will overwrite with the API's authoritative
     * figure on the next sync cycle.
     *
     * The derived external_id uses "order-derived-{md5(email+store_id)}" and never
     * collides with real Nuvemshop customer IDs. If SyncCustomersJob later fetches the
     * real customer record (by actual Nuvemshop ID), a separate row is created. The
     * order-derived row persists as the authoritative record for guest checkouts.
     */
    private function upsertCustomersFromOrders(Store $store, array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        // Group orders by normalised customer_email, aggregating date range and totals.
        $byEmail = [];
        foreach ($orders as $order) {
            $orderData = $this->orderAdapter->transform($order);
            $email = $orderData['customer_email'] ?? null;

            if (empty($email)) {
                continue;
            }

            $email = strtolower(trim($email));

            if (! isset($byEmail[$email])) {
                $byEmail[$email] = [
                    'email' => $email,
                    'name' => $orderData['customer_name'] ?? 'Desconhecido',
                    'phone' => $orderData['customer_phone'] ?? null,
                    'total_spent' => 0.0,
                    'order_count' => 0,
                    'first_order_at' => null,
                    'last_order_at' => null,
                ];
            }

            $byEmail[$email]['total_spent'] += (float) ($orderData['total'] ?? 0);
            $byEmail[$email]['order_count']++;

            // Use raw created_at from the API response to determine order date.
            $orderDate = $this->parseDateTime($order['created_at'] ?? null);
            if ($orderDate) {
                $formatted = $this->formatDateTimeForDb($orderDate);
                if ($byEmail[$email]['first_order_at'] === null || $formatted < $byEmail[$email]['first_order_at']) {
                    $byEmail[$email]['first_order_at'] = $formatted;
                }
                if ($byEmail[$email]['last_order_at'] === null || $formatted > $byEmail[$email]['last_order_at']) {
                    $byEmail[$email]['last_order_at'] = $formatted;
                }
            }
        }

        if (empty($byEmail)) {
            return;
        }

        $emails = array_keys($byEmail);

        // Fetch existing records using case-insensitive match (matches the unique index).
        $existing = SyncedCustomer::where('store_id', $store->id)
            ->whereIn(DB::raw('lower(email)'), $emails)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy(fn ($c) => strtolower($c->email));

        $newCount = 0;
        $updatedCount = 0;

        foreach ($byEmail as $email => $derived) {
            $customer = $existing->get($email);

            if ($customer) {
                // Extend the known date range only — do not touch total_orders / total_spent
                // because those come from the authoritative /customers API.
                $changed = false;

                $storedFirst = $customer->first_order_at?->format('Y-m-d H:i:s');
                if ($derived['first_order_at'] && (! $storedFirst || $derived['first_order_at'] < $storedFirst)) {
                    $customer->first_order_at = $derived['first_order_at'];
                    $changed = true;
                }

                $storedLast = $customer->last_order_at?->format('Y-m-d H:i:s');
                if ($derived['last_order_at'] && (! $storedLast || $derived['last_order_at'] > $storedLast)) {
                    $customer->last_order_at = $derived['last_order_at'];
                    $changed = true;
                }

                if ($changed) {
                    $customer->save();
                    $updatedCount++;
                }
            } else {
                // No record yet — create a best-effort record from order data.
                // SyncCustomersJob will overwrite total_orders/total_spent with API values.
                // Use updateOrCreate to handle race conditions with the unique email index.
                try {
                    SyncedCustomer::updateOrCreate(
                        [
                            'store_id' => $store->id,
                            'external_id' => 'order-derived-'.md5($email.$store->id),
                        ],
                        [
                            'name' => $derived['name'],
                            'email' => $email,
                            'phone' => $derived['phone'],
                            'total_orders' => $derived['order_count'],
                            'total_spent' => $derived['total_spent'],
                            'first_order_at' => $derived['first_order_at'],
                            'last_order_at' => $derived['last_order_at'],
                            'external_created_at' => $derived['first_order_at'],
                        ]
                    );
                    $newCount++;
                } catch (\Illuminate\Database\QueryException $e) {
                    // Unique constraint violation — a record with this email was inserted
                    // concurrently (e.g., by SyncCustomersJob). Safe to skip.
                    if (str_contains($e->getMessage(), 'synced_customers_store_email_unique')) {
                        Log::channel('sync')->debug('[ORDERS] Cliente já existe com este email, ignorando', [
                            'store_id' => $store->id,
                            'email' => $email,
                        ]);
                    } else {
                        throw $e;
                    }
                }
            }
        }

        Log::channel('sync')->debug('[ORDERS] Clientes atualizados a partir de pedidos', [
            'store_id' => $store->id,
            'emails_processed' => count($byEmail),
            'existing_updated' => $updatedCount,
            'new_created' => $newCount,
        ]);
    }

    // ==========================================
    // BULK UPSERT METHODS - Customers
    // ==========================================

    /**
     * Prepare customer data for bulk upsert.
     */
    private function prepareCustomerForBulkUpsert(Store $store, array $data): array
    {
        $now = now()->format('Y-m-d H:i:s');

        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $store->id,
            'external_id' => (string) $data['id'],
            'name' => $data['name'] ?? 'Desconhecido',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'total_orders' => (int) ($data['total_orders'] ?? 0),
            'total_spent' => (float) ($data['total_spent'] ?? 0),
            'external_created_at' => $this->formatDateTimeForDb($this->parseDateTime($data['created_at'] ?? null)),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Bulk upsert customers using database upsert for performance.
     *
     * Before upserting real Nuvemshop customers (numeric external_id), removes any
     * order-derived placeholder records with the same email so the real record becomes
     * the single authoritative row. This prevents the "one customer, two rows" problem
     * that occurs when upsertCustomersFromOrders() runs before syncCustomers().
     *
     * Deduplicates by store_id+external_id AND store_id+email to avoid
     * constraint violations on both unique indexes.
     *
     * Nuvemshop can return multiple customer records with different external_ids
     * but the same email (e.g., customer re-registered). The DB has a unique index
     * on (store_id, lower(email)), so we must keep only one record per email.
     * We keep the one with the highest total_orders (then total_spent as tiebreaker).
     */
    private function bulkUpsertCustomers(array $customers): void
    {
        if (empty($customers)) {
            return;
        }

        // Step 1: Deduplica pelo par store_id+external_id, mantendo o último
        $uniqueCustomers = [];
        foreach ($customers as $customer) {
            $key = $customer['store_id'].'_'.$customer['external_id'];
            $uniqueCustomers[$key] = $customer;
        }

        // Step 2: Deduplica pelo par store_id+lower(email), mantendo o mais relevante.
        // Nuvemshop pode ter múltiplos registros com external_ids diferentes mas mesmo email.
        // O índice unique (store_id, lower(email)) impede que ambos existam no banco.
        $byEmail = [];
        foreach ($uniqueCustomers as $customer) {
            if (empty($customer['email'])) {
                continue;
            }
            $emailKey = $customer['store_id'].'_'.strtolower($customer['email']);
            if (! isset($byEmail[$emailKey])) {
                $byEmail[$emailKey] = $customer;
            } else {
                $existing = $byEmail[$emailKey];
                // Mantém o com mais pedidos; em empate, o com maior gasto
                if ($customer['total_orders'] > $existing['total_orders']
                    || ($customer['total_orders'] === $existing['total_orders']
                        && $customer['total_spent'] > $existing['total_spent'])) {
                    $byEmail[$emailKey] = $customer;
                }
            }
        }

        // Reconstruir array final: registros sem email + vencedores por email
        $emailWinners = [];
        foreach ($byEmail as $customer) {
            $emailWinners[$customer['store_id'].'_'.$customer['external_id']] = true;
        }

        $finalCustomers = [];
        foreach ($uniqueCustomers as $key => $customer) {
            if (empty($customer['email'])) {
                $finalCustomers[] = $customer;
            } elseif (isset($emailWinners[$key])) {
                $finalCustomers[] = $customer;
            }
            // Descarta duplicatas de email que perderam a comparação
        }

        if (empty($finalCustomers)) {
            return;
        }

        // Step 3: Remove order-derived placeholder rows for the same (store_id, email) pairs.
        $emailsByStore = [];
        foreach ($finalCustomers as $customer) {
            if (! empty($customer['email'])) {
                $emailsByStore[$customer['store_id']][] = strtolower($customer['email']);
            }
        }

        foreach ($emailsByStore as $storeId => $emails) {
            $uniqueEmails = array_unique($emails);

            // Remove order-derived placeholders
            SyncedCustomer::where('store_id', $storeId)
                ->where('external_id', 'LIKE', 'order-derived-%')
                ->whereIn(DB::raw('lower(email)'), $uniqueEmails)
                ->forceDelete();

            // Step 4: Remove existing records with the same email but DIFFERENT external_id.
            // This handles the case where a customer re-registered on Nuvemshop with a new ID
            // but the same email. The new record from the API replaces the old one.
            $externalIdsByEmail = [];
            foreach ($finalCustomers as $customer) {
                if (! empty($customer['email']) && $customer['store_id'] === $storeId) {
                    $externalIdsByEmail[strtolower($customer['email'])] = $customer['external_id'];
                }
            }

            foreach (array_chunk(array_keys($externalIdsByEmail), 500) as $emailChunk) {
                $existing = SyncedCustomer::where('store_id', $storeId)
                    ->whereIn(DB::raw('lower(email)'), $emailChunk)
                    ->whereNull('deleted_at')
                    ->get(['id', 'email', 'external_id']);

                foreach ($existing as $record) {
                    $expectedExternalId = $externalIdsByEmail[strtolower($record->email)] ?? null;
                    if ($expectedExternalId && $record->external_id !== $expectedExternalId) {
                        $record->forceDelete();
                    }
                }
            }
        }

        SyncedCustomer::upsert(
            $finalCustomers,
            uniqueBy: ['store_id', 'external_id'],
            update: [
                'name',
                'email',
                'phone',
                'total_orders',
                'total_spent',
                'external_created_at',
                'updated_at',
            ]
        );
    }

    // ==========================================
    // BULK UPSERT METHODS - Coupons
    // ==========================================

    /**
     * Prepare coupon data for bulk upsert.
     */
    private function prepareCouponForBulkUpsert(Store $store, array $data): array
    {
        $couponData = $this->couponAdapter->transform($data);
        $now = now()->format('Y-m-d H:i:s');

        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $store->id,
            'external_id' => $couponData['external_id'],
            'code' => $couponData['code'],
            'type' => $couponData['type'],
            'value' => $couponData['value'],
            'valid' => $couponData['valid'],
            'used' => $couponData['used'],
            'max_uses' => $couponData['max_uses'],
            'start_date' => $this->formatDateTimeForDb($couponData['start_date']),
            'end_date' => $this->formatDateTimeForDb($couponData['end_date']),
            'min_price' => $couponData['min_price'],
            'categories' => json_encode($couponData['categories']),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Bulk upsert coupons using database upsert for performance.
     *
     * Deduplica cupons pelo par store_id+external_id antes do upsert
     * para evitar erro "ON CONFLICT DO UPDATE cannot affect row a second time"
     */
    private function bulkUpsertCoupons(array $coupons): void
    {
        if (empty($coupons)) {
            return;
        }

        // Deduplica cupons pelo par store_id+external_id, mantendo o último
        $uniqueCoupons = [];
        foreach ($coupons as $coupon) {
            $key = $coupon['store_id'].'_'.$coupon['external_id'];
            $uniqueCoupons[$key] = $coupon;
        }
        $coupons = array_values($uniqueCoupons);

        SyncedCoupon::upsert(
            $coupons,
            uniqueBy: ['store_id', 'external_id'],
            update: [
                'code',
                'type',
                'value',
                'valid',
                'used',
                'max_uses',
                'start_date',
                'end_date',
                'min_price',
                'categories',
                'updated_at',
            ]
        );
    }
}
