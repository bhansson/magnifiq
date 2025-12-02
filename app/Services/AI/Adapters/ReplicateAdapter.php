<?php

namespace App\Services\AI\Adapters;

use App\Contracts\AI\SupportsAsyncPollingContract;
use App\DTO\AI\ImageGenerationRequest;
use App\DTO\AI\ImageGenerationResponse;
use App\DTO\AI\ImagePayload;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReplicateAdapter extends AbstractAiAdapter implements SupportsAsyncPollingContract
{
    public function getProviderName(): string
    {
        return 'replicate';
    }

    public function supportsChatCompletion(): bool
    {
        // Replicate does support some LLMs, but for now we focus on image generation
        return false;
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    public function getPollingTimeout(): int
    {
        return (int) $this->getConfig('polling_timeout', 300);
    }

    public function getPollingInterval(): float
    {
        return (float) $this->getConfig('polling_interval', 2.0);
    }

    /**
     * Execute an image generation request.
     */
    public function generateImage(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $apiKey = $this->getApiKey();
        $model = $this->resolveModel($request->model, 'image_generation');

        // Create the prediction
        $prediction = $this->createPrediction($apiKey, $model, $request);

        $this->logDebug('Prediction created', [
            'id' => $prediction['id'],
            'model' => $model,
        ]);

        // Wait for prediction to complete
        $result = $this->waitForPrediction($apiKey, $prediction['id']);

        // Extract image from output
        $imagePayload = $this->extractImageFromOutput($result['output']);

        return new ImageGenerationResponse(
            image: $imagePayload,
            model: $model,
            rawResponse: $result,
        );
    }

    /**
     * Create a new prediction.
     *
     * @return array{id: string, status: string}
     */
    private function createPrediction(string $apiKey, string $model, ImageGenerationRequest $request): array
    {
        $endpoint = $this->getApiEndpoint().'predictions';

        // Build input based on common Replicate model patterns
        $input = $this->buildModelInput($request);

        $payload = [
            'version' => $this->resolveModelVersion($model),
            'input' => $input,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->getTimeout())
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $this->logError('Failed to create prediction', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                $this->getErrorMessage($response->status(), $response->json('detail'))
            );
        }

        return $response->json();
    }

    /**
     * Build model input from request.
     *
     * @return array<string, mixed>
     */
    private function buildModelInput(ImageGenerationRequest $request): array
    {
        $model = $this->resolveModel($request->model, 'image_generation');

        $input = [
            'prompt' => $request->prompt,
        ];

        // Handle aspect ratio
        if ($request->imageConfig?->aspectRatio) {
            $input['aspect_ratio'] = $request->imageConfig->aspectRatio;
        }

        // Handle dimensions
        if ($request->imageConfig?->width) {
            $input['width'] = $request->imageConfig->width;
        }

        if ($request->imageConfig?->height) {
            $input['height'] = $request->imageConfig->height;
        }

        // Handle input images for img2img
        if ($request->hasInputImages()) {
            $images = $request->getInputImagesArray();
            // Convert data URIs to Replicate file URLs
            $processedImages = array_map(
                fn (string $image) => $this->ensureImageUrl($image),
                $images
            );

            // Apply model-specific input parameter mapping
            $imageParams = $this->getImageInputParams($model, $processedImages);
            $input = array_merge($input, $imageParams);
        }

        // Merge any extra options (allows overriding auto-detected params)
        return array_merge($input, $request->extra);
    }

    /**
     * Get the correct image input parameters for the specific model.
     *
     * Different Replicate models expect different parameter names:
     * - google/gemini-*: uses 'image_input' (array)
     * - google/nano-banana-*: uses 'image_input' (array)
     * - bytedance/seedream-*: uses 'image' (single/array)
     * - black-forest-labs/flux-*: uses 'image' (single)
     * - qwen/*: uses 'image' (array)
     * - Most others: use 'image' (single) or 'images' (multiple)
     *
     * @param  array<string>  $images
     * @return array<string, mixed>
     */
    private function getImageInputParams(string $model, array $images): array
    {
        // Google models (Gemini, Nano Banana) use 'image_input' as an array
        if (str_contains($model, 'google/gemini') || str_contains($model, 'google/nano-banana')) {
            $this->logDebug('Using Google image_input format', [
                'model' => $model,
                'image_count' => count($images),
            ]);

            return ['image_input' => $images];
        }

        // Qwen uses 'image' as an array
        if (str_contains($model, 'qwen/')) {
            $this->logDebug('Using Qwen image array format', [
                'model' => $model,
                'image_count' => count($images),
            ]);

            return ['image' => $images];
        }

        // Seedream supports multiple images via 'image' array
        if (str_contains($model, 'seedream')) {
            $this->logDebug('Using Seedream image format', [
                'model' => $model,
                'image_count' => count($images),
            ]);

            return count($images) === 1
                ? ['image' => $images[0]]
                : ['image' => $images];
        }

        // FLUX models use single 'image' input
        if (str_contains($model, 'flux')) {
            $this->logDebug('Using FLUX image format', [
                'model' => $model,
            ]);

            return ['image' => $images[0]];
        }

        // Default behavior for most models
        $params = ['image' => $images[0]];

        // Some models support multiple images via 'images' key
        if (count($images) > 1) {
            $params['images'] = $images;
        }

        return $params;
    }

    /**
     * Ensure the image is a URL, uploading data URIs to Replicate's file storage.
     *
     * Replicate API only accepts URLs for image inputs, not data URIs.
     * This method detects data URIs and uploads them to Replicate's /files endpoint.
     */
    private function ensureImageUrl(string $image): string
    {
        // If it's already a URL, return as-is
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        // Check if it's a data URI
        if (! str_starts_with($image, 'data:')) {
            // Not a URL and not a data URI - assume it's a URL anyway
            return $image;
        }

        // Parse and upload data URI
        return $this->uploadDataUriToReplicate($image);
    }

    /**
     * Upload a data URI to Replicate's file storage and return the URL.
     *
     * @param  string  $dataUri  Data URI in format: data:image/jpeg;base64,...
     * @return string The URL of the uploaded file
     */
    private function uploadDataUriToReplicate(string $dataUri): string
    {
        // Parse the data URI
        if (! preg_match('/^data:([^;]+);base64,(.+)$/s', $dataUri, $matches)) {
            throw new RuntimeException('Invalid data URI format for image input.');
        }

        $mimeType = $matches[1];
        $base64Data = $matches[2];
        $binaryData = base64_decode($base64Data, true);

        if ($binaryData === false) {
            throw new RuntimeException('Failed to decode base64 image data.');
        }

        // Determine file extension from MIME type
        $extension = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $filename = 'input_'.uniqid().'.'.$extension;

        $this->logDebug('Uploading data URI to Replicate files', [
            'mime_type' => $mimeType,
            'size' => strlen($binaryData),
            'filename' => $filename,
        ]);

        // Upload to Replicate's /files endpoint
        $apiKey = $this->getApiKey();
        $endpoint = $this->getApiEndpoint().'files';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])
            ->timeout($this->getTimeout())
            ->attach('content', $binaryData, $filename)
            ->post($endpoint);

        if ($response->failed()) {
            $this->logError('Failed to upload file to Replicate', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Failed to upload image to Replicate: '.($response->json('detail') ?? $response->body())
            );
        }

        $fileUrl = $response->json('urls.get') ?? $response->json('url');

        if (! $fileUrl) {
            $this->logError('Replicate file upload response missing URL', [
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Replicate file upload did not return a valid URL.');
        }

        $this->logDebug('Successfully uploaded file to Replicate', [
            'file_id' => $response->json('id'),
            'url' => $fileUrl,
        ]);

        return $fileUrl;
    }

    /**
     * Resolve model string to Replicate version hash.
     *
     * Models can be specified as:
     * - owner/name (uses latest version via API)
     * - owner/name:version (explicit version)
     */
    private function resolveModelVersion(string $model): string
    {
        if (str_contains($model, ':')) {
            // Explicit version provided
            [, $version] = explode(':', $model, 2);

            return $version;
        }

        // Fetch latest version from API
        return $this->fetchLatestVersion($model);
    }

    /**
     * Fetch the latest version of a model.
     */
    private function fetchLatestVersion(string $model): string
    {
        $apiKey = $this->getApiKey();
        $endpoint = $this->getApiEndpoint()."models/{$model}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])
            ->timeout($this->getTimeout())
            ->get($endpoint);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to fetch model information for {$model}. Ensure the model exists and you have access."
            );
        }

        $latestVersion = $response->json('latest_version.id');

        if (! $latestVersion) {
            throw new RuntimeException(
                "Model {$model} has no published versions."
            );
        }

        return $latestVersion;
    }

    /**
     * Wait for a prediction to complete.
     *
     * @return array{status: string, output: mixed}
     */
    private function waitForPrediction(string $apiKey, string $predictionId): array
    {
        $timeout = $this->getPollingTimeout();
        $interval = $this->getPollingInterval();
        $startTime = time();

        while (true) {
            $status = $this->getPredictionStatus($predictionId);

            if ($status['status'] === 'succeeded') {
                return $status;
            }

            if ($status['status'] === 'failed') {
                $error = $status['error'] ?? 'Unknown error';
                $this->logError('Prediction failed', [
                    'prediction_id' => $predictionId,
                    'error' => $error,
                ]);

                throw new RuntimeException("Image generation failed: {$error}");
            }

            if ($status['status'] === 'canceled') {
                throw new RuntimeException('Image generation was canceled.');
            }

            // Check timeout
            if ((time() - $startTime) >= $timeout) {
                $this->logWarning('Prediction timed out', [
                    'prediction_id' => $predictionId,
                    'timeout' => $timeout,
                ]);

                throw new RuntimeException(
                    "Image generation timed out after {$timeout} seconds."
                );
            }

            // Wait before next poll
            usleep((int) ($interval * 1_000_000));
        }
    }

    /**
     * Get the status of a prediction.
     *
     * @return array{status: string, output: mixed, error: ?string}
     */
    public function getPredictionStatus(string $predictionId): array
    {
        $apiKey = $this->getApiKey();
        $endpoint = $this->getApiEndpoint()."predictions/{$predictionId}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])
            ->timeout($this->getTimeout())
            ->get($endpoint);

        if ($response->failed()) {
            throw new RuntimeException(
                'Failed to check prediction status.'
            );
        }

        return $response->json();
    }

    /**
     * Extract image from prediction output.
     *
     * Replicate output format varies by model:
     * - String URL (most common)
     * - Array of URLs
     * - Object with 'url' key
     */
    private function extractImageFromOutput(mixed $output): ImagePayload
    {
        // Single URL string
        if (is_string($output) && filter_var($output, FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($output);
        }

        // Array of URLs (take first)
        if (is_array($output)) {
            foreach ($output as $item) {
                if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                    return $this->fetchImageFromUrl($item);
                }

                // Some models return objects with url key
                if (is_array($item) && isset($item['url'])) {
                    return $this->fetchImageFromUrl($item['url']);
                }
            }
        }

        // Object with url key
        if (is_array($output) && isset($output['url'])) {
            return $this->fetchImageFromUrl($output['url']);
        }

        // Base64 encoded
        if (is_string($output) && ! filter_var($output, FILTER_VALIDATE_URL)) {
            // Might be base64
            $binary = base64_decode($output, true);
            if ($binary !== false) {
                return ImagePayload::fromBinary($binary);
            }
        }

        $this->logWarning('Unexpected output format', ['output' => $output]);

        throw new RuntimeException('Unable to extract image from prediction output.');
    }

    /**
     * Get user-friendly error message based on status code.
     */
    private function getErrorMessage(int $status, ?string $detail = null): string
    {
        $baseMessage = match ($status) {
            400 => 'Invalid request parameters.',
            401 => 'Replicate API key is invalid or expired.',
            402 => 'Replicate account has insufficient credits.',
            403 => 'Access denied to this model.',
            404 => 'Model or version not found.',
            422 => 'Invalid model input parameters.',
            429 => 'Rate limit exceeded. Please try again later.',
            500, 502, 503 => 'Replicate service is temporarily unavailable.',
            default => 'An error occurred communicating with Replicate.',
        };

        if ($detail) {
            return "{$baseMessage} Details: {$detail}";
        }

        return $baseMessage;
    }
}
