<?php

use App\Livewire\ManageProductFeeds;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('component loads catalogs on mount', function () {
    ProductCatalog::factory()->count(3)->create(['team_id' => $this->team->id]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->assertSet('catalogs', function ($catalogs) {
            return $catalogs->count() === 3;
        });
});

test('can create catalog', function () {
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
});

test('creating catalog logs activity', function () {
    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('newCatalogName', 'Test Catalog')
        ->call('createCatalog');

    $this->assertDatabaseHas('team_activities', [
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'type' => TeamActivity::TYPE_CATALOG_CREATED,
    ]);
});

test('catalog name is required', function () {
    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('newCatalogName', '')
        ->call('createCatalog')
        ->assertHasErrors(['newCatalogName' => 'required']);
});

test('can toggle create catalog form', function () {
    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->assertSet('showCreateCatalog', false)
        ->call('toggleCreateCatalog')
        ->assertSet('showCreateCatalog', true)
        ->call('toggleCreateCatalog')
        ->assertSet('showCreateCatalog', false);
});

test('can start editing catalog', function () {
    $catalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Original Name',
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->call('startEditCatalog', $catalog->id)
        ->assertSet('editingCatalogId', $catalog->id)
        ->assertSet('editingCatalogName', 'Original Name');
});

test('can update catalog', function () {
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
});

test('can cancel editing catalog', function () {
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
});

test('can delete catalog', function () {
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
});

test('deleting catalog logs activity', function () {
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
});

test('deleting catalog sets feeds to standalone', function () {
    $catalog = ProductCatalog::factory()->create(['team_id' => $this->team->id]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'product_catalog_id' => $catalog->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->call('deleteCatalog', $catalog->id);

    $feed->refresh();
    expect($feed->product_catalog_id)->toBeNull();
});

test('can start moving feed', function () {
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
});

test('can move feed to catalog', function () {
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
    expect($feed->product_catalog_id)->toEqual($catalog->id);
});

test('can move feed to standalone', function () {
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
    expect($feed->product_catalog_id)->toBeNull();
});

test('moving feed logs activity', function () {
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
});

test('can cancel moving feed', function () {
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
});

test('cannot access other teams catalog', function () {
    $otherTeam = Team::factory()->create();
    $catalog = ProductCatalog::factory()->create(['team_id' => $otherTeam->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->call('startEditCatalog', $catalog->id);
});

test('cannot delete other teams catalog', function () {
    $otherTeam = Team::factory()->create();
    $catalog = ProductCatalog::factory()->create(['team_id' => $otherTeam->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->call('deleteCatalog', $catalog->id);
});

test('cannot move other teams feed', function () {
    $otherTeam = Team::factory()->create();
    $feed = ProductFeed::factory()->create(['team_id' => $otherTeam->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->call('startMoveFeed', $feed->id);
});

test('catalogs show correct feed count', function () {
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
});

test('catalogs only show current team', function () {
    ProductCatalog::factory()->count(2)->create(['team_id' => $this->team->id]);

    $otherTeam = Team::factory()->create();
    ProductCatalog::factory()->count(3)->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->assertSet('catalogs', function ($catalogs) {
            return $catalogs->count() === 2;
        });
});