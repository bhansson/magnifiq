<?php

use App\Livewire\ManageProductAiTemplates;
use App\Models\ProductAiTemplate;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \App\Models\ProductAiTemplate::syncDefaultTemplates();
});

test('templates page is accessible', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('ai-templates.index'))
        ->assertOk()
        ->assertSeeText('Template Library');
});

test('user can create custom template', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test(ManageProductAiTemplates::class)
        ->call('startCreate')
        ->set('form.name', 'Creative Summary')
        ->set('form.description', 'Crafts a short marketing summary for the product.')
        ->set('form.prompt', 'Write a short summary for {{ title }} using {{ description }}')
        ->set('form.content_type', 'text')
        ->call('save')
        ->assertSet('showForm', false);

    $this->assertDatabaseHas('product_ai_templates', [
        'team_id' => $team->id,
        'name' => 'Creative Summary',
        'is_default' => false,
        'is_active' => true,
    ]);

    $template = ProductAiTemplate::query()
        ->where('team_id', $team->id)
        ->where('name', 'Creative Summary')
        ->first();

    expect($template)->not->toBeNull();
    expect($template->description)->toBe('Crafts a short marketing summary for the product.');
    expect($template->settings['content_type'])->toBe('text');
    expect(collect($template->context ?? [])->pluck('key')->all())->toEqualCanonicalizing(['title', 'description']);
});