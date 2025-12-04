<?php

use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin dashboard displays environment configuration', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('AI Models');
    $response->assertSee('Environment');
    $response->assertSee('Debug Mode');
    $response->assertSee('Text');
    $response->assertSee('Vision');
});

test('admin dashboard displays user statistics', function () {
    // Create users with different roles
    User::factory()->count(3)->create(['role' => 'user']);
    User::factory()->create(['role' => 'admin']);
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Users');
    $response->assertSee('total users');
    $response->assertSee('Superadmins');
    $response->assertSee('Admins');
});

test('admin dashboard displays team statistics', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Create customer and partner teams
    Team::factory()->count(2)->create(['type' => 'customer', 'user_id' => $superadmin->id]);
    Team::factory()->create(['type' => 'partner', 'user_id' => $superadmin->id]);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Teams');
    $response->assertSee('total teams');
    $response->assertSee('Customer teams');
    $response->assertSee('Partner teams');
});

test('admin dashboard displays ai job statistics', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Create some jobs with different statuses
    ProductAiJob::factory()->create([
        'team_id' => $superadmin->currentTeam->id,
        'user_id' => $superadmin->id,
        'status' => ProductAiJob::STATUS_COMPLETED,
    ]);
    ProductAiJob::factory()->create([
        'team_id' => $superadmin->currentTeam->id,
        'user_id' => $superadmin->id,
        'status' => ProductAiJob::STATUS_FAILED,
        'last_error' => 'Test error',
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('AI Jobs');
    $response->assertSee('total jobs');
    $response->assertSee('Queued');
    $response->assertSee('Processing');
    $response->assertSee('Completed');
    $response->assertSee('Failed');
});

test('admin dashboard displays photo studio statistics', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Create some photo studio generations
    PhotoStudioGeneration::factory()->count(3)->create([
        'team_id' => $superadmin->currentTeam->id,
        'user_id' => $superadmin->id,
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Photo Studio');
    $response->assertSee('Total generations');
    $response->assertSee('This week');
    $response->assertSee('This month');
});

test('admin dashboard shows recent user registrations', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $recentUser = User::factory()->create(['name' => 'John Recent']);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Recent Registrations');
    $response->assertSee('John Recent');
});

test('admin dashboard shows recent teams', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    Team::factory()->create([
        'name' => 'Acme Corp',
        'type' => 'customer',
        'user_id' => $superadmin->id,
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Recent Teams');
    $response->assertSee('Acme Corp');
});

test('admin role cannot access admin dashboard', function () {
    $admin = User::factory()->withPersonalTeam()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(403);
});

test('admin dashboard livewire component renders correctly', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $this->actingAs($superadmin);

    Livewire::test(\App\Livewire\AdminDashboard::class)
        ->assertStatus(200)
        ->assertSee('AI Models')
        ->assertSee('Users')
        ->assertSee('Teams')
        ->assertSee('AI Jobs')
        ->assertSee('Photo Studio');
});
