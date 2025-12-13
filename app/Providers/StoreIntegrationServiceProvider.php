<?php

namespace App\Providers;

use App\Services\StoreIntegration\StoreManager;
use Illuminate\Support\ServiceProvider;

class StoreIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreManager::class, function ($app) {
            return new StoreManager($app);
        });
    }
}
