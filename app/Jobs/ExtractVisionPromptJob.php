<?php

namespace App\Jobs;

use App\DTO\AI\ChatRequest;
use App\DTO\AI\ContentPart;
use App\Facades\AI;
use App\Models\Product;
use App\Models\ProductAiJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExtractVisionPromptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * AI driver used for this job (visible in Horizon).
     */
    public ?string $aiDriver = null;

    /**
     * Model used for this job (visible in Horizon).
     */
    public ?string $model = null;

    /**
     * System prompt used for this job (visible in Horizon).
     */
    public ?string $systemPrompt = null;

    /**
     * Mode-specific extraction prompt used for this job (visible in Horizon).
     */
    public ?string $extractionPrompt = null;

    public function __construct(
        public int $productAiJobId,
        public int $teamId,
        public ?int $userId,
        public array $imageDataUris,
        public string $compositionMode,
        public array $compositionImages,
        public string $creativeBrief = '',
    ) {
        $this->onQueue('vision');
        $this->aiDriver = AI::getDriverForFeature('vision');
        $this->model = config('ai.features.vision.model');
        $this->systemPrompt = config('photo-studio.prompts.extraction.system');
        $this->extractionPrompt = config("photo-studio.composition.extraction_prompts.{$compositionMode}");
    }

    public function handle(): void
    {
        $jobRecord = ProductAiJob::find($this->productAiJobId);

        if (! $jobRecord) {
            Log::warning('ExtractVisionPromptJob: Job record not found', [
                'product_ai_job_id' => $this->productAiJobId,
            ]);

            return;
        }

        $jobRecord->markProcessing();

        try {
            $request = $this->buildChatRequest();

            $response = AI::forFeature('vision')->chat($request);

            if ($response->content === '') {
                throw new RuntimeException('Received an empty response from the AI provider.');
            }

            $jobRecord->markCompleted([
                'prompt_result' => $response->content,
                'ai_driver' => $this->aiDriver,
                'model' => $this->model,
                'response_model' => $response->model,
                'usage' => $response->usage,
                'finish_reason' => $response->finishReason,
            ]);
        } catch (Throwable $exception) {
            Log::error('ExtractVisionPromptJob failed', [
                'product_ai_job_id' => $this->productAiJobId,
                'team_id' => $this->teamId,
                'user_id' => $this->userId,
                'composition_mode' => $this->compositionMode,
                'ai_driver' => $this->aiDriver,
                'model' => $this->model,
                'exception' => $exception->getMessage(),
            ]);

            $jobRecord->markFailed($exception->getMessage(), [
                'ai_driver' => $this->aiDriver,
                'model' => $this->model,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function buildChatRequest(): ChatRequest
    {
        // Build product details for context
        $productDetails = collect($this->compositionImages)
            ->filter(fn ($img) => $img['type'] === 'product' && $img['product_id'])
            ->map(function ($img) {
                $product = Product::find($img['product_id']);

                return $product ? sprintf(
                    '- %s (Brand: %s, SKU: %s)',
                    $product->title ?: 'Untitled',
                    $product->brand ?: 'N/A',
                    $product->sku ?: 'N/A'
                ) : null;
            })
            ->filter()
            ->implode("\n");

        $contextText = $this->extractionPrompt;

        if ($productDetails) {
            $contextText .= "\n\nProducts included:\n".$productDetails;
        }

        if ($this->creativeBrief !== '') {
            $contextText .= "\n\nCreative direction from the user: ".$this->creativeBrief;
        }

        // Build content parts with text and multiple images
        $contentParts = [
            ContentPart::text($contextText),
        ];

        foreach ($this->imageDataUris as $dataUri) {
            $contentParts[] = ContentPart::imageUrl($dataUri);
        }

        return ChatRequest::multimodal(
            content: $contentParts,
            systemPrompt: $this->systemPrompt,
            model: $this->model,
            maxTokens: 700,
            temperature: 0.4,
        );
    }
}
