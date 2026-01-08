<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Models\SyncedOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncedOrder>
 */
class SyncedOrderFactory extends Factory
{
    protected $model = SyncedOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 1000);
        $discount = fake()->randomFloat(2, 0, $subtotal * 0.2);
        $shipping = fake()->randomFloat(2, 0, 50);
        $total = $subtotal - $discount + $shipping;

        return [
            'store_id' => Store::factory(),
            'external_id' => fake()->unique()->numerify('######'),
            'order_number' => fake()->unique()->numerify('#####'),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'shipping_status' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'payment_method' => fake()->randomElement(['credit_card', 'debit_card', 'pix', 'boleto']),
            'items' => [
                [
                    'product_id' => fake()->numerify('######'),
                    'name' => fake()->words(3, true),
                    'quantity' => fake()->numberBetween(1, 5),
                    'price' => fake()->randomFloat(2, 10, 200),
                ],
            ],
            'shipping_address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'zip_code' => fake()->postcode(),
                'country' => 'BR',
            ],
            'external_created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Indicate that the order is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'status' => OrderStatus::Paid,
        ]);
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
        ]);
    }

    /**
     * Indicate that the order is shipped.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Shipped,
            'payment_status' => PaymentStatus::Paid,
            'shipping_status' => 'shipped',
        ]);
    }

    /**
     * Indicate that the order is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'shipping_status' => 'delivered',
        ]);
    }

    /**
     * Create an order for a specific store.
     */
    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'store_id' => $store->id,
        ]);
    }

    /**
     * Create an order with a specific total.
     */
    public function withTotal(float $total): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal' => $total,
            'discount' => 0,
            'shipping' => 0,
            'total' => $total,
        ]);
    }
}
