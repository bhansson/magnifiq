<?php

namespace App\DTO\AI;

/**
 * Encapsulates a chat/text completion request.
 */
readonly class ChatRequest
{
    /**
     * @param  array<ChatMessage>  $messages  The conversation messages
     * @param  string|null  $model  Model identifier (uses feature default if null)
     * @param  int|null  $maxTokens  Maximum tokens in response
     * @param  float|null  $temperature  Sampling temperature (0.0-2.0)
     * @param  array<string, mixed>  $extra  Provider-specific options
     */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public array $extra = [],
    ) {}

    /**
     * Create a simple single-message request.
     */
    public static function simple(string $prompt, ?string $systemPrompt = null, ?string $model = null): self
    {
        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = ChatMessage::system($systemPrompt);
        }

        $messages[] = ChatMessage::user($prompt);

        return new self(messages: $messages, model: $model);
    }

    /**
     * Create a request with multimodal content (text + images).
     *
     * @param  array<ContentPart>  $content
     */
    public static function multimodal(
        array $content,
        ?string $systemPrompt = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): self {
        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = ChatMessage::system($systemPrompt);
        }

        $messages[] = ChatMessage::user($content);

        return new self(
            messages: $messages,
            model: $model,
            maxTokens: $maxTokens,
            temperature: $temperature,
        );
    }
}
