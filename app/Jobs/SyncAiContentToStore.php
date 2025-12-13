<?php

namespace App\Jobs;

use App\Facades\Store;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use App\Models\StoreConnection;
use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use App\Services\StoreIntegration\ShopifyLocaleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAiContentToStore implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const METAFIELD_NAMESPACE = 'magnifiq';

    public int $tries = 3;

    /**
     * Backoff intervals (seconds) between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $generationId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ShopifyLocaleService $localeService): void
    {
        $generation = ProductAiGeneration::with(['template', 'product.feed.storeConnection'])
            ->find($this->generationId);

        if (! $generation) {
            Log::warning('SyncAiContentToStore: Generation not found', [
                'generation_id' => $this->generationId,
            ]);

            return;
        }

        $connection = $generation->getStoreConnection();

        if (! $connection) {
            Log::debug('SyncAiContentToStore: No store connection for product', [
                'generation_id' => $generation->id,
                'product_id' => $generation->product_id,
            ]);

            return;
        }

        if (! $connection->isConnected()) {
            Log::debug('SyncAiContentToStore: Store connection not active', [
                'generation_id' => $generation->id,
                'connection_id' => $connection->id,
                'status' => $connection->status,
            ]);

            return;
        }

        $template = $generation->template;

        if (! $template) {
            Log::warning('SyncAiContentToStore: Template not found for generation', [
                'generation_id' => $generation->id,
            ]);

            return;
        }

        $product = $generation->product;
        $externalId = $product->external_id ?? null;

        if (! $externalId) {
            Log::debug('SyncAiContentToStore: Product has no external ID', [
                'generation_id' => $generation->id,
                'product_id' => $product->id,
            ]);

            return;
        }

        // Non-Shopify platforms use simple metafield sync (no translations)
        if (! $connection->isShopify()) {
            $this->syncPrimaryContent($generation, $connection);

            return;
        }

        // Get product language from feed
        $productLanguage = $product->feed?->language;

        // No language set on feed - treat as primary content
        if (! $productLanguage) {
            $this->syncPrimaryContent($generation, $connection);
            $this->queueSiblingTranslations($generation, $localeService);

            return;
        }

        // Determine if this is primary language or translation
        $isPrimary = $localeService->isPrimaryLanguage($connection, $productLanguage);

        if ($isPrimary) {
            $this->syncPrimaryContent($generation, $connection);
            $this->queueSiblingTranslations($generation, $localeService);
        } else {
            // Check if locale is published in store
            if (! $localeService->isLocalePublished($connection, $productLanguage)) {
                Log::info('SyncAiContentToStore: Locale not published in store, skipping', [
                    'generation_id' => $generation->id,
                    'language' => $productLanguage,
                ]);

                return;
            }

            $this->syncAsTranslation($generation, $connection, $localeService);
        }
    }

    /**
     * Sync content as primary language (to metafield value).
     */
    protected function syncPrimaryContent(
        ProductAiGeneration $generation,
        StoreConnection $connection
    ): void {
        $adapter = Store::forPlatform($connection->platform);
        $template = $generation->template;
        $product = $generation->product;
        $externalId = $product->external_id;

        $metafieldKey = $this->resolveMetafieldKey($template);
        $metafieldType = $this->resolveMetafieldType($template);

        try {
            if ($generation->isUnpublished()) {
                $adapter->deleteProductMetafield(
                    connection: $connection,
                    productId: $externalId,
                    namespace: self::METAFIELD_NAMESPACE,
                    key: $metafieldKey,
                );

                Log::info('SyncAiContentToStore: Deleted primary metafield', [
                    'generation_id' => $generation->id,
                    'product_id' => $product->id,
                    'external_id' => $externalId,
                    'metafield_key' => $metafieldKey,
                ]);
            } else {
                $adapter->writeProductMetafield(
                    connection: $connection,
                    productId: $externalId,
                    namespace: self::METAFIELD_NAMESPACE,
                    key: $metafieldKey,
                    value: $generation->content,
                    type: $metafieldType,
                );

                Log::info('SyncAiContentToStore: Synced primary content', [
                    'generation_id' => $generation->id,
                    'product_id' => $product->id,
                    'external_id' => $externalId,
                    'metafield_key' => $metafieldKey,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('SyncAiContentToStore: Failed to sync primary content', [
                'generation_id' => $generation->id,
                'product_id' => $product->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync content as a translation.
     */
    protected function syncAsTranslation(
        ProductAiGeneration $generation,
        StoreConnection $connection,
        ShopifyLocaleService $localeService
    ): void {
        /** @var ShopifyAdapter $adapter */
        $adapter = Store::forPlatform($connection->platform);
        $template = $generation->template;
        $product = $generation->product;
        $productLanguage = $product->feed->language;

        $metafieldKey = $this->resolveMetafieldKey($template);
        $targetLocale = $localeService->resolveShopifyLocale($productLanguage);

        try {
            if ($generation->isUnpublished()) {
                // Get the metafield to remove its translation
                $translatableContent = $adapter->getMetafieldTranslatableContent(
                    $connection,
                    $product->external_id,
                    self::METAFIELD_NAMESPACE,
                    $metafieldKey
                );

                if ($translatableContent) {
                    $adapter->removeTranslation(
                        $connection,
                        $translatableContent['metafieldId'],
                        $targetLocale
                    );

                    Log::info('SyncAiContentToStore: Removed translation', [
                        'generation_id' => $generation->id,
                        'locale' => $targetLocale,
                        'metafield_key' => $metafieldKey,
                    ]);
                }
            } else {
                // Get the metafield's translatable content (need digest)
                $translatableContent = $adapter->getMetafieldTranslatableContent(
                    $connection,
                    $product->external_id,
                    self::METAFIELD_NAMESPACE,
                    $metafieldKey
                );

                if (! $translatableContent) {
                    Log::warning('SyncAiContentToStore: Metafield not found for translation, queuing primary sync', [
                        'generation_id' => $generation->id,
                        'product_external_id' => $product->external_id,
                        'metafield_key' => $metafieldKey,
                    ]);

                    // Queue a job to sync the primary content first, then retry
                    $this->handleMissingPrimaryContent($generation, $connection, $localeService);

                    return;
                }

                $adapter->registerTranslation(
                    $connection,
                    $translatableContent['metafieldId'],
                    $targetLocale,
                    $generation->content,
                    $translatableContent['digest']
                );

                Log::info('SyncAiContentToStore: Registered translation', [
                    'generation_id' => $generation->id,
                    'locale' => $targetLocale,
                    'metafield_key' => $metafieldKey,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('SyncAiContentToStore: Failed to sync translation', [
                'generation_id' => $generation->id,
                'locale' => $targetLocale,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle case where primary content doesn't exist yet.
     * Find sibling in primary language and trigger its sync.
     */
    protected function handleMissingPrimaryContent(
        ProductAiGeneration $generation,
        StoreConnection $connection,
        ShopifyLocaleService $localeService
    ): void {
        $product = $generation->product;
        $template = $generation->template;

        // Find sibling products (same SKU in same catalog)
        $siblings = $product->siblingProducts();

        foreach ($siblings as $sibling) {
            $siblingLanguage = $sibling->feed?->language;

            if ($siblingLanguage && $localeService->isPrimaryLanguage($connection, $siblingLanguage)) {
                // Find latest generation for this template on sibling
                $siblingGeneration = $sibling->latestAiGenerationForTemplate($template->slug)->first();

                if ($siblingGeneration && $siblingGeneration->isPublished()) {
                    // Dispatch sync for primary, then re-queue ourselves
                    SyncAiContentToStore::dispatch($siblingGeneration->id);
                    SyncAiContentToStore::dispatch($generation->id)
                        ->delay(now()->addSeconds(30));

                    Log::info('SyncAiContentToStore: Queued primary sync first', [
                        'generation_id' => $generation->id,
                        'primary_generation_id' => $siblingGeneration->id,
                    ]);

                    return;
                }
            }
        }

        Log::warning('SyncAiContentToStore: No primary language content found', [
            'generation_id' => $generation->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Queue sync jobs for sibling products with published translations.
     * Called after primary content is synced so translations can be registered.
     */
    protected function queueSiblingTranslations(
        ProductAiGeneration $generation,
        ShopifyLocaleService $localeService
    ): void {
        // Only auto-sync if enabled (disabled by default to avoid unexpected behavior)
        if (! config('services.shopify.auto_sync_siblings', false)) {
            return;
        }

        $product = $generation->product;
        $template = $generation->template;

        $connection = $generation->getStoreConnection();
        if (! $connection) {
            return;
        }

        foreach ($product->siblingProducts() as $sibling) {
            $siblingLanguage = $sibling->feed?->language;

            // Skip primary language siblings (already synced)
            if (! $siblingLanguage || $localeService->isPrimaryLanguage($connection, $siblingLanguage)) {
                continue;
            }

            // Skip if locale not published in store
            if (! $localeService->isLocalePublished($connection, $siblingLanguage)) {
                continue;
            }

            $siblingGeneration = $sibling->latestAiGenerationForTemplate($template->slug)->first();

            if ($siblingGeneration?->isPublished()) {
                SyncAiContentToStore::dispatch($siblingGeneration->id)
                    ->delay(now()->addSeconds(5));

                Log::debug('SyncAiContentToStore: Queued sibling translation sync', [
                    'primary_generation_id' => $generation->id,
                    'sibling_generation_id' => $siblingGeneration->id,
                    'sibling_language' => $siblingLanguage,
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SyncAiContentToStore: Job permanently failed', [
            'generation_id' => $this->generationId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Resolve the metafield key from the template slug.
     */
    protected function resolveMetafieldKey(ProductAiTemplate $template): string
    {
        // Template slugs map directly to metafield keys:
        // description_summary, description, usps, faq
        return $template->slug;
    }

    /**
     * Resolve the Shopify metafield type based on template content type.
     */
    protected function resolveMetafieldType(ProductAiTemplate $template): string
    {
        $contentType = $template->contentType();

        // JSON content types (structured data)
        if ($contentType === 'json' || in_array($template->slug, [
            ProductAiTemplate::SLUG_FAQ,
            ProductAiTemplate::SLUG_USPS,
        ], true)) {
            return 'json';
        }

        // Text content types (plain text, allow line breaks)
        return 'multi_line_text_field';
    }
}
