<?php

use App\Jobs\GeneratePhotoStudioImage;
use App\Livewire\PhotoStudio;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('mode selection is visible', function () {
    $this->actingAs($this->user);

    Livewire::test(PhotoStudio::class)
        ->assertSee('Choose a mode, add your image(s)')
        ->assertSee('Product Group Image')
        ->assertSee('Lifestyle Context')
        ->assertSee('Reference + Hero');
});

test('can add product to composition', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'title' => 'Test Product',
        'image_link' => 'https://example.com/image.jpg',
    ]);

    $component = Livewire::test(PhotoStudio::class)
        ->call('addProductToComposition', $product->id);

    $component->assertSet('compositionImages.0.type', 'product')
        ->assertSet('compositionImages.0.product_id', $product->id)
        ->assertSet('compositionImages.0.title', 'Test Product');
});

test('cannot add duplicate product to composition', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image.jpg',
    ]);

    $component = Livewire::test(PhotoStudio::class)
        ->call('addProductToComposition', $product->id)
        ->call('addProductToComposition', $product->id);

    expect($component->get('compositionImages'))->toHaveCount(1);
});

test('can remove image from composition', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image.jpg',
    ]);

    Livewire::test(PhotoStudio::class)
        ->call('addProductToComposition', $product->id)
        ->assertCount('compositionImages', 1)
        ->call('removeFromComposition', 0)
        ->assertCount('compositionImages', 0);
});

test('can set hero image in reference hero mode', function () {
    $this->actingAs($this->user);

    $product1 = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image1.jpg',
    ]);

    $product2 = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image2.jpg',
    ]);

    Livewire::test(PhotoStudio::class)
        ->set('compositionMode', 'reference_hero')
        ->call('addProductToComposition', $product1->id)
        ->call('addProductToComposition', $product2->id)
        ->assertSet('compositionHeroIndex', 0)
        ->call('setCompositionHero', 1)
        ->assertSet('compositionHeroIndex', 1);
});

test('cannot extract prompt with less than two images', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image.jpg',
    ]);

    // Use 'products_together' mode which requires min_images: 2
    Livewire::test(PhotoStudio::class)
        ->set('compositionMode', 'products_together')
        ->call('addProductToComposition', $product->id)
        ->call('extractPrompt')
        ->assertSet('errorMessage', 'Add at least 2 images for this mode.');
});

test('can clear composition', function () {
    $this->actingAs($this->user);

    $product1 = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image1.jpg',
    ]);

    $product2 = Product::factory()->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image2.jpg',
    ]);

    Livewire::test(PhotoStudio::class)
        ->call('addProductToComposition', $product1->id)
        ->call('addProductToComposition', $product2->id)
        ->assertCount('compositionImages', 2)
        ->call('clearComposition')
        ->assertCount('compositionImages', 0)
        ->assertSet('compositionHeroIndex', 0);
});

test('composition respects max images limit', function () {
    $this->actingAs($this->user);

    config(['photo-studio.composition.max_images' => 3]);

    $products = Product::factory()->count(4)->create([
        'team_id' => $this->team->id,
        'image_link' => 'https://example.com/image.jpg',
    ]);

    $component = Livewire::test(PhotoStudio::class);

    foreach ($products as $product) {
        $component->call('addProductToComposition', $product->id);
    }

    expect($component->get('compositionImages'))->toHaveCount(3);
});

test('composition generation creates job record', function () {
    Queue::fake();
    Storage::fake('s3');

    $this->actingAs($this->user);

    $product1 = Product::factory()->create([
        'team_id' => $this->team->id,
        'title' => 'Product One',
        'image_link' => 'https://example.com/image1.jpg',
    ]);

    $product2 = Product::factory()->create([
        'team_id' => $this->team->id,
        'title' => 'Product Two',
        'image_link' => 'https://example.com/image2.jpg',
    ]);

    // Manually set the composition images with data_uri to bypass image fetching
    Livewire::test(PhotoStudio::class)
        ->set('compositionMode', 'products_together')
        ->set('compositionImages', [
            [
                'type' => 'product',
                'product_id' => $product1->id,
                'title' => $product1->title,
                'preview_url' => $product1->image_link,
                'data_uri' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMCwsLCgwKDQ4OEA0ODxAQERAMFA4TExQTEhMSEBESFBb/wAALCAACAAIBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACv/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
                'source_reference' => $product1->image_link,
            ],
            [
                'type' => 'product',
                'product_id' => $product2->id,
                'title' => $product2->title,
                'preview_url' => $product2->image_link,
                'data_uri' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMCwsLCgwKDQ4OEA0ODxAQERAMFA4TExQTEhMSEBESFBb/wAALCAACAAIBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACv/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
                'source_reference' => $product2->image_link,
            ],
        ])
        ->set('promptResult', 'A beautiful scene with products on a marble table')
        ->call('generateImage');

    Queue::assertPushed(GeneratePhotoStudioImage::class, function ($job) {
        return $job->compositionMode === 'products_together'
            && is_array($job->imageInput)
            && count($job->imageInput) === 2
            && is_array($job->sourceReferences)
            && count($job->sourceReferences) === 2;
    });

    $this->assertDatabaseHas('product_ai_jobs', [
        'team_id' => $this->team->id,
        'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
        'status' => ProductAiJob::STATUS_QUEUED,
    ]);
});

test('photo studio generation model composition helpers', function () {
    $generation = PhotoStudioGeneration::factory()->create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => 'composition',
        'composition_mode' => 'products_together',
        'source_references' => [
            ['type' => 'product', 'product_id' => 1, 'title' => 'Product 1'],
            ['type' => 'upload', 'product_id' => null, 'title' => 'upload.jpg'],
        ],
    ]);

    expect($generation->isComposition())->toBeTrue();
    expect($generation->getCompositionImageCount())->toEqual(2);
    expect($generation->getCompositionModeLabel())->toEqual('Product Group Image');

    // Non-composition generation should return false
    $singleGeneration = PhotoStudioGeneration::factory()->create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => 'uploaded_image',
        'composition_mode' => null,
    ]);

    expect($singleGeneration->isComposition())->toBeFalse();
    expect($singleGeneration->getCompositionImageCount())->toEqual(0);
});

test('gallery shows composition badge', function () {
    $this->actingAs($this->user);

    PhotoStudioGeneration::factory()->create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'composition_mode' => 'products_together',
        'source_references' => [
            ['type' => 'product', 'product_id' => 1],
            ['type' => 'product', 'product_id' => 2],
        ],
    ]);

    Livewire::test(PhotoStudio::class)
        ->assertSee('Product Group Image');
});