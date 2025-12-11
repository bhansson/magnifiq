<?php

use App\Livewire\ManageProductFeeds;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('catalog page is accessible', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('Product Catalogs');
});

test('manage product feeds component renders', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    Livewire::test(ManageProductFeeds::class)
        ->assertOk();
});

test('catalog page requires authentication', function () {
    $this->get(route('catalog.index'))
        ->assertRedirect(route('login'));
});
