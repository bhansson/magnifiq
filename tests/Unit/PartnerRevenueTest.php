<?php

use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can create partner revenue record', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
    $customer = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

    $revenue = PartnerRevenue::create([
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 10000, // $100
        'partner_share_percent' => 20.00,
        'partner_revenue_cents' => 2000, // $20
        'currency' => 'USD',
    ]);

    $this->assertDatabaseHas('partner_revenue', [
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer->id,
        'customer_revenue_cents' => 10000,
        'partner_revenue_cents' => 2000,
    ]);
});

test('partner has many revenue records', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
    $customer1 = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);
    $customer2 = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

    PartnerRevenue::create([
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer1->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 10000,
        'partner_share_percent' => 20.00,
        'partner_revenue_cents' => 2000,
        'currency' => 'USD',
    ]);

    PartnerRevenue::create([
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer2->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 5000,
        'partner_share_percent' => 20.00,
        'partner_revenue_cents' => 1000,
        'currency' => 'USD',
    ]);

    expect($partner->revenueRecords)->toHaveCount(2);
});

test('revenue amounts are cast to integers', function () {
    $user = User::factory()->create();
    $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
    $customer = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

    $revenue = PartnerRevenue::create([
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => '10000',
        'partner_share_percent' => '20.00',
        'partner_revenue_cents' => '2000',
        'currency' => 'USD',
    ]);

    expect($revenue->customer_revenue_cents)->toBeInt();
    expect($revenue->partner_revenue_cents)->toBeInt();
});