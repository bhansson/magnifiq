<?php

namespace Database\Factories;

use App\Models\PhotoStudioGeneration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoStudioGeneration>
 */
class PhotoStudioGenerationFactory extends Factory
{
    protected $model = PhotoStudioGeneration::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'product_id' => null,
            'source_type' => 'prompt_only',
            'source_reference' => null,
            'prompt' => $this->faker->sentence(20),
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/test/' . $this->faker->uuid . '.jpg',
            'image_width' => 1024,
            'image_height' => 1024,
            'response_id' => $this->faker->uuid,
            'response_model' => 'google/gemini-2.5-flash-image',
            'response_metadata' => [],
            'product_ai_job_id' => null,
        ];
    }
}
