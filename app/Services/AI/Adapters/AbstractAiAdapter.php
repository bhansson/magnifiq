<?php

namespace App\Services\AI\Adapters;

use App\Contracts\AI\AiProviderContract;
use App\DTO\AI\ChatRequest;
use App\DTO\AI\ChatResponse;
use App\DTO\AI\ImageGenerationRequest;
use App\DTO\AI\ImageGenerationResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractAiAdapter implements AiProviderContract
{
    /**
     * @param  array<string, mixed>  $config  Provider configuration
     */
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * Get a configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get the API key for this provider.
     */
    protected function getApiKey(): string
    {
        $apiKey = $this->getConfig('api_key');

        if (empty($apiKey)) {
            throw new RuntimeException(
                "API key not configured for {$this->getProviderName()} provider."
            );
        }

        return $apiKey;
    }

    /**
     * Get the API endpoint for this provider.
     */
    protected function getApiEndpoint(): string
    {
        return $this->getConfig('api_endpoint', '');
    }

    /**
     * Get the request timeout in seconds.
     */
    protected function getTimeout(): int
    {
        return (int) $this->getConfig('timeout', 120);
    }

    /**
     * Log a debug message with provider context.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug("[AI:{$this->getProviderName()}] {$message}", $context);
    }

    /**
     * Log a warning message with provider context.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning("[AI:{$this->getProviderName()}] {$message}", $context);
    }

    /**
     * Log an error message with provider context.
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[AI:{$this->getProviderName()}] {$message}", $context);
    }

    /**
     * Get the model to use, falling back to request model or feature default.
     */
    protected function resolveModel(?string $requestModel, string $feature): string
    {
        if ($requestModel !== null && $requestModel !== '') {
            return $requestModel;
        }

        $featureModel = config("ai.features.{$feature}.model");

        if ($featureModel !== null && $featureModel !== '') {
            return $featureModel;
        }

        throw new RuntimeException(
            "No model specified for {$feature} and no default configured."
        );
    }

    /**
     * Default implementations - subclasses should override as needed.
     */
    public function chat(ChatRequest $request): ChatResponse
    {
        throw new RuntimeException(
            "{$this->getProviderName()} does not support chat completions."
        );
    }

    public function generateImage(ImageGenerationRequest $request): ImageGenerationResponse
    {
        throw new RuntimeException(
            "{$this->getProviderName()} does not support image generation."
        );
    }

    public function supportsChatCompletion(): bool
    {
        return false;
    }

    public function supportsImageGeneration(): bool
    {
        return false;
    }
}
