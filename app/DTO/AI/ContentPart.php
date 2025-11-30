<?php

namespace App\DTO\AI;

/**
 * Represents a content part in a multimodal message.
 * Can be text or image content.
 */
readonly class ContentPart
{
    public function __construct(
        public string $type,
        public ?string $text = null,
        public ?string $imageUrl = null,
        public ?string $imageBase64 = null,
        public ?string $mimeType = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(type: 'text', text: $text);
    }

    public static function imageUrl(string $url): self
    {
        return new self(type: 'image_url', imageUrl: $url);
    }

    public static function imageBase64(string $base64, string $mimeType = 'image/jpeg'): self
    {
        return new self(type: 'image_base64', imageBase64: $base64, mimeType: $mimeType);
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isImage(): bool
    {
        return in_array($this->type, ['image_url', 'image_base64'], true);
    }
}
