<?php

namespace App\DTO\AI;

/**
 * Normalized chat/text completion response.
 */
readonly class ChatResponse
{
    /**
     * @param  string  $content  The generated text content
     * @param  string|null  $finishReason  Why the generation stopped (stop, length, etc.)
     * @param  array<string, mixed>|null  $usage  Token usage statistics
     * @param  string|null  $model  The model that generated the response
     * @param  array<string, mixed>  $rawResponse  Original provider response for debugging
     */
    public function __construct(
        public string $content,
        public ?string $finishReason = null,
        public ?array $usage = null,
        public ?string $model = null,
        public array $rawResponse = [],
    ) {}

    /**
     * Check if the response was truncated due to token limits.
     */
    public function wasTruncated(): bool
    {
        return in_array($this->finishReason, ['length', 'max_tokens', 'max_output_tokens'], true);
    }

    /**
     * Get the prompt token count if available.
     */
    public function getPromptTokens(): ?int
    {
        return $this->usage['prompt_tokens'] ?? null;
    }

    /**
     * Get the completion token count if available.
     */
    public function getCompletionTokens(): ?int
    {
        return $this->usage['completion_tokens'] ?? null;
    }

    /**
     * Get the total token count if available.
     */
    public function getTotalTokens(): ?int
    {
        return $this->usage['total_tokens'] ?? null;
    }
}
