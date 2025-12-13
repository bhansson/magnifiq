<?php

namespace App\Jobs;

use App\Facades\Store;
use App\Models\StoreConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SetupStoreMetafieldDefinitions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public StoreConnection $storeConnection
    ) {}

    /**
     * Execute the job.
     *
     * Creates metafield definitions with PUBLIC_READ storefront access
     * for all Magnifiq content types. This enables theme app extension
     * blocks to read the metafield values.
     */
    public function handle(): void
    {
        try {
            $adapter = Store::forPlatform($this->storeConnection->platform);
            $adapter->ensureMetafieldDefinitions($this->storeConnection);

            Log::info('Metafield definitions setup complete', [
                'connection_id' => $this->storeConnection->id,
                'platform' => $this->storeConnection->platform,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to setup metafield definitions', [
                'connection_id' => $this->storeConnection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
