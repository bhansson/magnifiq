<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('superadmin can access admin dashboard', function () {
    $superadmin = User::factory()->withPersonalTeam()->create([
        'role' => 'superadmin',
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Admin Dashboard');
});

test('regular user cannot access admin dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertStatus(403);
});

test('guest cannot access admin dashboard', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('superadmin can access partners page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create([
        'role' => 'superadmin',
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/partners');

    $response->assertStatus(200);
    $response->assertSee('Partners');
});

test('superadmin can access revenue page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create([
        'role' => 'superadmin',
    ]);

    $response = $this->actingAs($superadmin)->get('/admin/revenue');

    $response->assertStatus(200);
    $response->assertSee('Partner Revenue');
});

test('regular user cannot access partners page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/admin/partners');

    $response->assertStatus(403);
});

test('regular user cannot access revenue page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/admin/revenue');

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
