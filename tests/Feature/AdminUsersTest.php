<?php

use App\Models\ProductAiJob;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('superadmin can access users list page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/users');

    $response->assertStatus(200);
    $response->assertSee('Manage all users');
});

test('superadmin can view user details page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $targetUser = User::factory()->withPersonalTeam()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/users/'.$targetUser->id);

    $response->assertStatus(200);
    $response->assertSee('Target User');
    $response->assertSee('target@example.com');
});

test('regular user cannot access admin users page', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin/users');

    $response->assertStatus(403);
});

test('admin users list shows paginated users', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    User::factory()->count(5)->create();

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminUsers::class)
        ->assertStatus(200)
        ->assertSee('Manage all users');
});

test('admin users list can search by name', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $user1 = User::factory()->create(['name' => 'John Searchable']);
    $user2 = User::factory()->create(['name' => 'Jane Invisible']);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminUsers::class)
        ->set('search', 'Searchable')
        ->assertSee('John Searchable')
        ->assertDontSee('Jane Invisible');
});

test('admin users list can filter by role', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Guy']);
    $user = User::factory()->create(['role' => 'user', 'name' => 'Regular User']);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminUsers::class)
        ->set('role', 'admin')
        ->assertSee('Admin Guy')
        ->assertDontSee('Regular User');
});

test('admin user detail shows user teams', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $targetUser = User::factory()->withPersonalTeam()->create(['name' => 'Target User']);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminUserDetail::class, ['user' => $targetUser])
        ->assertStatus(200)
        ->assertSee('Target User')
        ->assertSee('Owned Teams')
        ->assertSee($targetUser->currentTeam->name);
});

test('admin user detail shows job statistics', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $targetUser = User::factory()->withPersonalTeam()->create(['name' => 'Target User']);

    ProductAiJob::factory()->create([
        'team_id' => $targetUser->currentTeam->id,
        'user_id' => $targetUser->id,
        'status' => ProductAiJob::STATUS_COMPLETED,
    ]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminUserDetail::class, ['user' => $targetUser])
        ->assertStatus(200)
        ->assertSee('AI Jobs')
        ->assertSee('Completed');
});

test('admin users page has back link to dashboard', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $targetUser = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($superadmin)->get('/admin/users/'.$targetUser->id);

    $response->assertStatus(200);
    $response->assertSee('Back to Users');
});

// Authorization tests - verify Livewire components are protected
test('regular user cannot access AdminUsers component via Livewire', function () {
    $user = User::factory()->create(['role' => 'user']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\AdminUsers::class)
        ->assertStatus(403);
});

test('regular user cannot access AdminUserDetail component via Livewire', function () {
    $user = User::factory()->create(['role' => 'user']);
    $targetUser = User::factory()->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\AdminUserDetail::class, ['user' => $targetUser])
        ->assertStatus(403);
});

test('unauthenticated user cannot access AdminUsers component', function () {
    Livewire::test(\App\Livewire\AdminUsers::class)
        ->assertStatus(403);
});
