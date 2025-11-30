<?php

namespace App\Contracts\AI;

use App\DTO\AI\ChatRequest;
use App\DTO\AI\ChatResponse;
use App\DTO\AI\ImageGenerationRequest;
use App\DTO\AI\ImageGenerationResponse;

interface AiProviderContract
{
    /**
     * Execute a chat/text completion request.
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * Execute an image generation request.
     * For async providers like Replicate, this blocks until completion.
     */
    public function generateImage(ImageGenerationRequest $request): ImageGenerationResponse;

    /**
     * Check if this provider supports chat completions.
     */
    public function supportsChatCompletion(): bool;

    /**
     * Check if this provider supports image generation.
     */
    public function supportsImageGeneration(): bool;

    /**
     * Get the provider name for logging and metadata.
     */
    public function getProviderName(): string;
}
