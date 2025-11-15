<?php

namespace Tests\Feature;

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagePartnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_partners_list(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner1 = Team::factory()->create(['type' => 'partner', 'name' => 'Acme Partner']);
        $partner2 = Team::factory()->create(['type' => 'partner', 'name' => 'Beta Partner']);
        $customer = Team::factory()->create(['type' => 'customer']);

        Livewire::test(ManagePartners::class)
            ->assertSee('Acme Partner')
            ->assertSee('Beta Partner')
            ->assertDontSee($customer->name);
    }

    public function test_can_create_new_partner(): void
    {
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
    }

    public function test_partner_name_is_required(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ManagePartners::class)
            ->set('name', '')
            ->set('partner_slug', 'test')
            ->call('createPartner')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_partner_slug_must_be_unique(): void
    {
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
    }

    public function test_can_delete_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Delete Me']);

        Livewire::test(ManagePartners::class)
            ->call('deletePartner', $partner->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('teams', [
            'id' => $partner->id,
        ]);
    }
}
