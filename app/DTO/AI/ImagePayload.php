<?php

namespace App\DTO\AI;

/**
 * Normalized image binary payload.
 * Resolves all provider-specific response formats into a consistent structure.
 */
readonly class ImagePayload
{
    /**
     * @param  string  $binary  Raw image binary data
     * @param  string  $extension  File extension (jpg, png, webp)
     * @param  string|null  $mimeType  MIME type if known
     * @param  int|null  $width  Image width in pixels
     * @param  int|null  $height  Image height in pixels
     */
    public function __construct(
        public string $binary,
        public string $extension,
        public ?string $mimeType = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /**
     * Create from base64 encoded data.
     */
    public static function fromBase64(string $base64, ?string $mimeType = null): self
    {
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            throw new \InvalidArgumentException('Invalid base64 data');
        }

        return self::fromBinary($binary, $mimeType);
    }

    /**
     * Create from binary data, detecting MIME type and extension.
     */
    public static function fromBinary(string $binary, ?string $mimeType = null): self
    {
        $detectedMime = $mimeType ?? self::detectMimeType($binary);
        $extension = self::mimeToExtension($detectedMime);
        $dimensions = self::detectDimensions($binary);

        return new self(
            binary: $binary,
            extension: $extension,
            mimeType: $detectedMime,
            width: $dimensions['width'] ?? null,
            height: $dimensions['height'] ?? null,
        );
    }

    /**
     * Detect MIME type from binary data.
     */
    public static function detectMimeType(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($binary);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Convert MIME type to file extension.
     */
    public static function mimeToExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            default => 'bin',
        };
    }

    /**
     * Detect image dimensions from binary data.
     *
     * @return array{width?: int, height?: int}
     */
    public static function detectDimensions(string $binary): array
    {
        $size = @getimagesizefromstring($binary);

        if ($size === false) {
            return [];
        }

        return [
            'width' => $size[0],
            'height' => $size[1],
        ];
    }

    /**
     * Get base64 encoded version of the binary.
     */
    public function toBase64(): string
    {
        return base64_encode($this->binary);
    }

    /**
     * Get data URI (e.g., for embedding in HTML).
     */
    public function toDataUri(): string
    {
        $mime = $this->mimeType ?? 'application/octet-stream';

        return "data:{$mime};base64,".$this->toBase64();
    }

    /**
     * Get the file size in bytes.
     */
    public function getSize(): int
    {
        return strlen($this->binary);
    }
}
