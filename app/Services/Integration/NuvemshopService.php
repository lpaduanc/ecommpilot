<?php

namespace App\Services\Integration;

use App\Enums\Platform;
use App\Models\Store;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class NuvemshopService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $apiBaseUrl = 'https://api.nuvemshop.com.br/v1';
    private string $authUrl = 'https://www.nuvemshop.com.br/apps/authorize';

    public function __construct()
    {
        $this->clientId = config('services.nuvemshop.client_id') ?? '';
        $this->clientSecret = config('services.nuvemshop.client_secret') ?? '';
        $this->redirectUri = config('services.nuvemshop.redirect_uri') ?? url('/api/integrations/nuvemshop/callback');
    }

    public function getAuthorizationUrl(int $userId): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'read_products write_products read_orders read_customers',
            'redirect_uri' => $this->redirectUri,
            'state' => $userId,
        ]);

        return "{$this->authUrl}/token?{$params}";
    }

    public function handleCallback(string $code, int $userId): Store
    {
        // Exchange code for token
        $response = Http::post("{$this->apiBaseUrl}/authorize", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Falha ao autenticar com Nuvemshop.');
        }

        $data = $response->json();

        // Get store info
        $storeInfo = $this->getStoreInfo($data['access_token'], $data['user_id']);

        // Create or update store
        return Store::updateOrCreate(
            [
                'platform' => Platform::Nuvemshop,
                'external_store_id' => $data['user_id'],
            ],
            [
                'user_id' => $userId,
                'name' => $storeInfo['name'] ?? 'Loja Nuvemshop',
                'domain' => $storeInfo['domain'] ?? null,
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
            ->get("{$this->apiBaseUrl}/{$storeId}/store");

        return $response->successful() ? $response->json() : [];
    }

    private function makeRequest(Store $store, string $method, string $endpoint, array $params = []): array
    {
        $response = Http::withToken($store->access_token)
            ->{strtolower($method)}("{$this->apiBaseUrl}{$endpoint}", $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Nuvemshop API error: {$response->status()}");
        }

        return $response->json();
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

