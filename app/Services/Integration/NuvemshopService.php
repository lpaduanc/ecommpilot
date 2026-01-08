<?php

namespace App\Services\Integration;

use App\Contracts\ProductAdapterInterface;
use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Store;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use App\Models\SystemSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class NuvemshopService
{
    private string $clientId;

    private string $clientSecret;

    private string $redirectUri;

    private string $apiBaseUrl = 'https://api.nuvemshop.com.br/v1';

    private string $authUrl = 'https://www.nuvemshop.com.br/apps/authorize';

    /**
     * Número máximo de requisições por minuto por loja
     */
    private const RATE_LIMIT_PER_MINUTE = 60;

    /**
     * Product adapter for transforming Nuvemshop data to SyncedProduct structure.
     */
    private ProductAdapterInterface $productAdapter;

    public function __construct(?ProductAdapterInterface $productAdapter = null)
    {
        // Try SystemSetting first, fallback to config
        $this->clientId = SystemSetting::get('nuvemshop.client_id')
            ?? config('services.nuvemshop.client_id')
            ?? '';

        $this->clientSecret = SystemSetting::get('nuvemshop.client_secret')
            ?? config('services.nuvemshop.client_secret')
            ?? '';

        $this->redirectUri = config('services.nuvemshop.redirect_uri')
            ?? url('/api/integrations/nuvemshop/callback');

        // Use provided adapter or default to NuvemshopProductAdapter
        $this->productAdapter = $productAdapter ?? new NuvemshopProductAdapter;
    }

    public function getAuthorizationUrl(int $userId, ?string $storeUrl = null): string
    {
        // Encode userId and storeUrl in state parameter
        $stateData = [
            'user_id' => $userId,
            'store_url' => $storeUrl,
        ];
        $state = base64_encode(json_encode($stateData));

        $params = http_build_query([
            'response_type' => 'code',
            'scope' => 'read_products write_products read_orders read_customers',
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
     * Decode state parameter from OAuth callback.
     */
    public function decodeState(string $state): array
    {
        $decoded = json_decode(base64_decode($state), true);

        return [
            'user_id' => $decoded['user_id'] ?? null,
            'store_url' => $decoded['store_url'] ?? null,
        ];
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

    public function syncProducts(Store $store): void
    {
        $page = 1;
        $perPage = 200;

        do {
            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/products", [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($response as $product) {
                $this->upsertProduct($store, $product);
            }

            $page++;
        } while (count($response) === $perPage);
    }

    public function syncOrders(Store $store): void
    {
        $page = 1;
        $perPage = 200;

        do {
            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/orders", [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($response as $order) {
                $this->upsertOrder($store, $order);
            }

            $page++;
        } while (count($response) === $perPage);
    }

    public function syncCustomers(Store $store): void
    {
        $page = 1;
        $perPage = 200;

        do {
            $response = $this->makeRequest($store, 'GET', "/{$store->external_store_id}/customers", [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($response as $customer) {
                $this->upsertCustomer($store, $customer);
            }

            $page++;
        } while (count($response) === $perPage);
    }

    private function getStoreInfo(string $accessToken, string $storeId): array
    {
        // Nuvemshop uses non-standard auth header: "Authentication: bearer {token}"
        $response = Http::withHeaders([
            'Authentication' => 'bearer '.$accessToken,
            'User-Agent' => 'EcommPilot (contact@ecommpilot.com)',
        ])
            ->timeout(30)
            ->retry(3, 100, function ($exception) {
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
        // Rate limiting por loja
        $rateLimitKey = "nuvemshop_api:{$store->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            Log::warning("Rate limit atingido para loja {$store->id}, aguardando {$seconds}s");
            sleep(min($seconds, 60));
        }

        RateLimiter::hit($rateLimitKey, 60);

        $maxAttempts = 2; // 1 tentativa inicial + 1 retry após refresh
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                // Refresh the store model to get the latest token
                $store->refresh();

                // Nuvemshop uses non-standard auth header: "Authentication: bearer {token}"
                // NOT the standard "Authorization: Bearer {token}"
                $response = Http::withHeaders([
                    'Authentication' => 'bearer '.$store->access_token,
                    'User-Agent' => 'EcommPilot (contact@ecommpilot.com)',
                ])
                    ->timeout(30)
                    ->retry(3, 100, function ($exception) {
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
     * Insert or update a product using the product adapter.
     *
     * This method uses the ProductAdapter to transform external Nuvemshop data
     * into the normalized structure required by SyncedProduct model.
     *
     * @param  Store  $store  The store the product belongs to
     * @param  array  $data  Raw product data from Nuvemshop API
     */
    private function upsertProduct(Store $store, array $data): void
    {
        // Transform external data using the adapter
        $productData = $this->productAdapter->transform($data);

        // Update or create the product
        SyncedProduct::updateOrCreate(
            [
                'store_id' => $store->id,
                'external_id' => $productData['external_id'],
            ],
            array_merge(['store_id' => $store->id], $productData)
        );

        Log::debug('Product synced successfully', [
            'store_id' => $store->id,
            'product_id' => $productData['external_id'],
            'product_name' => $productData['name'],
        ]);
    }

    private function upsertOrder(Store $store, array $data): void
    {
        $items = collect($data['products'] ?? [])->map(fn ($item) => [
            'product_id' => $item['product_id'] ?? null,
            'name' => $item['name'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0,
        ])->toArray();

        // Sanitize numeric fields - Nuvemshop may return strings like "table_default"
        $shipping = $data['shipping'] ?? 0;
        $shipping = is_numeric($shipping) ? (float) $shipping : 0;

        $subtotal = $data['subtotal'] ?? 0;
        $subtotal = is_numeric($subtotal) ? (float) $subtotal : 0;

        $discount = $data['discount'] ?? 0;
        $discount = is_numeric($discount) ? (float) $discount : 0;

        $total = $data['total'] ?? 0;
        $total = is_numeric($total) ? (float) $total : 0;

        SyncedOrder::updateOrCreate(
            [
                'store_id' => $store->id,
                'external_id' => $data['id'],
            ],
            [
                'order_number' => $data['number'] ?? $data['id'],
                'status' => $this->mapOrderStatus($data['status'] ?? 'open'),
                'payment_status' => $this->mapPaymentStatus($data['payment_status'] ?? 'pending'),
                'shipping_status' => $data['shipping_status'] ?? null,
                'customer_name' => $data['customer']['name'] ?? 'Desconhecido',
                'customer_email' => $data['customer']['email'] ?? null,
                'customer_phone' => $data['customer']['phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => $shipping,
                'total' => $total,
                'payment_method' => $data['payment_details']['method'] ?? null,
                'items' => $items,
                'shipping_address' => $data['shipping_address'] ?? null,
                'external_created_at' => $data['created_at'] ?? null,
            ]
        );
    }

    private function upsertCustomer(Store $store, array $data): void
    {
        SyncedCustomer::updateOrCreate(
            [
                'store_id' => $store->id,
                'external_id' => $data['id'],
            ],
            [
                'name' => $data['name'] ?? 'Desconhecido',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'total_orders' => $data['total_orders'] ?? 0,
                'total_spent' => $data['total_spent'] ?? 0,
                'external_created_at' => $data['created_at'] ?? null,
            ]
        );
    }

    private function mapOrderStatus(string $status): string
    {
        return match ($status) {
            'open', 'pending' => 'pending',
            'closed', 'paid' => 'paid',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'pending', 'authorized' => 'pending',
            'paid' => 'paid',
            'refunded', 'voided' => 'refunded',
            default => 'pending',
        };
    }
}
