<?php

use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('superadmin can access teams list page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/teams');

    $response->assertStatus(200);
    $response->assertSee('Manage all teams');
});

test('superadmin can view team details page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $team = Team::factory()->create([
        'name' => 'Target Team',
        'user_id' => $superadmin->id,
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/teams/' . $team->id);

    $response->assertStatus(200);
    $response->assertSee('Target Team');
    $response->assertSee('Team Details');
});

test('regular user cannot access admin teams page', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin/teams');

    $response->assertStatus(403);
});

test('admin teams list shows paginated teams', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    Team::factory()->count(5)->create(['user_id' => $superadmin->id]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeams::class)
        ->assertStatus(200)
        ->assertSee('Manage all teams');
});

test('admin teams list can search by name', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    Team::factory()->create(['name' => 'Searchable Team', 'user_id' => $superadmin->id]);
    Team::factory()->create(['name' => 'Hidden Team', 'user_id' => $superadmin->id]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeams::class)
        ->set('search', 'Searchable')
        ->assertSee('Searchable Team')
        ->assertDontSee('Hidden Team');
});

test('admin teams list can filter by type', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    Team::factory()->create(['name' => 'Customer Team', 'type' => 'customer', 'user_id' => $superadmin->id]);
    Team::factory()->create(['name' => 'Partner Team', 'type' => 'partner', 'user_id' => $superadmin->id]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeams::class)
        ->set('type', 'partner')
        ->assertSee('Partner Team')
        ->assertDontSee('Customer Team');
});

test('admin team detail shows team info and owner', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $owner = User::factory()->create(['name' => 'Team Owner']);
    $team = Team::factory()->create([
        'name' => 'Test Team',
        'user_id' => $owner->id,
    ]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeamDetail::class, ['team' => $team])
        ->assertStatus(200)
        ->assertSee('Test Team')
        ->assertSee('Team Owner')
        ->assertSee('Owner');
});

test('admin team detail shows activity summary', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $team = Team::factory()->create(['user_id' => $superadmin->id]);

    ProductAiJob::factory()->create([
        'team_id' => $team->id,
        'user_id' => $superadmin->id,
        'status' => ProductAiJob::STATUS_COMPLETED,
    ]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeamDetail::class, ['team' => $team])
        ->assertStatus(200)
        ->assertSee('Activity Summary')
        ->assertSee('AI Jobs');
});

test('admin team detail shows product feeds', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $team = Team::factory()->create(['user_id' => $superadmin->id]);

    ProductFeed::factory()->create([
        'team_id' => $team->id,
        'name' => 'Test Feed',
    ]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeamDetail::class, ['team' => $team])
        ->assertStatus(200)
        ->assertSee('Product Feeds')
        ->assertSee('Test Feed');
});

test('admin team detail shows team members', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $owner = User::factory()->create(['name' => 'Team Owner']);
    $team = Team::factory()->create(['user_id' => $owner->id]);

    $member = User::factory()->create(['name' => 'Team Member']);
    $team->users()->attach($member);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeamDetail::class, ['team' => $team])
        ->assertStatus(200)
        ->assertSee('Members')
        ->assertSee('Team Member');
});

test('admin team detail shows partner relationships', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $partner = Team::factory()->create([
        'name' => 'Partner Team',
        'type' => 'partner',
        'user_id' => $superadmin->id,
    ]);

    $customer = Team::factory()->create([
        'name' => 'Customer Team',
        'type' => 'customer',
        'user_id' => $superadmin->id,
        'parent_team_id' => $partner->id,
    ]);

    Livewire::actingAs($superadmin)
        ->test(\App\Livewire\AdminTeamDetail::class, ['team' => $partner])
        ->assertStatus(200)
        ->assertSee('Owned Customer Teams')
        ->assertSee('Customer Team');
});

test('admin teams page has back link to teams list', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $team = Team::factory()->create(['user_id' => $superadmin->id]);

    $response = $this->actingAs($superadmin)->get('/admin/teams/' . $team->id);

    $response->assertStatus(200);
    $response->assertSee('Back to Teams');
});
