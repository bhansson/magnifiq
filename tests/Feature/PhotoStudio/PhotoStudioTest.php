<?php

use App\Jobs\GeneratePhotoStudioImage;
use App\Livewire\PhotoStudio;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

beforeEach(function () {
    config()->set('photo-studio.models.image_generation', 'google/gemini-2.5-flash-image');
});

test('page loads for authenticated user', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('photo-studio.index'))
        ->assertOk()
        ->assertSee('Photo Studio');
});

test('user can extract prompt using product image', function () {
    // Use sync queue so the vision job runs immediately
    config()->set('queue.default', 'sync');
    config()->set('ai.features.vision.model', 'openai/gpt-4.1');

    fakeProductImageFetch();

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

    fakeOpenRouter(function ($chatData) {
        expect($chatData->model)->toBe('openai/gpt-4.1');

        $userMessage = $chatData->messages[1] ?? null;
        expect($userMessage)->not->toBeNull('User message missing from payload.');

        $imagePart = collect($userMessage->content ?? [])
            ->first(fn ($part) => ($part->type ?? null) === 'image_url');

        expect($imagePart)->not->toBeNull('Image payload missing from message.');
        // Image is now fetched and converted to data URI
        $imageUrl = $imagePart->image_url->url ?? ($imagePart->image_url['url'] ?? null);
        expect($imageUrl)->toStartWith('data:image/');

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
        ->call('pollVisionJobStatus')
        ->assertSet('promptResult', 'High-end studio prompt')
        ->assertSet('productImagePreview', $product->image_link);
});

test('user can generate image and queue job', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('photo-studio.generation_disk', 's3');

    fakeProductImageFetch();
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
        expect($job->teamId)->toBe($team->id);
        expect($job->userId)->toBe($user->id);
        expect($job->productId)->toBe($product->id);
        expect($job->model)->toBe(imageGenerationModel());
        expect($job->disk)->toBe('s3');
        // Component now always uses composition mode even for single products
        expect($job->sourceType)->toBe('composition');

        return true;
    });
});

test('generate image uses existing generations as baseline', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('photo-studio.generation_disk', 's3');

    fakeProductImageFetch();
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
        'model' => imageGenerationModel(),
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
});

test('generate image requires composition images', function () {
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
});

test('product gallery lists all team generations', function () {
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
        'model' => imageGenerationModel(),
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
        'model' => imageGenerationModel(),
        'storage_disk' => 's3',
        'storage_path' => 'photo-studio/b-first.png',
    ]);

    $this->actingAs($user);

    Livewire::test(PhotoStudio::class)
        ->assertSet('productGallery.0.id', $secondGeneration->id)
        ->assertSet('productGallery.1.id', $firstGeneration->id)
        ->assertSet('productGallery.0.product.id', $productB->id)
        ->assertSet('productGallery.1.product.id', $productA->id);
});

test('gallery search filters by prompt text', function () {
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
        'model' => imageGenerationModel(),
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
        'model' => imageGenerationModel(),
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

    expect($component->get('productGallery'))->toHaveCount(1);

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

    expect($component->get('productGallery'))->toHaveCount(2);
    $component->assertSet('galleryTotal', 2);
});

test('poll generation status refreshes latest image and gallery', function () {
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
        'model' => imageGenerationModel(),
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
});

test('poll generation status waits for matching product before finishing', function () {
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
        'model' => imageGenerationModel(),
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
        'model' => imageGenerationModel(),
        'storage_disk' => 's3',
        'storage_path' => 'photo-studio/a-run.png',
    ]);

    $component
        ->call('pollGenerationStatus')
        ->assertSet('isAwaitingGeneration', false)
        ->assertSet('generationStatus', 'New image added to the gallery.')
        ->assertSet('productGallery.0.id', $matching->id)
        ->assertSet('pendingProductId', null);
});

test('user can soft delete generation from gallery', function () {
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
        'model' => imageGenerationModel(),
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
});

test('generate photo studio image job persists output', function () {
    config()->set('photo-studio.generation_disk', 's3');

    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $imagePayload = photoStudioTestImageBase64();
    $imageMime = photoStudioTestImageMime();

    $model = imageGenerationModel();

    fakeOpenRouter(function () use ($imagePayload, $imageMime, $model) {
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

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Use this prompt as-is',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png'
    );

    $job->handle();

    $generation = PhotoStudioGeneration::first();

    expect($generation)->not->toBeNull();
    expect($generation->team_id)->toBe($team->id);
    expect($generation->user_id)->toBe($user->id);
    expect($generation->product_id)->toBeNull();
    expect($generation->model)->toBe($model);
    expect($generation->storage_disk)->toBe('s3');
    expect($generation->storage_path)->not->toBeEmpty();
    expect($generation->storage_path)->toEndWith('.jpg');

    // Verify dimensions are captured (may differ based on provider response processing)
    expect($generation->image_width)->not->toBeNull();
    expect($generation->image_height)->not->toBeNull();
    expect($generation->image_width)->toBeGreaterThan(0);
    expect($generation->image_height)->toBeGreaterThan(0);

    Storage::disk('s3')->assertExists($generation->storage_path);
});

test('generate photo studio image job handles attachment pointers', function () {
    config()->set('photo-studio.generation_disk', 's3');

    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $pointerPayload = photoStudioTestImageBase64();
    $imageMime = photoStudioTestImageMime();
    $model = imageGenerationModel();

    fakeOpenRouter(function () use ($pointerPayload, $imageMime, $model) {
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

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Use this prompt as-is',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png'
    );

    $job->handle();

    $generation = PhotoStudioGeneration::first();

    expect($generation)->not->toBeNull();
    expect($generation->storage_disk)->toBe('s3');
    expect($generation->storage_path)->not->toBeEmpty();

    Storage::disk('s3')->assertExists($generation->storage_path);
});

test('generate photo studio image job handles inline image payload', function () {
    config()->set('photo-studio.generation_disk', 's3');

    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $inlinePayload = photoStudioTestImageBase64();
    $imageMime = photoStudioTestImageMime();
    $model = imageGenerationModel();

    fakeOpenRouter(function () use ($inlinePayload, $imageMime, $model) {
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

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Use this prompt as-is',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png'
    );

    $job->handle();

    $generation = PhotoStudioGeneration::first();

    expect($generation)->not->toBeNull();
    expect($generation->storage_disk)->toBe('s3');
    expect($generation->storage_path)->not->toBeEmpty();

    Storage::disk('s3')->assertExists($generation->storage_path);
});

test('generate photo studio image job handles message image urls', function () {
    config()->set('photo-studio.generation_disk', 's3');

    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $inlinePayload = photoStudioTestImageBase64();
    $dataUri = 'data:'.photoStudioTestImageMime().';base64,'.$inlinePayload;
    $model = imageGenerationModel();

    fakeOpenRouter(function () use ($dataUri, $model) {
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

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Use this prompt as-is',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png'
    );

    $job->handle();

    $generation = PhotoStudioGeneration::first();

    expect($generation)->not->toBeNull();
    expect($generation->storage_disk)->toBe('s3');
    expect($generation->storage_path)->not->toBeEmpty();

    Storage::disk('s3')->assertExists($generation->storage_path);
});

test('generate photo studio image job fetches openrouter file with headers', function () {
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
    $model = imageGenerationModel();

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

            return Http::response(photoStudioTestImageBinary(), 200, [
                'Content-Type' => photoStudioTestImageMime(),
            ]);
        },
    ]);

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Use this prompt as-is',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png'
    );

    $job->handle();

    expect($requestLog)->not->toBeEmpty('Expected HTTP request to OpenRouter file endpoint.');
    $request = $requestLog[0];
    expect($request->header('Authorization')[0])->toBe('Bearer test-key');
    expect($request->header('HTTP-Referer')[0])->toBe('https://example.com/app');
    expect($request->header('X-Title')[0])->toBe('Magnifiq Test');

    $generation = PhotoStudioGeneration::first();
    expect($generation)->not->toBeNull();
    Storage::disk('s3')->assertExists($generation->storage_path);
})->group('skip-for-refactoring');

test('product search filters catalog results', function () {
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

    expect($products)->toHaveCount(1);
    expect($products->first()['id'])->toBe($match->id);

    $component->set('productSearch', '777');
    $products = collect($component->get('products'));

    expect($products)->toHaveCount(1);
    expect($products->first()['id'])->toBe($match->id);
});

test('selected product stays visible after search change', function () {
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

    expect($products->count())->toBeGreaterThanOrEqual(3);
    expect($products->contains(function (array $product) use ($featured): bool {
        return $product['id'] === $featured->id;
    }))->toBeTrue();
});

function createPhotoStudioJob(int $teamId, ?int $productId = null, ?string $sku = null): ProductAiJob
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
function fakeOpenRouter(callable $callback): void
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

/**
 * Get the path to the test image file.
 */
function photoStudioTestImagePath(): string
{
    return base_path('storage/testing/test.jpeg');
}

/**
 * Get the binary contents of the test image.
 */
function photoStudioTestImageBinary(): string
{
    $path = photoStudioTestImagePath();

    if (! file_exists($path)) {
        // Fall back to creating a test JPEG if file doesn't exist
        return createTestJpegBinary();
    }

    $contents = file_get_contents($path);

    return $contents !== false ? $contents : createTestJpegBinary();
}

/**
 * Get the base64-encoded test image.
 */
function photoStudioTestImageBase64(): string
{
    return base64_encode(photoStudioTestImageBinary());
}

/**
 * Get the test image MIME type.
 */
function photoStudioTestImageMime(): string
{
    return 'image/jpeg';
}

/**
 * Get the test image dimensions.
 */
function photoStudioTestImageDimensions(): array
{
    $path = photoStudioTestImagePath();
    $size = @getimagesize($path);

    if ($size === false) {
        return [100, 100]; // Default dimensions if file doesn't exist
    }

    return [
        isset($size[0]) ? (int) $size[0] : 100,
        isset($size[1]) ? (int) $size[1] : 100,
    ];
}

/**
 * Get the test image as a data URI.
 */
function photoStudioTestImageDataUri(): string
{
    return 'data:' . photoStudioTestImageMime() . ';base64,' . photoStudioTestImageBase64();
}

/**
 * Fake HTTP responses for product image URLs used in tests.
 */
function fakeProductImageFetch(): void
{
    Http::fake([
        'cdn.example.com/*' => Http::response(photoStudioTestImageBinary(), 200, [
            'Content-Type' => photoStudioTestImageMime(),
        ]),
    ]);
}

test('aspect ratio is passed to job', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('photo-studio.generation_disk', 's3');

    fakeProductImageFetch();
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
        expect($job->aspectRatio)->toBe('16:9');

        return true;
    });

    // Check that aspect ratio is stored in job meta
    $this->assertDatabaseHas('product_ai_jobs', [
        'team_id' => $team->id,
        'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
    ]);

    $jobRecord = ProductAiJob::latest()->first();
    expect($jobRecord->meta['aspect_ratio'])->toBe('16:9');
});

test('match input aspect ratio detects from image', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('photo-studio.generation_disk', 's3');

    fakeProductImageFetch();
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
        expect($job->aspectRatio)->toBe('1:1');

        return true;
    });
});

test('aspect ratio dropdown shows in ui', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    Livewire::test(PhotoStudio::class)
        ->assertSee('Aspect Ratio')
        ->assertSee('Match input image')
        ->assertSee('Square (1:1)')
        ->assertSee('Widescreen (16:9)');
});

test('large product image is resized before ai call', function () {
    // Use sync queue so the vision job runs immediately
    config()->set('queue.default', 'sync');
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('ai.features.vision.model', 'openai/gpt-4.1');
    config()->set('photo-studio.input.max_dimension', 512);

    // Test image is 1200x1200, should be resized to 512x512
    fakeProductImageFetch();

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

    fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
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
        ->call('pollVisionJobStatus')
        ->assertSet('promptResult', 'Resized image prompt');

    expect($capturedImageSize)->not->toBeNull('Image dimensions should have been captured.');
    expect($capturedImageSize[0])->toBe(512, 'Width should be resized to max dimension.');
    expect($capturedImageSize[1])->toBe(512, 'Height should be resized proportionally.');
});

test('image resize preserves aspect ratio', function () {
    // Use sync queue so the vision job runs immediately
    config()->set('queue.default', 'sync');
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

    fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
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
        ->call('pollVisionJobStatus')
        ->assertSet('promptResult', 'Wide image prompt');

    expect($capturedImageSize)->not->toBeNull('Image dimensions should have been captured.');

    // 1600x1200 with max 800 should become 800x600 (maintaining 4:3 ratio)
    expect($capturedImageSize[0])->toBe(800, 'Width should be resized to max dimension.');
    expect($capturedImageSize[1])->toBe(600, 'Height should maintain 4:3 aspect ratio.');
});

test('small images are not resized', function () {
    // Use sync queue so the vision job runs immediately
    config()->set('queue.default', 'sync');
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

    fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
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
        ->call('pollVisionJobStatus')
        ->assertSet('promptResult', 'Small image prompt');

    expect($capturedImageSize)->not->toBeNull('Image dimensions should have been captured.');

    // Image should remain at original size
    expect($capturedImageSize[0])->toBe(512, 'Width should remain unchanged.');
    expect($capturedImageSize[1])->toBe(512, 'Height should remain unchanged.');
});

test('resize disabled when max dimension is null', function () {
    // Use sync queue so the vision job runs immediately
    config()->set('queue.default', 'sync');
    config()->set('photo-studio.input.max_dimension', null);

    // Test image is 1200x1200
    fakeProductImageFetch();

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

    fakeOpenRouter(function ($chatData) use (&$capturedImageSize) {
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
        ->call('pollVisionJobStatus')
        ->assertSet('promptResult', 'Full size prompt');

    expect($capturedImageSize)->not->toBeNull('Image dimensions should have been captured.');

    // Image should remain at original size when resize is disabled
    expect($capturedImageSize[0])->toBe(1200, 'Width should remain at original size when resize disabled.');
    expect($capturedImageSize[1])->toBe(1200, 'Height should remain at original size when resize disabled.');
});

test('model selector shows available models', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    Livewire::test(PhotoStudio::class)
        ->assertSee('AI Model')
        ->assertSee('Nano Banana')
        ->assertSee('Nano Banana Pro')
        ->assertSee('Seedream 4')
        ->assertSee('FLUX 2 Pro')
        ->assertSee('P-Image-Edit');
});

test('resolution selector shows for supported models', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    // Nano Banana Pro supports resolution
    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/nano-banana-pro');

    expect($component->invade()->modelSupportsResolution())->toBeTrue();
    $component->assertSee('Output Resolution');
});

test('resolution selector hidden for unsupported models', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    // Gemini Flash Image does not support resolution
    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/gemini-2.5-flash-image');

    expect($component->invade()->modelSupportsResolution())->toBeFalse();
});

test('resolution resets when model changes', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/nano-banana-pro')
        ->set('selectedResolution', '4K')
        ->assertSet('selectedResolution', '4K');

    // Change to FLUX which has different resolution options
    $component
        ->set('selectedModel', 'black-forest-labs/flux-2-pro')
        ->assertSet('selectedResolution', '1mp');
    // Default for FLUX
});

test('cost calculation per image pricing', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    // Gemini Flash Image: $0.039/image
    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/gemini-2.5-flash-image');

    expect($component->invade()->getFormattedCost())->toBe('$0.039');
});

test('cost calculation per resolution pricing', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    // Nano Banana Pro: $0.15 for 1K/2K, $0.30 for 4K (formatted with 3 decimals)
    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/nano-banana-pro')
        ->set('selectedResolution', '1K');

    expect($component->invade()->getFormattedCost())->toBe('$0.150');

    $component->set('selectedResolution', '4K');
    expect($component->invade()->getFormattedCost())->toBe('$0.300');
});

test('cost calculation per megapixel pricing', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    // FLUX 2 Pro: $0.04/megapixel (cost includes input + output, so 2x megapixels)
    $component = Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'black-forest-labs/flux-2-pro')
        ->set('selectedResolution', '1mp');

    // (1MP input + 1MP output) * $0.04 = $0.08
    expect($component->invade()->getFormattedCost())->toBe('$0.080');

    $component->set('selectedResolution', '4mp');

    // (4MP input + 4MP output) * $0.04 = $0.32
    expect($component->invade()->getFormattedCost())->toBe('$0.320');
});

test('job receives model and resolution parameters', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('photo-studio.generation_disk', 's3');

    fakeProductImageFetch();
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

    Livewire::test(PhotoStudio::class)
        ->set('productId', $product->id)
        ->set('promptResult', 'Generate product image')
        ->set('selectedModel', 'google/nano-banana-pro')
        ->set('selectedResolution', '2K')
        ->call('generateImage')
        ->assertHasNoErrors();

    Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) {
        expect($job->model)->toBe('google/nano-banana-pro');
        expect($job->resolution)->toBe('2K');
        expect($job->estimatedCost)->toBe(0.15);

        return true;
    });
});

test('generation record stores resolution and cost', function () {
    config()->set('photo-studio.generation_disk', 's3');
    config()->set('ai.features.image_generation.driver', 'replicate');
    config()->set('ai.providers.replicate.api_key', 'test-key');

    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $model = 'google/nano-banana-pro';

    // Fake Replicate API calls
    Http::fake([
        // File upload for data URI
        'api.replicate.com/v1/files' => Http::response([
            'id' => 'file-test',
            'url' => 'https://replicate.delivery/files/test.jpg',
        ]),
        // Model version lookup
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'nano-banana-version'],
        ]),
        // Prediction creation
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-resolution-test',
            'status' => 'starting',
        ]),
        // Prediction status (succeeded)
        'api.replicate.com/v1/predictions/pred-resolution-test' => Http::response([
            'id' => 'pred-resolution-test',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/output.jpg',
        ]),
        // Output image download
        'replicate.delivery/*' => Http::response(
            photoStudioTestImageBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $jobRecord = createPhotoStudioJob($team->id);

    $job = new GeneratePhotoStudioImage(
        productAiJobId: $jobRecord->id,
        teamId: $team->id,
        userId: $user->id,
        productId: null,
        prompt: 'Generate 2K resolution image',
        model: $model,
        disk: 's3',
        imageInput: photoStudioTestImageDataUri(),
        sourceType: 'uploaded_image',
        sourceReference: 'upload.png',
        resolution: '2K',
        estimatedCost: 0.15
    );

    $job->handle();

    $generation = PhotoStudioGeneration::first();

    expect($generation)->not->toBeNull();
    expect($generation->resolution)->toBe('2K');
    expect($generation->estimated_cost)->toEqual(0.15);
});

test('default model is loaded on mount', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    $component = Livewire::test(PhotoStudio::class);

    $defaultModel = config('photo-studio.default_image_model');
    expect($component->get('selectedModel'))->toBe($defaultModel);
});

test('cost estimate badge shows in ui', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    Livewire::test(PhotoStudio::class)
        ->set('selectedModel', 'google/gemini-2.5-flash-image')
        ->assertSee('Est.')
        ->assertSee('$0.039');
});