<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAiJob>
 */
class ProductAiJobFactory extends Factory
{
    protected $model = ProductAiJob::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'product_id' => Product::factory(),
            'sku' => (string) fake()->unique()->numberBetween(1000, 999999),
            'product_ai_template_id' => ProductAiTemplate::factory(),
            'job_type' => ProductAiJob::TYPE_TEMPLATE,
            'status' => ProductAiJob::STATUS_QUEUED,
            'progress' => 0,
            'attempts' => 0,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
            'meta' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductAiJob::STATUS_COMPLETED,
            'progress' => 100,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductAiJob::STATUS_PROCESSING,
            'progress' => fake()->numberBetween(10, 90),
            'started_at' => now()->subMinutes(2),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductAiJob::STATUS_FAILED,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'last_error' => 'Test error message',
        ]);
    }
}
