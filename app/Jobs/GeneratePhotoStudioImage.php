<?php

namespace App\Jobs;

use App\DTO\AI\ImageConfig;
use App\DTO\AI\ImageGenerationRequest;
use App\Facades\AI;
use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\TeamActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeneratePhotoStudioImage implements ShouldQueue
{
    private const JPG_QUALITY = 90;

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public ?int $timeout = 90;

    /**
     * Backoff intervals (seconds) between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * AI driver used for this job (visible in Horizon).
     */
    public ?string $aiDriver = null;

    public function __construct(
        public int $productAiJobId,
        public int $teamId,
        public int $userId,
        public ?int $productId,
        public string $prompt,
        public string $model,
        public string $disk,
        public string|array|null $imageInput,
        public string $sourceType,
        public ?string $sourceReference = null,
        public ?int $parentId = null,
        public ?string $editInstruction = null,
        public ?string $compositionMode = null,
        public ?array $sourceReferences = null,
        public ?string $aspectRatio = null,
        public ?string $resolution = null,
        public ?float $estimatedCost = null,
    ) {
        // Capture the AI driver at dispatch time for Horizon visibility
        // Use model-specific provider from config, falling back to 'replicate'
        $models = config('photo-studio.image_models', []);
        $modelConfig = $models[$this->model] ?? null;
        $this->aiDriver = $modelConfig['provider'] ?? 'replicate';
    }

    public function handle(): void
    {
        $jobRecord = ProductAiJob::query()->findOrFail($this->productAiJobId);

        try {
            $jobRecord->markProcessing([], [
                'attempts' => $this->attempts(),
                'progress' => 10,
            ]);

            $this->ensureDiskIsConfigured();

            // Build the image generation request
            $imageConfig = $this->aspectRatio
                ? ImageConfig::aspectRatio($this->aspectRatio)
                : null;

            $request = new ImageGenerationRequest(
                prompt: $this->buildPrompt(),
                model: $this->model,
                inputImages: $this->imageInput,
                imageConfig: $imageConfig,
                temperature: 0.85,
                maxTokens: 2048,
                extra: $this->buildModelSpecificParams(),
            );

            // Use the provider specified in the model config, falling back to feature driver
            $aiDriver = $this->resolveProviderForModel();

            // Generate image using the model-specific provider
            $response = AI::driver($aiDriver)->generateImage($request);

            // Prepare image (PNG to JPG conversion if needed)
            $preparedImage = $this->prepareGeneratedImage(
                $response->image->binary,
                $response->image->extension
            );

            $jobRecord->updateProgress(60);

            $path = $this->storeGeneratedImage(
                binary: $preparedImage['binary'],
                extension: $preparedImage['extension']
            );

            $rawResponse = $response->rawResponse;
            $metadata = array_filter([
                'provider' => $rawResponse['provider'] ?? null,
                'usage' => $rawResponse['usage'] ?? null,
            ]);

            $generation = PhotoStudioGeneration::create([
                'team_id' => $this->teamId,
                'parent_id' => $this->parentId,
                'user_id' => $this->userId,
                'product_id' => $this->productId,
                'product_ai_job_id' => $jobRecord->id,
                'source_type' => $this->sourceType,
                'source_reference' => $this->sourceReference,
                'composition_mode' => $this->compositionMode,
                'source_references' => $this->sourceReferences,
                'prompt' => $this->prompt,
                'edit_instruction' => $this->editInstruction,
                'model' => $this->model,
                'resolution' => $this->resolution,
                'estimated_cost' => $this->estimatedCost,
                'storage_disk' => $this->disk,
                'storage_path' => $path,
                'image_width' => $preparedImage['width'],
                'image_height' => $preparedImage['height'],
                'response_id' => $rawResponse['id'] ?? null,
                'response_model' => $response->model ?? $rawResponse['model'] ?? null,
                'response_metadata' => $metadata ?: null,
            ]);

            $jobRecord->markCompleted([
                'photo_studio_generation_id' => $generation->id,
                'ai_driver' => $aiDriver,
            ]);

            TeamActivity::recordPhotoStudioGenerated($generation, $this->userId);
        } catch (Throwable $exception) {
            $jobRecord->markFailed($exception->getMessage());

            Log::error('Photo Studio job failed', [
                'team_id' => $this->teamId,
                'user_id' => $this->userId,
                'product_id' => $this->productId,
                'product_ai_job_id' => $jobRecord->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     *
     * This is critical for timeouts and worker kills where the catch block
     * inside handle() never executes.
     */
    public function failed(Throwable $exception): void
    {
        $jobRecord = ProductAiJob::query()->find($this->productAiJobId);

        if (! $jobRecord) {
            return;
        }

        $jobRecord->markFailed($exception->getMessage());

        Log::warning('Photo Studio job permanently failed', [
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'product_id' => $this->productId,
            'product_ai_job_id' => $this->productAiJobId,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Resolve the AI provider for the selected model.
     *
     * Photo Studio models specify their provider in config/photo-studio.php.
     * Falls back to 'replicate' for any models without an explicit provider.
     */
    private function resolveProviderForModel(): string
    {
        // Use array access because model keys contain forward slashes
        // which break Laravel's dot notation config lookup
        $models = config('photo-studio.image_models', []);
        $modelConfig = $models[$this->model] ?? null;

        return $modelConfig['provider'] ?? 'replicate';
    }

    /**
     * Build the full prompt including system context.
     */
    private function buildPrompt(): string
    {
        $systemPrompt = config('photo-studio.prompts.generation.system');

        if ($systemPrompt) {
            return $systemPrompt."\n\n".$this->prompt;
        }

        return $this->prompt;
    }

    /**
     * Build model-specific parameters for resolution handling.
     *
     * Different models accept resolution in different formats:
     * - Nano Banana Pro: 'resolution' => '1K', '2K', '4K'
     * - Seedream 4: 'size' => '1K', '2K', '4K'
     * - FLUX 2 Flex: 'megapixels' => 0.5, 1.0, 2.0, 4.0
     *
     * Resolution keys in config match the API values directly (e.g., '1K', '2K').
     *
     * @return array<string, mixed>
     */
    private function buildModelSpecificParams(): array
    {
        if (! $this->resolution) {
            return [];
        }

        // Use array access because model keys contain forward slashes
        // which break Laravel's dot notation config lookup
        $models = config('photo-studio.image_models', []);
        $modelConfig = $models[$this->model] ?? null;

        if (! $modelConfig || ! ($modelConfig['supports_resolution'] ?? false)) {
            return [];
        }

        $resConfig = $modelConfig['resolutions'][$this->resolution] ?? null;
        if (! $resConfig) {
            return [];
        }

        // FLUX models use megapixels from config
        if (str_contains($this->model, 'flux')) {
            return isset($resConfig['megapixels'])
                ? ['megapixels' => $resConfig['megapixels']]
                : [];
        }

        // Seedream uses 'size' parameter - resolution key is the value
        if (str_contains($this->model, 'seedream')) {
            return ['size' => $this->resolution];
        }

        // Nano Banana Pro uses 'resolution' parameter - resolution key is the value
        if (str_contains($this->model, 'nano-banana')) {
            return ['resolution' => $this->resolution];
        }

        return [];
    }

    /**
     * @return array{binary: string, extension: string, width: int|null, height: int|null}
     */
    private function prepareGeneratedImage(string $binary, string $extension): array
    {
        [$width, $height] = $this->detectImageDimensions($binary);

        if ($extension === 'png') {
            $binary = $this->convertPngToJpg($binary);
            $extension = 'jpg';

            [$postWidth, $postHeight] = $this->detectImageDimensions($binary);
            $width = $postWidth ?? $width;
            $height = $postHeight ?? $height;
        }

        return [
            'binary' => $binary,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function convertPngToJpg(string $binary): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required to convert PNG images.');
        }

        $png = @imagecreatefromstring($binary);

        if ($png === false) {
            throw new RuntimeException('Unable to decode PNG payload from provider response.');
        }

        $width = imagesx($png);
        $height = imagesy($png);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
        imagecopy($canvas, $png, 0, 0, 0, 0, $width, $height);

        ob_start();
        $written = imagejpeg($canvas, null, self::JPG_QUALITY);
        $jpegBinary = ob_get_clean();

        imagedestroy($png);
        imagedestroy($canvas);

        if ($jpegBinary === false || $written === false) {
            throw new RuntimeException('Failed to convert PNG payload to JPG.');
        }

        return $jpegBinary;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function detectImageDimensions(string $binary): array
    {
        if (! function_exists('getimagesizefromstring')) {
            return [null, null];
        }

        $size = @getimagesizefromstring($binary);

        if ($size === false) {
            return [null, null];
        }

        return [
            isset($size[0]) ? (int) $size[0] : null,
            isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    private function storeGeneratedImage(string $binary, string $extension): string
    {
        $directory = sprintf('photo-studio/%d/%s', $this->teamId, now()->format('Y/m/d'));
        $path = $directory.'/'.Str::uuid().'.'.$extension;

        $stored = Storage::disk($this->disk)->put($path, $binary, ['visibility' => 'public']);

        if (! $stored) {
            throw new RuntimeException('Unable to store the generated image.');
        }

        return $path;
    }

    private function ensureDiskIsConfigured(): void
    {
        $disks = config('filesystems.disks', []);

        if (! array_key_exists($this->disk, $disks)) {
            throw new RuntimeException('The configured storage disk for Photo Studio is not available.');
        }
    }
}
