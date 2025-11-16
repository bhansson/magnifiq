<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_owner_can_view_owned_teams(): void
    {
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

        $this->assertTrue($partnerOwner->can('view', $customer));
    }

    public function test_partner_member_can_view_owned_teams(): void
    {
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

        $this->assertTrue($partnerMember->can('view', $customer));
    }

    public function test_non_partner_cannot_view_unrelated_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->assertFalse($user->can('view', $team));
    }

    public function test_partner_can_update_owned_team_settings(): void
    {
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
        $this->assertTrue($partnerOwner->can('update', $customer));
    }
}
