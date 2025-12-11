<?php

use App\Jobs\SyncStoreProducts;
use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
use App\Models\StoreSyncJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'store-integrations.platforms.shopify.client_id' => 'test-client-id',
        'store-integrations.platforms.shopify.client_secret' => 'test-client-secret',
    ]);
});

test('sync job creates product feed if none exists', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Test Store',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $connection->refresh();

    expect($connection->product_feed_id)->not()->toBeNull();
    expect(ProductFeed::where('store_connection_id', $connection->id)->exists())->toBeTrue();
});

test('sync job imports products from shopify', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Test Product',
                                    'descriptionHtml' => '<p>Description</p>',
                                    'handle' => 'test-product',
                                    'vendor' => 'Test Vendor',
                                    'productType' => 'Widget',
                                    'status' => 'ACTIVE',
                                    'featuredImage' => ['url' => 'https://example.com/image.jpg'],
                                    'images' => ['edges' => []],
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'title' => 'Default',
                                                    'sku' => 'TEST-SKU-001',
                                                    'barcode' => '123456789',
                                                    'price' => '19.99',
                                                    'compareAtPrice' => null,
                                                    'inventoryQuantity' => 100,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $this->assertDatabaseHas('products', [
        'team_id' => $this->team->id,
        'title' => 'Test Product',
        'sku' => 'TEST-SKU-001',
    ]);
});

test('sync job creates sync job record', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $this->assertDatabaseHas('store_sync_jobs', [
        'store_connection_id' => $connection->id,
        'team_id' => $this->team->id,
        'status' => StoreSyncJob::STATUS_COMPLETED,
    ]);
});

test('sync job updates connection status on success', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'last_synced_at' => null,
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $connection->refresh();

    expect($connection->status)->toBe(StoreConnection::STATUS_CONNECTED);
    expect($connection->last_synced_at)->not()->toBeNull();
    expect($connection->last_error)->toBeNull();
});

test('sync job marks connection as error on failure', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::response(['errors' => ['message' => 'Unauthorized']], 401),
    ]);

    $job = new SyncStoreProducts($connection);

    expect(fn () => $job->handle())->toThrow(\RuntimeException::class);

    $connection->refresh();

    expect($connection->status)->toBe(StoreConnection::STATUS_ERROR);
    expect($connection->last_error)->not()->toBeNull();
});

test('sync job deletes stale products', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
    ]);
    $connection->update(['product_feed_id' => $feed->id]);

    $existingProduct = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'sku' => 'OLD-SKU-TO-DELETE',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $this->assertDatabaseMissing('products', ['id' => $existingProduct->id]);
});

test('sync job updates existing products', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
    ]);
    $connection->update(['product_feed_id' => $feed->id]);

    $existingProduct = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'sku' => 'TEST-SKU-001',
        'title' => 'Old Title',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Test Store']]])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Updated Title',
                                    'descriptionHtml' => '',
                                    'handle' => 'test-product',
                                    'vendor' => null,
                                    'productType' => null,
                                    'status' => 'ACTIVE',
                                    'featuredImage' => null,
                                    'images' => ['edges' => []],
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'title' => 'Default',
                                                    'sku' => 'TEST-SKU-001',
                                                    'barcode' => null,
                                                    'price' => '29.99',
                                                    'compareAtPrice' => null,
                                                    'inventoryQuantity' => 50,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    $existingProduct->refresh();

    expect($existingProduct->title)->toBe('Updated Title');
    expect($existingProduct->description)->toBe('');
});
