<?php

namespace App\DTO\AI;

/**
 * Represents a single message in a chat conversation.
 * Supports both simple text and multimodal (text + images) content.
 */
readonly class ChatMessage
{
    /**
     * @param  string  $role  The message role (system, user, assistant)
     * @param  string|array<ContentPart>  $content  Text string or array of content parts for multimodal
     */
    public function __construct(
        public string $role,
        public string|array $content,
    ) {}

    public static function system(string $content): self
    {
        return new self(role: 'system', content: $content);
    }

    public static function user(string|array $content): self
    {
        return new self(role: 'user', content: $content);
    }

    public static function assistant(string $content): self
    {
        return new self(role: 'assistant', content: $content);
    }

    public function isMultimodal(): bool
    {
        return is_array($this->content);
    }

    /**
     * Get text content, extracting from multimodal if necessary.
     */
    public function getTextContent(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        $textParts = array_filter(
            $this->content,
            fn (ContentPart $part) => $part->isText()
        );

        return implode("\n", array_map(
            fn (ContentPart $part) => $part->text ?? '',
            $textParts
        ));
    }
}
