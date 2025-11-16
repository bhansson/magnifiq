<?php

namespace Tests\Feature;

use App\Models\PhotoStudioGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoStudioEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_edit_modal_loads_generation(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $generation = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'prompt' => 'Original prompt text',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\PhotoStudio::class)
            ->call('openEditModal', $generation->id)
            ->assertSet('editModalOpen', true)
            ->assertSet('editingGeneration.id', $generation->id)
            ->assertSet('editingGeneration.prompt', 'Original prompt text')
            ->assertSet('editPrompt', '');
    }

    public function test_open_edit_modal_validates_team_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $generation = PhotoStudioGeneration::factory()->create([
            'team_id' => $otherUser->currentTeam->id,
            'user_id' => $otherUser->id,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\PhotoStudio::class)
            ->call('openEditModal', $generation->id)
            ->assertSet('editModalOpen', false);
    }

    public function test_close_edit_modal_resets_state(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $generation = PhotoStudioGeneration::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\PhotoStudio::class)
            ->call('openEditModal', $generation->id)
            ->set('editPrompt', 'Some text')
            ->call('closeEditModal')
            ->assertSet('editModalOpen', false)
            ->assertSet('editingGeneration', null)
            ->assertSet('editPrompt', '');
    }
}
