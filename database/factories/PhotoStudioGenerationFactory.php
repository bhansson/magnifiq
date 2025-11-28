<?php

namespace Database\Factories;

use App\Models\PhotoStudioGeneration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PhotoStudioGeneration>
 */
class PhotoStudioGenerationFactory extends Factory
{
    protected $model = PhotoStudioGeneration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'product_id' => null,
            'source_type' => 'uploaded_image',
            'source_reference' => 'test-image.jpg',
            'prompt' => $this->faker->sentence(10),
            'model' => 'test/model',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/1/'.now()->format('Y/m/d').'/'.$this->faker->uuid().'.jpg',
            'image_width' => 1024,
            'image_height' => 1024,
        ];
    }

    /**
     * Create a composition generation.
     */
    public function composition(string $mode = 'products_together'): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'composition',
            'composition_mode' => $mode,
            'source_references' => [
                ['type' => 'product', 'product_id' => 1, 'title' => 'Product 1', 'source_reference' => 'https://example.com/1.jpg'],
                ['type' => 'product', 'product_id' => 2, 'title' => 'Product 2', 'source_reference' => 'https://example.com/2.jpg'],
            ],
        ]);
    }

    /**
     * Create an edited generation (child of another).
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'edited_generation',
            'edit_instruction' => $this->faker->sentence(5),
        ]);
    }
}
