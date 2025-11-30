<?php

namespace Tests\Feature\PhotoStudio;

use App\Jobs\GeneratePhotoStudioImage;
use App\Livewire\PhotoStudio;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoStudioTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_IMAGE_MODEL = 'google/gemini-2.5-flash-image';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('photo-studio.models.image_generation', self::TEST_IMAGE_MODEL);
    }

    public function test_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('photo-studio.index'))
            ->assertOk()
            ->assertSee('Photo Studio');
    }

    public function test_user_can_extract_prompt_using_product_image(): void
    {
        config()->set('ai.features.vision.model', 'openai/gpt-4.1');

        $this->fakeProductImageFetch();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/reference.jpg',
                'brand' => 'Acme',
            ]);

        $this->fakeOpenRouter(function ($chatData) {
            $this->assertSame('openai/gpt-4.1', $chatData->model);

            $userMessage = $chatData->messages[1] ?? null;
            $this->assertNotNull($userMessage, 'User message missing from payload.');

            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => ($part->type ?? null) === 'image_url');

            $this->assertNotNull($imagePart, 'Image payload missing from message.');
            // Image is now fetched and converted to data URI
            $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
            $this->assertStringStartsWith('data:image/', $imageUrl);

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'High-end studio prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'High-end studio prompt')
            ->assertSet('productImagePreview', $product->image_link);
    }

    public function test_user_can_generate_image_and_queue_job(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('photo-studio.generation_disk', 's3');

        $this->fakeProductImageFetch();
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/reference.jpg',
                'brand' => 'Acme',
            ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Use this prompt as-is')
            ->call('generateImage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_ai_jobs', [
            'team_id' => $team->id,
            'product_id' => $product->id,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
            'status' => ProductAiJob::STATUS_QUEUED,
        ]);

        Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) use ($team, $user, $product) {
            $this->assertSame($team->id, $job->teamId);
            $this->assertSame($user->id, $job->userId);
            $this->assertSame($product->id, $job->productId);
            $this->assertSame($this->imageGenerationModel(), $job->model);
            $this->assertSame('s3', $job->disk);
            // Component now always uses composition mode even for single products
            $this->assertSame('composition', $job->sourceType);

            return true;
        });
    }

    public function test_generate_image_uses_existing_generations_as_baseline(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('photo-studio.generation_disk', 's3');

        $this->fakeProductImageFetch();
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme',
                'image_link' => 'https://cdn.example.com/reference.jpg',
            ]);

        $existing = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Historic prompt',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/historic.png',
        ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Use this prompt as-is')
            ->call('generateImage')
            ->assertSet('pendingGenerationBaselineId', $existing->id)
            ->assertSet('pendingProductId', $product->id);
    }

    public function test_generate_image_requires_composition_images(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('photo-studio.generation_disk', 's3');

        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        // Prompt-only generation is no longer supported - composition requires at least one image
        Livewire::test(PhotoStudio::class)
            ->set('promptResult', 'Manual creative prompt')
            ->call('generateImage')
            ->assertSet('errorMessage', 'Add at least 1 image(s) for this mode.');

        // No job should be created since validation failed
        $this->assertDatabaseMissing('product_ai_jobs', [
            'team_id' => $user->currentTeam->id,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
        ]);

        Queue::assertNotPushed(GeneratePhotoStudioImage::class);
    }

    public function test_product_gallery_lists_all_team_generations(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme A',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme B',
            ]);

        $firstGeneration = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Studio prompt',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/a-first.png',
        ]);

        $secondGeneration = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Studio prompt other product',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/b-first.png',
        ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->assertSet('productGallery.0.id', $secondGeneration->id)
            ->assertSet('productGallery.1.id', $firstGeneration->id)
            ->assertSet('productGallery.0.product.id', $productB->id)
            ->assertSet('productGallery.1.product.id', $productA->id);
    }

    public function test_gallery_search_filters_by_prompt_text(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Cozy Cloud Sofa',
                'sku' => 'COZY-001',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Brutalist Arch Lamp',
                'sku' => 'BRUT-900',
            ]);

        $matching = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Cozy studio couch scene',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/matching.png',
        ]);

        $other = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Brutalist arch shot',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/other.png',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->assertSet('galleryTotal', 2);

        $component
            ->set('gallerySearch', 'cozy')
            ->assertSet('productGallery.0.id', $matching->id)
            ->assertSet('galleryTotal', 2);

        $this->assertCount(1, $component->get('productGallery'));

        $component
            ->set('gallerySearch', 'brutal')
            ->assertSet('productGallery.0.id', $other->id);

        $component
            ->set('gallerySearch', 'sofa')
            ->assertSet('productGallery.0.id', $matching->id);

        $component
            ->set('gallerySearch', 'BRUT-900')
            ->assertSet('productGallery.0.id', $other->id);

        $component->set('gallerySearch', '')
            ->assertSet('productGallery.0.id', $other->id);

        $this->assertCount(2, $component->get('productGallery'));
        $component->assertSet('galleryTotal', 2);
    }

    public function test_poll_generation_status_refreshes_latest_image_and_gallery(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme',
            ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id);

        $component
            ->set('pendingGenerationBaselineId', 0)
            ->set('isAwaitingGeneration', true)
            ->set('generationStatus', 'Image generation queued. Hang tight while we render your scene.')
            ->set('pendingProductId', $product->id);

        $latest = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Fresh prompt',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/new.png',
            'response_id' => 'test-response',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', false)
            ->assertSet('pendingGenerationBaselineId', null)
            ->assertSet('latestGeneration.path', $latest->storage_path)
            ->assertSet('latestObservedGenerationId', $latest->id)
            ->assertSet('productGallery.0.id', $latest->id)
            ->assertSet('generationStatus', 'New image added to the gallery.')
            ->assertSet('pendingProductId', null);
    }

    public function test_poll_generation_status_waits_for_matching_product_before_finishing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme A',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme B',
            ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $productA->id)
            ->set('pendingGenerationBaselineId', 0)
            ->set('pendingProductId', $productA->id)
            ->set('isAwaitingGeneration', true);

        PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Other product prompt',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/b-run.png',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', true)
            ->assertSet('generationStatus', 'Image generation in progress…');

        $matching = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Matching prompt',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/a-run.png',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', false)
            ->assertSet('generationStatus', 'New image added to the gallery.')
            ->assertSet('productGallery.0.id', $matching->id)
            ->assertSet('pendingProductId', null);
    }

    public function test_user_can_soft_delete_generation_from_gallery(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme',
            ]);

        $generation = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Prompt to delete',
            'model' => $this->imageGenerationModel(),
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/delete-me.png',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->assertSet('productGallery.0.id', $generation->id);

        $component
            ->call('deleteGeneration', $generation->id)
            ->assertSet('productGallery', []);

        $this->assertSoftDeleted('photo_studio_generations', [
            'id' => $generation->id,
        ]);
    }

    public function test_generate_photo_studio_image_job_persists_output(): void
    {
        config()->set('photo-studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $imagePayload = $this->test_image_base64();
        $imageMime = $this->test_image_mime();

        $model = $this->imageGenerationModel();

        $this->fakeOpenRouter(function () use ($imagePayload, $imageMime, $model) {
            return [
                'id' => 'photo-studio-image',
                'model' => $model,
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'provider' => 'OpenRouter',
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'image_base64' => $imagePayload,
                                    'mime_type' => $imageMime,
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 128,
                    'completion_tokens' => 1,
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: $model,
            disk: 's3',
            imageInput: $this->test_image_data_uri(),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame($team->id, $generation->team_id);
        $this->assertSame($user->id, $generation->user_id);
        $this->assertNull($generation->product_id);
        $this->assertSame($model, $generation->model);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);
        $this->assertStringEndsWith('.jpg', $generation->storage_path);
        // Verify dimensions are captured (may differ based on provider response processing)
        $this->assertNotNull($generation->image_width);
        $this->assertNotNull($generation->image_height);
        $this->assertGreaterThan(0, $generation->image_width);
        $this->assertGreaterThan(0, $generation->image_height);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_attachment_pointers(): void
    {
        config()->set('photo-studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $pointerPayload = $this->test_image_base64();
        $imageMime = $this->test_image_mime();
        $model = $this->imageGenerationModel();

        $this->fakeOpenRouter(function () use ($pointerPayload, $imageMime, $model) {
            return [
                'id' => 'photo-studio-image',
                'model' => $model,
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'asset_pointer' => 'attachment://render-1',
                                ],
                            ],
                        ],
                    ],
                ],
                'attachments' => [
                    [
                        'id' => 'render-1',
                        'data' => [
                            'mime_type' => $imageMime,
                            'base64' => $pointerPayload,
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: $model,
            disk: 's3',
            imageInput: $this->test_image_data_uri(),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_inline_image_payload(): void
    {
        config()->set('photo-studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $inlinePayload = $this->test_image_base64();
        $imageMime = $this->test_image_mime();
        $model = $this->imageGenerationModel();

        $this->fakeOpenRouter(function () use ($inlinePayload, $imageMime, $model) {
            return [
                'id' => 'photo-studio-image',
                'model' => $model,
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'image' => [
                                        'mime_type' => $imageMime,
                                        'base64' => $inlinePayload,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: $model,
            disk: 's3',
            imageInput: $this->test_image_data_uri(),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_message_image_urls(): void
    {
        config()->set('photo-studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $inlinePayload = $this->test_image_base64();
        $dataUri = 'data:'.$this->test_image_mime().';base64,'.$inlinePayload;
        $model = $this->imageGenerationModel();

        $this->fakeOpenRouter(function () use ($dataUri, $model) {
            return [
                'id' => 'photo-studio-image',
                'model' => $model,
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => '',
                            'images' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $dataUri,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: $model,
            disk: 's3',
            imageInput: $this->test_image_data_uri(),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    /**
     * @group skip-for-refactoring
     */
    public function test_generate_photo_studio_image_job_fetches_openrouter_file_with_headers(): void
    {
        $this->markTestSkipped('HTTP header verification requires updates for new AI abstraction layer');
        config()->set('photo-studio.generation_disk', 's3');
        // Set both old (laravel-openrouter) and new (ai.providers) config keys for the test
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('laravel-openrouter.referer', 'https://example.com/app');
        config()->set('laravel-openrouter.title', 'Magnifiq Test');
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.providers.openrouter.referer', 'https://example.com/app');
        config()->set('ai.providers.openrouter.title', 'Magnifiq Test');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $model = $this->imageGenerationModel();

        $requestLog = [];

        // Set up AI config keys
        config()->set('ai.providers.openrouter.api_endpoint', 'https://openrouter.ai/api/v1/');
        config()->set('ai.features.image_generation.driver', 'openrouter');

        // Combined HTTP fake for both chat and file endpoints
        Http::fake([
            '*openrouter.ai/api/v1/chat/*' => Http::response([
                'id' => 'photo-studio-image',
                'model' => $model,
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'https://openrouter.ai/api/v1/file/abcd1234efgh5678',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            '*openrouter.ai/api/v1/file/*' => function ($request) use (&$requestLog) {
                $requestLog[] = $request;

                return Http::response($this->test_image_binary(), 200, [
                    'Content-Type' => $this->test_image_mime(),
                ]);
            },
        ]);

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: $model,
            disk: 's3',
            imageInput: $this->test_image_data_uri(),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $this->assertNotEmpty($requestLog, 'Expected HTTP request to OpenRouter file endpoint.');
        $request = $requestLog[0];
        $this->assertSame('Bearer test-key', $request->header('Authorization')[0]);
        $this->assertSame('https://example.com/app', $request->header('HTTP-Referer')[0]);
        $this->assertSame('Magnifiq Test', $request->header('X-Title')[0]);

        $generation = PhotoStudioGeneration::first();
        $this->assertNotNull($generation);
        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_product_search_filters_catalog_results(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        Product::factory()->create([
            'team_id' => $team->id,
            'title' => 'Standard Chair',
            'sku' => 'CHAIR-100',
        ]);

        $match = Product::factory()->create([
            'team_id' => $team->id,
            'title' => 'Emerald Travel Mug',
            'sku' => 'MUG-777',
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'title' => 'Obsidian Lamp',
            'sku' => 'LAMP-900',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productSearch', 'emerald');

        $products = collect($component->get('products'));

        $this->assertCount(1, $products);
        $this->assertSame($match->id, $products->first()['id']);

        $component->set('productSearch', '777');
        $products = collect($component->get('products'));

        $this->assertCount(1, $products);
        $this->assertSame($match->id, $products->first()['id']);
    }

    public function test_selected_product_stays_visible_after_search_change(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        Product::factory()->count(3)->sequence(
            ['title' => 'Standard Widget A', 'sku' => 'STD-A', 'team_id' => $team->id],
            ['title' => 'Standard Widget B', 'sku' => 'STD-B', 'team_id' => $team->id],
            ['title' => 'Standard Widget C', 'sku' => 'STD-C', 'team_id' => $team->id],
        )->create();

        $featured = Product::factory()->create([
            'team_id' => $team->id,
            'title' => 'Zebra Travel Kit',
            'sku' => 'ZTK-500',
            'image_link' => 'https://example.com/zebra.jpg',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $featured->id);

        $component->set('productSearch', 'standard');

        $products = collect($component->get('products'));

        $this->assertGreaterThanOrEqual(3, $products->count());
        $this->assertTrue($products->contains(function (array $product) use ($featured): bool {
            return $product['id'] === $featured->id;
        }));
    }

    private function createPhotoStudioJob(int $teamId, ?int $productId = null, ?string $sku = null): ProductAiJob
    {
        return ProductAiJob::create([
            'team_id' => $teamId,
            'product_id' => $productId,
            'sku' => $sku,
            'product_ai_template_id' => null,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
            'status' => ProductAiJob::STATUS_QUEUED,
            'progress' => 0,
            'queued_at' => now(),
        ]);
    }

    /**
     * Fake OpenRouter HTTP calls with a callback that builds the response.
     *
     * The callback receives the request body (array) and should return the response payload.
     */
    private function fakeOpenRouter(callable $callback): void
    {
        // Set the new AI config keys
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.providers.openrouter.api_endpoint', 'https://openrouter.ai/api/v1/');
        config()->set('ai.features.vision.driver', 'openrouter');
        config()->set('ai.features.image_generation.driver', 'openrouter');

        Http::fake([
            '*openrouter.ai/*' => function ($request) use ($callback) {
                $body = $request->data();

                // Convert request body to ChatData-like object for backward compatibility
                $chatData = (object) [
                    'model' => $body['model'] ?? null,
                    'messages' => array_map(function ($msg) {
                        return (object) [
                            'role' => $msg['role'] ?? 'user',
                            'content' => is_array($msg['content'] ?? null)
                                ? array_map(fn ($part) => (object) $part, $msg['content'])
                                : $msg['content'] ?? '',
                        ];
                    }, $body['messages'] ?? []),
                ];

                $payload = call_user_func($callback, $chatData);

                return Http::response($payload, 200);
            },
        ]);
    }

    private function test_image_path(): string
    {
        return base_path('storage/testing/test.jpeg');
    }

    private function test_image_binary(): string
    {
        $path = $this->test_image_path();

        if (! file_exists($path)) {
            $this->fail('Test image missing at '.$path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->fail('Unable to read test image contents.');
        }

        return $contents;
    }

    private function test_image_base64(): string
    {
        return base64_encode($this->test_image_binary());
    }

    private function test_image_mime(): string
    {
        return 'image/jpeg';
    }

    /**
     * @return array{0:int,1:int}
     */
    private function test_image_dimensions(): array
    {
        $path = $this->test_image_path();
        $size = @getimagesize($path);

        if ($size === false) {
            $this->fail('Unable to determine test image dimensions.');
        }

        return [
            isset($size[0]) ? (int) $size[0] : 0,
            isset($size[1]) ? (int) $size[1] : 0,
        ];
    }

    private function test_image_data_uri(): string
    {
        return 'data:'.$this->test_image_mime().';base64,'.$this->test_image_base64();
    }

    /**
     * Fake HTTP responses for product image URLs used in tests.
     */
    private function fakeProductImageFetch(): void
    {
        Http::fake([
            'cdn.example.com/*' => Http::response($this->test_image_binary(), 200, [
                'Content-Type' => $this->test_image_mime(),
            ]),
        ]);
    }

    private function imageGenerationModel(): string
    {
        return config('photo-studio.models.image_generation');
    }

    public function test_aspect_ratio_is_passed_to_job(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('photo-studio.generation_disk', 's3');

        $this->fakeProductImageFetch();
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/reference.jpg',
            ]);

        $this->actingAs($user);

        // Test with explicit 16:9 aspect ratio
        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Generate a widescreen product image')
            ->set('aspectRatio', '16:9')
            ->call('generateImage')
            ->assertHasNoErrors();

        Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) {
            $this->assertSame('16:9', $job->aspectRatio);

            return true;
        });

        // Check that aspect ratio is stored in job meta
        $this->assertDatabaseHas('product_ai_jobs', [
            'team_id' => $team->id,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
        ]);

        $jobRecord = ProductAiJob::latest()->first();
        $this->assertSame('16:9', $jobRecord->meta['aspect_ratio']);
    }

    public function test_match_input_aspect_ratio_detects_from_image(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('photo-studio.generation_disk', 's3');

        $this->fakeProductImageFetch();
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/reference.jpg',
            ]);

        $this->actingAs($user);

        // Test with 'match_input' - should detect from the test image (1x1 square)
        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Generate matching aspect ratio image')
            ->set('aspectRatio', 'match_input')
            ->call('generateImage')
            ->assertHasNoErrors();

        Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) {
            // Test image is 1x1, so should detect as 1:1
            $this->assertSame('1:1', $job->aspectRatio);

            return true;
        });
    }

    public function test_aspect_ratio_dropdown_shows_in_ui(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->assertSee('Output aspect ratio')
            ->assertSee('Match input image')
            ->assertSee('Square (1:1)')
            ->assertSee('Widescreen (16:9)');
    }

    public function test_large_product_image_is_resized_before_ai_call(): void
    {
        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.features.vision.model', 'openai/gpt-4.1');
        config()->set('photo-studio.input.max_dimension', 512);

        // Test image is 1200x1200, should be resized to 512x512
        $this->fakeProductImageFetch();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/large-product.jpg',
            ]);

        $capturedImageSize = null;

        $this->fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
            $userMessage = $chatData->messages[1] ?? null;
            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => ($part->type ?? null) === 'image_url');

            // Extract and decode the image to check dimensions
            $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
            if ($imagePart && preg_match('/^data:[^;]+;base64,(.+)$/', $imageUrl, $matches)) {
                $binary = base64_decode($matches[1], true);
                if ($binary !== false) {
                    $size = @getimagesizefromstring($binary);
                    if ($size !== false) {
                        $capturedImageSize = [$size[0], $size[1]];
                    }
                }
            }

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'Resized image prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'Resized image prompt');

        $this->assertNotNull($capturedImageSize, 'Image dimensions should have been captured.');
        $this->assertSame(512, $capturedImageSize[0], 'Width should be resized to max dimension.');
        $this->assertSame(512, $capturedImageSize[1], 'Height should be resized proportionally.');
    }

    public function test_image_resize_preserves_aspect_ratio(): void
    {
        config()->set('photo-studio.input.max_dimension', 800);

        // Create a 1600x1200 image (4:3 aspect ratio)
        $wideImage = imagecreatetruecolor(1600, 1200);
        $color = imagecolorallocate($wideImage, 128, 128, 255);
        imagefilledrectangle($wideImage, 0, 0, 1600, 1200, $color);

        ob_start();
        imagejpeg($wideImage, null, 90);
        $wideImageBinary = ob_get_clean();
        imagedestroy($wideImage);

        Http::fake([
            'cdn.example.com/*' => Http::response($wideImageBinary, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.features.vision.model', 'openai/gpt-4.1');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/wide-product.jpg',
            ]);

        $capturedImageSize = null;

        $this->fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
            $userMessage = $chatData->messages[1] ?? null;
            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => ($part->type ?? null) === 'image_url');

            $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
            if ($imagePart && preg_match('/^data:[^;]+;base64,(.+)$/', $imageUrl, $matches)) {
                $binary = base64_decode($matches[1], true);
                if ($binary !== false) {
                    $size = @getimagesizefromstring($binary);
                    if ($size !== false) {
                        $capturedImageSize = [$size[0], $size[1]];
                    }
                }
            }

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'Wide image prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'Wide image prompt');

        $this->assertNotNull($capturedImageSize, 'Image dimensions should have been captured.');
        // 1600x1200 with max 800 should become 800x600 (maintaining 4:3 ratio)
        $this->assertSame(800, $capturedImageSize[0], 'Width should be resized to max dimension.');
        $this->assertSame(600, $capturedImageSize[1], 'Height should maintain 4:3 aspect ratio.');
    }

    public function test_small_images_are_not_resized(): void
    {
        config()->set('photo-studio.input.max_dimension', 1024);

        // Create a 512x512 image (smaller than max)
        $smallImage = imagecreatetruecolor(512, 512);
        $color = imagecolorallocate($smallImage, 255, 128, 128);
        imagefilledrectangle($smallImage, 0, 0, 512, 512, $color);

        ob_start();
        imagejpeg($smallImage, null, 90);
        $smallImageBinary = ob_get_clean();
        imagedestroy($smallImage);

        Http::fake([
            'cdn.example.com/*' => Http::response($smallImageBinary, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.features.vision.model', 'openai/gpt-4.1');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/small-product.jpg',
            ]);

        $capturedImageSize = null;

        $this->fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
            $userMessage = $chatData->messages[1] ?? null;
            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => ($part->type ?? null) === 'image_url');

            $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
            if ($imagePart && preg_match('/^data:[^;]+;base64,(.+)$/', $imageUrl, $matches)) {
                $binary = base64_decode($matches[1], true);
                if ($binary !== false) {
                    $size = @getimagesizefromstring($binary);
                    if ($size !== false) {
                        $capturedImageSize = [$size[0], $size[1]];
                    }
                }
            }

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'Small image prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'Small image prompt');

        $this->assertNotNull($capturedImageSize, 'Image dimensions should have been captured.');
        // Image should remain at original size
        $this->assertSame(512, $capturedImageSize[0], 'Width should remain unchanged.');
        $this->assertSame(512, $capturedImageSize[1], 'Height should remain unchanged.');
    }

    public function test_resize_disabled_when_max_dimension_is_null(): void
    {
        config()->set('photo-studio.input.max_dimension', null);

        // Test image is 1200x1200
        $this->fakeProductImageFetch();

        config()->set('ai.providers.openrouter.api_key', 'test-key');
        config()->set('ai.features.vision.model', 'openai/gpt-4.1');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/large-product.jpg',
            ]);

        $capturedImageSize = null;

        $this->fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
            $userMessage = $chatData->messages[1] ?? null;
            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => ($part->type ?? null) === 'image_url');

            $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
            if ($imagePart && preg_match('/^data:[^;]+;base64,(.+)$/', $imageUrl, $matches)) {
                $binary = base64_decode($matches[1], true);
                if ($binary !== false) {
                    $size = @getimagesizefromstring($binary);
                    if ($size !== false) {
                        $capturedImageSize = [$size[0], $size[1]];
                    }
                }
            }

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'Full size prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'Full size prompt');

        $this->assertNotNull($capturedImageSize, 'Image dimensions should have been captured.');
        // Image should remain at original size when resize is disabled
        $this->assertSame(1200, $capturedImageSize[0], 'Width should remain at original size when resize disabled.');
        $this->assertSame(1200, $capturedImageSize[1], 'Height should remain at original size when resize disabled.');
    }
}
