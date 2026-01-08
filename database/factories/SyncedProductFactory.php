<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\SyncedProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncedProduct>
 */
class SyncedProductFactory extends Factory
{
    protected $model = SyncedProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'external_id' => fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 500),
            'compare_at_price' => null,
            'stock_quantity' => fake()->numberBetween(0, 100),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'images' => [fake()->imageUrl(640, 480, 'products', true)],
            'categories' => [fake()->word()],
            'variants' => [],
            'is_active' => true,
            'external_created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'external_updated_at' => now(),
        ];
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Indicate that the product has low stock.
     */
    public function lowStock(int $quantity = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $quantity,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product has a discount.
     */
    public function withDiscount(float $discountPercent = 30): static
    {
        return $this->state(function (array $attributes) use ($discountPercent) {
            $price = $attributes['price'] ?? 100;

            return [
                'compare_at_price' => round($price / (1 - $discountPercent / 100), 2),
            ];
        });
    }

    /**
     * Create a product for a specific store.
     */
    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'store_id' => $store->id,
        ]);
    }
}
