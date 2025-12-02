<?php

use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('partner can have logo path', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
        'logo_path' => 'partners/logos/acme-corp.png',
    ]);

    expect($partner->logo_path)->toEqual('partners/logos/acme-corp.png');
});

test('partner can have custom slug', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
        'partner_slug' => 'acme',
    ]);

    expect($partner->partner_slug)->toEqual('acme');
});

test('partner slug must be unique', function () {
    $user = User::factory()->create();
    Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
        'partner_slug' => 'acme',
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    Team::factory()->create([
        'user_id' => $user->id,
        'type' => 'partner',
        'partner_slug' => 'acme', // Duplicate
    ]);
});