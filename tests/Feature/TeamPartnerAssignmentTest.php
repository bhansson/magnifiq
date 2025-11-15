<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPartnerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_team_can_be_assigned_to_partner(): void
    {
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

        $this->assertEquals($partner->id, $customer->parent_team_id);
        $this->assertTrue($customer->parentTeam->is($partner));
        $this->assertCount(1, $partner->ownedTeams);
    }

    public function test_partner_can_have_multiple_customer_teams(): void
    {
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

        $this->assertCount(2, $partner->fresh()->ownedTeams);
        $this->assertTrue($partner->ownedTeams->contains($customer1));
        $this->assertTrue($partner->ownedTeams->contains($customer2));
    }

    public function test_reassigning_team_to_different_partner(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $partner1 = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
        $partner2 = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);

        $customer = Team::factory()->create([
            'user_id' => $user->id,
            'parent_team_id' => $partner1->id,
        ]);

        $this->assertCount(1, $partner1->fresh()->ownedTeams);
        $this->assertCount(0, $partner2->fresh()->ownedTeams);

        // Reassign to partner2
        $customer->update(['parent_team_id' => $partner2->id]);

        $this->assertCount(0, $partner1->fresh()->ownedTeams);
        $this->assertCount(1, $partner2->fresh()->ownedTeams);
    }
}
