<?php

use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('partner owner can view owned teams', function () {
    $partnerOwner = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $partnerOwner->id,
        'type' => 'partner',
    ]);

    $customerOwner = User::factory()->create();
    $customer = Team::factory()->create([
        'user_id' => $customerOwner->id,
        'parent_team_id' => $partner->id,
    ]);

    // Add partner owner to customer team
    $customer->users()->attach($partnerOwner, ['role' => 'partner_admin']);

    expect($partnerOwner->can('view', $customer))->toBeTrue();
});

test('partner member can view owned teams', function () {
    $partnerOwner = User::factory()->create();
    $partnerMember = User::factory()->create();

    $partner = Team::factory()->create([
        'user_id' => $partnerOwner->id,
        'type' => 'partner',
    ]);

    // Add member to partner team
    $partner->users()->attach($partnerMember, ['role' => 'admin']);

    $customerOwner = User::factory()->create();
    $customer = Team::factory()->create([
        'user_id' => $customerOwner->id,
        'parent_team_id' => $partner->id,
    ]);

    // Add partner member to customer team
    $customer->users()->attach($partnerMember, ['role' => 'partner_admin']);

    expect($partnerMember->can('view', $customer))->toBeTrue();
});

test('non partner cannot view unrelated team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->can('view', $team))->toBeFalse();
});

test('partner can update owned team settings', function () {
    $partnerOwner = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $partnerOwner->id,
        'type' => 'partner',
    ]);

    $customerOwner = User::factory()->create();
    $customer = Team::factory()->create([
        'user_id' => $customerOwner->id,
        'parent_team_id' => $partner->id,
    ]);

    $customer->users()->attach($partnerOwner, ['role' => 'partner_admin']);

    // Partner should be able to update customer team
    expect($partnerOwner->can('update', $customer))->toBeTrue();
});