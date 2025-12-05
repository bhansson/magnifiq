<?php

namespace App\Services\AI;

use App\Contracts\AI\AiProviderContract;
use App\Services\AI\Adapters\OpenAiAdapter;
use App\Services\AI\Adapters\OpenRouterAdapter;
use App\Services\AI\Adapters\ReplicateAdapter;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * AI Provider Manager.
 *
 * Manages AI provider adapters using Laravel's Manager pattern,
 * similar to Cache::driver() or Mail::mailer().
 *
 * @method AiProviderContract driver(string|null $driver = null)
 */
class AiManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'openrouter');
    }

    /**
     * Get the AI provider for a specific feature.
     *
     * Features: 'chat', 'vision', 'image_generation'
     */
    public function forFeature(string $feature): AiProviderContract
    {
        $featureConfig = $this->config->get("ai.features.{$feature}");

        if ($featureConfig === null) {
            throw new InvalidArgumentException("AI feature [{$feature}] is not configured.");
        }

        $driver = $featureConfig['driver'] ?? $this->getDefaultDriver();

        return $this->driver($driver);
    }

    /**
     * Get the model configured for a specific feature.
     */
    public function getModelForFeature(string $feature): ?string
    {
        return $this->config->get("ai.features.{$feature}.model");
    }

    /**
     * Get the driver name configured for a specific feature.
     */
    public function getDriverForFeature(string $feature): string
    {
        $featureConfig = $this->config->get("ai.features.{$feature}");

        return $featureConfig['driver'] ?? $this->getDefaultDriver();
    }

    /**
     * Check if the configured provider for a feature has an API key.
     */
    public function hasApiKeyForFeature(string $feature): bool
    {
        $driver = $this->getDriverForFeature($feature);

        return $this->hasApiKeyForDriver($driver);
    }

    /**
     * Check if a specific driver has an API key configured.
     */
    public function hasApiKeyForDriver(string $driver): bool
    {
        return ! empty($this->config->get("ai.providers.{$driver}.api_key"));
    }

    /**
     * Create the OpenAI driver.
     */
    protected function createOpenaiDriver(): OpenAiAdapter
    {
        $config = $this->config->get('ai.providers.openai', []);

        return new OpenAiAdapter($config);
    }

    /**
     * Create the OpenRouter driver.
     */
    protected function createOpenrouterDriver(): OpenRouterAdapter
    {
        $config = $this->config->get('ai.providers.openrouter', []);

        return new OpenRouterAdapter($config);
    }

    /**
     * Create the Replicate driver.
     */
    protected function createReplicateDriver(): ReplicateAdapter
    {
        $config = $this->config->get('ai.providers.replicate', []);

        return new ReplicateAdapter($config);
    }
}
