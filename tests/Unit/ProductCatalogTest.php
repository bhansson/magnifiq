<?php

use App\Models\Product;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('catalog can be created', function () {
    $team = Team::factory()->create();

    $catalog = ProductCatalog::create([
        'team_id' => $team->id,
        'name' => 'Main Store Catalog',
    ]);

    $this->assertDatabaseHas('product_catalogs', [
        'id' => $catalog->id,
        'team_id' => $team->id,
        'name' => 'Main Store Catalog',
    ]);
});

test('catalog has team relationship', function () {
    $team = Team::factory()->create();
    $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

    expect($catalog->team)->toBeInstanceOf(Team::class);
    expect($catalog->team->id)->toEqual($team->id);
});

test('catalog has feeds relationship', function () {
    $catalog = ProductCatalog::factory()->create();

    $feed1 = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feed2 = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    expect($catalog->feeds)->toHaveCount(2);
    expect($catalog->feeds->contains($feed1))->toBeTrue();
    expect($catalog->feeds->contains($feed2))->toBeTrue();
});

test('catalog languages returns unique languages', function () {
    $catalog = ProductCatalog::factory()->create();

    ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'de',
    ]);

    $languages = $catalog->languages();

    expect($languages)->toHaveCount(3);
    expect($languages->contains('en'))->toBeTrue();
    expect($languages->contains('sv'))->toBeTrue();
    expect($languages->contains('de'))->toBeTrue();
});

test('catalog products returns products from all feeds', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    $productEn = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
        'title' => 'Product English',
    ]);

    $productSv = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
        'title' => 'Product Swedish',
    ]);

    expect($catalog->products)->toHaveCount(2);
    expect($catalog->products->contains($productEn))->toBeTrue();
    expect($catalog->products->contains($productSv))->toBeTrue();
});

test('catalog products count', function () {
    $catalog = ProductCatalog::factory()->create();

    $feed = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    Product::factory()->count(5)->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feed->id,
    ]);

    expect($catalog->productsCount())->toEqual(5);
});

test('catalog is empty when no feeds', function () {
    $catalog = ProductCatalog::factory()->create();

    expect($catalog->isEmpty())->toBeTrue();
});

test('catalog is not empty when has feeds', function () {
    $catalog = ProductCatalog::factory()->create();

    ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    expect($catalog->isEmpty())->toBeFalse();
});

test('distinct products returns one per sku', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    // Same SKU in both feeds
    Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
    ]);

    Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
    ]);

    // Different SKU
    Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-002',
    ]);

    $distinct = $catalog->distinctProducts('en');

    expect($distinct)->toHaveCount(2);
});

test('distinct products prefers primary language', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    $productEn = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
        'title' => 'English Title',
    ]);

    Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
        'title' => 'Swedish Title',
    ]);

    $distinct = $catalog->distinctProducts('en');

    expect($distinct)->toHaveCount(1);
    expect($distinct->first()->title)->toEqual('English Title');
});

test('feed is in catalog', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedInCatalog = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    $feedStandalone = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => null,
    ]);

    expect($feedInCatalog->isInCatalog())->toBeTrue();
    expect($feedStandalone->isInCatalog())->toBeFalse();
});

test('feed catalog relationship', function () {
    $catalog = ProductCatalog::factory()->create();

    $feed = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    expect($feed->catalog)->toBeInstanceOf(ProductCatalog::class);
    expect($feed->catalog->id)->toEqual($catalog->id);
});

test('product sibling products returns same sku in catalog', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    $feedDe = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'de',
    ]);

    $productEn = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
    ]);

    $productSv = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
    ]);

    $productDe = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedDe->id,
        'sku' => 'SKU-001',
    ]);

    $siblings = $productEn->siblingProducts();

    expect($siblings)->toHaveCount(2);
    expect($siblings->contains($productSv))->toBeTrue();
    expect($siblings->contains($productDe))->toBeTrue();
    expect($siblings->contains($productEn))->toBeFalse();
});

test('product sibling products empty when not in catalog', function () {
    $feed = ProductFeed::factory()->create(['product_catalog_id' => null]);

    $product = Product::factory()->create([
        'team_id' => $feed->team_id,
        'product_feed_id' => $feed->id,
    ]);

    expect($product->siblingProducts()->isEmpty())->toBeTrue();
});

test('product all language versions includes self', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    $productEn = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
    ]);

    $productSv = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
    ]);

    $versions = $productEn->allLanguageVersions();

    expect($versions)->toHaveCount(2);
    expect($versions->contains($productEn))->toBeTrue();
    expect($versions->contains($productSv))->toBeTrue();
});

test('product all language versions only self when not in catalog', function () {
    $feed = ProductFeed::factory()->create(['product_catalog_id' => null]);

    $product = Product::factory()->create([
        'team_id' => $feed->team_id,
        'product_feed_id' => $feed->id,
    ]);

    $versions = $product->allLanguageVersions();

    expect($versions)->toHaveCount(1);
    expect($versions->contains($product))->toBeTrue();
});

test('product has language siblings', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedEn = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    $feedSv = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
        'language' => 'sv',
    ]);

    $productEn = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-001',
    ]);

    Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedSv->id,
        'sku' => 'SKU-001',
    ]);

    $productAlone = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedEn->id,
        'sku' => 'SKU-002',
    ]);

    expect($productEn->hasLanguageSiblings())->toBeTrue();
    expect($productAlone->hasLanguageSiblings())->toBeFalse();
});

test('product is in catalog', function () {
    $catalog = ProductCatalog::factory()->create();

    $feedInCatalog = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    $feedStandalone = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => null,
    ]);

    $productInCatalog = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedInCatalog->id,
    ]);

    $productStandalone = Product::factory()->create([
        'team_id' => $catalog->team_id,
        'product_feed_id' => $feedStandalone->id,
    ]);

    expect($productInCatalog->isInCatalog())->toBeTrue();
    expect($productStandalone->isInCatalog())->toBeFalse();
});

test('deleting catalog sets feeds to standalone', function () {
    $catalog = ProductCatalog::factory()->create();

    $feed = ProductFeed::factory()->create([
        'team_id' => $catalog->team_id,
        'product_catalog_id' => $catalog->id,
    ]);

    $catalog->delete();

    $feed->refresh();

    expect($feed->product_catalog_id)->toBeNull();
    expect($feed->isInCatalog())->toBeFalse();
});

test('catalog factory with feeds', function () {
    $catalog = ProductCatalog::factory()
        ->withFeeds(['en', 'sv', 'de'])
        ->create();

    expect($catalog->feeds)->toHaveCount(3);
    expect($catalog->languages()->contains('en'))->toBeTrue();
    expect($catalog->languages()->contains('sv'))->toBeTrue();
    expect($catalog->languages()->contains('de'))->toBeTrue();
});