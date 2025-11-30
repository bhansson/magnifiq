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
            // Most Replicate models use 'image' for single input
            $input['image'] = $images[0];

            // Some models support multiple images
            if (count($images) > 1) {
                $input['images'] = $images;
            }
        }

        // Merge any extra options
        return array_merge($input, $request->extra);
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
     * Fetch image from URL.
     */
    private function fetchImageFromUrl(string $url): ImagePayload
    {
        $response = Http::timeout(60)->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Unable to download the generated image.');
        }

        return ImagePayload::fromBinary(
            (string) $response->body(),
            $response->header('Content-Type')
        );
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
