<?php

namespace App\DTO\AI;

/**
 * Encapsulates an image generation request.
 */
readonly class ImageGenerationRequest
{
    /**
     * @param  string  $prompt  The generation prompt
     * @param  string|null  $model  Model identifier (uses feature default if null)
     * @param  string|array<string>|null  $inputImages  Input image(s) for img2img, URLs or base64
     * @param  ImageConfig|null  $imageConfig  Image configuration (aspect ratio, dimensions)
     * @param  float|null  $temperature  Sampling temperature
     * @param  int|null  $maxTokens  Maximum tokens (for models that use token-based generation)
     * @param  array<string, mixed>  $extra  Provider-specific options
     */
    public function __construct(
        public string $prompt,
        public ?string $model = null,
        public string|array|null $inputImages = null,
        public ?ImageConfig $imageConfig = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public array $extra = [],
    ) {}

    /**
     * Create a text-to-image request (no input images).
     */
    public static function textToImage(
        string $prompt,
        ?string $model = null,
        ?ImageConfig $imageConfig = null,
    ): self {
        return new self(
            prompt: $prompt,
            model: $model,
            imageConfig: $imageConfig,
        );
    }

    /**
     * Create an image-to-image request.
     *
     * @param  string|array<string>  $inputImages
     */
    public static function imageToImage(
        string $prompt,
        string|array $inputImages,
        ?string $model = null,
        ?ImageConfig $imageConfig = null,
    ): self {
        return new self(
            prompt: $prompt,
            model: $model,
            inputImages: $inputImages,
            imageConfig: $imageConfig,
        );
    }

    /**
     * Check if this is an image-to-image request.
     */
    public function hasInputImages(): bool
    {
        if ($this->inputImages === null) {
            return false;
        }

        if (is_string($this->inputImages)) {
            return $this->inputImages !== '';
        }

        return count($this->inputImages) > 0;
    }

    /**
     * Get input images as array.
     *
     * @return array<string>
     */
    public function getInputImagesArray(): array
    {
        if ($this->inputImages === null) {
            return [];
        }

        return is_array($this->inputImages) ? $this->inputImages : [$this->inputImages];
    }
}
