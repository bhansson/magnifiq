<?php

use App\Livewire\ManageProductFeeds;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('importing feed with "new" catalog option creates catalog with feed name', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        // After fetch, feedName is auto-populated - override it
        ->set('feedName', 'My New Feed')
        ->set('language', 'en')
        ->set('catalogOption', 'new')
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Feed imported successfully.');

    // Assert catalog was created with feed name
    $this->assertDatabaseHas('product_catalogs', [
        'team_id' => $this->team->id,
        'name' => 'My New Feed',
    ]);

    // Assert feed was created and assigned to the catalog
    $catalog = ProductCatalog::where('team_id', $this->team->id)->where('name', 'My New Feed')->first();
    $this->assertDatabaseHas('product_feeds', [
        'team_id' => $this->team->id,
        'name' => 'My New Feed',
        'product_catalog_id' => $catalog->id,
    ]);

    // Assert activity was logged for catalog creation
    $this->assertDatabaseHas('team_activities', [
        'team_id' => $this->team->id,
        'type' => TeamActivity::TYPE_CATALOG_CREATED,
        'subject_id' => $catalog->id,
    ]);
});

test('importing feed with existing catalog assigns feed to that catalog', function () {
    // Create an existing catalog
    $existingCatalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Existing Catalog',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        // After fetch, override auto-populated values
        ->set('feedName', 'Another Feed')
        ->set('language', 'sv')
        ->set('catalogOption', (string) $existingCatalog->id)
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Feed imported successfully.');

    // Assert feed was created and assigned to the existing catalog
    $this->assertDatabaseHas('product_feeds', [
        'team_id' => $this->team->id,
        'name' => 'Another Feed',
        'product_catalog_id' => $existingCatalog->id,
    ]);

    // Assert no new catalog was created
    expect(ProductCatalog::where('team_id', $this->team->id)->count())->toBe(1);
});

test('feed name is required when importing', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        // Clear the auto-populated feed name
        ->set('feedName', '')
        ->set('language', 'en')
        ->set('catalogOption', 'new')
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasErrors(['feedName' => 'required']);

    // Assert no feed or catalog was created
    expect(ProductFeed::where('team_id', $this->team->id)->count())->toBe(0);
    expect(ProductCatalog::where('team_id', $this->team->id)->count())->toBe(0);
});

test('selecting invalid catalog id shows error', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'Test Feed')
        ->set('language', 'en')
        ->set('catalogOption', '99999') // Non-existent catalog ID
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertSet('errorMessage', 'Selected catalog not found.');

    // Assert no feed was created
    expect(ProductFeed::where('team_id', $this->team->id)->count())->toBe(0);
});

test('catalog option is required when importing', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'Test Feed')
        ->set('language', 'en')
        ->set('catalogOption', '') // Empty catalog option
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasErrors(['catalogOption' => 'required']);
});

test('catalog option resets to new after successful import', function () {
    $existingCatalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Existing Catalog',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'Another Feed')
        ->set('language', 'en')
        ->set('catalogOption', (string) $existingCatalog->id)
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasNoErrors()
        ->assertSet('catalogOption', 'new'); // Should reset to 'new'
});

test('catalog dropdown shows existing catalogs after fetch', function () {
    $catalog1 = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Catalog One',
    ]);
    $catalog2 = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Catalog Two',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    // Before fetch, showMapping is false so catalog dropdown input is not rendered
    $component = Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->assertSet('showMapping', false)
        ->assertDontSeeHtml('id="catalogOption"');

    // After fetch, catalog dropdown appears with options
    $component
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->assertSet('showMapping', true)
        ->assertSeeHtml('id="catalogOption"')
        ->assertSee('Catalog One')
        ->assertSee('Catalog Two')
        ->assertSee('+ Create new catalog');
});

test('feed name is auto-populated from URL domain after fetch', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'mystore.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://www.mystore.com/feeds/products.xml')
        ->call('fetchFields')
        ->assertSet('feedName', 'mystore.com'); // www. stripped, just domain
});

test('language is auto-detected from RSS language tag', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
<language>sv</language>
<item>
<id>SKU-001</id>
<title>Produkt</title>
<link>https://example.com/product</link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->assertSet('language', 'sv');
});

test('language is auto-detected from URL pattern', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/sv/feed.xml')
        ->call('fetchFields')
        ->assertSet('language', 'sv');
});

test('language defaults to english when not detected', function () {
    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->assertSet('language', 'en');
});

test('duplicate feed url with same language is rejected', function () {
    // Create an existing feed
    $existingFeed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Existing Feed',
        'feed_url' => 'https://example.com/feed.xml',
        'language' => 'en',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'New Feed')
        ->set('language', 'en') // Same language as existing
        ->set('catalogOption', 'new')
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertSet('errorMessage', 'A feed with this URL and language already exists: "Existing Feed". Use the refresh option to update it instead.');

    // Assert no new feed was created
    expect(ProductFeed::where('team_id', $this->team->id)->count())->toBe(1);
});

test('creating new catalog with existing name reuses existing catalog', function () {
    // Create an existing catalog
    $existingCatalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'My Catalog',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Product</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'My Catalog') // Same name as existing catalog
        ->set('language', 'en')
        ->set('catalogOption', 'new')
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Feed imported successfully.');

    // Assert no new catalog was created
    expect(ProductCatalog::where('team_id', $this->team->id)->count())->toBe(1);

    // Assert feed was assigned to existing catalog
    $feed = ProductFeed::where('team_id', $this->team->id)->first();
    expect($feed->product_catalog_id)->toBe($existingCatalog->id);
});

test('same feed url with different language is allowed', function () {
    // Create an existing feed in English
    ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'English Feed',
        'feed_url' => 'https://example.com/feed.xml',
        'language' => 'en',
    ]);

    $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>SKU-001</g:id>
<g:title>Produkt</g:title>
<g:link>https://example.com/product</g:link>
</item>
</channel>
</rss>
XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', 'https://example.com/feed.xml')
        ->call('fetchFields')
        ->set('feedName', 'Swedish Feed')
        ->set('language', 'sv') // Different language
        ->set('catalogOption', 'new')
        ->set('mapping.sku', 'g:id')
        ->set('mapping.title', 'g:title')
        ->set('mapping.url', 'g:link')
        ->call('importFeed')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', 'Feed imported successfully.');

    // Assert both feeds exist
    expect(ProductFeed::where('team_id', $this->team->id)->count())->toBe(2);
});
