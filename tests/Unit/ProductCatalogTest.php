<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_can_be_created(): void
    {
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
    }

    public function test_catalog_has_team_relationship(): void
    {
        $team = Team::factory()->create();
        $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

        $this->assertInstanceOf(Team::class, $catalog->team);
        $this->assertEquals($team->id, $catalog->team->id);
    }

    public function test_catalog_has_feeds_relationship(): void
    {
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

        $this->assertCount(2, $catalog->feeds);
        $this->assertTrue($catalog->feeds->contains($feed1));
        $this->assertTrue($catalog->feeds->contains($feed2));
    }

    public function test_catalog_languages_returns_unique_languages(): void
    {
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

        $this->assertCount(3, $languages);
        $this->assertTrue($languages->contains('en'));
        $this->assertTrue($languages->contains('sv'));
        $this->assertTrue($languages->contains('de'));
    }

    public function test_catalog_products_returns_products_from_all_feeds(): void
    {
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

        $this->assertCount(2, $catalog->products);
        $this->assertTrue($catalog->products->contains($productEn));
        $this->assertTrue($catalog->products->contains($productSv));
    }

    public function test_catalog_products_count(): void
    {
        $catalog = ProductCatalog::factory()->create();

        $feed = ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => $catalog->id,
        ]);

        Product::factory()->count(5)->create([
            'team_id' => $catalog->team_id,
            'product_feed_id' => $feed->id,
        ]);

        $this->assertEquals(5, $catalog->productsCount());
    }

    public function test_catalog_is_empty_when_no_feeds(): void
    {
        $catalog = ProductCatalog::factory()->create();

        $this->assertTrue($catalog->isEmpty());
    }

    public function test_catalog_is_not_empty_when_has_feeds(): void
    {
        $catalog = ProductCatalog::factory()->create();

        ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => $catalog->id,
        ]);

        $this->assertFalse($catalog->isEmpty());
    }

    public function test_distinct_products_returns_one_per_sku(): void
    {
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

        $this->assertCount(2, $distinct);
    }

    public function test_distinct_products_prefers_primary_language(): void
    {
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

        $this->assertCount(1, $distinct);
        $this->assertEquals('English Title', $distinct->first()->title);
    }

    public function test_feed_is_in_catalog(): void
    {
        $catalog = ProductCatalog::factory()->create();

        $feedInCatalog = ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => $catalog->id,
        ]);

        $feedStandalone = ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => null,
        ]);

        $this->assertTrue($feedInCatalog->isInCatalog());
        $this->assertFalse($feedStandalone->isInCatalog());
    }

    public function test_feed_catalog_relationship(): void
    {
        $catalog = ProductCatalog::factory()->create();

        $feed = ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => $catalog->id,
        ]);

        $this->assertInstanceOf(ProductCatalog::class, $feed->catalog);
        $this->assertEquals($catalog->id, $feed->catalog->id);
    }

    public function test_product_sibling_products_returns_same_sku_in_catalog(): void
    {
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

        $this->assertCount(2, $siblings);
        $this->assertTrue($siblings->contains($productSv));
        $this->assertTrue($siblings->contains($productDe));
        $this->assertFalse($siblings->contains($productEn));
    }

    public function test_product_sibling_products_empty_when_not_in_catalog(): void
    {
        $feed = ProductFeed::factory()->create(['product_catalog_id' => null]);

        $product = Product::factory()->create([
            'team_id' => $feed->team_id,
            'product_feed_id' => $feed->id,
        ]);

        $this->assertTrue($product->siblingProducts()->isEmpty());
    }

    public function test_product_all_language_versions_includes_self(): void
    {
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

        $this->assertCount(2, $versions);
        $this->assertTrue($versions->contains($productEn));
        $this->assertTrue($versions->contains($productSv));
    }

    public function test_product_all_language_versions_only_self_when_not_in_catalog(): void
    {
        $feed = ProductFeed::factory()->create(['product_catalog_id' => null]);

        $product = Product::factory()->create([
            'team_id' => $feed->team_id,
            'product_feed_id' => $feed->id,
        ]);

        $versions = $product->allLanguageVersions();

        $this->assertCount(1, $versions);
        $this->assertTrue($versions->contains($product));
    }

    public function test_product_has_language_siblings(): void
    {
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

        $this->assertTrue($productEn->hasLanguageSiblings());
        $this->assertFalse($productAlone->hasLanguageSiblings());
    }

    public function test_product_is_in_catalog(): void
    {
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

        $this->assertTrue($productInCatalog->isInCatalog());
        $this->assertFalse($productStandalone->isInCatalog());
    }

    public function test_deleting_catalog_sets_feeds_to_standalone(): void
    {
        $catalog = ProductCatalog::factory()->create();

        $feed = ProductFeed::factory()->create([
            'team_id' => $catalog->team_id,
            'product_catalog_id' => $catalog->id,
        ]);

        $catalog->delete();

        $feed->refresh();

        $this->assertNull($feed->product_catalog_id);
        $this->assertFalse($feed->isInCatalog());
    }

    public function test_catalog_factory_with_feeds(): void
    {
        $catalog = ProductCatalog::factory()
            ->withFeeds(['en', 'sv', 'de'])
            ->create();

        $this->assertCount(3, $catalog->feeds);
        $this->assertTrue($catalog->languages()->contains('en'));
        $this->assertTrue($catalog->languages()->contains('sv'));
        $this->assertTrue($catalog->languages()->contains('de'));
    }
}
