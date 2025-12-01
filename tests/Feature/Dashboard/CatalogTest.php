<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\ManageProductFeeds;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_page_is_accessible(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Imported Feeds');
    }

    public function test_manage_product_feeds_component_renders(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        Livewire::test(ManageProductFeeds::class)
            ->assertOk();
    }

    public function test_catalog_page_requires_authentication(): void
    {
        $this->get(route('catalog.index'))
            ->assertRedirect(route('login'));
    }
}
