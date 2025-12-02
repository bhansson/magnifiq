<?php

use App\DTO\AI\ImageGenerationRequest;
use App\Services\AI\Adapters\ReplicateAdapter;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createAdapter(): ReplicateAdapter
{
    return new ReplicateAdapter([
        'api_key' => 'test-api-key',
        'api_endpoint' => 'https://api.replicate.com/v1/',
        'timeout' => 60,
        'polling_timeout' => 300,
        'polling_interval' => 0.1, // Fast polling for tests
    ]);
}

test('generates image with url input without file upload', function () {
    Http::fake([
        // Model info request
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'abc123version'],
        ]),
        // Prediction creation
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-123',
            'status' => 'starting',
        ]),
        // Prediction status (immediately succeeded)
        'api.replicate.com/v1/predictions/pred-123' => Http::response([
            'id' => 'pred-123',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/output-image.jpg',
        ]),
        // Output image download
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'A beautiful sunset',
        model: 'test/model',
        inputImages: 'https://example.com/existing-image.jpg', // URL, not data URI
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify NO file upload was made (URL was passed through)
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/files'));

    // Verify prediction was created with the URL directly
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['image'])
            && $body['input']['image'] === 'https://example.com/existing-image.jpg';
    });
});

test('uploads data uri to replicate files before prediction', function () {
    $testDataUri = 'data:image/jpeg;base64,'.createTestJpegBase64();

    Http::fake([
        // File upload endpoint
        'api.replicate.com/v1/files' => Http::response([
            'id' => 'file-abc123',
            'url' => 'https://replicate.delivery/files/uploaded-image.jpg',
            'urls' => [
                'get' => 'https://replicate.delivery/files/uploaded-image.jpg',
            ],
        ]),
        // Model info request
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'abc123version'],
        ]),
        // Prediction creation
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-456',
            'status' => 'starting',
        ]),
        // Prediction status
        'api.replicate.com/v1/predictions/pred-456' => Http::response([
            'id' => 'pred-456',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/output.jpg',
        ]),
        // Output image download
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'A beautiful sunset',
        model: 'test/model',
        inputImages: $testDataUri, // Data URI that needs uploading
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify file upload WAS made
    Http::assertSent(fn ($request) => str_contains($request->url(), '/files'));

    // Verify prediction was created with the uploaded file URL (not data URI)
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        // Should use the uploaded file URL, not the original data URI
        return isset($body['input']['image'])
            && str_contains($body['input']['image'], 'replicate.delivery');
    });
});

test('handles multiple data uri images', function () {
    $dataUri1 = 'data:image/jpeg;base64,'.createTestJpegBase64();
    $dataUri2 = 'data:image/png;base64,'.createTestPngBase64();

    Http::fake([
        // File upload endpoint - returns different URLs for each call
        'api.replicate.com/v1/files' => Http::sequence()
            ->push([
                'id' => 'file-1',
                'url' => 'https://replicate.delivery/files/image1.jpg',
            ])
            ->push([
                'id' => 'file-2',
                'url' => 'https://replicate.delivery/files/image2.png',
            ]),
        // Model info
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'version123'],
        ]),
        // Prediction
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-789',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-789' => Http::response([
            'id' => 'pred-789',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Combine these images',
        model: 'test/model',
        inputImages: [$dataUri1, $dataUri2],
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify TWO file uploads were made
    $fileUploadCount = 0;
    Http::assertSent(function ($request) use (&$fileUploadCount) {
        if (str_contains($request->url(), '/files')) {
            $fileUploadCount++;
        }

        return true;
    });

    expect($fileUploadCount)->toEqual(2);
});

test('throws exception for invalid data uri format', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'version123'],
        ]),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Test',
        model: 'test/model',
        inputImages: 'data:invalid-format', // Invalid data URI
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid data URI format');

    $adapter->generateImage($request);
});

test('throws exception when file upload fails', function () {
    $testDataUri = 'data:image/jpeg;base64,'.createTestJpegBase64();

    Http::fake([
        'api.replicate.com/v1/files' => Http::response([
            'detail' => 'Upload failed',
        ], 500),
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'version123'],
        ]),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Test',
        model: 'test/model',
        inputImages: $testDataUri,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Failed to upload image to Replicate');

    $adapter->generateImage($request);
});

test('gemini model uses image input array parameter', function () {
    $testDataUri = 'data:image/jpeg;base64,'.createTestJpegBase64();

    Http::fake([
        'api.replicate.com/v1/files' => Http::response([
            'id' => 'file-gemini-123',
            'url' => 'https://replicate.delivery/files/gemini-upload.jpg',
        ]),
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'gemini-version-abc'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-gemini',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-gemini' => Http::response([
            'id' => 'pred-gemini',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/gemini-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    // Use a Gemini model name
    $request = new ImageGenerationRequest(
        prompt: 'Generate a product photo',
        model: 'google/gemini-2.5-flash-image',
        inputImages: $testDataUri,
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify Gemini-specific 'image_input' parameter (array) is used
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        // Gemini expects 'image_input' as an array, NOT 'image'
        return isset($body['input']['image_input'])
            && is_array($body['input']['image_input'])
            && ! isset($body['input']['image']);
    });
});

test('non gemini model uses image parameter', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'flux-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-flux',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-flux' => Http::response([
            'id' => 'pred-flux',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/flux-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    // Use a non-Gemini model
    $request = new ImageGenerationRequest(
        prompt: 'Generate a photo',
        model: 'black-forest-labs/flux-schnell',
        inputImages: 'https://example.com/input.jpg',
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify standard 'image' parameter is used (not 'image_input')
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        // Non-Gemini models use 'image' (string), not 'image_input' (array)
        return isset($body['input']['image'])
            && is_string($body['input']['image'])
            && ! isset($body['input']['image_input']);
    });
});

test('nano banana model uses image input array parameter', function () {
    $testDataUri = 'data:image/jpeg;base64,'.createTestJpegBase64();

    Http::fake([
        'api.replicate.com/v1/files' => Http::response([
            'id' => 'file-nano-123',
            'url' => 'https://replicate.delivery/files/nano-upload.jpg',
        ]),
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'nano-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-nano',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-nano' => Http::response([
            'id' => 'pred-nano',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/nano-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Generate a product photo',
        model: 'google/nano-banana-pro',
        inputImages: $testDataUri,
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify Nano Banana uses 'image_input' as an array (like Gemini)
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['image_input'])
            && is_array($body['input']['image_input'])
            && ! isset($body['input']['image']);
    });
});

test('qwen model uses image array parameter', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'qwen-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-qwen',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-qwen' => Http::response([
            'id' => 'pred-qwen',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/qwen-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Edit this image',
        model: 'qwen/qwen-image-edit-plus',
        inputImages: 'https://example.com/input.jpg',
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify Qwen uses 'image' as an array
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['image'])
            && is_array($body['input']['image']);
    });
});

test('prunaai model uses images array parameter', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'prunaai-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-prunaai',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-prunaai' => Http::response([
            'id' => 'pred-prunaai',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/prunaai-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Edit this image',
        model: 'prunaai/p-image-edit',
        inputImages: 'https://example.com/input.jpg',
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify PrunaAI uses 'images' as an array
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['images'])
            && is_array($body['input']['images']);
    });
});

test('seedream model uses image input array parameter', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'seedream-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-seedream',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-seedream' => Http::response([
            'id' => 'pred-seedream',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/seedream-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Generate an image',
        model: 'bytedance/seedream-4',
        inputImages: 'https://example.com/input.jpg',
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify Seedream uses 'image_input' as an array (always array, even for single image)
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['image_input'])
            && is_array($body['input']['image_input']);
    });
});

test('flux 2 pro model uses input images array parameter', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'flux-2-pro-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-flux-2-pro',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-flux-2-pro' => Http::response([
            'id' => 'pred-flux-2-pro',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/flux-2-pro-output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    $request = new ImageGenerationRequest(
        prompt: 'Generate an image',
        model: 'black-forest-labs/flux-2-pro',
        inputImages: 'https://example.com/input.jpg',
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify FLUX 2 Pro uses 'input_images' as an array
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['input_images'])
            && is_array($body['input']['input_images']);
    });
});

test('extra params are passed to prediction', function () {
    Http::fake([
        'api.replicate.com/v1/models/*' => Http::response([
            'latest_version' => ['id' => 'test-version'],
        ]),
        'api.replicate.com/v1/predictions' => Http::response([
            'id' => 'pred-extra',
            'status' => 'starting',
        ]),
        'api.replicate.com/v1/predictions/pred-extra' => Http::response([
            'id' => 'pred-extra',
            'status' => 'succeeded',
            'output' => 'https://replicate.delivery/output.jpg',
        ]),
        'replicate.delivery/*' => Http::response(
            createTestJpegBinary(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $adapter = createAdapter();

    // Test that extra params (like resolution settings) are passed through
    $request = new ImageGenerationRequest(
        prompt: 'Generate at 4K',
        model: 'google/nano-banana-pro',
        inputImages: 'https://example.com/input.jpg',
        extra: ['resolution' => '4K'],
    );

    $response = $adapter->generateImage($request);

    expect($response->image)->not->toBeNull();

    // Verify 'resolution' parameter was passed
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/predictions')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        return isset($body['input']['resolution'])
            && $body['input']['resolution'] === '4K';
    });
});