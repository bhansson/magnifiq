<?php

namespace App\DTO\AI;

/**
 * Normalized image generation response.
 */
readonly class ImageGenerationResponse
{
    /**
     * @param  ImagePayload  $image  The generated image
     * @param  string|null  $revisedPrompt  The prompt as revised by the model (if applicable)
     * @param  string|null  $model  The model that generated the image
     * @param  array<string, mixed>  $rawResponse  Original provider response for debugging
     */
    public function __construct(
        public ImagePayload $image,
        public ?string $revisedPrompt = null,
        public ?string $model = null,
        public array $rawResponse = [],
    ) {}

    /**
     * Check if the prompt was revised by the model.
     */
    public function hasRevisedPrompt(): bool
    {
        return $this->revisedPrompt !== null && $this->revisedPrompt !== '';
    }
}
