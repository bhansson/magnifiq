<?php

namespace Database\Factories;

use App\Models\StoreConnection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoreConnection>
 */
class StoreConnectionFactory extends Factory
{
    protected $model = StoreConnection::class;

    public function definition(): array
    {
        $storeName = $this->faker->unique()->slug(2);

        return [
            'team_id' => Team::factory(),
            'platform' => 'shopify',
            'name' => $this->faker->company().' Store',
            'store_identifier' => "{$storeName}.myshopify.com",
            'access_token' => 'shpat_'.$this->faker->sha256(),
            'scopes' => ['read_products', 'read_inventory'],
            'status' => StoreConnection::STATUS_CONNECTED,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreConnection::STATUS_PENDING,
            'access_token' => null,
        ]);
    }

    public function syncing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreConnection::STATUS_SYNCING,
        ]);
    }

    public function withError(string $message = 'Connection failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreConnection::STATUS_ERROR,
            'last_error' => $message,
        ]);
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subMinutes(5),
        ]);
    }
}
