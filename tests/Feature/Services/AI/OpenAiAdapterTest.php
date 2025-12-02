<?php

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatRequest;
use App\DTO\AI\ContentPart;
use App\DTO\AI\ImageGenerationRequest;
use App\Services\AI\Adapters\OpenAiAdapter;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatCreateResponse;
use OpenAI\Responses\Images\CreateResponse as ImagesCreateResponse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config()->set('ai.providers.openai.api_key', 'test-key');
    config()->set('ai.features.chat.model', 'gpt-4o');
    config()->set('ai.features.vision.model', 'gpt-4o');
    config()->set('ai.features.image_generation.model', 'dall-e-3');

    $this->adapter = new OpenAiAdapter([
        'api_key' => 'test-key',
        'timeout' => 120,
    ]);
});

test('provider name is openai', function () {
    expect($this->adapter->getProviderName())->toBe('openai');
});

test('supports chat completion', function () {
    expect($this->adapter->supportsChatCompletion())->toBeTrue();
});

test('supports image generation', function () {
    expect($this->adapter->supportsImageGeneration())->toBeTrue();
});

test('chat completion with simple text', function () {
    OpenAI::fake([
        ChatCreateResponse::fake([
            'id' => 'chatcmpl-test123',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
                'total_tokens' => 18,
            ],
        ]),
    ]);

    $request = new ChatRequest(
        messages: [
            ChatMessage::system('You are a helpful assistant.'),
            ChatMessage::user('Hello!'),
        ],
        model: 'gpt-4o',
    );

    $response = $this->adapter->chat($request);

    expect($response->content)->toBe('Hello! How can I help you today?');
    expect($response->finishReason)->toBe('stop');
    expect($response->getPromptTokens())->toBe(10);
    expect($response->getCompletionTokens())->toBe(8);
    expect($response->getTotalTokens())->toBe(18);
    expect($response->wasTruncated())->toBeFalse();
});

test('chat completion with multimodal content image url', function () {
    OpenAI::fake([
        ChatCreateResponse::fake([
            'id' => 'chatcmpl-vision123',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This image shows a beautiful sunset over the ocean.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $request = ChatRequest::multimodal(
        content: [
            ContentPart::text('What do you see in this image?'),
            ContentPart::imageUrl('https://example.com/sunset.jpg'),
        ],
        model: 'gpt-4o',
    );

    $response = $this->adapter->chat($request);

    expect($response->content)->toBe('This image shows a beautiful sunset over the ocean.');
    expect($response->finishReason)->toBe('stop');
});

test('chat completion with multimodal content base64 image', function () {
    OpenAI::fake([
        ChatCreateResponse::fake([
            'id' => 'chatcmpl-vision-base64',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'The image shows a product on a white background.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    // Small base64 encoded test image (1x1 red pixel PNG)
    $base64Image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $request = ChatRequest::multimodal(
        content: [
            ContentPart::text('Describe this product image.'),
            ContentPart::imageBase64($base64Image, 'image/png'),
        ],
        model: 'gpt-4o',
    );

    $response = $this->adapter->chat($request);

    expect($response->content)->toBe('The image shows a product on a white background.');
});

test('chat detects truncated response', function () {
    OpenAI::fake([
        ChatCreateResponse::fake([
            'id' => 'chatcmpl-truncated',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This response was cut off because...',
                    ],
                    'finish_reason' => 'length',
                ],
            ],
        ]),
    ]);

    $request = ChatRequest::simple('Tell me a very long story.', model: 'gpt-4o');

    $response = $this->adapter->chat($request);

    expect($response->wasTruncated())->toBeTrue();
    expect($response->finishReason)->toBe('length');
});

test('image generation with dalle3', function () {
    // Create a minimal PNG image for the test
    $pngBinary = createTestPngBinary();

    // Fake the image URL download
    Http::fake([
        'oaidalleapiprodscus.blob.core.windows.net/*' => Http::response($pngBinary, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    OpenAI::fake([
        ImagesCreateResponse::fake([
            'created' => time(),
            'data' => [
                [
                    'url' => 'https://oaidalleapiprodscus.blob.core.windows.net/test-image.png',
                    'revised_prompt' => 'A photorealistic image of a modern office with natural lighting.',
                ],
            ],
        ]),
    ]);

    $request = ImageGenerationRequest::textToImage(
        prompt: 'A modern office with natural lighting',
        model: 'dall-e-3',
    );

    $response = $this->adapter->generateImage($request);

    expect($response->image)->not->toBeNull();
    expect($response->model)->toBe('dall-e-3');
    expect($response->hasRevisedPrompt())->toBeTrue();
    expect($response->revisedPrompt)->toBe('A photorealistic image of a modern office with natural lighting.');
});

test('adapter can be resolved from ai manager', function () {
    config()->set('ai.default', 'openai');
    config()->set('ai.features.chat.driver', 'openai');
    config()->set('ai.providers.openai.api_key', 'test-key');

    $provider = app(\App\Services\AI\AiManager::class)->forFeature('chat');

    expect($provider)->toBeInstanceOf(OpenAiAdapter::class);
    expect($provider->getProviderName())->toBe('openai');
});

test('adapter resolves model from feature config', function () {
    config()->set('ai.features.chat.model', 'gpt-4-turbo');

    OpenAI::fake([
        ChatCreateResponse::fake([
            'model' => 'gpt-4-turbo',
            'choices' => [
                [
                    'message' => ['content' => 'Response from gpt-4-turbo'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    // Request without explicit model - should use feature config
    $request = new ChatRequest(
        messages: [ChatMessage::user('Hello')],
        model: null,
    );

    $response = $this->adapter->chat($request);

    expect($response->content)->toBe('Response from gpt-4-turbo');
});

test('adapter normalizes prefixed model names', function () {
    // Model names with "openai/" prefix should work (stripped for OpenAI API)
    config()->set('ai.features.chat.model', 'openai/gpt-5');

    OpenAI::fake([
        ChatCreateResponse::fake([
            'model' => 'gpt-5',
            'choices' => [
                [
                    'message' => ['content' => 'Response from gpt-5'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $request = new ChatRequest(
        messages: [ChatMessage::user('Hello')],
        model: null,
    );

    $response = $this->adapter->chat($request);

    expect($response->content)->toBe('Response from gpt-5');
});