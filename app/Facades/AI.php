<?php

namespace App\Facades;

use App\Contracts\AI\AiProviderContract;
use App\Services\AI\AiManager;
use Illuminate\Support\Facades\Facade;

/**
 * AI Provider Facade.
 *
 * @method static AiProviderContract driver(string|null $driver = null)
 * @method static AiProviderContract forFeature(string $feature)
 * @method static string|null getModelForFeature(string $feature)
 * @method static string getDefaultDriver()
 * @method static AiManager extend(string $driver, \Closure $callback)
 *
 * @see \App\Services\AI\AiManager
 */
class AI extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AiManager::class;
    }
}
