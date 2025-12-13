<?php

use App\Jobs\SyncStoreProducts;
use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('reclaims orphaned feed when reconnecting store', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'shop' => ['name' => 'Test Store'],
                'products' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => 'gid://shopify/Product/123',
                                'title' => 'Test Product',
                                'descriptionHtml' => 'Description',
                                'handle' => 'test-product',
                                'vendor' => 'Test',
                                'productType' => 'Widget',
                                'status' => 'ACTIVE',
                                'featuredImage' => null,
                                'images' => ['edges' => []],
                                'variants' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => 'gid://shopify/ProductVariant/456',
                                                'title' => 'Default',
                                                'sku' => 'TEST-SKU',
                                                'barcode' => null,
                                                'price' => '29.99',
                                                'compareAtPrice' => null,
                                                'inventoryQuantity' => 10,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                ],
            ],
        ]),
    ]);

    $team = Team::factory()->create();

    // Simulate an orphaned feed from a previous connection
    $orphanedFeed = ProductFeed::create([
        'team_id' => $team->id,
        'name' => 'Old Store Name',
        'language' => 'en',
        'source_type' => ProductFeed::SOURCE_TYPE_STORE_CONNECTION,
        'store_connection_id' => null, // Orphaned - old connection was deleted
        'field_mappings' => [],
    ]);

    // Create a product with Shopify external_id (from previous sync)
    Product::create([
        'team_id' => $team->id,
        'product_feed_id' => $orphanedFeed->id,
        'sku' => 'TEST-SKU',
        'external_id' => 'gid://shopify/Product/123',
        'title' => 'Old Product Title',
        'url' => 'https://test-store.myshopify.com/products/test-product',
    ]);

    // Create a new store connection (simulating reconnection)
    $newConnection = StoreConnection::factory()->create([
        'team_id' => $team->id,
        'platform' => StoreConnection::PLATFORM_SHOPIFY,
        'store_identifier' => 'test-store.myshopify.com',
        'status' => StoreConnection::STATUS_CONNECTED,
        'product_feed_id' => null, // No feed linked yet
    ]);

    // Run the sync job
    $job = new SyncStoreProducts($newConnection);
    $job->handle();

    // Verify the orphaned feed was reclaimed
    $orphanedFeed->refresh();
    $newConnection->refresh();

    expect($orphanedFeed->store_connection_id)->toBe($newConnection->id);
    expect($newConnection->product_feed_id)->toBe($orphanedFeed->id);

    // Verify no duplicate feed was created
    $feedCount = ProductFeed::where('team_id', $team->id)
        ->where('source_type', ProductFeed::SOURCE_TYPE_STORE_CONNECTION)
        ->count();

    expect($feedCount)->toBe(1);
});

test('creates new feed when no orphaned feed exists', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'shop' => ['name' => 'Test Store'],
                'products' => [
                    'edges' => [],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                ],
            ],
        ]),
    ]);

    $team = Team::factory()->create();

    $connection = StoreConnection::factory()->create([
        'team_id' => $team->id,
        'platform' => StoreConnection::PLATFORM_SHOPIFY,
        'store_identifier' => 'new-store.myshopify.com',
        'status' => StoreConnection::STATUS_CONNECTED,
        'product_feed_id' => null,
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $connection->refresh();

    expect($connection->product_feed_id)->not->toBeNull();

    $feed = ProductFeed::find($connection->product_feed_id);
    expect($feed->store_connection_id)->toBe($connection->id);
    expect($feed->source_type)->toBe(ProductFeed::SOURCE_TYPE_STORE_CONNECTION);
});

test('does not reclaim feed from different team', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'shop' => ['name' => 'Test Store'],
                'products' => [
                    'edges' => [],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                ],
            ],
        ]),
    ]);

    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    // Orphaned feed belongs to team1
    $orphanedFeed = ProductFeed::create([
        'team_id' => $team1->id,
        'name' => 'Team 1 Store',
        'language' => 'en',
        'source_type' => ProductFeed::SOURCE_TYPE_STORE_CONNECTION,
        'store_connection_id' => null,
        'field_mappings' => [],
    ]);

    Product::create([
        'team_id' => $team1->id,
        'product_feed_id' => $orphanedFeed->id,
        'sku' => 'SKU-1',
        'external_id' => 'gid://shopify/Product/999',
        'title' => 'Product',
        'url' => 'https://team1-store.myshopify.com/products/product',
    ]);

    // New connection is for team2
    $connection = StoreConnection::factory()->create([
        'team_id' => $team2->id,
        'platform' => StoreConnection::PLATFORM_SHOPIFY,
        'store_identifier' => 'team2-store.myshopify.com',
        'status' => StoreConnection::STATUS_CONNECTED,
        'product_feed_id' => null,
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    // Orphaned feed should NOT be reclaimed (different team)
    $orphanedFeed->refresh();
    expect($orphanedFeed->store_connection_id)->toBeNull();

    // A new feed should have been created for team2
    $connection->refresh();
    expect($connection->product_feed_id)->not->toBe($orphanedFeed->id);
});
