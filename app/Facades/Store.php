<?php

namespace App\Facades;

use App\Services\StoreIntegration\Contracts\StoreAdapterContract;
use App\Services\StoreIntegration\StoreManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static StoreAdapterContract forPlatform(string $platform)
 * @method static StoreAdapterContract driver(string|null $driver = null)
 *
 * @see StoreManager
 */
class Store extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StoreManager::class;
    }
}
