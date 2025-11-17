<?php

namespace Tests\Feature\Products;

use App\Livewire\ManageProductFeeds;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductFeedRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refreshing_feed_preserves_product_ids_and_ai_job_references(): void
    {
        // Arrange: Create user, team, and feed
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'feed_url' => 'https://example.com/feed.xml',
            'field_mappings' => [
                'sku' => 'g:id',
                'title' => 'g:title',
                'url' => 'g:link',
            ],
        ]);

        // Create initial products
        $product1 = Product::factory()->create([
            'product_feed_id' => $feed->id,
            'team_id' => $team->id,
            'sku' => 'SKU-001',
            'title' => 'Product One',
            'url' => 'https://example.com/product-1',
        ]);

        $product2 = Product::factory()->create([
            'product_feed_id' => $feed->id,
            'team_id' => $team->id,
            'sku' => 'SKU-002',
            'title' => 'Product Two',
            'url' => 'https://example.com/product-2',
        ]);

        // Create AI job for product1
        $aiJob = ProductAiJob::factory()->create([
            'team_id' => $team->id,
            'product_id' => $product1->id,
            'sku' => $product1->sku,
            'status' => ProductAiJob::STATUS_COMPLETED,
        ]);

        // Store the original product IDs
        $originalProduct1Id = $product1->id;
        $originalProduct2Id = $product2->id;

        // Simulate feed refresh with updated data
        $feedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>SKU-001</g:id>
<g:title>Product One (Updated)</g:title>
<g:link>https://example.com/product-1</g:link>
</item>
<item>
<g:id>SKU-002</g:id>
<g:title>Product Two (Updated)</g:title>
<g:link>https://example.com/product-2</g:link>
</item>
<item>
<g:id>SKU-003</g:id>
<g:title>Product Three (New)</g:title>
<g:link>https://example.com/product-3</g:link>
</item>
</channel>
</rss>
XML;

        Http::fake([
            'example.com/*' => Http::response($feedXml, 200),
        ]);

        $this->actingAs($user);

        // Act: Refresh the feed
        Livewire::test(ManageProductFeeds::class)
            ->call('refreshFeed', $feed->id);

        // Assert: Products with matching SKUs should still exist with same IDs
        $this->assertDatabaseHas('products', [
            'id' => $originalProduct1Id,
            'sku' => 'SKU-001',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $originalProduct2Id,
            'sku' => 'SKU-002',
        ]);

        // Reload the products
        $product1 = Product::find($originalProduct1Id);
        $product2 = Product::find($originalProduct2Id);

        $this->assertNotNull($product1, 'Product 1 should still exist');
        $this->assertNotNull($product2, 'Product 2 should still exist');

        // Assert: Product data should be updated
        $this->assertEquals('Product One (Updated)', $product1->title);
        $this->assertEquals('Product Two (Updated)', $product2->title);

        // Assert: New product should be added
        $this->assertDatabaseHas('products', [
            'product_feed_id' => $feed->id,
            'sku' => 'SKU-003',
            'title' => 'Product Three (New)',
        ]);

        // Assert: AI job should still reference the correct product
        $aiJob->refresh();
        $this->assertEquals($originalProduct1Id, $aiJob->product_id, 'AI job should still reference the same product ID');
        $this->assertNotNull($aiJob->product, 'AI job should be able to load the product relationship');
        $this->assertEquals('Product One (Updated)', $aiJob->product->title);

        // Assert: Total product count should be 3
        $this->assertEquals(3, Product::where('product_feed_id', $feed->id)->count());
    }
}
