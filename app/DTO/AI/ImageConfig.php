<?php

namespace App\DTO\AI;

/**
 * Configuration for image generation.
 */
readonly class ImageConfig
{
    /**
     * @param  string|null  $aspectRatio  Aspect ratio (e.g., '1:1', '16:9', '4:3')
     * @param  int|null  $width  Specific width in pixels
     * @param  int|null  $height  Specific height in pixels
     * @param  string|null  $quality  Quality setting (e.g., 'standard', 'hd')
     * @param  string|null  $style  Style preset (provider-specific)
     */
    public function __construct(
        public ?string $aspectRatio = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $quality = null,
        public ?string $style = null,
    ) {}

    /**
     * Create a config with just aspect ratio.
     */
    public static function aspectRatio(string $ratio): self
    {
        return new self(aspectRatio: $ratio);
    }

    /**
     * Create a config with specific dimensions.
     */
    public static function dimensions(int $width, int $height): self
    {
        return new self(width: $width, height: $height);
    }

    /**
     * Create a square config.
     */
    public static function square(int $size = 1024): self
    {
        return new self(width: $size, height: $size, aspectRatio: '1:1');
    }
}
