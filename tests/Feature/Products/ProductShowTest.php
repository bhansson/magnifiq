<?php

namespace Tests\Feature\Products;

use App\Jobs\RunProductAiTemplateJob;
use App\Livewire\ProductShow;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_details_page_is_accessible_for_team_member(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Example Product Title',
                'brand' => 'Acme',
                'sku' => 'TEST-001',
            ]);

        $this->actingAs($user);

        $this->get(route('products.show', [
            'catalog' => $catalog->slug,
            'sku' => 'TEST-001',
        ]))
            ->assertOk()
            ->assertSeeText('Example Product Title')
            ->assertSeeText('Summary');
    }

    public function test_product_details_page_returns_not_found_for_other_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $catalog = ProductCatalog::factory()->create(['team_id' => $otherUser->currentTeam->id]);

        $foreignFeed = ProductFeed::factory()->create([
            'team_id' => $otherUser->currentTeam->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $foreignProduct = Product::factory()
            ->for($foreignFeed, 'feed')
            ->create([
                'team_id' => $otherUser->currentTeam->id,
                'brand' => 'Acme',
                'sku' => 'FOREIGN-001',
            ]);

        $this->actingAs($user);

        $this->get(route('products.show', [
            'catalog' => $catalog->slug,
            'sku' => 'FOREIGN-001',
        ]))
            ->assertNotFound();
    }

    public function test_queue_generation_dispatches_job_from_details_page(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');

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
            ]);

        $this->actingAs($user);

        ProductAiTemplate::syncDefaultTemplates();

        $templates = ProductAiTemplate::query()
            ->whereIn('slug', config('product-ai.actions.generate_summary', []))
            ->get();

        $this->assertNotEmpty($templates);

        $livewire = Livewire::test(ProductShow::class, ['productId' => $product->id]);

        foreach ($templates as $template) {
            $livewire->call('queueGeneration', $template->id)
                ->assertHasNoErrors();

            $this->assertDatabaseHas('product_ai_jobs', [
                'product_id' => $product->id,
                'product_ai_template_id' => $template->id,
                'status' => ProductAiJob::STATUS_QUEUED,
                'job_type' => ProductAiJob::TYPE_TEMPLATE,
            ]);

            Queue::assertPushed(RunProductAiTemplateJob::class, function (RunProductAiTemplateJob $job) use ($product, $template): bool {
                $jobRecord = ProductAiJob::find($job->productAiJobId);

                return $jobRecord?->product_id === $product->id
                    && $jobRecord->product_ai_template_id === $template->id;
            });
        }
    }

    public function test_product_shows_language_tabs_when_in_catalog_with_siblings(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

        $feedEn = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $feedSv = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'sv',
        ]);

        $productEn = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feedEn->id,
            'sku' => 'SKU-001',
            'title' => 'English Product',
        ]);

        $productSv = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feedSv->id,
            'sku' => 'SKU-001',
            'title' => 'Swedish Product',
        ]);

        $this->actingAs($user);

        // Products in catalog use semantic URL: /products/{catalog}/{sku}/{lang}
        $expectedUrl = route('products.show', [
            'catalog' => $catalog->slug,
            'sku' => 'SKU-001',
            'lang' => 'sv',
        ]);

        // URL should be path-based, not query string
        $this->assertStringContainsString('/sv', $expectedUrl);
        $this->assertStringNotContainsString('?lang=', $expectedUrl);

        Livewire::test(ProductShow::class, [
            'productId' => $productEn->id,
            'catalogSlug' => $catalog->slug,
        ])
            ->assertSee('EN')
            ->assertSee('SV')
            ->assertSeeHtml('href="'.$expectedUrl.'"');
    }

    public function test_product_does_not_show_language_tabs_when_standalone(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => null,
            'language' => 'en',
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feed->id,
            'sku' => 'SKU-001',
            'title' => 'Standalone Product',
        ]);

        $this->actingAs($user);

        // Standalone products should not show the language switcher tabs
        // (the "Language:" label appears in product info, but not the tab row)
        $html = Livewire::test(ProductShow::class, ['productId' => $product->id])->html();

        // Should not have language pill-style tabs with links
        $this->assertStringNotContainsString('Currently viewing', $html);
    }

    public function test_product_shows_catalog_name_in_metadata(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create([
            'team_id' => $team->id,
            'name' => 'My Test Catalog',
        ]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feed->id,
            'sku' => 'CATALOG-001',
        ]);

        $this->actingAs($user);

        Livewire::test(ProductShow::class, [
            'productId' => $product->id,
            'catalogSlug' => $catalog->slug,
        ])
            ->assertSee('My Test Catalog');
    }

    public function test_semantic_url_shows_product_by_catalog_and_sku(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create([
            'team_id' => $team->id,
            'name' => 'Winter Collection',
        ]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feed->id,
            'sku' => 'WINTER-001',
            'title' => 'Cozy Winter Jacket',
        ]);

        $this->actingAs($user);

        $this->get(route('products.show', [
            'catalog' => $catalog->slug,
            'sku' => 'WINTER-001',
        ]))
            ->assertOk()
            ->assertSeeText('Cozy Winter Jacket');
    }

    public function test_semantic_url_with_lang_parameter_shows_correct_language_version(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

        $feedEn = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'en',
        ]);

        $feedSv = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'sv',
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feedEn->id,
            'sku' => 'MULTI-001',
            'title' => 'English Title',
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feedSv->id,
            'sku' => 'MULTI-001',
            'title' => 'Swedish Title',
        ]);

        $this->actingAs($user);

        // Request Swedish version via lang parameter
        $this->get(route('products.show', [
            'catalog' => $catalog->slug,
            'sku' => 'MULTI-001',
            'lang' => 'sv',
        ]))
            ->assertOk()
            ->assertSeeText('Swedish Title');
    }

    public function test_product_get_url_returns_semantic_url_for_catalog_products(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $catalog = ProductCatalog::factory()->create(['team_id' => $team->id]);

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => $catalog->id,
            'language' => 'de',
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feed->id,
            'sku' => 'URL-001',
        ]);

        $url = $product->getUrl();

        $this->assertStringContainsString($catalog->slug, $url);
        $this->assertStringContainsString('URL-001', $url);
        // Language should be a path segment, not query parameter
        $this->assertStringContainsString('/de', $url);
        $this->assertStringNotContainsString('?lang=', $url);
    }

    public function test_product_get_url_returns_null_for_standalone_products(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'product_catalog_id' => null,
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'product_feed_id' => $feed->id,
            'sku' => 'STANDALONE-001',
        ]);

        $this->assertNull($product->getUrl());
        $this->assertFalse($product->hasSemanticUrl());
    }
}
