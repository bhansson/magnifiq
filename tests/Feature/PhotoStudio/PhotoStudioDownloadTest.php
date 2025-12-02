<?php

use App\Models\PhotoStudioGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('photo-studio.models.image_generation', 'google/gemini-2.5-flash-image');
});

test('user can download generation from own team', function () {
    Storage::fake('public');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $generation = PhotoStudioGeneration::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'product_id' => null,
        'source_type' => 'product_image',
        'source_reference' => 'https://cdn.example.com/reference.png',
        'prompt' => 'Prompt text',
        'model' => imageGenerationModel(),
        'storage_disk' => 'public',
        'storage_path' => 'photo-studio/test.png',
    ]);

    Storage::disk('public')->put('photo-studio/test.png', 'fake-image');

    $this->actingAs($user)
        ->get(route('photo-studio.gallery.download', $generation))
        ->assertOk()
        ->assertDownload(sprintf('photo-studio-%s-test.png', $generation->id));
});

test('user cannot download generation from another team', function () {
    Storage::fake('public');

    $user = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherTeam = $otherUser->currentTeam;

    $generation = PhotoStudioGeneration::create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherUser->id,
        'product_id' => null,
        'source_type' => 'product_image',
        'source_reference' => 'https://cdn.example.com/reference.png',
        'prompt' => 'Prompt text',
        'model' => imageGenerationModel(),
        'storage_disk' => 'public',
        'storage_path' => 'photo-studio/other-test.png',
    ]);

    Storage::disk('public')->put('photo-studio/other-test.png', 'fake-image');

    $this->actingAs($user)
        ->get(route('photo-studio.gallery.download', $generation))
        ->assertNotFound();
});