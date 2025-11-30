<?php

namespace Tests\Feature\Services\AI;

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatRequest;
use App\DTO\AI\ContentPart;
use App\DTO\AI\ImageGenerationRequest;
use App\Services\AI\Adapters\OpenAiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatCreateResponse;
use OpenAI\Responses\Images\CreateResponse as ImagesCreateResponse;
use Tests\TestCase;

class OpenAiAdapterTest extends TestCase
{
    use RefreshDatabase;

    private OpenAiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.features.chat.model', 'gpt-4o');
        config()->set('ai.features.vision.model', 'gpt-4o');
        config()->set('ai.features.image_generation.model', 'dall-e-3');

        $this->adapter = new OpenAiAdapter([
            'api_key' => 'test-key',
            'timeout' => 120,
        ]);
    }

    public function test_provider_name_is_openai(): void
    {
        $this->assertSame('openai', $this->adapter->getProviderName());
    }

    public function test_supports_chat_completion(): void
    {
        $this->assertTrue($this->adapter->supportsChatCompletion());
    }

    public function test_supports_image_generation(): void
    {
        $this->assertTrue($this->adapter->supportsImageGeneration());
    }

    public function test_chat_completion_with_simple_text(): void
    {
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

        $this->assertSame('Hello! How can I help you today?', $response->content);
        $this->assertSame('stop', $response->finishReason);
        $this->assertSame(10, $response->getPromptTokens());
        $this->assertSame(8, $response->getCompletionTokens());
        $this->assertSame(18, $response->getTotalTokens());
        $this->assertFalse($response->wasTruncated());
    }

    public function test_chat_completion_with_multimodal_content_image_url(): void
    {
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

        $this->assertSame('This image shows a beautiful sunset over the ocean.', $response->content);
        $this->assertSame('stop', $response->finishReason);
    }

    public function test_chat_completion_with_multimodal_content_base64_image(): void
    {
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

        $this->assertSame('The image shows a product on a white background.', $response->content);
    }

    public function test_chat_detects_truncated_response(): void
    {
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

        $this->assertTrue($response->wasTruncated());
        $this->assertSame('length', $response->finishReason);
    }

    public function test_image_generation_with_dalle3(): void
    {
        // Create a minimal PNG image for the test
        $pngBinary = $this->createTestPngBinary();

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

        $this->assertNotNull($response->image);
        $this->assertSame('dall-e-3', $response->model);
        $this->assertTrue($response->hasRevisedPrompt());
        $this->assertSame('A photorealistic image of a modern office with natural lighting.', $response->revisedPrompt);
    }

    public function test_adapter_can_be_resolved_from_ai_manager(): void
    {
        config()->set('ai.default', 'openai');
        config()->set('ai.features.chat.driver', 'openai');
        config()->set('ai.providers.openai.api_key', 'test-key');

        $provider = app(\App\Services\AI\AiManager::class)->forFeature('chat');

        $this->assertInstanceOf(OpenAiAdapter::class, $provider);
        $this->assertSame('openai', $provider->getProviderName());
    }

    public function test_adapter_resolves_model_from_feature_config(): void
    {
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

        $this->assertSame('Response from gpt-4-turbo', $response->content);
    }

    public function test_adapter_normalizes_prefixed_model_names(): void
    {
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

        $this->assertSame('Response from gpt-5', $response->content);
    }

    /**
     * Create a minimal valid PNG binary for testing.
     */
    private function createTestPngBinary(): string
    {
        // 1x1 red pixel PNG
        $img = imagecreatetruecolor(1, 1);
        imagefilledrectangle($img, 0, 0, 1, 1, imagecolorallocate($img, 255, 0, 0));

        ob_start();
        imagepng($img);
        $binary = ob_get_clean();
        imagedestroy($img);

        return $binary;
    }
}
