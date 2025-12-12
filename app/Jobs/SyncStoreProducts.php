<?php

namespace App\Jobs;

use App\Facades\Store;
use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\StoreConnection;
use App\Models\StoreSyncJob;
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

            $feed = $this->ensureProductFeed();
            $existingSkus = $this->getExistingSkus($feed);
            $seenSkus = [];

            $counts = [
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
            ];

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

            $this->storeConnection->update([
                'status' => StoreConnection::STATUS_CONNECTED,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $syncJob->update([
                'status' => StoreSyncJob::STATUS_COMPLETED,
                'products_synced' => $counts['synced'],
                'products_created' => $counts['created'],
                'products_updated' => $counts['updated'],
                'products_deleted' => $deleted,
                'completed_at' => now(),
            ]);

            Log::info('Store product sync completed', [
                'connection_id' => $this->storeConnection->id,
                'platform' => $this->storeConnection->platform,
                'synced' => $counts['synced'],
                'created' => $counts['created'],
                'updated' => $counts['updated'],
                'deleted' => $deleted,
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
        if ($this->storeConnection->product_feed_id && $this->storeConnection->productFeed) {
            return $this->storeConnection->productFeed;
        }

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
