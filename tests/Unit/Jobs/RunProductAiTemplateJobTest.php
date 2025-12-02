<?php

use App\Jobs\RunProductAiTemplateJob;
use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('job creates summary record and trims history', function () {
    config()->set('ai.providers.openrouter.api_key', 'test-key');
    config()->set('ai.providers.openrouter.api_endpoint', 'https://openrouter.ai/api/v1/');
    config()->set('ai.features.chat.model', 'test-model');
    config()->set('ai.features.chat.driver', 'openrouter');

    ProductAiTemplate::syncDefaultTemplates();

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $feed = ProductFeed::factory()->create([
        'team_id' => $team->id,
    ]);

    $product = Product::factory()
        ->for($feed, 'feed')
        ->create([
            'team_id' => $team->id,
            'sku' => 'SKU-100',
            'description' => 'Test description for the product',
        ]);

    // Seed 10 existing summaries to ensure the history trimming logic executes.
    $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();

    foreach (range(1, 10) as $offset) {
        $generation = ProductAiGeneration::create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'product_ai_template_id' => $summaryTemplate->id,
            'sku' => $product->sku,
            'content' => 'Legacy summary #'.$offset,
        ]);

        $generation->forceFill([
            'created_at' => now()->subMinutes(60 + $offset),
            'updated_at' => now()->subMinutes(60 + $offset),
        ])->save();
    }

    // Fake HTTP response for OpenRouter API
    Http::fake([
        '*openrouter.ai/*' => Http::response([
            'id' => 'test-generation',
            'model' => 'test-model',
            'object' => 'chat.completion',
            'created' => now()->timestamp,
            'choices' => [
                [
                    'message' => [
                        'content' => 'Newly generated summary.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $jobRecord = ProductAiJob::create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'product_ai_template_id' => $summaryTemplate->id,
        'job_type' => ProductAiJob::TYPE_TEMPLATE,
        'status' => ProductAiJob::STATUS_QUEUED,
        'progress' => 0,
        'queued_at' => now(),
    ]);

    $job = new RunProductAiTemplateJob($jobRecord->id);
    $job->handle();

    // Verify an HTTP request was made
    Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));

    // Check job completed successfully
    $jobRecord->refresh();
    expect($jobRecord->status)->toBe(ProductAiJob::STATUS_COMPLETED, 'Job should be completed. Error: '.$jobRecord->last_error);

    // Debug: Get the most recent generation
    $latestGeneration = ProductAiGeneration::where('product_id', $product->id)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();

    expect($latestGeneration)->not->toBeNull('A new generation should have been created');
    expect($latestGeneration->content)->toBe('Newly generated summary.');

    expect(ProductAiGeneration::where('product_id', $product->id)
        ->where('product_ai_template_id', $summaryTemplate->id)
        ->count())->toBe(10);

    $jobRecord->refresh();

    expect($jobRecord->status)->toBe(ProductAiJob::STATUS_COMPLETED);
    expect($jobRecord->progress)->toBe(100);
    expect($jobRecord->finished_at)->not->toBeNull();
    expect(data_get($jobRecord->meta, 'generation_id'))->not->toBeEmpty();
});