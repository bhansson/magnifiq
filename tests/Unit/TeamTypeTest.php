<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_has_customer_type_by_default(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->assertEquals('customer', $team->type);
    }

    public function test_team_can_be_partner_type(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
        ]);

        $this->assertEquals('partner', $team->type);
        $this->assertTrue($team->isPartner());
    }

    public function test_team_can_have_parent_team(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
        ]);
        $customer = Team::factory()->create([
            'user_id' => $user->id,
            'parent_team_id' => $partner->id,
        ]);

        $this->assertEquals($partner->id, $customer->parent_team_id);
        $this->assertTrue($customer->parentTeam->is($partner));
    }

    public function test_partner_can_have_many_owned_teams(): void
    {
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

        $this->assertCount(2, $partner->ownedTeams);
        $this->assertTrue($partner->ownedTeams->contains($team1));
        $this->assertTrue($partner->ownedTeams->contains($team2));
    }
}
