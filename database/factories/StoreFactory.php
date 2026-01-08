<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => Platform::Nuvemshop,
            'external_store_id' => fake()->unique()->numerify('######'),
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'email' => fake()->companyEmail(),
            'access_token' => 'test_access_token_' . Str::random(40),
            'refresh_token' => 'test_refresh_token_' . Str::random(40),
            'sync_status' => SyncStatus::Pending,
            'last_sync_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the store has been synced successfully.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => SyncStatus::Completed,
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Indicate that the store is currently syncing.
     */
    public function syncing(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => SyncStatus::Syncing,
        ]);
    }

    /**
     * Indicate that the store sync has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => SyncStatus::Failed,
        ]);
    }

    /**
     * Create a store for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
