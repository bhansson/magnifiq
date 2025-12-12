<?php

namespace App\Jobs;

use App\Facades\Store;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

    public function handle(): void
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

        $metafieldKey = $this->resolveMetafieldKey($template);
        $metafieldType = $this->resolveMetafieldType($template);

        // If unpublished, sync empty content to hide in store
        $value = $generation->isUnpublished()
            ? $this->getEmptyValue($metafieldType)
            : $generation->content;

        try {
            $adapter = Store::forPlatform($connection->platform);

            $adapter->writeProductMetafield(
                connection: $connection,
                productId: $externalId,
                namespace: self::METAFIELD_NAMESPACE,
                key: $metafieldKey,
                value: $value,
                type: $metafieldType,
            );

            Log::info('SyncAiContentToStore: Successfully synced content to store', [
                'generation_id' => $generation->id,
                'product_id' => $product->id,
                'external_id' => $externalId,
                'template_slug' => $template->slug,
                'metafield_key' => $metafieldKey,
                'is_unpublished' => $generation->isUnpublished(),
            ]);
        } catch (Throwable $e) {
            Log::error('SyncAiContentToStore: Failed to sync content to store', [
                'generation_id' => $generation->id,
                'product_id' => $product->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
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

    /**
     * Get the appropriate empty value for the metafield type.
     */
    protected function getEmptyValue(string $metafieldType): string
    {
        if ($metafieldType === 'json') {
            return '[]';
        }

        return '';
    }
}
