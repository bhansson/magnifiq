<?php

namespace App\Services\AI\Adapters;

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatRequest;
use App\DTO\AI\ChatResponse;
use App\DTO\AI\ContentPart;
use App\DTO\AI\ImageGenerationRequest;
use App\DTO\AI\ImageGenerationResponse;
use App\DTO\AI\ImagePayload;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageConfigData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageUrlData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\TextContentData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use RuntimeException;

class OpenRouterAdapter extends AbstractAiAdapter
{
    public function getProviderName(): string
    {
        return 'openrouter';
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
     * Uses Laravel's HTTP client directly for testability with Http::fake().
     */
    public function chat(ChatRequest $request): ChatResponse
    {
        $this->getApiKey(); // Validate API key is set
        $endpoint = $this->getApiEndpoint().'chat/completions';

        $model = $this->resolveModel($request->model, 'chat');
        $messages = $this->buildMessagesForHttp($request->messages);

        $payload = array_filter([
            'messages' => $messages,
            'model' => $model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ], fn ($v) => $v !== null);

        $payload = array_merge($payload, $request->extra);

        $response = Http::timeout($this->getTimeout())
            ->withHeaders($this->getOpenRouterHeaders())
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $this->logError('Chat request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                $this->getErrorMessage($response->status(), $response->json('error.message'))
            );
        }

        $data = $response->json();

        return $this->buildChatResponse($data, $model);
    }

    /**
     * Execute an image generation request.
     */
    public function generateImage(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $this->getApiKey(); // Validate API key is set

        $model = $this->resolveModel($request->model, 'image_generation');
        $messages = $this->buildImageGenerationMessages($request);

        $imageConfig = $request->imageConfig?->aspectRatio
            ? new ImageConfigData(aspect_ratio: $request->imageConfig->aspectRatio)
            : null;

        $chatData = new ChatData(
            messages: $messages,
            model: $model,
            temperature: $request->temperature ?? 0.85,
            max_tokens: $request->maxTokens ?? 2048,
            modalities: ['text', 'image'],
            image_config: $imageConfig,
        );

        try {
            $response = LaravelOpenRouter::chatRequest($chatData)->toArray();
        } catch (GuzzleException $exception) {
            throw $this->handleGuzzleException($exception);
        }

        $imagePayload = $this->extractGeneratedImage($response);

        return new ImageGenerationResponse(
            image: $imagePayload,
            model: $model,
            rawResponse: $response,
        );
    }

    /**
     * Build MessageData array from ChatMessage objects.
     *
     * @param  array<ChatMessage>  $messages
     * @return array<MessageData>
     */
    private function buildMessages(array $messages): array
    {
        return array_map(function (ChatMessage $message) {
            if ($message->isMultimodal()) {
                return new MessageData(
                    role: $message->role,
                    content: $this->buildMultimodalContent($message->content),
                );
            }

            return new MessageData(
                role: $message->role,
                content: $message->content,
            );
        }, $messages);
    }

    /**
     * Build messages array for direct HTTP requests (without DTOs).
     *
     * @param  array<ChatMessage>  $messages
     * @return array<int, array{role: string, content: string|array}>
     */
    private function buildMessagesForHttp(array $messages): array
    {
        return array_map(function (ChatMessage $message) {
            if ($message->isMultimodal()) {
                return [
                    'role' => $message->role,
                    'content' => $this->buildMultimodalContentForHttp($message->content),
                ];
            }

            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }, $messages);
    }

    /**
     * Build multimodal content array for direct HTTP requests (without DTOs).
     *
     * @param  array<ContentPart>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function buildMultimodalContentForHttp(array $parts): array
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
                    'image_url' => ['url' => $part->imageUrl],
                ];
            } elseif ($part->imageBase64 !== null) {
                $mime = $part->mimeType ?? 'image/jpeg';
                $dataUri = "data:{$mime};base64,{$part->imageBase64}";
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $dataUri],
                ];
            }
        }

        return $content;
    }

    /**
     * Build multimodal content array for OpenRouter.
     *
     * @param  array<ContentPart>  $parts
     * @return array<TextContentData|ImageContentPartData>
     */
    private function buildMultimodalContent(array $parts): array
    {
        $content = [];

        foreach ($parts as $part) {
            if ($part->isText()) {
                $content[] = new TextContentData(
                    type: 'text',
                    text: $part->text ?? '',
                );
            } elseif ($part->imageUrl !== null) {
                $content[] = new ImageContentPartData(
                    type: 'image_url',
                    image_url: new ImageUrlData(url: $part->imageUrl),
                );
            } elseif ($part->imageBase64 !== null) {
                $mime = $part->mimeType ?? 'image/jpeg';
                $dataUri = "data:{$mime};base64,{$part->imageBase64}";
                $content[] = new ImageContentPartData(
                    type: 'image_url',
                    image_url: new ImageUrlData(url: $dataUri),
                );
            }
        }

        return $content;
    }

    /**
     * Build messages for image generation request.
     *
     * @return array<MessageData>
     */
    private function buildImageGenerationMessages(ImageGenerationRequest $request): array
    {
        $userContent = [];

        // Add text prompt
        $userContent[] = new TextContentData(
            type: 'text',
            text: $request->prompt,
        );

        // Add input images if provided
        foreach ($request->getInputImagesArray() as $image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $userContent[] = new ImageContentPartData(
                    type: 'image_url',
                    image_url: new ImageUrlData(url: $image),
                );
            } elseif (str_starts_with($image, 'data:')) {
                $userContent[] = new ImageContentPartData(
                    type: 'image_url',
                    image_url: new ImageUrlData(url: $image),
                );
            } else {
                // Assume base64
                $dataUri = 'data:image/jpeg;base64,'.$image;
                $userContent[] = new ImageContentPartData(
                    type: 'image_url',
                    image_url: new ImageUrlData(url: $dataUri),
                );
            }
        }

        return [
            new MessageData(
                role: 'user',
                content: $userContent,
            ),
        ];
    }

    /**
     * Build ChatResponse from OpenRouter response array.
     */
    private function buildChatResponse(array $data, string $model): ChatResponse
    {
        $rawContent = Arr::get($data, 'choices.0.message.content');
        $content = $this->normalizeMessageContent($rawContent);
        $finishReason = Str::lower((string) Arr::get($data, 'choices.0.finish_reason', ''));

        return new ChatResponse(
            content: $content,
            finishReason: $finishReason,
            usage: $data['usage'] ?? null,
            model: $model,
            rawResponse: $data,
        );
    }

    /**
     * Normalize message content to string.
     */
    private function normalizeMessageContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $segments = [];

        foreach ($content as $segment) {
            if (is_string($segment)) {
                $segments[] = $segment;

                continue;
            }

            if (is_array($segment)) {
                $text = $segment['text'] ?? $segment['content'] ?? null;
                if (is_string($text)) {
                    $segments[] = $text;
                }
            }
        }

        return trim(implode("\n", array_filter($segments)));
    }

    /**
     * Handle Guzzle exception and convert to RuntimeException with user-friendly message.
     */
    private function handleGuzzleException(GuzzleException $exception): RuntimeException
    {
        $errorMessage = 'Failed to communicate with AI provider.';
        $status = null;

        if (method_exists($exception, 'getResponse') && $exception->getResponse()) {
            $status = $exception->getResponse()->getStatusCode();

            $errorMessage = match ($status) {
                400 => 'The AI provider rejected the request. Check the parameters and try again.',
                401 => 'AI credentials are invalid or expired. Update the API key and retry.',
                402 => 'Out of credits or the request needs fewer max tokens.',
                403 => 'Provider flagged the request for moderation. Review the input content.',
                408 => 'Provider timed out before the model could respond.',
                429 => 'Provider rate limit was hit; wait a moment and try again.',
                502 => 'The selected AI model is currently unavailable. Try again shortly.',
                503 => 'No AI provider is available for the selected model at the moment.',
                default => $errorMessage,
            };
        }

        $this->logWarning('Request failed', [
            'status' => $status,
            'exception' => $exception->getMessage(),
        ]);

        return new RuntimeException($errorMessage, previous: $exception);
    }

    // =========================================================================
    // Image Extraction Logic (moved from GeneratePhotoStudioImage)
    // =========================================================================

    /**
     * Extract generated image from OpenRouter response.
     */
    private function extractGeneratedImage(array $response): ImagePayload
    {
        $choices = Arr::get($response, 'choices', []);
        $attachments = $this->normalizeAttachments(Arr::get($response, 'attachments', []));

        // Try choices first
        foreach ($choices as $choice) {
            $message = Arr::get($choice, 'message', []);
            $content = $message['content'] ?? null;

            $payload = $this->extractFromContent($content, $attachments);
            if ($payload) {
                return $payload;
            }

            if (! empty($message['images'])) {
                $payload = $this->extractFromImageEntries($message['images'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->extractFromAttachmentReferences($message['attachments'] ?? [], $attachments, true);
            if ($payload) {
                return $payload;
            }
        }

        // Try outputs
        $outputs = Arr::get($response, 'outputs', []);
        $payload = $this->extractFromOutputs($outputs, $attachments);
        if ($payload) {
            return $payload;
        }

        // Try top-level attachments
        $payload = $this->extractFromAttachmentReferences(Arr::get($response, 'attachments', []), $attachments, true);
        if ($payload) {
            return $payload;
        }

        // Try data entries (DALL-E style)
        $dataEntries = Arr::get($response, 'data', []);
        foreach ($dataEntries as $entry) {
            if (isset($entry['b64_json'])) {
                return $this->decodeBase64Image($entry['b64_json'], $entry['mime_type'] ?? null);
            }

            if (isset($entry['url'])) {
                return $this->fetchImageFromUrl($entry['url']);
            }
        }

        // Last resort: try inline data in first content
        $firstContent = Arr::get($choices, '0.message.content');
        if (is_string($firstContent)) {
            $inline = $this->tryDecodeInlineData($firstContent);
            if ($inline) {
                return $inline;
            }
        }

        $this->logWarning('Image response missing image payload', ['response' => $response]);

        throw new RuntimeException('Image data missing from provider response.');
    }

    /**
     * Decode base64 image data.
     */
    private function decodeBase64Image(string $encoded, ?string $mime = null): ImagePayload
    {
        // Handle data URI format
        if (str_contains($encoded, ',')) {
            [$header, $body] = array_pad(explode(',', $encoded, 2), 2, null);

            if ($body !== null && str_contains((string) $header, 'base64')) {
                $encoded = $body;

                if (! $mime && preg_match('/data:(.*?);/', (string) $header, $matches)) {
                    $mime = $matches[1] ?? null;
                }
            }
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            throw new RuntimeException('Failed to decode generated image payload.');
        }

        return ImagePayload::fromBinary($binary, $mime);
    }

    /**
     * Extract image from content field.
     *
     * @param  array<string, array<string, mixed>>  $attachments
     */
    private function extractFromContent(mixed $content, array $attachments): ?ImagePayload
    {
        if (is_string($content)) {
            return $this->tryDecodeInlineData($content);
        }

        if (! is_iterable($content)) {
            return null;
        }

        foreach ($content as $segment) {
            if (is_string($segment)) {
                $payload = $this->tryDecodeInlineData($segment);
                if ($payload) {
                    return $payload;
                }

                continue;
            }

            if (! is_array($segment)) {
                continue;
            }

            if (isset($segment['image']) && is_array($segment['image'])) {
                $imagePayload = $this->extractFromImageArray($segment['image']);
                if ($imagePayload) {
                    return $imagePayload;
                }
            }

            $base64 = $segment['image_base64'] ?? $segment['b64_json'] ?? ($segment['data'] ?? null);
            if ($base64 && is_string($base64)) {
                return $this->decodeBase64Image($base64, $segment['mime_type'] ?? null);
            }

            $url = Arr::get($segment, 'image_url.url', $segment['url'] ?? null);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->fetchImageFromUrl($url);
            }

            if (! empty($segment['asset_pointer'])) {
                $pointerPayload = $this->resolveAttachmentPointer($segment['asset_pointer'], $attachments);
                if ($pointerPayload) {
                    return $pointerPayload;
                }
            }

            if (! empty($segment['data']) && is_array($segment['data'])) {
                $dataPayload = $this->extractFromImageArray($segment['data'], $segment['mime_type'] ?? null);
                if ($dataPayload) {
                    return $dataPayload;
                }
            }
        }

        return null;
    }

    /**
     * Extract from image entries array.
     *
     * @param  array<int, mixed>  $entries
     * @param  array<string, array<string, mixed>>  $attachments
     */
    private function extractFromImageEntries(array $entries, array $attachments): ?ImagePayload
    {
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $payload = $this->tryDecodeInlineData($entry);
                if ($payload) {
                    return $payload;
                }

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['image_url']['url'])) {
                $url = $entry['image_url']['url'];

                if (is_string($url)) {
                    $payload = $this->tryDecodeInlineData($url);

                    if ($payload) {
                        return $payload;
                    }
                }
            }

            if (isset($entry['asset_pointer'])) {
                $payload = $this->resolveAttachmentPointer($entry['asset_pointer'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->extractFromImageArray($entry, null);
            if ($payload) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * Extract from attachment references.
     *
     * @param  array<int, mixed>  $references
     * @param  array<string, array<string, mixed>>  $attachments
     */
    private function extractFromAttachmentReferences(array $references, array $attachments, bool $allowRaw = false): ?ImagePayload
    {
        foreach ($references as $reference) {
            if (is_string($reference)) {
                $payload = $this->resolveAttachmentPointer($reference, $attachments);
                if ($payload) {
                    return $payload;
                }

                continue;
            }

            if (! is_array($reference)) {
                continue;
            }

            if (! empty($reference['asset_pointer'])) {
                $payload = $this->resolveAttachmentPointer($reference['asset_pointer'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            if ($allowRaw) {
                $payload = $this->decodeAttachmentRecord($reference);
                if ($payload) {
                    return $payload;
                }
            }
        }

        return null;
    }

    /**
     * Normalize attachments array to a map keyed by ID.
     *
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<string, array<string, mixed>>
     */
    private function normalizeAttachments(array $attachments): array
    {
        $map = [];

        foreach ($attachments as $attachment) {
            $id = $attachment['id'] ?? $attachment['name'] ?? null;

            if (! $id) {
                continue;
            }

            $map[$id] = $attachment;
            $map['attachment://'.$id] = $attachment;
            $map['asset://'.$id] = $attachment;
        }

        return $map;
    }

    /**
     * Resolve an attachment pointer to image data.
     *
     * @param  array<string, array<string, mixed>>  $attachments
     */
    private function resolveAttachmentPointer(string $pointer, array $attachments): ?ImagePayload
    {
        $candidate = $attachments[$pointer] ?? null;

        if (! $candidate) {
            $normalized = preg_replace('/^(attachment|asset):\/\//', '', $pointer);

            if (is_string($normalized)) {
                $candidate = $attachments[$normalized] ?? null;

                if (! $candidate && str_contains($normalized, '#')) {
                    $beforeHash = strstr($normalized, '#', true);
                    if ($beforeHash !== false) {
                        $candidate = $attachments[$beforeHash] ?? null;
                    }
                }
            }
        }

        if (! $candidate) {
            return null;
        }

        return $this->decodeAttachmentRecord($candidate);
    }

    /**
     * Decode an attachment record to image data.
     *
     * @param  array<string, mixed>  $attachment
     */
    private function decodeAttachmentRecord(array $attachment): ?ImagePayload
    {
        if (isset($attachment['data']) && ! is_array($attachment['data'])) {
            return $this->decodeBase64Image($attachment['data'], $attachment['mime_type'] ?? $attachment['mime'] ?? null);
        }

        if (isset($attachment['data']) && is_array($attachment['data'])) {
            $payload = $this->extractFromImageArray($attachment['data'], $attachment['mime_type'] ?? $attachment['mime'] ?? null);
            if ($payload) {
                return $payload;
            }
        }

        if (isset($attachment['b64_json'])) {
            return $this->decodeBase64Image($attachment['b64_json'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['image_base64'])) {
            return $this->decodeBase64Image($attachment['image_base64'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['image']) && is_array($attachment['image'])) {
            return $this->extractFromImageArray($attachment['image'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['url']) && filter_var($attachment['url'], FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($attachment['url']);
        }

        return null;
    }

    /**
     * Extract from outputs array.
     *
     * @param  array<int|string, mixed>  $outputs
     * @param  array<string, array<string, mixed>>  $attachments
     */
    private function extractFromOutputs(array $outputs, array $attachments): ?ImagePayload
    {
        foreach ($outputs as $output) {
            if (isset($output['content'])) {
                $payload = $this->extractFromContent($output['content'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->decodeAttachmentRecord($output);
            if ($payload) {
                return $payload;
            }

            if (isset($output['attachments'])) {
                $payload = $this->extractFromAttachmentReferences($output['attachments'], $attachments, true);
                if ($payload) {
                    return $payload;
                }
            }
        }

        return null;
    }

    /**
     * Extract from image array structure.
     *
     * @param  array<string, mixed>  $image
     */
    private function extractFromImageArray(array $image, ?string $fallbackMime = null): ?ImagePayload
    {
        $mime = $image['mime_type'] ?? $image['mime'] ?? $fallbackMime;

        if (isset($image['base64'])) {
            return $this->decodeBase64Image($image['base64'], $mime);
        }

        if (isset($image['b64_json'])) {
            return $this->decodeBase64Image($image['b64_json'], $mime);
        }

        if (isset($image['data'])) {
            if (is_string($image['data'])) {
                return $this->decodeBase64Image($image['data'], $mime);
            }

            if (is_array($image['data'])) {
                return $this->extractFromImageArray($image['data'], $mime);
            }
        }

        if (isset($image['url']) && filter_var($image['url'], FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($image['url']);
        }

        return null;
    }

    /**
     * Try to decode inline data from string content.
     */
    private function tryDecodeInlineData(string $content): ?ImagePayload
    {
        if (preg_match('/data:(image\\/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+\\/=\\r\\n]+)/', $content, $matches)) {
            return $this->decodeBase64Image($matches[0], $matches[1] ?? null);
        }

        if (filter_var($content, FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($content);
        }

        return null;
    }

    /**
     * Fetch image from URL, adding OpenRouter auth headers when needed.
     */
    protected function fetchImageFromUrl(string $url, array $headers = []): ImagePayload
    {
        if (str_starts_with($url, 'https://openrouter.ai/')) {
            $headers = array_merge($this->getOpenRouterHeaders(), $headers);
        }

        return parent::fetchImageFromUrl($url, $headers);
    }

    /**
     * Get user-friendly error message based on HTTP status code.
     */
    private function getErrorMessage(int $status, ?string $detail = null): string
    {
        $baseMessage = match ($status) {
            400 => 'Invalid request parameters.',
            401 => 'OpenRouter API key is invalid or expired.',
            402 => 'OpenRouter account has insufficient credits.',
            403 => 'Access denied to this model.',
            404 => 'Model not found.',
            422 => 'Invalid model input parameters.',
            429 => 'Rate limit exceeded. Please try again later.',
            500, 502, 503 => 'OpenRouter service is temporarily unavailable.',
            default => 'An error occurred communicating with OpenRouter.',
        };

        if ($detail) {
            return "{$baseMessage} Details: {$detail}";
        }

        return $baseMessage;
    }

    /**
     * Get OpenRouter authentication headers.
     *
     * @return array<string, string>
     */
    private function getOpenRouterHeaders(): array
    {
        $headers = [];

        $apiKey = $this->getConfig('api_key');
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $referer = $this->getConfig('referer');
        if ($referer) {
            $headers['HTTP-Referer'] = $referer;
        }

        $title = $this->getConfig('title');
        if ($title) {
            $headers['X-Title'] = $title;
        }

        return $headers;
    }
}
