<?php

use App\Jobs\RunProductAiTemplateJob;
use App\Livewire\ProductShow;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('product details page is accessible for team member', function () {
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
});

test('product details page returns not found for other team', function () {
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
});

test('queue generation dispatches job from details page', function () {
    config()->set('ai.providers.openai.api_key', 'test-key');

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

    expect($templates)->not->toBeEmpty();

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
});

test('product shows language tabs when in catalog with siblings', function () {
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
});

test('product does not show language tabs when standalone', function () {
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
});

test('product shows catalog name in metadata', function () {
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
});

test('semantic url shows product by catalog and sku', function () {
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
});

test('semantic url with lang parameter shows correct language version', function () {
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
});

test('product get url returns semantic url for catalog products', function () {
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
});

test('product get url returns null for standalone products', function () {
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

    expect($product->getUrl())->toBeNull();
    expect($product->hasSemanticUrl())->toBeFalse();
});

test('product with additional image shows image switcher badge', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $feed = ProductFeed::factory()->create([
        'team_id' => $team->id,
    ]);

    $product = Product::factory()->create([
        'team_id' => $team->id,
        'product_feed_id' => $feed->id,
        'title' => 'Product With Two Images',
        'image_link' => 'https://example.com/primary.jpg',
        'additional_image_link' => 'https://example.com/additional.jpg',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductShow::class, ['productId' => $product->id]);

    // The image switcher should show the toggle badge with "1/2" indicator
    $component->assertSeeHtml('x-text="(currentIndex + 1) + \'/\' + images.length"');
});

test('product without additional image shows simple image', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $feed = ProductFeed::factory()->create([
        'team_id' => $team->id,
    ]);

    $product = Product::factory()->create([
        'team_id' => $team->id,
        'product_feed_id' => $feed->id,
        'title' => 'Product With Single Image',
        'image_link' => 'https://example.com/single.jpg',
        'additional_image_link' => null,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductShow::class, ['productId' => $product->id]);

    // The component should not have the toggle button (only appears with multiple images)
    $html = $component->html();

    // Image URL is passed via @js() to Alpine, so it appears as JSON escaped in the HTML
    // The URL is in the images array: images: ["https:\/\/example.com\/single.jpg"]
    $this->assertStringContainsString('example.com', $html);

    // The toggle badge should not appear (only rendered when $hasMultipleImages is true)
    // We check for the absence of the @click handler with toggle()
    $this->assertStringNotContainsString('@click.stop.prevent="toggle()"', $html);
});
