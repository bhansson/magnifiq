<?php

use App\Models\StoreConnection;
use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use App\Services\StoreIntegration\ShopifyLocaleService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('resolves magnifiq language to shopify locale for direct mappings', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $service = new ShopifyLocaleService($adapter);

    expect($service->resolveShopifyLocale('en'))->toBe('en');
    expect($service->resolveShopifyLocale('de'))->toBe('de');
    expect($service->resolveShopifyLocale('fr'))->toBe('fr');
    expect($service->resolveShopifyLocale('sv'))->toBe('sv');
    expect($service->resolveShopifyLocale('ja'))->toBe('ja');
});

test('resolves magnifiq language to shopify locale for regional variants', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $service = new ShopifyLocaleService($adapter);

    expect($service->resolveShopifyLocale('en-gb'))->toBe('en-GB');
    expect($service->resolveShopifyLocale('en-us'))->toBe('en-US');
    expect($service->resolveShopifyLocale('pt-br'))->toBe('pt-BR');
});

test('resolves norwegian variants correctly', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $service = new ShopifyLocaleService($adapter);

    // Norwegian (no) should map to Norwegian Bokmal (nb)
    expect($service->resolveShopifyLocale('no'))->toBe('nb');
    expect($service->resolveShopifyLocale('nb'))->toBe('nb');
});

test('resolves unknown language to itself', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $service = new ShopifyLocaleService($adapter);

    expect($service->resolveShopifyLocale('zh'))->toBe('zh');
    expect($service->resolveShopifyLocale('ko'))->toBe('ko');
});

test('normalizes case when resolving locale', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $service = new ShopifyLocaleService($adapter);

    expect($service->resolveShopifyLocale('EN'))->toBe('en');
    expect($service->resolveShopifyLocale('EN-GB'))->toBe('en-GB');
    expect($service->resolveShopifyLocale(' de '))->toBe('de');
});

test('detects primary language correctly', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getPrimaryLocale')->andReturn('en');

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    expect($service->isPrimaryLanguage($connection, 'en'))->toBeTrue();
    expect($service->isPrimaryLanguage($connection, 'de'))->toBeFalse();
    expect($service->isPrimaryLanguage($connection, 'fr'))->toBeFalse();
});

test('matches base language for regional variants', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getPrimaryLocale')->andReturn('en-US');

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // 'en' should match 'en-US' (base language match)
    expect($service->isPrimaryLanguage($connection, 'en'))->toBeTrue();
    expect($service->isPrimaryLanguage($connection, 'en-gb'))->toBeTrue();
    expect($service->isPrimaryLanguage($connection, 'de'))->toBeFalse();
});

test('returns true for primary when no primary locale found', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getPrimaryLocale')->andReturn(null);

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // When no primary is found, treat everything as primary to avoid breaking sync
    expect($service->isPrimaryLanguage($connection, 'en'))->toBeTrue();
    expect($service->isPrimaryLanguage($connection, 'de'))->toBeTrue();
});

test('checks if locale is published', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getShopLocales')->andReturn([
        ['locale' => 'en', 'primary' => true, 'published' => true],
        ['locale' => 'de', 'primary' => false, 'published' => true],
        ['locale' => 'fr', 'primary' => false, 'published' => false],
    ]);

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    expect($service->isLocalePublished($connection, 'en'))->toBeTrue();
    expect($service->isLocalePublished($connection, 'de'))->toBeTrue();
    expect($service->isLocalePublished($connection, 'fr'))->toBeFalse();
    expect($service->isLocalePublished($connection, 'es'))->toBeFalse();
});

test('matches locale variants when checking published status', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getShopLocales')->andReturn([
        ['locale' => 'en-US', 'primary' => true, 'published' => true],
        ['locale' => 'de-DE', 'primary' => false, 'published' => true],
    ]);

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // 'en' should match 'en-US'
    expect($service->isLocalePublished($connection, 'en'))->toBeTrue();
    expect($service->isLocalePublished($connection, 'en-gb'))->toBeTrue();
    expect($service->isLocalePublished($connection, 'de'))->toBeTrue();
});

test('caches primary locale', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getPrimaryLocale')->once()->andReturn('en');

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // First call - should hit adapter
    $result1 = $service->getPrimaryLocale($connection);

    // Second call - should use cache
    $result2 = $service->getPrimaryLocale($connection);

    expect($result1)->toBe('en');
    expect($result2)->toBe('en');
});

test('caches published locales', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getShopLocales')->once()->andReturn([
        ['locale' => 'en', 'primary' => true, 'published' => true],
        ['locale' => 'de', 'primary' => false, 'published' => true],
    ]);

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // First call - should hit adapter
    $result1 = $service->getPublishedLocales($connection);

    // Second call - should use cache
    $result2 = $service->getPublishedLocales($connection);

    expect($result1)->toHaveCount(2);
    expect($result2)->toHaveCount(2);
});

test('clears cache for connection', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getPrimaryLocale')->twice()->andReturn('en', 'de');

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    // First call - cache 'en'
    $result1 = $service->getPrimaryLocale($connection);
    expect($result1)->toBe('en');

    // Clear cache
    $service->clearCache($connection);

    // Second call - should hit adapter again and return 'de'
    $result2 = $service->getPrimaryLocale($connection);
    expect($result2)->toBe('de');
});

test('filters published locales only', function () {
    $adapter = Mockery::mock(ShopifyAdapter::class);
    $adapter->shouldReceive('getShopLocales')->andReturn([
        ['locale' => 'en', 'primary' => true, 'published' => true],
        ['locale' => 'de', 'primary' => false, 'published' => true],
        ['locale' => 'fr', 'primary' => false, 'published' => false],
        ['locale' => 'es', 'primary' => false, 'published' => false],
    ]);

    $service = new ShopifyLocaleService($adapter);
    $connection = StoreConnection::factory()->make(['id' => 1]);

    $published = $service->getPublishedLocales($connection);

    expect($published)->toHaveCount(2);
    expect(collect($published)->pluck('locale')->toArray())->toBe(['en', 'de']);
});
