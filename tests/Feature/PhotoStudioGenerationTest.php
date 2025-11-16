<?php

namespace Tests\Feature;

use App\Models\PhotoStudioGeneration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoStudioGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_can_have_parent_relationship(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $original = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $edited = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'parent_generation_id' => $original->id,
        ]);

        $this->assertInstanceOf(PhotoStudioGeneration::class, $edited->parent);
        $this->assertEquals($original->id, $edited->parent->id);
    }

    public function test_generation_can_have_edits_relationship(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $original = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        PhotoStudioGeneration::factory()->count(2)->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'parent_generation_id' => $original->id,
        ]);

        $this->assertCount(2, $original->edits);
    }

    public function test_is_edit_returns_true_when_has_parent(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $original = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $edited = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'parent_generation_id' => $original->id,
        ]);

        $this->assertFalse($original->isEdit());
        $this->assertTrue($edited->isEdit());
    }
}
