<?php

namespace App\Services\Integration;

use App\Contracts\CouponAdapterInterface;
use App\Contracts\OrderAdapterInterface;
use App\Contracts\ProductAdapterInterface;
use App\Enums\Platform;
use App\Enums\SyncStatus;
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
     * Nuvemshop permite 60/min, usamos 50 para dar margem de segurança.
     */
    private const RATE_LIMIT_PER_MINUTE = 50;

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

        $params = http_build_query([
            'response_type' => 'code',
            'scope' => 'read_products write_products read_orders read_customers read_coupons',
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ]);

        // If store URL is provided, use it to build the auth URL
        // Format: https://{storeUrl}/admin/apps/{clientId}/authorize
        if ($storeUrl) {
            $authUrl = "https://{$storeUrl}/admin/apps/{$this->clientId}/authorize";

            return "{$authUrl}?{$params}";
        }

        // Fallback to generic Nuvemshop auth URL
        return "{$this->authUrl}?{$params}";
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

        Log::info('Creating/updating store from Nuvemshop callback', [
            'user_id' => $userId,
            'external_store_id' => $data['user_id'],
            'store_url_provided' => $storeUrl,
            'domain_from_api' => $storeInfo['domain'] ?? null,
            'final_domain' => $domain,
        ]);

        // Create or update store
        // When reconnecting, clear token_requires_reconnection flag and reset sync_status
        return Store::updateOrCreate(
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
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_requires_reconnection' => false,
                'sync_status' => SyncStatus::Pending,
                'metadata' => $storeInfo,
            ]
        );
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

        Log::info("Customers sync completed for store {$store->id}", [
            'total_synced' => $totalSynced,
            'incremental' => $updatedSince !== null,
        ]);
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
        // Rate limiting por loja (50 req/min para margem de segurança)
        $rateLimitKey = "nuvemshop_api:{$store->id}";

        // Aguarda se atingiu rate limit
        while (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            Log::channel('sync')->info("[RATE-LIMIT] Aguardando {$seconds}s para loja {$store->id} (limite: ".self::RATE_LIMIT_PER_MINUTE.'/min)');
            sleep($seconds + 1); // Espera o tempo completo + 1s de buffer
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

                // Se recebeu 401 e ainda tem tentativas, tenta refresh do token
                if ($response->status() === 401 && $attempt < $maxAttempts) {
                    Log::info("Token expirado para loja {$store->id}, tentando renovar...");

                    if ($this->refreshAccessToken($store)) {
                        Log::info("Token renovado com sucesso para loja {$store->id}, retentando requisição...");

                        continue; // Retry the request with new token
                    }

                    // Se falhou o refresh, não adianta tentar novamente
                    Log::error("Falha ao renovar token para loja {$store->id}");
                    break;
                }

                // Para qualquer outro erro ou sucesso, processa normalmente
                if (! $response->successful()) {
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
     * Handles token invalidation according to Nuvemshop's OAuth model.
     *
     * IMPORTANT: According to Nuvemshop's official documentation, access tokens
     * DO NOT EXPIRE. They only become invalid when:
     * 1. A new token is generated (invalidates the previous one)
     * 2. The user uninstalls the app
     *
     * Since Nuvemshop does not support refresh_token grant type, when we receive
     * a 401 error, it means the user needs to reconnect the app through the full
     * OAuth flow. This method marks the store as requiring reconnection.
     *
     * @param  Store  $store  The store whose token has been invalidated
     * @return bool Always returns false to indicate automatic refresh is not possible
     */
    private function refreshAccessToken(Store $store): bool
    {
        Log::warning('Token invalidado detectado para loja. Nuvemshop requer reconexão completa via OAuth.', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'platform' => $store->platform->value,
        ]);

        // Mark store as requiring reconnection
        // This will trigger UI notification for the user to reconnect
        $store->markAsTokenExpired();

        Log::info('Loja marcada como token_expired. Usuário precisa reconectar.', [
            'store_id' => $store->id,
            'sync_status' => $store->sync_status->value,
            'token_requires_reconnection' => $store->token_requires_reconnection,
        ]);

        // Return false to indicate that automatic token refresh is not possible
        // The calling code should stop attempting the request
        return false;
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
     * Deduplica clientes pelo par store_id+external_id antes do upsert
     * para evitar erro "ON CONFLICT DO UPDATE cannot affect row a second time"
     */
    private function bulkUpsertCustomers(array $customers): void
    {
        if (empty($customers)) {
            return;
        }

        // Deduplica clientes pelo par store_id+external_id, mantendo o último
        $uniqueCustomers = [];
        foreach ($customers as $customer) {
            $key = $customer['store_id'].'_'.$customer['external_id'];
            $uniqueCustomers[$key] = $customer;
        }
        $customers = array_values($uniqueCustomers);

        SyncedCustomer::upsert(
            $customers,
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
