<?php

namespace Tests\Unit;

use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_partner_revenue_record(): void
    {
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
    }

    public function test_partner_has_many_revenue_records(): void
    {
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

        $this->assertCount(2, $partner->revenueRecords);
    }

    public function test_revenue_amounts_are_cast_to_integers(): void
    {
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

        $this->assertIsInt($revenue->customer_revenue_cents);
        $this->assertIsInt($revenue->partner_revenue_cents);
    }
}
