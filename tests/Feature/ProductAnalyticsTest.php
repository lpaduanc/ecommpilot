<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use App\Models\User;
use App\Services\ProductAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private ProductAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductAnalyticsService;

        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        // Create user with store
        $this->user = User::factory()->create([
            'role' => UserRole::Client,
        ]);

        // Assign client role with permissions
        $this->user->assignRole('client');

        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'platform' => Platform::Nuvemshop,
        ]);

        $this->user->update(['active_store_id' => $this->store->id]);
    }

    public function test_calculates_units_sold_correctly(): void
    {
        // Create product
        $product = SyncedProduct::factory()->create([
            'store_id' => $this->store->id,
            'external_id' => '123',
            'name' => 'Test Product',
            'price' => 100.00,
            'cost' => 50.00,
            'stock_quantity' => 100,
        ]);

        // Create orders with this product
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [
                ['product_id' => '123', 'name' => 'Test Product', 'quantity' => 5, 'price' => 100],
            ],
            'external_created_at' => now()->subDays(10),
        ]);

        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [
                ['product_id' => '123', 'name' => 'Test Product', 'quantity' => 3, 'price' => 100],
            ],
            'external_created_at' => now()->subDays(5),
        ]);

        // Calculate analytics
        $result = $this->service->calculateProductAnalytics($this->store, collect([$product]));

        $analytics = $result['products'][$product->id];

        $this->assertEquals(8, $analytics['units_sold']);
        $this->assertEquals(800.00, $analytics['total_sold']);
    }

    public function test_calculates_conversion_rate_correctly(): void
    {
        // Create product
        $product = SyncedProduct::factory()->create([
            'store_id' => $this->store->id,
            'external_id' => '123',
            'name' => 'Test Product',
        ]);

        // Create 10 orders, only 3 contain the product
        for ($i = 0; $i < 10; $i++) {
            $items = $i < 3
                ? [['product_id' => '123', 'name' => 'Test Product', 'quantity' => 1, 'price' => 100]]
                : [['product_id' => '999', 'name' => 'Other Product', 'quantity' => 1, 'price' => 50]];

            SyncedOrder::factory()->create([
                'store_id' => $this->store->id,
                'payment_status' => PaymentStatus::Paid,
                'items' => $items,
                'external_created_at' => now()->subDays(5),
            ]);
        }

        // Calculate analytics
        $result = $this->service->calculateProductAnalytics($this->store, collect([$product]));

        $analytics = $result['products'][$product->id];

        // Conversion rate should be 30% (3 out of 10 orders)
        $this->assertEquals(30.0, $analytics['conversion_rate']);
        $this->assertEquals(3, $analytics['orders_with_product']);
    }

    public function test_calculates_profit_and_margin_correctly(): void
    {
        // Create product with cost
        $product = SyncedProduct::factory()->create([
            'store_id' => $this->store->id,
            'external_id' => '123',
            'name' => 'Test Product',
            'price' => 100.00,
            'cost' => 60.00,
            'stock_quantity' => 100,
        ]);

        // Create order
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [
                ['product_id' => '123', 'name' => 'Test Product', 'quantity' => 10, 'price' => 100],
            ],
            'external_created_at' => now()->subDays(5),
        ]);

        // Calculate analytics
        $result = $this->service->calculateProductAnalytics($this->store, collect([$product]));

        $analytics = $result['products'][$product->id];

        // Total sold: 10 * 100 = 1000
        // Total cost: 10 * 60 = 600
        // Total profit: 1000 - 600 = 400
        // Margin: (400 / 1000) * 100 = 40%
        $this->assertEquals(1000.00, $analytics['total_sold']);
        $this->assertEquals(400.00, $analytics['total_profit']);
        $this->assertEquals(40.0, $analytics['margin']);
    }

    public function test_calculates_stock_health_correctly(): void
    {
        // Create products with different stock levels
        $products = collect([
            // High stock health (> 30 days)
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '1',
                'name' => 'High Stock Product',
                'stock_quantity' => 100,
            ]),
            // Adequate stock health (14-30 days)
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '2',
                'name' => 'Adequate Stock Product',
                'stock_quantity' => 50,
            ]),
            // Low stock health (7-14 days)
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '3',
                'name' => 'Low Stock Product',
                'stock_quantity' => 20,
            ]),
            // Critical stock health (< 7 days)
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '4',
                'name' => 'Critical Stock Product',
                'stock_quantity' => 5,
            ]),
        ]);

        // Create orders: each product sells ~2 units per day (60 units in 30 days)
        foreach ($products as $index => $product) {
            for ($i = 0; $i < 30; $i++) {
                SyncedOrder::factory()->create([
                    'store_id' => $this->store->id,
                    'payment_status' => PaymentStatus::Paid,
                    'items' => [
                        [
                            'product_id' => (string) $product->external_id,
                            'name' => $product->name,
                            'quantity' => 2,
                            'price' => 100,
                        ],
                    ],
                    'external_created_at' => now()->subDays($i),
                ]);
            }
        }

        // Calculate analytics
        $result = $this->service->calculateProductAnalytics($this->store, $products, 30);

        // Product 1: 100 stock / 2 per day = 50 days -> Alto
        $this->assertEquals('Alto', $result['products'][$products[0]->id]['stock_health']);

        // Product 2: 50 stock / 2 per day = 25 days -> Adequado
        $this->assertEquals('Adequado', $result['products'][$products[1]->id]['stock_health']);

        // Product 3: 20 stock / 2 per day = 10 days -> Baixo
        $this->assertEquals('Baixo', $result['products'][$products[2]->id]['stock_health']);

        // Product 4: 5 stock / 2 per day = 2.5 days -> Crítico
        $this->assertEquals('Crítico', $result['products'][$products[3]->id]['stock_health']);
    }

    public function test_assigns_abc_categories_correctly(): void
    {
        // Create products with varying sales
        $products = collect([
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '1',
                'name' => 'Product A1',
            ]),
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '2',
                'name' => 'Product A2',
            ]),
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '3',
                'name' => 'Product B',
            ]),
            SyncedProduct::factory()->create([
                'store_id' => $this->store->id,
                'external_id' => '4',
                'name' => 'Product C',
            ]),
        ]);

        // Create orders with different quantities to simulate ABC pattern
        // Product 1: 400 units (40% of revenue)
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [['product_id' => '1', 'name' => 'Product A1', 'quantity' => 400, 'price' => 100]],
            'external_created_at' => now()->subDays(10),
        ]);

        // Product 2: 400 units (40% of revenue)
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [['product_id' => '2', 'name' => 'Product A2', 'quantity' => 400, 'price' => 100]],
            'external_created_at' => now()->subDays(10),
        ]);

        // Product 3: 150 units (15% of revenue)
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [['product_id' => '3', 'name' => 'Product B', 'quantity' => 150, 'price' => 100]],
            'external_created_at' => now()->subDays(10),
        ]);

        // Product 4: 50 units (5% of revenue)
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [['product_id' => '4', 'name' => 'Product C', 'quantity' => 50, 'price' => 100]],
            'external_created_at' => now()->subDays(10),
        ]);

        // Calculate analytics
        $result = $this->service->calculateProductAnalytics($this->store, $products);

        // Products 1 and 2 should be category A (80% of sales)
        $this->assertEquals('A', $result['products'][$products[0]->id]['abc_category']);
        $this->assertEquals('A', $result['products'][$products[1]->id]['abc_category']);

        // Product 3 should be category B (next 15%)
        $this->assertEquals('B', $result['products'][$products[2]->id]['abc_category']);

        // Product 4 should be category C (last 5%)
        $this->assertEquals('C', $result['products'][$products[3]->id]['abc_category']);
    }

    public function test_api_returns_product_analytics(): void
    {
        // Create product
        $product = SyncedProduct::factory()->create([
            'store_id' => $this->store->id,
            'external_id' => '123',
            'name' => 'Test Product',
            'price' => 100.00,
            'cost' => 50.00,
        ]);

        // Create order
        SyncedOrder::factory()->create([
            'store_id' => $this->store->id,
            'payment_status' => PaymentStatus::Paid,
            'items' => [
                ['product_id' => '123', 'name' => 'Test Product', 'quantity' => 5, 'price' => 100],
            ],
            'external_created_at' => now()->subDays(5),
        ]);

        // Make API request
        $response = $this->actingAs($this->user)
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'cost',
                        'stock_quantity',
                        'analytics' => [
                            'units_sold',
                            'conversion_rate',
                            'total_sold',
                            'sales_percentage',
                            'total_profit',
                            'margin',
                            'abc_category',
                            'stock_health',
                            'days_of_stock',
                        ],
                    ],
                ],
                'total',
                'last_page',
                'current_page',
                'totals' => [
                    'total_products',
                    'total_units_sold',
                    'total_revenue',
                    'total_profit',
                    'average_margin',
                ],
                'abc_analysis' => [
                    'category_a',
                    'category_b',
                    'category_c',
                ],
            ]);

        // Verify analytics data
        $data = $response->json('data.0');
        $this->assertEquals(5, $data['analytics']['units_sold']);
        $this->assertEquals(500.00, $data['analytics']['total_sold']);
    }
}
