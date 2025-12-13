<?php

namespace App\Services\StoreIntegration;

use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use App\Services\StoreIntegration\Contracts\StoreAdapterContract;
use Illuminate\Support\Manager;

/**
 * Store Integration Manager.
 *
 * Manages store platform adapters using Laravel's Manager pattern,
 * similar to Cache::driver() or AI::forFeature().
 *
 * @method StoreAdapterContract driver(string|null $driver = null)
 */
class StoreManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return 'shopify';
    }

    /**
     * Get the adapter for a specific platform.
     */
    public function forPlatform(string $platform): StoreAdapterContract
    {
        return $this->driver($platform);
    }

    /**
     * Create the Shopify driver.
     *
     * @noinspection PhpUnused
     */
    protected function createShopifyDriver(): ShopifyAdapter
    {
        $config = $this->config->get('store-integrations.platforms.shopify', []);

        return new ShopifyAdapter($config);
    }

    // Future platform drivers can be added here:
    // protected function createWoocommerceDriver(): WooCommerceAdapter
    // protected function createBigcommerceDriver(): BigCommerceAdapter
}
