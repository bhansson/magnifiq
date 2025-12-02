<?php

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can open edit modal for partner', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Original Partner Name',
        'partner_slug' => 'original-slug',
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->assertSet('editingPartnerId', $partner->id)
        ->assertSet('name', 'Original Partner Name')
        ->assertSet('partner_slug', 'original-slug')
        ->assertSet('showEditModal', true);
});

test('can update partner name and slug', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Old Name',
        'partner_slug' => 'old-slug',
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('name', 'New Partner Name')
        ->set('partner_slug', 'new-slug')
        ->call('updatePartner')
        ->assertHasNoErrors();

    $partner->refresh();
    expect($partner->name)->toEqual('New Partner Name');
    expect($partner->partner_slug)->toEqual('new-slug');
});

test('partner name is required when editing', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Test Partner',
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('name', '')
        ->call('updatePartner')
        ->assertHasErrors(['name' => 'required']);
});

test('partner slug must be unique when editing', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    // Create two partners with different slugs
    $partner1 = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'partner_slug' => 'existing-slug',
    ]);

    $partner2 = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'partner_slug' => 'other-slug',
    ]);

    // Try to update partner2 with partner1's slug
    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner2->id)
        ->set('partner_slug', 'existing-slug')
        ->call('updatePartner')
        ->assertHasErrors(['partner_slug' => 'unique']);
});

test('can keep same slug when editing', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Original Name',
        'partner_slug' => 'my-slug',
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('name', 'Updated Name')
        ->set('partner_slug', 'my-slug') // Keep the same slug
        ->call('updatePartner')
        ->assertHasNoErrors();

    $partner->refresh();
    expect($partner->name)->toEqual('Updated Name');
    expect($partner->partner_slug)->toEqual('my-slug');
});

test('can upload new logo when editing', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Test Partner',
    ]);

    $newLogo = UploadedFile::fake()->image('new-logo.png', 200, 200);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('logo', $newLogo)
        ->call('updatePartner')
        ->assertHasNoErrors();

    $partner->refresh();
    expect($partner->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($partner->logo_path);
});

test('can replace existing logo', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    // Create partner with existing logo
    $oldLogo = UploadedFile::fake()->image('old-logo.png', 200, 200);
    $oldLogoPath = $oldLogo->store('partners/logos', 'public');

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Test Partner',
        'logo_path' => $oldLogoPath,
    ]);

    Storage::disk('public')->assertExists($oldLogoPath);

    // Upload new logo
    $newLogo = UploadedFile::fake()->image('new-logo.png', 200, 200);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('logo', $newLogo)
        ->call('updatePartner')
        ->assertHasNoErrors();

    $partner->refresh();

    // Old logo should be deleted
    Storage::disk('public')->assertMissing($oldLogoPath);

    // New logo should exist
    Storage::disk('public')->assertExists($partner->logo_path);
});

test('can remove logo from partner', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    // Create partner with logo
    $logo = UploadedFile::fake()->image('logo.png', 200, 200);
    $logoPath = $logo->store('partners/logos', 'public');

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Test Partner',
        'logo_path' => $logoPath,
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->call('removeLogo')
        ->assertHasNoErrors();

    $partner->refresh();
    expect($partner->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

test('logo file must be image', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
    ]);

    $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->set('logo', $invalidFile)
        ->call('updatePartner')
        ->assertHasErrors(['logo']);
});

test('edit modal closes after successful update', function () {
    Storage::fake('public');

    $admin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);
    $this->actingAs($admin);

    $partner = Team::factory()->create([
        'user_id' => $admin->id,
        'type' => 'partner',
        'name' => 'Test Partner',
    ]);

    Livewire::test(ManagePartners::class)
        ->call('openEditModal', $partner->id)
        ->assertSet('showEditModal', true)
        ->set('name', 'Updated Name')
        ->call('updatePartner')
        ->assertSet('showEditModal', false)
        ->assertSet('editingPartnerId', null);
});