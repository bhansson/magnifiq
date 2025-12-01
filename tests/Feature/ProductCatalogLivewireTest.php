<?php

namespace Tests\Feature;

use App\Livewire\ManageProductFeeds;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_component_loads_catalogs_on_mount(): void
    {
        ProductCatalog::factory()->count(3)->create(['team_id' => $this->team->id]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->assertSet('catalogs', function ($catalogs) {
                return $catalogs->count() === 3;
            });
    }

    public function test_can_create_catalog(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->set('newCatalogName', 'Main Store')
            ->call('createCatalog')
            ->assertSet('newCatalogName', '')
            ->assertSet('showCreateCatalog', false);

        $this->assertDatabaseHas('product_catalogs', [
            'team_id' => $this->team->id,
            'name' => 'Main Store',
        ]);
    }

    public function test_creating_catalog_logs_activity(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->set('newCatalogName', 'Test Catalog')
            ->call('createCatalog');

        $this->assertDatabaseHas('team_activities', [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'type' => TeamActivity::TYPE_CATALOG_CREATED,
        ]);
    }

    public function test_catalog_name_is_required(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->set('newCatalogName', '')
            ->call('createCatalog')
            ->assertHasErrors(['newCatalogName' => 'required']);
    }

    public function test_can_toggle_create_catalog_form(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->assertSet('showCreateCatalog', false)
            ->call('toggleCreateCatalog')
            ->assertSet('showCreateCatalog', true)
            ->call('toggleCreateCatalog')
            ->assertSet('showCreateCatalog', false);
    }

    public function test_can_start_editing_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Original Name',
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startEditCatalog', $catalog->id)
            ->assertSet('editingCatalogId', $catalog->id)
            ->assertSet('editingCatalogName', 'Original Name');
    }

    public function test_can_update_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Old Name',
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startEditCatalog', $catalog->id)
            ->set('editingCatalogName', 'New Name')
            ->call('updateCatalog')
            ->assertSet('editingCatalogId', null)
            ->assertSet('editingCatalogName', '');

        $this->assertDatabaseHas('product_catalogs', [
            'id' => $catalog->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_cancel_editing_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Test Catalog',
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startEditCatalog', $catalog->id)
            ->set('editingCatalogName', 'Changed Name')
            ->call('cancelEditCatalog')
            ->assertSet('editingCatalogId', null)
            ->assertSet('editingCatalogName', '');

        $this->assertDatabaseHas('product_catalogs', [
            'id' => $catalog->id,
            'name' => 'Test Catalog',
        ]);
    }

    public function test_can_delete_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'To Delete',
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('deleteCatalog', $catalog->id);

        $this->assertDatabaseMissing('product_catalogs', [
            'id' => $catalog->id,
        ]);
    }

    public function test_deleting_catalog_logs_activity(): void
    {
        $catalog = ProductCatalog::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Deleted Catalog',
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('deleteCatalog', $catalog->id);

        $this->assertDatabaseHas('team_activities', [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'type' => TeamActivity::TYPE_CATALOG_DELETED,
        ]);
    }

    public function test_deleting_catalog_sets_feeds_to_standalone(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => $catalog->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('deleteCatalog', $catalog->id);

        $feed->refresh();
        $this->assertNull($feed->product_catalog_id);
    }

    public function test_can_start_moving_feed(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => $catalog->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id)
            ->assertSet('movingFeedId', $feed->id)
            ->assertSet('moveToCatalogId', $catalog->id);
    }

    public function test_can_move_feed_to_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id)
            ->set('moveToCatalogId', $catalog->id)
            ->call('confirmMoveFeed')
            ->assertSet('movingFeedId', null)
            ->assertSet('moveToCatalogId', null);

        $feed->refresh();
        $this->assertEquals($catalog->id, $feed->product_catalog_id);
    }

    public function test_can_move_feed_to_standalone(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => $catalog->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id)
            ->set('moveToCatalogId', null)
            ->call('confirmMoveFeed');

        $feed->refresh();
        $this->assertNull($feed->product_catalog_id);
    }

    public function test_moving_feed_logs_activity(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id)
            ->set('moveToCatalogId', $catalog->id)
            ->call('confirmMoveFeed');

        $this->assertDatabaseHas('team_activities', [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'type' => TeamActivity::TYPE_FEED_MOVED,
        ]);
    }

    public function test_can_cancel_moving_feed(): void
    {
        $feed = ProductFeed::factory()->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id)
            ->call('cancelMoveFeed')
            ->assertSet('movingFeedId', null)
            ->assertSet('moveToCatalogId', null);
    }

    public function test_cannot_access_other_teams_catalog(): void
    {
        $otherTeam = Team::factory()->create();
        $catalog = ProductCatalog::factory()->create(['team_id' => $otherTeam->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startEditCatalog', $catalog->id);
    }

    public function test_cannot_delete_other_teams_catalog(): void
    {
        $otherTeam = Team::factory()->create();
        $catalog = ProductCatalog::factory()->create(['team_id' => $otherTeam->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('deleteCatalog', $catalog->id);
    }

    public function test_cannot_move_other_teams_feed(): void
    {
        $otherTeam = Team::factory()->create();
        $feed = ProductFeed::factory()->create(['team_id' => $otherTeam->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->call('startMoveFeed', $feed->id);
    }

    public function test_catalogs_show_correct_feed_count(): void
    {
        $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

        ProductFeed::factory()->count(3)->create([
            'team_id' => $this->team->id,
            'product_catalog_id' => $catalog->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->assertSet('catalogs', function ($catalogs) {
                return $catalogs->first()->feeds_count === 3;
            });
    }

    public function test_catalogs_only_show_current_team(): void
    {
        ProductCatalog::factory()->count(2)->create(['team_id' => $this->team->id]);

        $otherTeam = Team::factory()->create();
        ProductCatalog::factory()->count(3)->create(['team_id' => $otherTeam->id]);

        Livewire::actingAs($this->user)
            ->test(ManageProductFeeds::class)
            ->assertSet('catalogs', function ($catalogs) {
                return $catalogs->count() === 2;
            });
    }
}
