<?php

use App\Jobs\SyncStoreProducts;
use App\Models\Product;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
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

test('multi-language store creates catalog and feeds per locale', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Multi-Lang Store',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // testConnection
            ->push(['data' => ['shop' => ['name' => 'Multi-Lang Store']]])
            // getShopLocales - returns 2 locales
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                        ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
                    ],
                ],
            ])
            // fetchProducts for primary locale (en)
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Test Product',
                                    'descriptionHtml' => '<p>English description</p>',
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
                                                    'barcode' => null,
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
            ])
            // fetchProductsForLocale for secondary locale (de)
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Test Product',
                                    'descriptionHtml' => '<p>English description</p>',
                                    'handle' => 'test-product',
                                    'vendor' => 'Test Vendor',
                                    'productType' => 'Widget',
                                    'status' => 'ACTIVE',
                                    'featuredImage' => ['url' => 'https://example.com/image.jpg'],
                                    'images' => ['edges' => []],
                                    'translations' => [
                                        ['key' => 'title', 'value' => 'Testprodukt'],
                                        ['key' => 'body_html', 'value' => '<p>Deutsche Beschreibung</p>'],
                                    ],
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'title' => 'Default',
                                                    'sku' => 'TEST-SKU-001',
                                                    'barcode' => null,
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

    // Should create a catalog
    $catalog = ProductCatalog::where('team_id', $this->team->id)->first();
    expect($catalog)->not()->toBeNull();
    expect($catalog->name)->toBe('Multi-Lang Store');

    // Should create 2 feeds in the catalog
    $feeds = ProductFeed::where('store_connection_id', $connection->id)->get();
    expect($feeds)->toHaveCount(2);

    // Both feeds should be in the catalog
    expect($feeds->pluck('product_catalog_id')->unique()->first())->toBe($catalog->id);

    // Should have feeds for both languages
    expect($feeds->pluck('language')->sort()->values()->all())->toBe(['de', 'en']);
});

test('translated content is imported correctly', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Multi-Lang Store',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Multi-Lang Store']]])
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                        ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
                    ],
                ],
            ])
            // English products
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'English Title',
                                    'descriptionHtml' => '<p>English description</p>',
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
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ])
            // German products with translations
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'English Title',
                                    'descriptionHtml' => '<p>English description</p>',
                                    'handle' => 'test-product',
                                    'vendor' => null,
                                    'productType' => null,
                                    'status' => 'ACTIVE',
                                    'featuredImage' => null,
                                    'images' => ['edges' => []],
                                    'translations' => [
                                        ['key' => 'title', 'value' => 'Deutscher Titel'],
                                        ['key' => 'body_html', 'value' => '<p>Deutsche Beschreibung</p>'],
                                    ],
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'title' => 'Default',
                                                    'sku' => 'TEST-SKU-001',
                                                    'barcode' => null,
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
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                ],
            ]),
    ]);

    $job = new SyncStoreProducts($connection);
    $job->handle();

    // Check English product
    $englishFeed = ProductFeed::where('store_connection_id', $connection->id)
        ->where('language', 'en')
        ->first();
    $englishProduct = $englishFeed->products()->first();

    expect($englishProduct->title)->toBe('English Title');
    expect($englishProduct->description)->toBe('<p>English description</p>');

    // Check German product
    $germanFeed = ProductFeed::where('store_connection_id', $connection->id)
        ->where('language', 'de')
        ->first();
    $germanProduct = $germanFeed->products()->first();

    expect($germanProduct->title)->toBe('Deutscher Titel');
    expect($germanProduct->description)->toBe('<p>Deutsche Beschreibung</p>');

    // Both should have same SKU
    expect($germanProduct->sku)->toBe($englishProduct->sku);
});

test('products share sku across language feeds in catalog', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Multi-Lang Store',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Multi-Lang Store']]])
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                        ['locale' => 'fr', 'name' => 'French', 'primary' => false, 'published' => true],
                    ],
                ],
            ])
            // English products
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'English Product',
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
                                                    'sku' => 'SHARED-SKU',
                                                    'barcode' => null,
                                                    'price' => '10.00',
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
            ])
            // French products
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'English Product',
                                    'descriptionHtml' => '',
                                    'handle' => 'test-product',
                                    'vendor' => null,
                                    'productType' => null,
                                    'status' => 'ACTIVE',
                                    'featuredImage' => null,
                                    'images' => ['edges' => []],
                                    'translations' => [
                                        ['key' => 'title', 'value' => 'Produit Français'],
                                    ],
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'title' => 'Default',
                                                    'sku' => 'SHARED-SKU',
                                                    'barcode' => null,
                                                    'price' => '10.00',
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

    $catalog = ProductCatalog::where('team_id', $this->team->id)->first();

    // Both language versions should be in catalog with same SKU
    $products = $catalog->products()->get();
    expect($products)->toHaveCount(2);
    expect($products->pluck('sku')->unique()->first())->toBe('SHARED-SKU');

    // getProductsBySku should return both
    $skuProducts = $catalog->getProductsBySku('SHARED-SKU');
    expect($skuProducts)->toHaveCount(2);
});

test('single language store uses legacy sync without catalog', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Single-Lang Store',
    ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Single-Lang Store']]])
            // Single locale - should fall back to legacy sync
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                    ],
                ],
            ])
            ->push([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Single Lang Product',
                                    'descriptionHtml' => '',
                                    'handle' => 'product',
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
                                                    'sku' => 'SINGLE-SKU',
                                                    'barcode' => null,
                                                    'price' => '10.00',
                                                    'compareAtPrice' => null,
                                                    'inventoryQuantity' => 10,
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

    // Should NOT create a catalog
    expect(ProductCatalog::where('team_id', $this->team->id)->exists())->toBeFalse();

    // Should create only one feed
    $feeds = ProductFeed::where('store_connection_id', $connection->id)->get();
    expect($feeds)->toHaveCount(1);

    // Feed should not be in a catalog
    expect($feeds->first()->product_catalog_id)->toBeNull();
});

test('cleanup removes feeds when locale is unpublished', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Multi-Lang Store',
    ]);

    // Create initial catalog with 2 feeds
    $catalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Multi-Lang Store',
    ]);

    $englishFeed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'product_catalog_id' => $catalog->id,
        'store_connection_id' => $connection->id,
        'language' => 'en',
    ]);

    $germanFeed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'product_catalog_id' => $catalog->id,
        'store_connection_id' => $connection->id,
        'language' => 'de',
    ]);

    // Create products in German feed
    Product::factory()->count(3)->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $germanFeed->id,
    ]);

    // Now sync with only English published (German unpublished)
    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            ->push(['data' => ['shop' => ['name' => 'Multi-Lang Store']]])
            // Only English is now published
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                    ],
                ],
            ])
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

    // German feed and its products should be deleted
    expect(ProductFeed::find($germanFeed->id))->toBeNull();
    expect(Product::where('product_feed_id', $germanFeed->id)->exists())->toBeFalse();

    // English feed should still exist
    expect(ProductFeed::find($englishFeed->id))->not()->toBeNull();
});
