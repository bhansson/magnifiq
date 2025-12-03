<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('superadmin can access admin dashboard', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Admin Dashboard');
});

test('superadmin can access partners page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/partners');

    $response->assertStatus(200);
    $response->assertSee('Partners');
});

test('superadmin can access revenue page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/revenue');

    $response->assertStatus(200);
    $response->assertSee('Partner Revenue');
});

test('regular user cannot access partners page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin/partners');

    $response->assertStatus(403);
});

test('regular user cannot access revenue page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin/revenue');

    $response->assertStatus(403);
});

test('admin role cannot access admin pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/partners');
    $response->assertStatus(403);

    $response = $this->actingAs($admin)->get('/admin/revenue');
    $response->assertStatus(403);
});

test('guest cannot access partners page', function () {
    $response = $this->get('/admin/partners');

    $response->assertRedirect('/login');
});

test('guest cannot access revenue page', function () {
    $response = $this->get('/admin/revenue');

    $response->assertRedirect('/login');
});

test('user role helper methods', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    // Test isSuperAdmin()
    expect($superadmin->isSuperAdmin())->toBeTrue();
    expect($admin->isSuperAdmin())->toBeFalse();
    expect($user->isSuperAdmin())->toBeFalse();

    // Test isAdmin()
    expect($superadmin->isAdmin())->toBeTrue();
    expect($admin->isAdmin())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();

    // Test hasRole()
    expect($superadmin->hasRole('superadmin'))->toBeTrue();
    expect($admin->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->hasRole('admin'))->toBeFalse();
});

test('new users default to user role', function () {
    $user = User::factory()->create();

    expect($user->role)->toEqual('user');
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->isSuperAdmin())->toBeFalse();
});

test('superadmin sees admin links in navigation', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Admin Dashboard');
    $response->assertSee('Partners');
    $response->assertSee('Revenue');
});

test('regular user does not see admin links in navigation', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertDontSee('Admin Dashboard', false);
    $response->assertDontSee('Partners', false);
    $response->assertDontSee('Revenue', false);
});
