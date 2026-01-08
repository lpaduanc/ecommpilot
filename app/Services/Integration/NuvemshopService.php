<?php

namespace App\Services\Integration;

use App\Enums\Platform;
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

    public function __construct()
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
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->retry(3, 100, function ($exception) {
                return $this->shouldRetry($exception);
            })
            ->get("{$this->apiBaseUrl}/{$storeId}/store");

        return $response->successful() ? $response->json() : [];
    }

    /**
     * Faz uma requisição à API com retry, rate limiting e token refresh
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

        try {
            $response = Http::withToken($store->access_token)
                ->timeout(30)
                ->retry(3, 100, function ($exception, $request) use ($store) {
                    // Tenta refresh do token em caso de 401
                    if ($exception instanceof RequestException && $exception->response->status() === 401) {
                        return $this->tryRefreshToken($store, $request);
                    }

                    return $this->shouldRetry($exception);
                }, throw: false)
                ->{strtolower($method)}("{$this->apiBaseUrl}{$endpoint}", $params);

            if (! $response->successful()) {
                $this->handleApiError($response, $store, $endpoint);
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Nuvemshop API request failed', [
                'store_id' => $store->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
     * Tenta atualizar o token de acesso
     */
    private function tryRefreshToken(Store $store, $request): bool
    {
        if (empty($store->refresh_token)) {
            Log::warning("Token expirado e sem refresh_token para loja {$store->id}");

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post("{$this->authUrl}/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $store->refresh_token,
                    'grant_type' => 'refresh_token',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $store->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $store->refresh_token,
                ]);

                // Atualiza o token no request para retry
                $request->withToken($data['access_token']);

                Log::info("Token atualizado com sucesso para loja {$store->id}");

                return true;
            }
        } catch (\Exception $e) {
            Log::error("Falha ao atualizar token para loja {$store->id}: {$e->getMessage()}");
        }

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

    private function upsertProduct(Store $store, array $data): void
    {
        $images = collect($data['images'] ?? [])->pluck('src')->toArray();
        $categories = collect($data['categories'] ?? [])->pluck('name')->toArray();

        SyncedProduct::updateOrCreate(
            [
                'store_id' => $store->id,
                'external_id' => $data['id'],
            ],
            [
                'name' => $data['name']['pt'] ?? $data['name']['en'] ?? 'Sem nome',
                'description' => $data['description']['pt'] ?? $data['description']['en'] ?? null,
                'price' => $data['variants'][0]['price'] ?? 0,
                'compare_at_price' => $data['variants'][0]['compare_at_price'] ?? null,
                'stock_quantity' => $data['variants'][0]['stock'] ?? 0,
                'sku' => $data['variants'][0]['sku'] ?? null,
                'images' => $images,
                'categories' => $categories,
                'variants' => $data['variants'] ?? [],
                'is_active' => $data['published'] ?? true,
                'external_created_at' => $data['created_at'] ?? null,
                'external_updated_at' => $data['updated_at'] ?? null,
            ]
        );
    }

    private function upsertOrder(Store $store, array $data): void
    {
        $items = collect($data['products'] ?? [])->map(fn ($item) => [
            'product_id' => $item['product_id'] ?? null,
            'name' => $item['name'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0,
        ])->toArray();

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
                'subtotal' => $data['subtotal'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'shipping' => $data['shipping'] ?? 0,
                'total' => $data['total'] ?? 0,
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
