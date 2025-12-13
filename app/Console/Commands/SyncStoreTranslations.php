<?php

namespace App\Console\Commands;

use App\Jobs\SyncAiContentToStore;
use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\StoreConnection;
use App\Services\StoreIntegration\ShopifyLocaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncStoreTranslations extends Command
{
    protected $signature = 'store:sync-translations
                            {connection : The store connection ID}
                            {--dry-run : Show what would be synced without actually dispatching jobs}
                            {--template= : Only sync specific template slug (e.g., description, faq)}';

    protected $description = 'Bulk sync AI content translations to a Shopify store';

    protected int $primaryJobsQueued = 0;

    protected int $translationJobsQueued = 0;

    protected int $skippedProducts = 0;

    public function handle(ShopifyLocaleService $localeService): int
    {
        $connection = StoreConnection::find($this->argument('connection'));

        if (! $connection) {
            $this->error('Store connection not found.');

            return self::FAILURE;
        }

        if (! $connection->isShopify()) {
            $this->error('Translation sync is only supported for Shopify stores.');

            return self::FAILURE;
        }

        if (! $connection->isConnected()) {
            $this->error('Store connection is not active. Status: '.$connection->status);

            return self::FAILURE;
        }

        $this->info("Syncing translations for store: {$connection->shop_name}");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - No jobs will be dispatched');
        }

        $primaryLocale = $localeService->getPrimaryLocale($connection);
        $this->info("Store primary locale: {$primaryLocale}");

        $publishedLocales = $localeService->getPublishedLocales($connection);
        $localeList = collect($publishedLocales)->pluck('locale')->implode(', ');
        $this->info("Published locales: {$localeList}");
        $this->newLine();

        // Get all products with external_id for this connection's feeds
        $products = $this->getProductsForConnection($connection);

        $this->info("Found {$products->count()} products to process");
        $this->newLine();

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            $this->processProduct($product, $connection, $localeService);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function getProductsForConnection(StoreConnection $connection): Collection
    {
        return Product::query()
            ->whereHas('feed', function ($query) use ($connection) {
                $query->where('store_connection_id', $connection->id);
            })
            ->whereNotNull('external_id')
            ->with(['feed.catalog', 'aiGenerations.template'])
            ->get();
    }

    protected function processProduct(
        Product $product,
        StoreConnection $connection,
        ShopifyLocaleService $localeService
    ): void {
        $templateSlug = $this->option('template');

        // Get all published generations for this product
        $generations = $product->aiGenerations
            ->filter(fn ($gen) => $gen->isPublished())
            ->when($templateSlug, fn ($c) => $c->filter(
                fn ($gen) => $gen->template?->slug === $templateSlug
            ));

        if ($generations->isEmpty()) {
            $this->skippedProducts++;

            return;
        }

        $productLanguage = $product->feed?->language;

        foreach ($generations as $generation) {
            $isPrimary = ! $productLanguage || $localeService->isPrimaryLanguage($connection, $productLanguage);

            if ($isPrimary) {
                $this->queuePrimarySync($generation);
            } else {
                // Check if locale is published in store
                if (! $localeService->isLocalePublished($connection, $productLanguage)) {
                    continue;
                }

                $this->queueTranslationSync($generation);
            }
        }
    }

    protected function queuePrimarySync(ProductAiGeneration $generation): void
    {
        if (! $this->option('dry-run')) {
            SyncAiContentToStore::dispatch($generation->id);
        }

        $this->primaryJobsQueued++;
    }

    protected function queueTranslationSync(ProductAiGeneration $generation): void
    {
        if (! $this->option('dry-run')) {
            // Delay translation syncs to ensure primary content is synced first
            SyncAiContentToStore::dispatch($generation->id)
                ->delay(now()->addSeconds(10));
        }

        $this->translationJobsQueued++;
    }

    protected function displaySummary(): void
    {
        $action = $this->option('dry-run') ? 'Would queue' : 'Queued';

        $this->info("{$action} {$this->primaryJobsQueued} primary content sync jobs");
        $this->info("{$action} {$this->translationJobsQueued} translation sync jobs");
        $this->info("Skipped {$this->skippedProducts} products (no published content)");

        if (! $this->option('dry-run') && ($this->primaryJobsQueued + $this->translationJobsQueued) > 0) {
            $this->newLine();
            $this->info('Jobs have been dispatched to the queue. Run `php artisan queue:work --queue=ai` to process them.');
        }
    }
}
