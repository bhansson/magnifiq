<?php

use App\Livewire\PartnerRevenueDashboard;
use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('displays partner revenue records', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin);

    $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Acme']);
    $customer = Team::factory()->create(['parent_team_id' => $partner->id]);

    PartnerRevenue::create([
        'partner_team_id' => $partner->id,
        'customer_team_id' => $customer->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 10000,
        'partner_share_percent' => 20.00,
        'partner_revenue_cents' => 2000,
        'currency' => 'USD',
    ]);

    Livewire::test(PartnerRevenueDashboard::class)
        ->assertSee('Acme')
        ->assertSee('$100.00') // customer revenue
        ->assertSee('$20.00');
    // partner revenue
});

test('filters by partner', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin);

    $partner1 = Team::factory()->create(['type' => 'partner', 'name' => 'Partner A']);
    $partner2 = Team::factory()->create(['type' => 'partner', 'name' => 'Partner B']);

    $customer1 = Team::factory()->create(['parent_team_id' => $partner1->id]);
    $customer2 = Team::factory()->create(['parent_team_id' => $partner2->id]);

    PartnerRevenue::create([
        'partner_team_id' => $partner1->id,
        'customer_team_id' => $customer1->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 10000,
        'partner_share_percent' => 20.00,
        'partner_revenue_cents' => 2000,
        'currency' => 'USD',
    ]);

    PartnerRevenue::create([
        'partner_team_id' => $partner2->id,
        'customer_team_id' => $customer2->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'customer_revenue_cents' => 5000,
        'partner_share_percent' => 15.00,
        'partner_revenue_cents' => 750,
        'currency' => 'USD',
    ]);

    Livewire::test(PartnerRevenueDashboard::class)
        ->set('selectedPartnerId', $partner1->id)
        ->assertSee('Partner A')
        ->assertSee('$20.00')
        ->assertDontSee('$7.50');
    // partner2's revenue
});

test('displays total revenue for partner', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin);

    $partner = Team::factory()->create(['type' => 'partner']);
    $customer1 = Team::factory()->create(['parent_team_id' => $partner->id]);
    $customer2 = Team::factory()->create(['parent_team_id' => $partner->id]);

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

    Livewire::test(PartnerRevenueDashboard::class)
        ->set('selectedPartnerId', $partner->id)
        ->assertSee('$30.00');
    // Total partner revenue: $20 + $10
});

// Authorization tests - verify Livewire component is protected
test('regular user cannot access PartnerRevenueDashboard component', function () {
    $user = User::factory()->create(['role' => 'user']);

    Livewire::actingAs($user)
        ->test(PartnerRevenueDashboard::class)
        ->assertStatus(403);
});

test('admin user cannot access PartnerRevenueDashboard component', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(PartnerRevenueDashboard::class)
        ->assertStatus(403);
});

test('unauthenticated user cannot access PartnerRevenueDashboard component', function () {
    Livewire::test(PartnerRevenueDashboard::class)
        ->assertStatus(403);
});
