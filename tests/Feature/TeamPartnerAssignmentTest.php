<?php

use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('customer team can be assigned to partner', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
        'name' => 'Partner Corp',
    ]);

    $customer = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'customer',
        'parent_team_id' => $partner->id,
    ]);

    expect($customer->parent_team_id)->toEqual($partner->id);
    expect($customer->parentTeam->is($partner))->toBeTrue();
    expect($partner->ownedTeams)->toHaveCount(1);
});

test('partner can have multiple customer teams', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
    ]);

    $customer1 = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner->id,
    ]);

    $customer2 = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner->id,
    ]);

    expect($partner->fresh()->ownedTeams)->toHaveCount(2);
    expect($partner->ownedTeams->contains($customer1))->toBeTrue();
    expect($partner->ownedTeams->contains($customer2))->toBeTrue();
});

test('reassigning team to different partner', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $partner1 = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
    $partner2 = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);

    $customer = Team::factory()->create([
        'user_id' => $user->id,
        'parent_team_id' => $partner1->id,
    ]);

    expect($partner1->fresh()->ownedTeams)->toHaveCount(1);
    expect($partner2->fresh()->ownedTeams)->toHaveCount(0);

    // Reassign to partner2
    $customer->update(['parent_team_id' => $partner2->id]);

    expect($partner1->fresh()->ownedTeams)->toHaveCount(0);
    expect($partner2->fresh()->ownedTeams)->toHaveCount(1);
});