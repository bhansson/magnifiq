<?php

use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('team has customer type by default', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    expect($team->type)->toEqual('customer');
});

test('team can be partner type', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
    ]);

    expect($team->type)->toEqual('partner');
    expect($team->isPartner())->toBeTrue();
});

test('team can have parent team', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
    ]);
    $customer = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner->id,
    ]);

    expect($customer->parent_team_id)->toEqual($partner->id);
    expect($customer->parentTeam->is($partner))->toBeTrue();
});

test('partner can have many owned teams', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
    ]);
    $team1 = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner->id,
    ]);
    $team2 = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner->id,
    ]);

    expect($partner->ownedTeams)->toHaveCount(2);
    expect($partner->ownedTeams->contains($team1))->toBeTrue();
    expect($partner->ownedTeams->contains($team2))->toBeTrue();
});