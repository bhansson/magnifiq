<?php

namespace App\Services\StoreIntegration\Adapters;

use App\Services\StoreIntegration\Contracts\StoreAdapterContract;
use Illuminate\Support\Facades\Log;

abstract class AbstractStoreAdapter implements StoreAdapterContract
{
    public function __construct(
        protected array $config = [],
    ) {}

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug("[Store:{$this->getPlatform()}] {$message}", $context);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        Log::info("[Store:{$this->getPlatform()}] {$message}", $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error("[Store:{$this->getPlatform()}] {$message}", $context);
    }
}
