<?php

namespace Tests\Feature\PhotoStudio;

use App\Jobs\GeneratePhotoStudioImage;
use App\Livewire\PhotoStudio;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoStudioCompositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_composition_tab_is_visible(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PhotoStudio::class)
            ->assertSee('Composition');
    }

    public function test_switching_to_composition_tab_shows_mode_selection(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition')
            ->assertSee('Choose a mode, add your image(s)')
            ->assertSee('Product Group Image')
            ->assertSee('Lifestyle Context')
            ->assertSee('Reference + Hero');
    }

    public function test_can_add_product_to_composition(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'title' => 'Test Product',
            'image_link' => 'https://example.com/image.jpg',
        ]);

        $component = Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition')
            ->call('addProductToComposition', $product->id);

        $component->assertSet('compositionImages.0.type', 'product')
            ->assertSet('compositionImages.0.product_id', $product->id)
            ->assertSet('compositionImages.0.title', 'Test Product');
    }

    public function test_cannot_add_duplicate_product_to_composition(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'image_link' => 'https://example.com/image.jpg',
        ]);

        $component = Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition')
            ->call('addProductToComposition', $product->id)
            ->call('addProductToComposition', $product->id);

        $this->assertCount(1, $component->get('compositionImages'));
    }

    public function test_can_remove_image_from_composition(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'image_link' => 'https://example.com/image.jpg',
        ]);

        Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition')
            ->call('addProductToComposition', $product->id)
            ->assertCount('compositionImages', 1)
            ->call('removeFromComposition', 0)
            ->assertCount('compositionImages', 0);
    }

    public function test_can_set_hero_image_in_reference_hero_mode(): void
    {
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
            ->set('activeTab', 'composition')
            ->set('compositionMode', 'reference_hero')
            ->call('addProductToComposition', $product1->id)
            ->call('addProductToComposition', $product2->id)
            ->assertSet('compositionHeroIndex', 0)
            ->call('setCompositionHero', 1)
            ->assertSet('compositionHeroIndex', 1);
    }

    public function test_cannot_extract_prompt_with_less_than_two_images(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'image_link' => 'https://example.com/image.jpg',
        ]);

        // Use 'products_together' mode which requires min_images: 2
        Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition')
            ->set('compositionMode', 'products_together')
            ->call('addProductToComposition', $product->id)
            ->call('extractPrompt')
            ->assertSet('errorMessage', 'Add at least 2 images for this mode.');
    }

    public function test_can_clear_composition(): void
    {
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
            ->set('activeTab', 'composition')
            ->call('addProductToComposition', $product1->id)
            ->call('addProductToComposition', $product2->id)
            ->assertCount('compositionImages', 2)
            ->call('clearComposition')
            ->assertCount('compositionImages', 0)
            ->assertSet('compositionHeroIndex', 0);
    }

    public function test_composition_respects_max_images_limit(): void
    {
        $this->actingAs($this->user);

        config(['photo-studio.composition.max_images' => 3]);

        $products = Product::factory()->count(4)->create([
            'team_id' => $this->team->id,
            'image_link' => 'https://example.com/image.jpg',
        ]);

        $component = Livewire::test(PhotoStudio::class)
            ->set('activeTab', 'composition');

        foreach ($products as $product) {
            $component->call('addProductToComposition', $product->id);
        }

        $this->assertCount(3, $component->get('compositionImages'));
    }

    public function test_composition_generation_creates_job_record(): void
    {
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
            ->set('activeTab', 'composition')
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
    }

    public function test_photo_studio_generation_model_composition_helpers(): void
    {
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

        $this->assertTrue($generation->isComposition());
        $this->assertEquals(2, $generation->getCompositionImageCount());
        $this->assertEquals('Product Group Image', $generation->getCompositionModeLabel());

        // Non-composition generation should return false
        $singleGeneration = PhotoStudioGeneration::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'source_type' => 'uploaded_image',
            'composition_mode' => null,
        ]);

        $this->assertFalse($singleGeneration->isComposition());
        $this->assertEquals(0, $singleGeneration->getCompositionImageCount());
    }

    public function test_gallery_shows_composition_badge(): void
    {
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
    }
}
