<?php

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can view partners list', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $partner1 = Team::factory()->create(['type' => 'partner', 'name' => 'Acme Partner']);
    $partner2 = Team::factory()->create(['type' => 'partner', 'name' => 'Beta Partner']);
    $customer = Team::factory()->create(['type' => 'customer']);

    Livewire::test(ManagePartners::class)
        ->assertSee('Acme Partner')
        ->assertSee('Beta Partner')
        ->assertDontSee($customer->name);
});

test('can create new partner', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(ManagePartners::class)
        ->set('name', 'New Partner Inc')
        ->set('partner_slug', 'newpartner')
        ->set('partner_share_percent', 25.00)
        ->call('createPartner')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'name' => 'New Partner Inc',
        'type' => 'partner',
        'partner_slug' => 'newpartner',
    ]);
});

test('partner name is required', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(ManagePartners::class)
        ->set('name', '')
        ->set('partner_slug', 'test')
        ->call('createPartner')
        ->assertHasErrors(['name' => 'required']);
});

test('partner slug must be unique', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    Team::factory()->create([
        'type' => 'partner',
        'partner_slug' => 'acme',
    ]);

    Livewire::test(ManagePartners::class)
        ->set('name', 'Another Partner')
        ->set('partner_slug', 'acme')
        ->call('createPartner')
        ->assertHasErrors(['partner_slug' => 'unique']);
});

test('can delete partner', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Delete Me']);

    Livewire::test(ManagePartners::class)
        ->call('deletePartner', $partner->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('teams', [
        'id' => $partner->id,
    ]);
});

test('can upload partner logo', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    $logo = UploadedFile::fake()->image('logo.png', 200, 200);

    Livewire::test(ManagePartners::class)
        ->set('name', 'Logo Partner')
        ->set('partner_slug', 'logopartner')
        ->set('logo', $logo)
        ->call('createPartner')
        ->assertHasNoErrors();

    $partner = Team::query()->where('name', 'Logo Partner')->first();

    expect($partner->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($partner->logo_path);
});