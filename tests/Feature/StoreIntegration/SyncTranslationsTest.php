<?php

use App\Jobs\SyncAiContentToStore;
use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'store-integrations.platforms.shopify.client_id' => 'test-client-id',
        'store-integrations.platforms.shopify.client_secret' => 'test-client-secret',
    ]);
});

test('syncs primary language content to metafield value', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'en',
    ]);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => 'gid://shopify/Product/123',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'English product description',
            'unpublished_at' => null,
        ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // getShopLocales
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                        ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
                    ],
                ],
            ])
            // writeProductMetafield (productUpdate)
            ->push([
                'data' => [
                    'productUpdate' => [
                        'product' => ['id' => 'gid://shopify/Product/123'],
                        'userErrors' => [],
                    ],
                ],
            ]),
    ]);

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    Http::assertSentCount(2);
});

test('syncs secondary language content as translation', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'de', // German - not primary
    ]);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => 'gid://shopify/Product/123',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'German product description',
            'unpublished_at' => null,
        ]);

    $shopLocalesResponse = [
        'data' => [
            'shopLocales' => [
                ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
            ],
        ],
    ];

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // 1. getShopLocales (for isPrimaryLanguage -> getPrimaryLocale)
            ->push($shopLocalesResponse)
            // 2. getShopLocales (for isLocalePublished -> getPublishedLocales)
            ->push($shopLocalesResponse)
            // 3. getMetafieldTranslatableContent
            ->push([
                'data' => [
                    'translatableResource' => [
                        'resourceId' => 'gid://shopify/Product/123',
                        'nestedTranslatableResources' => [
                            'nodes' => [
                                [
                                    'resourceId' => 'gid://shopify/Metafield/456',
                                    'translatableContent' => [
                                        ['key' => 'value', 'value' => 'English content', 'digest' => 'abc123', 'locale' => 'en'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            // 4. isMetafieldMatch (internal call to verify namespace/key)
            ->push([
                'data' => [
                    'node' => [
                        'namespace' => 'magnifiq',
                        'key' => 'description',
                    ],
                ],
            ])
            // 5. registerTranslation
            ->push([
                'data' => [
                    'translationsRegister' => [
                        'translations' => [
                            ['locale' => 'de', 'key' => 'value', 'value' => 'German product description'],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ]),
    ]);

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    Http::assertSentCount(5);
});

test('skips unpublished locales', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'fr', // French - not published in store
    ]);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => 'gid://shopify/Product/123',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'French product description',
            'unpublished_at' => null,
        ]);

    $shopLocalesResponse = [
        'data' => [
            'shopLocales' => [
                ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
                ['locale' => 'fr', 'name' => 'French', 'primary' => false, 'published' => false],
            ],
        ],
    ];

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // 1. getShopLocales (for isPrimaryLanguage -> getPrimaryLocale)
            ->push($shopLocalesResponse)
            // 2. getShopLocales (for isLocalePublished -> getPublishedLocales)
            ->push($shopLocalesResponse),
    ]);

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    // 2 calls: isPrimaryLanguage + isLocalePublished. No translation sync because locale not published.
    Http::assertSentCount(2);
});

test('queues primary content sync when metafield not found for translation', function () {
    Queue::fake();

    $catalog = ProductCatalog::factory()->create([
        'team_id' => $this->team->id,
    ]);

    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    // English feed (primary)
    $englishFeed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'product_catalog_id' => $catalog->id,
        'language' => 'en',
    ]);

    // German feed (secondary)
    $germanFeed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'product_catalog_id' => $catalog->id,
        'language' => 'de',
    ]);

    // Same SKU = sibling products
    $englishProduct = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $englishFeed->id,
        'external_id' => 'gid://shopify/Product/123',
        'sku' => 'SHARED-SKU',
    ]);

    $germanProduct = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $germanFeed->id,
        'external_id' => 'gid://shopify/Product/123',
        'sku' => 'SHARED-SKU',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    // English generation (primary - published)
    $englishGeneration = ProductAiGeneration::factory()
        ->forProduct($englishProduct)
        ->forTemplate($template)
        ->create([
            'content' => 'English product description',
            'unpublished_at' => null,
        ]);

    // German generation (secondary)
    $germanGeneration = ProductAiGeneration::factory()
        ->forProduct($germanProduct)
        ->forTemplate($template)
        ->create([
            'content' => 'German product description',
            'unpublished_at' => null,
        ]);

    $shopLocalesResponse = [
        'data' => [
            'shopLocales' => [
                ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
            ],
        ],
    ];

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // 1. getShopLocales (for isPrimaryLanguage -> getPrimaryLocale)
            ->push($shopLocalesResponse)
            // 2. getShopLocales (for isLocalePublished -> getPublishedLocales)
            ->push($shopLocalesResponse)
            // 3. getMetafieldTranslatableContent - metafield not found
            ->push([
                'data' => [
                    'translatableResource' => [
                        'resourceId' => 'gid://shopify/Product/123',
                        'nestedTranslatableResources' => [
                            'nodes' => [], // No metafields - triggers handleMissingPrimaryContent
                        ],
                    ],
                ],
            ]),
    ]);

    $job = new SyncAiContentToStore($germanGeneration->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    // Should queue sync for primary (English) first, then re-queue German with delay
    Queue::assertPushed(SyncAiContentToStore::class, function ($job) use ($englishGeneration) {
        return $job->generationId === $englishGeneration->id;
    });

    Queue::assertPushed(SyncAiContentToStore::class, function ($job) use ($germanGeneration) {
        return $job->generationId === $germanGeneration->id;
    });
});

test('removes translation when unpublished', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'de', // German - not primary
    ]);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => 'gid://shopify/Product/123',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'German product description',
            'unpublished_at' => now(), // Unpublished
        ]);

    $shopLocalesResponse = [
        'data' => [
            'shopLocales' => [
                ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => true],
            ],
        ],
    ];

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // 1. getShopLocales (for isPrimaryLanguage -> getPrimaryLocale)
            ->push($shopLocalesResponse)
            // 2. getShopLocales (for isLocalePublished -> getPublishedLocales)
            ->push($shopLocalesResponse)
            // 3. getMetafieldTranslatableContent
            ->push([
                'data' => [
                    'translatableResource' => [
                        'resourceId' => 'gid://shopify/Product/123',
                        'nestedTranslatableResources' => [
                            'nodes' => [
                                [
                                    'resourceId' => 'gid://shopify/Metafield/456',
                                    'translatableContent' => [
                                        ['key' => 'value', 'value' => 'English content', 'digest' => 'abc123', 'locale' => 'en'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            // 4. isMetafieldMatch (internal call to verify namespace/key)
            ->push([
                'data' => [
                    'node' => [
                        'namespace' => 'magnifiq',
                        'key' => 'description',
                    ],
                ],
            ])
            // 5. removeTranslation
            ->push([
                'data' => [
                    'translationsRemove' => [
                        'translations' => [
                            ['locale' => 'de', 'key' => 'value'],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ]),
    ]);

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    Http::assertSentCount(5);
});

test('deletes primary metafield when unpublished', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'en', // Primary
    ]);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => 'gid://shopify/Product/123',
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'English product description',
            'unpublished_at' => now(), // Unpublished
        ]);

    Http::fake([
        '*/admin/api/*/graphql.json' => Http::sequence()
            // getShopLocales
            ->push([
                'data' => [
                    'shopLocales' => [
                        ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                    ],
                ],
            ])
            // deleteProductMetafield (metafieldsDelete)
            ->push([
                'data' => [
                    'metafieldsDelete' => [
                        'deletedMetafields' => [
                            ['key' => 'description', 'namespace' => 'magnifiq', 'ownerId' => 'gid://shopify/Product/123'],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ]),
    ]);

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    Http::assertSentCount(2);
});

test('products without external_id are skipped', function () {
    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $feed = ProductFeed::factory()->create([
        'team_id' => $this->team->id,
        'store_connection_id' => $connection->id,
        'language' => 'en',
    ]);

    // Product without external_id - can't sync to store
    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'product_feed_id' => $feed->id,
        'external_id' => null,
    ]);

    $template = ProductAiTemplate::factory()->create([
        'team_id' => $this->team->id,
        'slug' => 'description',
    ]);

    $generation = ProductAiGeneration::factory()
        ->forProduct($product)
        ->forTemplate($template)
        ->create([
            'content' => 'Product description',
            'unpublished_at' => null,
        ]);

    Http::fake();

    $job = new SyncAiContentToStore($generation->id);
    $job->handle(app(\App\Services\StoreIntegration\ShopifyLocaleService::class));

    // No API calls should be made for product without external_id
    Http::assertNothingSent();
});
