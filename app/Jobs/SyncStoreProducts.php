<?php

namespace App\Jobs;

use App\Facades\Store;
use App\Models\Product;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
use App\Models\StoreSyncJob;
use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use App\Services\StoreIntegration\ShopifyLocaleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStoreProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 600];

    public function __construct(public StoreConnection $storeConnection)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $syncJob = StoreSyncJob::create([
            'store_connection_id' => $this->storeConnection->id,
            'team_id' => $this->storeConnection->team_id,
            'status' => StoreSyncJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $this->storeConnection->markSyncing();

        try {
            $adapter = Store::forPlatform($this->storeConnection->platform);

            if (! $adapter->testConnection($this->storeConnection)) {
                throw new \RuntimeException('Store connection test failed. Token may be invalid.');
            }

            // Multi-locale sync for Shopify stores
            if ($this->storeConnection->isShopify() && $adapter instanceof ShopifyAdapter) {
                $totalCounts = $this->syncMultiLocale($adapter);
            } else {
                // Single-locale sync for other platforms
                $totalCounts = $this->syncSingleLocale($adapter);
            }

            $this->storeConnection->update([
                'status' => StoreConnection::STATUS_CONNECTED,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $syncJob->update([
                'status' => StoreSyncJob::STATUS_COMPLETED,
                'products_synced' => $totalCounts['synced'],
                'products_created' => $totalCounts['created'],
                'products_updated' => $totalCounts['updated'],
                'products_deleted' => $totalCounts['deleted'],
                'completed_at' => now(),
            ]);

            Log::info('Store product sync completed', [
                'connection_id' => $this->storeConnection->id,
                'platform' => $this->storeConnection->platform,
                'synced' => $totalCounts['synced'],
                'created' => $totalCounts['created'],
                'updated' => $totalCounts['updated'],
                'deleted' => $totalCounts['deleted'],
            ]);

        } catch (Throwable $e) {
            $this->storeConnection->markError($e->getMessage());

            $syncJob->update([
                'status' => StoreSyncJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('Store product sync failed', [
                'connection_id' => $this->storeConnection->id,
                'platform' => $this->storeConnection->platform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync products from a multi-locale Shopify store.
     *
     * Creates one ProductFeed per published locale, optionally grouped
     * under a ProductCatalog for multi-language stores.
     *
     * @return array{synced: int, created: int, updated: int, deleted: int}
     */
    protected function syncMultiLocale(ShopifyAdapter $adapter): array
    {
        $localeService = app(ShopifyLocaleService::class);
        $publishedLocales = $localeService->getPublishedLocales($this->storeConnection);

        // Check if there's an existing catalog for this connection
        $existingCatalog = ProductCatalog::query()
            ->whereHas('feeds', function ($query) {
                $query->where('store_connection_id', $this->storeConnection->id);
            })
            ->where('team_id', $this->storeConnection->team_id)
            ->first();

        // Single-language store with no existing catalog: use legacy sync
        if (count($publishedLocales) <= 1 && ! $existingCatalog) {
            return $this->syncSingleLocale($adapter);
        }

        // Multi-language store OR was multi-language (has catalog): maintain catalog
        $catalog = $existingCatalog ?? $this->ensureProductCatalog();

        $totalCounts = ['synced' => 0, 'created' => 0, 'updated' => 0, 'deleted' => 0];
        $syncedLocales = [];

        foreach ($publishedLocales as $localeData) {
            $feed = $this->ensureProductFeedForLocale($localeData, $catalog);
            $counts = $this->syncProductsForFeed($feed, $localeData, $adapter, $localeService);

            $totalCounts['synced'] += $counts['synced'];
            $totalCounts['created'] += $counts['created'];
            $totalCounts['updated'] += $counts['updated'];
            $totalCounts['deleted'] += $counts['deleted'];

            $syncedLocales[] = $localeData['locale'];
        }

        // Cleanup feeds for locales that are no longer published
        $orphanedDeleted = $this->cleanupOrphanedLocaleFeeds($syncedLocales, $catalog);
        $totalCounts['deleted'] += $orphanedDeleted;

        Log::info('Multi-locale sync completed', [
            'connection_id' => $this->storeConnection->id,
            'locales_synced' => $syncedLocales,
            'catalog_id' => $catalog->id,
        ]);

        return $totalCounts;
    }

    /**
     * Sync products using single-locale (legacy) approach.
     *
     * @return array{synced: int, created: int, updated: int, deleted: int}
     */
    protected function syncSingleLocale($adapter): array
    {
        $feed = $this->ensureProductFeed();
        $existingSkus = $this->getExistingSkus($feed);
        $seenSkus = [];

        $counts = ['synced' => 0, 'created' => 0, 'updated' => 0];

        $batch = [];
        $batchSize = config('store-integrations.sync.batch_size', 100);

        foreach ($adapter->fetchProducts($this->storeConnection) as $storeProduct) {
            $batch[] = $storeProduct;
            $seenSkus[] = $storeProduct->sku;

            if (count($batch) >= $batchSize) {
                $this->processBatch($feed, $batch, $existingSkus, $counts);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            $this->processBatch($feed, $batch, $existingSkus, $counts);
        }

        $deleted = $this->deleteStaleProducts($feed, $seenSkus);

        return [
            'synced' => $counts['synced'],
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'deleted' => $deleted,
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->storeConnection->markError($exception->getMessage());

        Log::error('Store product sync job failed permanently', [
            'connection_id' => $this->storeConnection->id,
            'platform' => $this->storeConnection->platform,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function ensureProductFeed(): ProductFeed
    {
        // Check if connection already has a linked feed
        if ($this->storeConnection->product_feed_id && $this->storeConnection->productFeed) {
            return $this->storeConnection->productFeed;
        }

        // Look for an orphaned feed from a previous connection to this store
        // This handles reconnection scenarios where the old connection was deleted
        $orphanedFeed = $this->findOrphanedFeed();

        if ($orphanedFeed) {
            $this->reclaimOrphanedFeed($orphanedFeed);

            return $orphanedFeed;
        }

        // No existing feed found, create a new one
        $feed = ProductFeed::create([
            'team_id' => $this->storeConnection->team_id,
            'name' => $this->storeConnection->name,
            'language' => 'en',
            'source_type' => ProductFeed::SOURCE_TYPE_STORE_CONNECTION,
            'store_connection_id' => $this->storeConnection->id,
            'field_mappings' => [
                'sku' => 'sku',
                'title' => 'title',
                'description' => 'description',
                'url' => 'url',
                'image_link' => 'image_link',
            ],
        ]);

        $this->storeConnection->update(['product_feed_id' => $feed->id]);
        $this->storeConnection->refresh();

        return $feed;
    }

    /**
     * Find an orphaned feed from a previous connection to this store.
     *
     * When a store connection is deleted and recreated (e.g., after app reinstall),
     * the original feed may still exist with products and AI generations. We look
     * for feeds that match by team, source type, and have Shopify external IDs.
     */
    protected function findOrphanedFeed(): ?ProductFeed
    {
        return ProductFeed::query()
            ->where('team_id', $this->storeConnection->team_id)
            ->where('source_type', ProductFeed::SOURCE_TYPE_STORE_CONNECTION)
            ->where(function ($query) {
                // Either orphaned (null store_connection_id) or already linked to us
                $query->whereNull('store_connection_id')
                    ->orWhere('store_connection_id', $this->storeConnection->id);
            })
            ->whereHas('products', function ($query) {
                // Has products with Shopify external IDs
                $query->where('external_id', 'like', 'gid://shopify/Product/%');
            })
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Reclaim an orphaned feed by linking it to the current store connection.
     */
    protected function reclaimOrphanedFeed(ProductFeed $feed): void
    {
        $feed->update([
            'store_connection_id' => $this->storeConnection->id,
            'name' => $this->storeConnection->name,
        ]);

        $this->storeConnection->update(['product_feed_id' => $feed->id]);
        $this->storeConnection->refresh();

        Log::info('Reclaimed orphaned feed for store connection', [
            'feed_id' => $feed->id,
            'connection_id' => $this->storeConnection->id,
            'store' => $this->storeConnection->store_identifier,
        ]);
    }

    /**
     * Ensure a ProductCatalog exists for this multi-language store.
     */
    protected function ensureProductCatalog(): ProductCatalog
    {
        // Check if any existing feeds for this connection have a catalog
        $existingCatalog = ProductCatalog::query()
            ->whereHas('feeds', function ($query) {
                $query->where('store_connection_id', $this->storeConnection->id);
            })
            ->where('team_id', $this->storeConnection->team_id)
            ->first();

        if ($existingCatalog) {
            return $existingCatalog;
        }

        // Create new catalog for this store
        return ProductCatalog::create([
            'team_id' => $this->storeConnection->team_id,
            'name' => $this->storeConnection->name,
        ]);
    }

    /**
     * Ensure a ProductFeed exists for a specific locale.
     *
     * @param  array{locale: string, name: string, primary: bool, published: bool}  $localeData
     */
    protected function ensureProductFeedForLocale(array $localeData, ProductCatalog $catalog): ProductFeed
    {
        $localeService = app(ShopifyLocaleService::class);
        $magnifiqLanguage = $localeService->mapShopifyLocaleToMagnifiq($localeData['locale']);

        // Look for existing feed with this language for this connection
        $existingFeed = ProductFeed::query()
            ->where('store_connection_id', $this->storeConnection->id)
            ->where('language', $magnifiqLanguage)
            ->first();

        if ($existingFeed) {
            // Update catalog association if needed
            if ($existingFeed->product_catalog_id !== $catalog->id) {
                $existingFeed->update(['product_catalog_id' => $catalog->id]);
            }

            return $existingFeed;
        }

        // Create new feed for this locale
        $feedName = $localeData['primary']
            ? $this->storeConnection->name
            : "{$this->storeConnection->name} ({$localeData['name']})";

        return ProductFeed::create([
            'team_id' => $this->storeConnection->team_id,
            'product_catalog_id' => $catalog->id,
            'store_connection_id' => $this->storeConnection->id,
            'name' => $feedName,
            'language' => $magnifiqLanguage,
            'source_type' => ProductFeed::SOURCE_TYPE_STORE_CONNECTION,
            'field_mappings' => [
                'sku' => 'sku',
                'title' => 'title',
                'description' => 'description',
                'url' => 'url',
                'image_link' => 'image_link',
            ],
        ]);
    }

    /**
     * Sync products for a specific feed/locale.
     *
     * @param  array{locale: string, name: string, primary: bool, published: bool}  $localeData
     * @return array{synced: int, created: int, updated: int, deleted: int}
     */
    protected function syncProductsForFeed(
        ProductFeed $feed,
        array $localeData,
        ShopifyAdapter $adapter,
        ShopifyLocaleService $localeService
    ): array {
        $existingSkus = $this->getExistingSkus($feed);
        $seenSkus = [];
        $counts = ['synced' => 0, 'created' => 0, 'updated' => 0];

        $batch = [];
        $batchSize = config('store-integrations.sync.batch_size', 100);

        // Primary locale: use regular fetch; secondary: fetch with translations
        $products = $localeData['primary']
            ? $adapter->fetchProducts($this->storeConnection)
            : $adapter->fetchProductsForLocale($this->storeConnection, $localeData['locale']);

        foreach ($products as $storeProduct) {
            $batch[] = $storeProduct;
            $seenSkus[] = $storeProduct->sku;

            if (count($batch) >= $batchSize) {
                $this->processBatch($feed, $batch, $existingSkus, $counts);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            $this->processBatch($feed, $batch, $existingSkus, $counts);
        }

        $deleted = $this->deleteStaleProducts($feed, $seenSkus);

        Log::debug('Synced products for locale', [
            'connection_id' => $this->storeConnection->id,
            'locale' => $localeData['locale'],
            'feed_id' => $feed->id,
            'synced' => $counts['synced'],
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'deleted' => $deleted,
        ]);

        return [
            'synced' => $counts['synced'],
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'deleted' => $deleted,
        ];
    }

    /**
     * Delete feeds for locales that are no longer published.
     *
     * @param  array<string>  $syncedLocales  Shopify locale codes that were synced
     * @return int Number of products deleted
     */
    protected function cleanupOrphanedLocaleFeeds(array $syncedLocales, ProductCatalog $catalog): int
    {
        $localeService = app(ShopifyLocaleService::class);

        // Map synced Shopify locales to Magnifiq languages
        $syncedLanguages = array_map(
            fn ($locale) => $localeService->mapShopifyLocaleToMagnifiq($locale),
            $syncedLocales
        );

        // Find feeds for this connection that aren't in the synced languages
        $orphanedFeeds = ProductFeed::query()
            ->where('store_connection_id', $this->storeConnection->id)
            ->whereNotIn('language', $syncedLanguages)
            ->get();

        $totalDeleted = 0;

        foreach ($orphanedFeeds as $feed) {
            $productCount = $feed->products()->count();
            $feed->products()->delete();
            $feed->delete();

            $totalDeleted += $productCount;

            Log::info('Deleted orphaned locale feed', [
                'connection_id' => $this->storeConnection->id,
                'feed_id' => $feed->id,
                'language' => $feed->language,
                'products_deleted' => $productCount,
            ]);
        }

        return $totalDeleted;
    }

    /**
     * @return array<string, int>
     */
    protected function getExistingSkus(ProductFeed $feed): array
    {
        return Product::query()
            ->where('product_feed_id', $feed->id)
            ->pluck('id', 'sku')
            ->all();
    }

    /**
     * @param  array<\App\Services\StoreIntegration\DTO\StoreProduct>  $batch
     * @param  array<string, int>  $existingSkus
     * @param  array<string, int>  $counts
     */
    protected function processBatch(ProductFeed $feed, array $batch, array $existingSkus, array &$counts): void
    {
        DB::transaction(function () use ($feed, $batch, $existingSkus, &$counts) {
            foreach ($batch as $storeProduct) {
                $productData = [
                    'team_id' => $feed->team_id,
                    'product_feed_id' => $feed->id,
                    'sku' => $storeProduct->sku,
                    'external_id' => $storeProduct->externalId,
                    'title' => $storeProduct->title,
                    'description' => $storeProduct->description,
                    'brand' => $storeProduct->brand,
                    'url' => $storeProduct->url,
                    'image_link' => $storeProduct->imageUrl,
                    'additional_image_link' => $storeProduct->additionalImages
                        ? implode(',', $storeProduct->additionalImages)
                        : null,
                    'gtin' => $storeProduct->gtin,
                ];

                if (isset($existingSkus[$storeProduct->sku])) {
                    Product::where('id', $existingSkus[$storeProduct->sku])->update($productData);
                    $counts['updated']++;
                } else {
                    Product::create($productData);
                    $counts['created']++;
                }

                $counts['synced']++;
            }
        });
    }

    /**
     * @param  array<string>  $seenSkus
     */
    protected function deleteStaleProducts(ProductFeed $feed, array $seenSkus): int
    {
        if (empty($seenSkus)) {
            return Product::where('product_feed_id', $feed->id)->delete();
        }

        return Product::query()
            ->where('product_feed_id', $feed->id)
            ->whereNotIn('sku', $seenSkus)
            ->delete();
    }
}
