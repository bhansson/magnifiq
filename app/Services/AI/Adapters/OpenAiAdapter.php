<?php

namespace App\Services\AI\Adapters;

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatRequest;
use App\DTO\AI\ChatResponse;
use App\DTO\AI\ContentPart;
use App\DTO\AI\ImageGenerationRequest;
use App\DTO\AI\ImageGenerationResponse;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class OpenAiAdapter extends AbstractAiAdapter
{
    public function getProviderName(): string
    {
        return 'openai';
    }

    public function supportsChatCompletion(): bool
    {
        return true;
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    /**
     * Execute a chat completion request.
     *
     * Supports both simple text and multimodal (vision) requests.
     */
    public function chat(ChatRequest $request): ChatResponse
    {
        $model = $this->normalizeModelName($this->resolveModel($request->model, 'chat'));
        $messages = $this->buildMessages($request->messages);

        $payload = array_filter([
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ], fn ($v) => $v !== null);

        $payload = array_merge($payload, $request->extra);

        try {
            $response = OpenAI::chat()->create($payload);
        } catch (\Exception $exception) {
            $this->logError('Chat request failed', [
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                $this->getErrorMessage($exception),
                previous: $exception
            );
        }

        return $this->buildChatResponse($response, $model);
    }

    /**
     * Execute an image generation request using DALL-E.
     */
    public function generateImage(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $model = $this->normalizeModelName($this->resolveModel($request->model, 'image_generation'));

        $payload = [
            'model' => $model,
            'prompt' => $request->prompt,
            'n' => 1,
            'response_format' => 'url',
        ];

        // Handle image dimensions
        if ($request->imageConfig?->width && $request->imageConfig?->height) {
            $payload['size'] = "{$request->imageConfig->width}x{$request->imageConfig->height}";
        } elseif ($request->imageConfig?->aspectRatio) {
            $payload['size'] = $this->aspectRatioToSize($request->imageConfig->aspectRatio, $model);
        }

        // Handle quality for DALL-E 3
        if (str_contains($model, 'dall-e-3')) {
            $payload['quality'] = $request->extra['quality'] ?? 'standard';
            $payload['style'] = $request->extra['style'] ?? 'vivid';
        }

        $payload = array_merge($payload, array_diff_key($request->extra, array_flip(['quality', 'style'])));

        try {
            $response = OpenAI::images()->create($payload);
        } catch (\Exception $exception) {
            $this->logError('Image generation failed', [
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                $this->getErrorMessage($exception),
                previous: $exception
            );
        }

        $imageData = $response->data[0] ?? null;

        if (! $imageData) {
            throw new RuntimeException('No image data returned from OpenAI.');
        }

        $imagePayload = $this->fetchImageFromUrl($imageData->url);

        return new ImageGenerationResponse(
            image: $imagePayload,
            revisedPrompt: $imageData->revisedPrompt ?? null,
            model: $model,
            rawResponse: $response->toArray(),
        );
    }

    /**
     * Build messages array for OpenAI API.
     *
     * @param  array<ChatMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(array $messages): array
    {
        return array_map(function (ChatMessage $message) {
            if ($message->isMultimodal()) {
                return [
                    'role' => $message->role,
                    'content' => $this->buildMultimodalContent($message->content),
                ];
            }

            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }, $messages);
    }

    /**
     * Build multimodal content array for OpenAI.
     *
     * @param  array<ContentPart>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function buildMultimodalContent(array $parts): array
    {
        $content = [];

        foreach ($parts as $part) {
            if ($part->isText()) {
                $content[] = [
                    'type' => 'text',
                    'text' => $part->text ?? '',
                ];
            } elseif ($part->imageUrl !== null) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $part->imageUrl,
                    ],
                ];
            } elseif ($part->imageBase64 !== null) {
                $mime = $part->mimeType ?? 'image/jpeg';
                $dataUri = "data:{$mime};base64,{$part->imageBase64}";
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $dataUri,
                    ],
                ];
            }
        }

        return $content;
    }

    /**
     * Build ChatResponse from OpenAI response.
     *
     * @param  \OpenAI\Responses\Chat\CreateResponse  $response
     */
    private function buildChatResponse($response, string $model): ChatResponse
    {
        $choice = $response->choices[0] ?? null;

        if (! $choice) {
            throw new RuntimeException('No response choices returned from OpenAI.');
        }

        $content = $choice->message->content ?? '';
        $finishReason = strtolower($choice->finishReason ?? '');

        $usage = null;
        if ($response->usage) {
            $usage = [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'total_tokens' => $response->usage->totalTokens,
            ];
        }

        return new ChatResponse(
            content: trim($content),
            finishReason: $finishReason,
            usage: $usage,
            model: $model,
            rawResponse: $response->toArray(),
        );
    }

    /**
     * Convert aspect ratio to DALL-E size parameter.
     */
    private function aspectRatioToSize(string $aspectRatio, string $model): string
    {
        // DALL-E 3 supported sizes: 1024x1024, 1792x1024, 1024x1792
        // DALL-E 2 supported sizes: 256x256, 512x512, 1024x1024
        $isDalle3 = str_contains($model, 'dall-e-3');

        return match ($aspectRatio) {
            '16:9', '16/9' => $isDalle3 ? '1792x1024' : '1024x1024',
            '9:16', '9/16' => $isDalle3 ? '1024x1792' : '1024x1024',
            '4:3', '4/3' => $isDalle3 ? '1792x1024' : '1024x1024',
            '3:4', '3/4' => $isDalle3 ? '1024x1792' : '1024x1024',
            default => '1024x1024',
        };
    }

    /**
     * Normalize model name by stripping provider prefix.
     *
     * Allows unified model naming (e.g., "openai/gpt-5") across all adapters.
     * OpenRouter uses prefixed names, but OpenAI API expects bare model names.
     */
    private function normalizeModelName(string $model): string
    {
        // Strip "openai/" prefix if present for OpenAI API compatibility
        if (str_starts_with($model, 'openai/')) {
            return substr($model, 7);
        }

        return $model;
    }

    /**
     * Get user-friendly error message from exception.
     */
    private function getErrorMessage(\Exception $exception): string
    {
        $message = $exception->getMessage();

        // OpenAI exceptions often include status codes in the message
        if (str_contains($message, '401') || str_contains($message, 'Incorrect API key')) {
            return 'OpenAI API key is invalid or expired.';
        }

        if (str_contains($message, '402') || str_contains($message, 'insufficient_quota')) {
            return 'OpenAI account has insufficient credits.';
        }

        if (str_contains($message, '429') || str_contains($message, 'Rate limit')) {
            return 'OpenAI rate limit exceeded. Please try again later.';
        }

        if (str_contains($message, '500') || str_contains($message, '502') || str_contains($message, '503')) {
            return 'OpenAI service is temporarily unavailable.';
        }

        if (str_contains($message, 'content_policy_violation')) {
            return 'The request was rejected due to content policy violation.';
        }

        if (str_contains($message, 'invalid_model') || str_contains($message, 'model_not_found')) {
            return 'The specified model is not available.';
        }

        return "OpenAI request failed: {$message}";
    }
}
