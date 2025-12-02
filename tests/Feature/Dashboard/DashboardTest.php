<?php

use App\Livewire\Dashboard;
use App\Models\ProductAiJob;
use App\Models\TeamActivity;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard page is accessible', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Dashboard');
});

test('dashboard displays job statistics', function () {
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
});

test('dashboard displays recent jobs', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    ProductAiJob::factory()->create([
        'team_id' => $team->id,
        'status' => ProductAiJob::STATUS_COMPLETED,
    ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('Recent AI Jobs');
});

test('dashboard displays team activity', function () {
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
});

test('dashboard only shows own team activity', function () {
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
});

test('dashboard only shows own team jobs', function () {
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
});