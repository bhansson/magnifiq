<?php

namespace App\Jobs;

use App\DTO\AI\ImageConfig;
use App\DTO\AI\ImageGenerationRequest;
use App\Facades\AI;
use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
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
    ) {
        // Capture the AI driver at dispatch time for Horizon visibility
        $this->aiDriver = AI::getDriverForFeature('image_generation');
    }

    public function handle(): void
    {
        $jobRecord = ProductAiJob::query()->findOrFail($this->productAiJobId);

        try {
            $jobRecord->forceFill([
                'status' => ProductAiJob::STATUS_PROCESSING,
                'attempts' => $this->attempts(),
                'started_at' => now(),
                'progress' => 10,
                'last_error' => null,
            ])->save();

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
            );

            // Get the driver name for tracking (visible in Horizon)
            $aiDriver = AI::getDriverForFeature('image_generation');

            // Generate image using AI provider abstraction
            $response = AI::forFeature('image_generation')->generateImage($request);

            // Prepare image (PNG to JPG conversion if needed)
            $preparedImage = $this->prepareGeneratedImage(
                $response->image->binary,
                $response->image->extension
            );

            $jobRecord->forceFill([
                'progress' => 60,
            ])->save();

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
                'storage_disk' => $this->disk,
                'storage_path' => $path,
                'image_width' => $preparedImage['width'],
                'image_height' => $preparedImage['height'],
                'response_id' => $rawResponse['id'] ?? null,
                'response_model' => $response->model ?? $rawResponse['model'] ?? null,
                'response_metadata' => $metadata ?: null,
            ]);

            $meta = $jobRecord->meta ?? [];
            $meta['photo_studio_generation_id'] = $generation->id;
            $meta['ai_driver'] = $aiDriver;

            $jobRecord->forceFill([
                'status' => ProductAiJob::STATUS_COMPLETED,
                'progress' => 100,
                'finished_at' => now(),
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            $jobRecord->forceFill([
                'status' => ProductAiJob::STATUS_FAILED,
                'progress' => 0,
                'finished_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 500),
            ])->save();

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

        $jobRecord->forceFill([
            'status' => ProductAiJob::STATUS_FAILED,
            'progress' => 0,
            'finished_at' => now(),
            'last_error' => Str::limit($exception->getMessage(), 500),
        ])->save();

        Log::warning('Photo Studio job permanently failed', [
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'product_id' => $this->productId,
            'product_ai_job_id' => $this->productAiJobId,
            'exception' => $exception->getMessage(),
        ]);
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
