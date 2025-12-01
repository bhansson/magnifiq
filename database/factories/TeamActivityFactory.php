<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamActivity>
 */
class TeamActivityFactory extends Factory
{
    protected $model = TeamActivity::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'type' => TeamActivity::TYPE_JOB_COMPLETED,
            'subject_type' => null,
            'subject_id' => null,
            'properties' => [],
        ];
    }

    public function jobCompleted(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_JOB_COMPLETED,
            'properties' => [
                'job_type' => 'template',
                'product_title' => fake()->words(3, true),
            ],
        ]);
    }

    public function jobFailed(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_JOB_FAILED,
            'properties' => [
                'job_type' => 'photo_studio',
                'product_title' => fake()->words(3, true),
                'error' => 'Test error message',
            ],
        ]);
    }

    public function feedImported(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_FEED_IMPORTED,
            'properties' => [
                'feed_name' => fake()->domainName(),
                'product_count' => fake()->numberBetween(10, 500),
            ],
        ]);
    }

    public function feedRefreshed(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_FEED_REFRESHED,
            'properties' => [
                'feed_name' => fake()->domainName(),
            ],
        ]);
    }

    public function photoStudioGenerated(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_PHOTO_STUDIO_GENERATED,
            'properties' => [
                'product_title' => fake()->words(3, true),
                'model' => 'google/gemini-2.5-flash-image',
            ],
        ]);
    }

    public function memberAdded(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_TEAM_MEMBER_ADDED,
            'properties' => [
                'member_name' => fake()->name(),
            ],
        ]);
    }

    public function memberRemoved(): static
    {
        return $this->state(fn () => [
            'type' => TeamActivity::TYPE_TEAM_MEMBER_REMOVED,
            'properties' => [
                'member_name' => fake()->name(),
            ],
        ]);
    }
}
