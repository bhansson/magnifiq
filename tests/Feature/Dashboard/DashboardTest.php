<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard;
use App\Models\ProductAiJob;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_is_accessible(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Dashboard');
    }

    public function test_dashboard_displays_job_statistics(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        ProductAiJob::factory()->create([
            'team_id' => $team->id,
            'status' => ProductAiJob::STATUS_QUEUED,
        ]);
        ProductAiJob::factory()->create([
            'team_id' => $team->id,
            'status' => ProductAiJob::STATUS_PROCESSING,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Queued')
            ->assertSee('Processing');
    }

    public function test_dashboard_displays_recent_jobs(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        ProductAiJob::factory()->create([
            'team_id' => $team->id,
            'status' => ProductAiJob::STATUS_COMPLETED,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Recent AI Jobs');
    }

    public function test_dashboard_displays_team_activity(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        TeamActivity::factory()->feedImported()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'properties' => ['feed_name' => 'Test Feed', 'product_count' => 50],
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Team Activity')
            ->assertSee('Test Feed');
    }

    public function test_dashboard_only_shows_own_team_activity(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        TeamActivity::factory()->feedImported()->create([
            'team_id' => $otherUser->currentTeam->id,
            'user_id' => $otherUser->id,
            'properties' => ['feed_name' => 'Other Team Feed', 'product_count' => 100],
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertDontSee('Other Team Feed');
    }

    public function test_dashboard_only_shows_own_team_jobs(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        ProductAiJob::factory()->create([
            'team_id' => $otherUser->currentTeam->id,
            'status' => ProductAiJob::STATUS_QUEUED,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertViewHas('recentJobs', function ($jobs) {
                return $jobs->isEmpty();
            });
    }
}
