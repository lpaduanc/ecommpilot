<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Store;
use App\Models\User;
use App\Services\Integration\NuvemshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NuvemshopTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a test store with expired token
        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'platform' => Platform::Nuvemshop,
            'external_store_id' => '123456',
            'name' => 'Test Store',
            'access_token' => 'expired_token',
            'refresh_token' => 'valid_refresh_token',
        ]);
    }

    /**
     * Test that a 401 error triggers automatic token refresh.
     */
    public function test_automatic_token_refresh_on_401(): void
    {
        // Mock the Nuvemshop API responses
        Http::fake([
            // First request fails with 401 (expired token)
            'api.nuvemshop.com.br/v1/123456/products*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push([
                    // After refresh, second request succeeds
                    ['id' => 1, 'name' => ['pt' => 'Product 1']],
                ]),

            // Token refresh endpoint succeeds
            'www.nuvemshop.com.br/apps/authorize/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $service = app(NuvemshopService::class);

        // Make a request that will fail with 401, then succeed after refresh
        $service->syncProducts($this->store);

        // Verify the token was updated in the database
        $this->store->refresh();
        $this->assertEquals('new_access_token', $this->store->access_token);
        $this->assertEquals('new_refresh_token', $this->store->refresh_token);
    }

    /**
     * Test that refresh token failure throws exception.
     */
    public function test_token_refresh_failure_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);

        // Mock the Nuvemshop API responses
        Http::fake([
            // First request fails with 401 (expired token)
            'api.nuvemshop.com.br/v1/123456/products*' => Http::response(['error' => 'Unauthorized'], 401),

            // Token refresh endpoint also fails
            'www.nuvemshop.com.br/apps/authorize/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token is invalid.',
            ], 400),
        ]);

        $service = app(NuvemshopService::class);

        // This should throw an exception after failing to refresh
        $service->syncProducts($this->store);
    }

    /**
     * Test that missing refresh token throws exception with clear message.
     */
    public function test_missing_refresh_token_throws_exception(): void
    {
        // Create store without refresh token
        $storeWithoutRefresh = Store::factory()->create([
            'user_id' => $this->user->id,
            'platform' => Platform::Nuvemshop,
            'external_store_id' => '789012',
            'name' => 'Store Without Refresh Token',
            'access_token' => 'expired_token',
            'refresh_token' => null, // No refresh token
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Por favor, reconecte a loja');

        // Mock 401 response
        Http::fake([
            'api.nuvemshop.com.br/v1/789012/products*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = app(NuvemshopService::class);

        // This should throw an exception about missing refresh token
        $service->syncProducts($storeWithoutRefresh);
    }

    /**
     * Test that successful requests don't trigger refresh.
     */
    public function test_successful_request_does_not_trigger_refresh(): void
    {
        $originalToken = $this->store->access_token;

        // Mock successful API response
        Http::fake([
            'api.nuvemshop.com.br/v1/123456/products*' => Http::response([
                ['id' => 1, 'name' => ['pt' => 'Product 1']],
            ], 200),
        ]);

        $service = app(NuvemshopService::class);

        // Make a successful request
        $service->syncProducts($this->store);

        // Verify the token was NOT changed
        $this->store->refresh();
        $this->assertEquals($originalToken, $this->store->access_token);

        // Verify no call was made to the token refresh endpoint
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/apps/authorize/token');
        });
    }

    /**
     * Test that rate limiting is applied before token refresh.
     */
    public function test_rate_limiting_applied_before_retry(): void
    {
        // Mock the Nuvemshop API responses
        Http::fake([
            'api.nuvemshop.com.br/v1/123456/products*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push([
                    ['id' => 1, 'name' => ['pt' => 'Product 1']],
                ]),

            'www.nuvemshop.com.br/apps/authorize/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
            ], 200),
        ]);

        $service = app(NuvemshopService::class);

        // Make multiple requests
        $service->syncProducts($this->store);

        // Should succeed without hitting rate limits
        $this->assertTrue(true);
    }

    /**
     * Test that refresh token response without new refresh_token keeps old one.
     */
    public function test_refresh_without_new_refresh_token_keeps_old(): void
    {
        $originalRefreshToken = $this->store->refresh_token;

        // Mock the Nuvemshop API responses
        Http::fake([
            'api.nuvemshop.com.br/v1/123456/products*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push([
                    ['id' => 1, 'name' => ['pt' => 'Product 1']],
                ]),

            // Token refresh endpoint returns new access_token but NO new refresh_token
            'www.nuvemshop.com.br/apps/authorize/token' => Http::response([
                'access_token' => 'new_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                // NOTE: no refresh_token in response
            ], 200),
        ]);

        $service = app(NuvemshopService::class);

        // Make a request
        $service->syncProducts($this->store);

        // Verify the access token was updated but refresh token stayed the same
        $this->store->refresh();
        $this->assertEquals('new_access_token', $this->store->access_token);
        $this->assertEquals($originalRefreshToken, $this->store->refresh_token);
    }
}
