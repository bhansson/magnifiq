<?php

use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use App\Models\ProductFeed;
use App\Models\Team;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->publicPath = base_path('storage/testing/public_'.Str::lower(Str::random(8)));
    File::ensureDirectoryExists($this->publicPath);
    $this->app->usePublicPath($this->publicPath);

    ProductAiTemplate::syncDefaultTemplates();
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);

});

test('it generates json exports for products', function () {
    $team = Team::factory()->create();
    $feed = ProductFeed::factory()->create([
        'team_id' => $team->id,
        'language' => 'sv',
    ]);
    $product = Product::factory()
        ->for($team)
        ->for($feed, 'feed')
        ->create([
            'sku' => 'SKU-123',
            'brand' => 'Test Brand',
            'image_link' => 'https://example.com/primary.jpg',
            'additional_image_link' => 'https://example.com/alt.jpg',
        ]);

    $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();
    $descriptionTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION)->firstOrFail();
    $uspTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_USPS)->firstOrFail();
    $faqTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_FAQ)->firstOrFail();

    $summary = ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $summaryTemplate->id,
        'sku' => $product->sku,
        'content' => 'Generated summary content',
    ]);

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $descriptionTemplate->id,
        'sku' => $product->sku,
        'content' => 'Generated description content',
    ]);

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $uspTemplate->id,
        'sku' => $product->sku,
        'content' => [
            'Ships within 24 hours',
            'Premium materials that last',
        ],
    ]);

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $faqTemplate->id,
        'sku' => $product->sku,
        'content' => [
            [
                'question' => 'Does it include a warranty?',
                'answer' => 'Yes, it includes a 2-year manufacturer warranty.',
            ],
            [
                'question' => 'Is international shipping available?',
                'answer' => 'We ship worldwide with tracked delivery.',
            ],
        ],
    ]);

    $product->refresh();

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-123.json';
    expect($filePath)->toBeFile();

    $payload = json_decode(File::get($filePath), true);

    expect($payload['sku'])->toBe($product->sku);
    expect($payload['team_hash'])->toBe($team->public_hash);
    expect($payload['language'])->toBe('sv');
    expect($payload['image_link'])->toBe('https://example.com/primary.jpg');
    expect($payload['additional_image_link'])->toBe('https://example.com/alt.jpg');
    expect($payload['ai']['description_summary']['content'])->toBe($summary->content);
    $this->assertArrayNotHasKey('meta', $payload['ai']['description_summary']);
    expect($payload['ai']['usps']['content'])->toBe(['Ships within 24 hours', 'Premium materials that last']);
    $this->assertArrayNotHasKey('meta', $payload['ai']['usps']);
    expect($payload['ai']['faq']['content'])->toBe([
        [
            'question' => 'Does it include a warranty?',
            'answer' => 'Yes, it includes a 2-year manufacturer warranty.',
        ],
        [
            'question' => 'Is international shipping available?',
            'answer' => 'We ship worldwide with tracked delivery.',
        ],
    ]);
    $this->assertArrayNotHasKey('meta', $payload['ai']['faq']);
});

test('it skips unchanged products', function () {
    $team = Team::factory()->create();
    $product = Product::factory()
        ->for($team)
        ->create([
            'sku' => 'SKU-456',
            'brand' => 'Test Brand',
        ]);

    $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $summaryTemplate->id,
        'sku' => $product->sku,
        'content' => 'Initial summary',
    ]);

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-456.json';
    expect($filePath)->toBeFile();

    clearstatcache(true, $filePath);
    $modifiedBefore = filemtime($filePath);

    sleep(1);

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    clearstatcache(true, $filePath);
    $modifiedAfter = filemtime($filePath);

    expect($modifiedAfter)->toBe($modifiedBefore, 'Product export should not be rewritten when unchanged.');
});

test('it rewrites exports when product is updated', function () {
    $team = Team::factory()->create();
    $product = Product::factory()
        ->for($team)
        ->create([
            'sku' => 'SKU-789',
            'brand' => 'Test Brand',
        ]);

    $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $summaryTemplate->id,
        'sku' => $product->sku,
        'content' => 'First summary',
    ]);

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-789.json';
    expect($filePath)->toBeFile();

    $initialPayload = json_decode(File::get($filePath), true);
    expect($initialPayload['ai']['description_summary']['content'])->toBe('First summary');

    clearstatcache(true, $filePath);
    $modifiedBefore = filemtime($filePath);

    sleep(1);

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $summaryTemplate->id,
        'sku' => $product->sku,
        'content' => 'Updated summary text',
    ]);

    $product->refresh();

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    clearstatcache(true, $filePath);
    $modifiedAfter = filemtime($filePath);

    expect($modifiedAfter)->toBeGreaterThan($modifiedBefore, 'Product export should be rewritten after an update.');

    $updatedPayload = json_decode(File::get($filePath), true);
    expect($updatedPayload['ai']['description_summary']['content'])->toBe('Updated summary text');
});

test('it generates missing team hash before export', function () {
    $team = Team::factory()->create();

    $team->forceFill(['public_hash' => null])->save();

    $product = Product::factory()
        ->for($team)
        ->create([
            'sku' => 'SKU-999',
            'brand' => 'Test Brand',
        ]);

    $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();

    ProductAiGeneration::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'product_ai_template_id' => $summaryTemplate->id,
        'sku' => $product->sku,
        'content' => 'Summary for missing hash team',
    ]);

    $this->artisan('products:generate-public-json')
        ->assertExitCode(0);

    $team->refresh();

    expect($team->public_hash)->not->toBeNull();

    $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-999.json';
    expect($filePath)->toBeFile();
});