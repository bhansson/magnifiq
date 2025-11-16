<?php

namespace Tests\Feature;

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EditPartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_open_edit_modal_for_partner(): void
    {
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
    }

    public function test_can_update_partner_name_and_slug(): void
    {
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
        $this->assertEquals('New Partner Name', $partner->name);
        $this->assertEquals('new-slug', $partner->partner_slug);
    }

    public function test_partner_name_is_required_when_editing(): void
    {
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
    }

    public function test_partner_slug_must_be_unique_when_editing(): void
    {
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
    }

    public function test_can_keep_same_slug_when_editing(): void
    {
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
        $this->assertEquals('Updated Name', $partner->name);
        $this->assertEquals('my-slug', $partner->partner_slug);
    }

    public function test_can_upload_new_logo_when_editing(): void
    {
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
        $this->assertNotNull($partner->logo_path);
        Storage::disk('public')->assertExists($partner->logo_path);
    }

    public function test_can_replace_existing_logo(): void
    {
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
    }

    public function test_can_remove_logo_from_partner(): void
    {
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
        $this->assertNull($partner->logo_path);
        Storage::disk('public')->assertMissing($logoPath);
    }

    public function test_logo_file_must_be_image(): void
    {
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
    }

    public function test_edit_modal_closes_after_successful_update(): void
    {
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
    }
}
