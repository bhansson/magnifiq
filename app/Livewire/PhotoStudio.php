<?php

namespace App\Livewire;

use App\Jobs\GeneratePhotoStudioImage;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File as FileRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageUrlData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\TextContentData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use RuntimeException;
use Throwable;

class PhotoStudio extends Component
{
    use WithFileUploads;

    private const PRODUCT_SEARCH_LIMIT = 50;

    public ?TemporaryUploadedFile $image = null;

    public ?int $productId = null;

    public string $creativeBrief = '';

    public ?string $promptResult = null;

    public ?string $errorMessage = null;

    public bool $isProcessing = false;

    public ?string $productImagePreview = null;

    public ?string $generatedImageUrl = null;

    /**
     * @var array{path: string, disk: string, response_id: string|null}|null
     */
    public ?array $latestGeneration = null;

    public ?string $generationStatus = null;

    /**
     * Gallery of previously generated images for the selected product.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $productGallery = [];

    public string $gallerySearch = '';

    public int $galleryTotal = 0;

    public ?int $latestObservedGenerationId = null;

    public ?int $pendingGenerationBaselineId = null;

    public bool $isAwaitingGeneration = false;

    public ?int $pendingProductId = null;

    /**
     * Light-weight product catalogue for the select element.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $products = [];

    public string $productSearch = '';

    public int $productResultsLimit = self::PRODUCT_SEARCH_LIMIT;

    public ?int $editingGenerationId = null;

    public string $editInstruction = '';

    public bool $showEditModal = false;

    public bool $editSubmitting = false;

    public ?string $editSuccessMessage = null;

    public bool $editGenerating = false;

    public ?int $editBaselineGenerationId = null;

    public ?int $editNewGenerationId = null;

    public function mount(): void
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        $this->refreshProductOptions();

        $this->syncSelectedProductPreview();
        $this->refreshLatestGeneration();
        $this->refreshProductGallery();
    }

    /**
     * Reset the stored prompt when the creative direction changes.
     */
    public function updatedCreativeBrief(): void
    {
        $this->promptResult = null;
        $this->resetGenerationPreview();
    }

    /**
     * Ensure only one source of truth is active.
     */
    public function updatedProductId(): void
    {
        if ($this->productId) {
            $this->image = null;
        }

        $this->promptResult = null;
        $this->resetGenerationPreview();
        $this->syncSelectedProductPreview();
        $this->refreshProductGallery();
    }

    public function updatedProductSearch(): void
    {
        $this->refreshProductOptions();
    }

    /**
     * When a new image is uploaded, clear the product selection.
     */
    public function updatedImage(): void
    {
        $this->productId = null;
        $this->promptResult = null;
        $this->resetGenerationPreview();
        $this->productImagePreview = null;
        $this->refreshProductGallery();
    }

    public function updatedGallerySearch(): void
    {
        $this->refreshProductGallery();
    }

    public function extractPrompt(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->promptResult = null;
        $this->resetGenerationPreview();

        $this->validate();

        if (! $this->hasImageSource()) {
            $message = 'Upload an image or choose a product to continue.';
            $this->addError('image', $message);
            $this->addError('productId', $message);

            return;
        }

        if (! config('laravel-openrouter.api_key')) {
            $this->errorMessage = 'Configure an OpenRouter API key before extracting prompts.';

            return;
        }

        $this->isProcessing = true;

        try {
            [$imageUrl, $product] = $this->resolveImageSource();

            $messages = $this->buildMessages($imageUrl, $product);

            $model = config('photo-studio.models.vision');

            if (! $model) {
                throw new RuntimeException(
                    'Photo Studio vision model is not configured. Set OPENROUTER_PHOTO_STUDIO_MODEL in your environment.'
                );
            }

            $chatData = new ChatData(
                messages: $messages,
                model: $model,
                max_tokens: 700,
                temperature: 0.4,
            );

            $response = LaravelOpenRouter::chatRequest($chatData);

            $content = $this->extractResponseContent($response->toArray());

            if ($content === '') {
                throw new RuntimeException('Received an empty response from the AI provider.');
            }

            $this->promptResult = $content;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $context = [
                'user_id' => Auth::id(),
                'product_id' => $this->productId,
                'exception' => $exception,
            ];

            // Extract full API response body from Guzzle exceptions
            if ($exception instanceof \GuzzleHttp\Exception\ClientException) {
                $context['response_body'] = (string) $exception->getResponse()?->getBody();
            }

            Log::error('Photo Studio prompt extraction failed', $context);

            $this->errorMessage = 'Unable to extract a prompt right now. Please try again in a moment.';
        } finally {
            $this->isProcessing = false;
        }
    }

    public function generateImage(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->generationStatus = null;

        if (! config('laravel-openrouter.api_key')) {
            $this->errorMessage = 'Configure an OpenRouter API key before generating images.';

            return;
        }

        if (! $this->promptResult) {
            $this->errorMessage = 'Prompt is missing.';

            return;
        }

        $this->validate();

        $disk = config('photo-studio.generation_disk', 's3');
        $availableDisks = config('filesystems.disks', []);

        if (! array_key_exists($disk, $availableDisks)) {
            $this->errorMessage = 'The configured storage disk for Photo Studio is not available.';

            return;
        }

        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        try {
            $previousGenerationId = PhotoStudioGeneration::query()
                ->where('team_id', $team->id)
                ->where('product_id', $this->productId)
                ->max('id');

            $imageInput = null;
            $product = null;
            $sourceType = 'prompt_only';
            $sourceReference = null;

            if ($this->hasImageSource()) {
                [$imageInput, $product] = $this->resolveImageSource();
                $sourceType = $this->image instanceof TemporaryUploadedFile ? 'uploaded_image' : 'product_image';
                $sourceReference = $this->image instanceof TemporaryUploadedFile
                    ? ($this->image->getClientOriginalName() ?: $this->image->getFilename())
                    : $product?->image_link;
            }

            $model = config('photo-studio.models.image_generation');

            if (! $model) {
                $this->errorMessage = 'Photo Studio image model is not configured. Set OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL in your environment.';

                return;
            }

            $this->resetGenerationPreview();

            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'product_id' => $product?->id,
                'sku' => $product?->sku,
                'product_ai_template_id' => null,
                'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
                'meta' => array_filter([
                    'source_type' => $sourceType,
                    'source_reference' => $sourceReference,
                    'prompt' => $this->promptResult,
                    'model' => $model,
                ]),
            ]);

            GeneratePhotoStudioImage::dispatch(
                productAiJobId: $jobRecord->id,
                teamId: $team->id,
                userId: Auth::id(),
                productId: $product?->id,
                prompt: $this->promptResult,
                model: $model,
                disk: $disk,
                imageInput: $imageInput,
                sourceType: $sourceType,
                sourceReference: $sourceReference,
            );

            $this->pendingGenerationBaselineId = $previousGenerationId ?? 0;
            $this->isAwaitingGeneration = true;
            $this->pendingProductId = $product?->id;
            $this->generationStatus = 'Image generation queued. Hang tight while we render your scene.';
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Photo Studio image generation failed', [
                'user_id' => Auth::id(),
                'product_id' => $this->productId,
                'exception' => $exception,
            ]);

            $this->errorMessage = 'Unable to generate an image right now. Please try again in a moment.';
        }
    }

    public function pollGenerationStatus(): void
    {
        if (! $this->isAwaitingGeneration) {
            return;
        }

        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            $this->isAwaitingGeneration = false;

            return;
        }

        $baseline = $this->pendingGenerationBaselineId ?? 0;

        $latest = PhotoStudioGeneration::query()
            ->where('team_id', $teamId)
            ->where('id', '>', $baseline)
            ->when(
                $this->pendingProductId === null,
                static function ($query): void {
                    $query->whereNull('product_id');
                },
                function ($query): void {
                    $query->where('product_id', $this->pendingProductId);
                }
            )
            ->latest()
            ->first();

        if (! $latest) {
            $this->generationStatus = 'Image generation in progress…';

            return;
        }

        $this->refreshProductGallery();
        $latestGalleryId = $this->productGallery[0]['id'] ?? null;

        if ($latestGalleryId !== $latest->id) {
            $this->generationStatus = 'Image generation in progress…';

            return;
        }

        $this->refreshLatestGeneration();

        $this->isAwaitingGeneration = false;
        $this->pendingGenerationBaselineId = null;
        $this->generationStatus = 'New image added to the gallery.';
        $this->pendingProductId = null;
    }

    public function deleteGeneration(int $generationId): void
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        $generation = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->find($generationId);

        if (! $generation) {
            return;
        }

        $generation->delete();

        if ($this->latestObservedGenerationId === $generationId) {
            $this->refreshLatestGeneration();
        }

        $this->refreshProductGallery();
    }

    public function openEditModal(int $generationId): void
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        $generation = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->find($generationId);

        if (! $generation) {
            return;
        }

        $this->editingGenerationId = $generationId;
        $this->editInstruction = '';
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingGenerationId = null;
        $this->editInstruction = '';
        $this->editSubmitting = false;
        $this->editSuccessMessage = null;
        $this->editGenerating = false;
        $this->editBaselineGenerationId = null;
        $this->editNewGenerationId = null;
        $this->resetErrorBag();
    }

    public function pollEditGeneration(): void
    {
        if (! $this->editGenerating) {
            return;
        }

        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return;
        }

        $baseline = $this->editBaselineGenerationId ?? 0;

        $newGeneration = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->where('parent_id', $this->editingGenerationId)
            ->where('id', '>', $baseline)
            ->latest()
            ->first();

        if ($newGeneration) {
            $this->editGenerating = false;
            $this->refreshProductGallery();

            // Automatically switch to editing the new generation
            $this->editingGenerationId = $newGeneration->id;
            $this->editNewGenerationId = null;
            $this->editInstruction = '';
        }
    }

    public function submitEdit(): void
    {
        $this->resetErrorBag();
        $this->editSubmitting = true;
        $this->editSuccessMessage = null;

        $this->validate([
            'editInstruction' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        $parentGeneration = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->find($this->editingGenerationId);

        if (! $parentGeneration) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'The selected image is no longer available.');

            return;
        }

        if (! config('laravel-openrouter.api_key')) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'Configure an OpenRouter API key before generating images.');

            return;
        }

        $disk = config('photo-studio.generation_disk', 's3');
        $model = config('photo-studio.models.image_generation');

        if (! $model) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'Photo Studio image model is not configured. Set OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL in your environment.');

            return;
        }

        try {
            // Establish baseline generation ID for polling
            $previousGenerationId = PhotoStudioGeneration::query()
                ->where('team_id', $team->id)
                ->where('parent_id', $parentGeneration->id)
                ->max('id');

            $imageUrl = Storage::disk($parentGeneration->storage_disk)->url($parentGeneration->storage_path);

            $editTemplate = config('photo-studio.prompts.edit_template', "{original_prompt}\n\nModification requested: {instruction}");
            $newPrompt = str_replace(
                ['{original_prompt}', '{instruction}'],
                [$parentGeneration->prompt, $this->editInstruction],
                $editTemplate
            );

            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'product_id' => $parentGeneration->product_id,
                'sku' => $parentGeneration->product?->sku,
                'product_ai_template_id' => null,
                'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
                'meta' => array_filter([
                    'source_type' => 'edited_generation',
                    'source_reference' => $parentGeneration->storage_path,
                    'parent_generation_id' => $parentGeneration->id,
                    'edit_instruction' => $this->editInstruction,
                    'prompt' => $newPrompt,
                    'model' => $model,
                ]),
            ]);

            GeneratePhotoStudioImage::dispatch(
                productAiJobId: $jobRecord->id,
                teamId: $team->id,
                userId: Auth::id(),
                productId: $parentGeneration->product_id,
                prompt: $newPrompt,
                model: $model,
                disk: $disk,
                imageInput: $imageUrl,
                sourceType: 'edited_generation',
                sourceReference: $parentGeneration->storage_path,
                parentId: $parentGeneration->id,
                editInstruction: $this->editInstruction,
            );

            $this->editSubmitting = false;
            $this->editSuccessMessage = null;
            $this->editInstruction = '';
            $this->editGenerating = true;
            $this->editBaselineGenerationId = $previousGenerationId ?? 0;
            $this->generationStatus = 'Edit queued. The modified image will appear in the gallery shortly.';
        } catch (Throwable $exception) {
            Log::error('Photo Studio edit generation failed', [
                'user_id' => Auth::id(),
                'parent_generation_id' => $parentGeneration->id,
                'exception' => $exception,
            ]);

            $this->editSubmitting = false;
            $this->addError('editInstruction', 'Unable to edit the image right now. Please try again in a moment.');
        }
    }

    public function render(): View
    {
        return view('livewire.photo-studio');
    }

    protected function rules(): array
    {
        $teamId = Auth::user()?->currentTeam?->id;

        return [
            'image' => [
                'nullable',
                FileRule::image()
                    ->max(8 * 1024), // 8 MB
            ],
            'productId' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where('team_id', $teamId),
            ],
            'creativeBrief' => ['nullable', 'string', 'max:600'],
        ];
    }

    private function hasImageSource(): bool
    {
        return $this->image instanceof TemporaryUploadedFile || $this->productId !== null;
    }

    /**
     * @return array{0: string, 1: Product|null}
     *
     * @throws ValidationException
     */
    private function resolveImageSource(): array
    {
        if ($this->image instanceof TemporaryUploadedFile) {
            return [$this->encodeUploadedImage($this->image), null];
        }

        $teamId = Auth::user()?->currentTeam?->id;

        $product = Product::query()
            ->where('team_id', $teamId)
            ->find($this->productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'productId' => 'The selected product is no longer available.',
            ]);
        }

        if (! $product->image_link) {
            throw ValidationException::withMessages([
                'productId' => 'The selected product does not have an image to analyse.',
            ]);
        }

        // Fetch and convert external image to ensure compatibility with AI vision APIs
        $imageDataUri = $this->fetchAndConvertExternalImage($product->image_link);

        return [$imageDataUri, $product];
    }

    /**
     * Fetch an external image and convert to JPEG data URI if needed.
     *
     * AI vision APIs typically only support JPEG, PNG, GIF, and WebP.
     * Formats like AVIF need to be converted.
     */
    private function fetchAndConvertExternalImage(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch the product image from: '.$url);
        }

        $binary = $response->body();
        $contentType = $response->header('Content-Type') ?? $this->detectMimeFromBinary($binary);

        // Supported formats that don't need conversion
        $supportedFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($contentType, $supportedFormats, true)) {
            return 'data:'.$contentType.';base64,'.base64_encode($binary);
        }

        // Convert unsupported formats (AVIF, HEIC, BMP, etc.) to JPEG
        return $this->convertImageToJpegDataUri($binary);
    }

    /**
     * Convert any image binary to JPEG data URI.
     */
    private function convertImageToJpegDataUri(string $binary): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD extension is required to convert image formats.');
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw new RuntimeException('Unable to process the product image format.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Create a new true color image with white background (for transparency)
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpegBinary = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if ($jpegBinary === false) {
            throw new RuntimeException('Failed to convert image to JPEG format.');
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpegBinary);
    }

    /**
     * Detect MIME type from binary content.
     */
    private function detectMimeFromBinary(string $binary): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_buffer($finfo, $binary) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    private function encodeUploadedImage(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Failed to read the uploaded image.');
        }

        $mime = $file->getMimeType() ?: 'image/png';

        // Supported formats that don't need conversion
        $supportedFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($mime, $supportedFormats, true)) {
            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        // Convert unsupported formats (AVIF, HEIC, BMP, etc.) to JPEG
        return $this->convertImageToJpegDataUri($contents);
    }

    /**
     * @return MessageData[]
     */
    private function buildMessages(string $imageUrl, ?Product $product): array
    {
        $systemPrompt = config('photo-studio.prompts.extraction.system');
        $userText = config('photo-studio.prompts.extraction.user');

        $details = $product ? sprintf(
            "Product name: %s\nBrand: %s\nSKU: %s",
            $product->title ?: 'N/A',
            $product->brand ?: 'N/A',
            $product->sku ?: 'N/A',
        ) : 'Product metadata: not provided.';

        $contentParts = [
            new TextContentData(
                type: TextContentData::ALLOWED_TYPE,
                text: $userText."\n\n".$details
            ),
            $this->creativeBrief !== ''
                ? new TextContentData(
                    type: TextContentData::ALLOWED_TYPE,
                    text: 'Creative direction from the user: '.$this->creativeBrief
                )
                : null,
            new ImageContentPartData(
                type: ImageContentPartData::ALLOWED_TYPE,
                image_url: new ImageUrlData(
                    url: $imageUrl,
                    detail: 'high'
                )
            ),
        ];

        return [
            new MessageData(
                role: 'system',
                content: $systemPrompt
            ),
            new MessageData(
                role: 'user',
                content: array_values(array_filter($contentParts))
            ),
        ];
    }

    private function resolveDiskUrl(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function resetGenerationPreview(): void
    {
        $this->generatedImageUrl = null;
        $this->latestGeneration = null;
        $this->latestObservedGenerationId = null;
        $this->isAwaitingGeneration = false;
        $this->pendingGenerationBaselineId = null;
        $this->generationStatus = null;
        $this->pendingProductId = null;
    }

    private function refreshLatestGeneration(): void
    {
        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            $this->resetGenerationPreview();

            return;
        }

        $latest = PhotoStudioGeneration::query()
            ->where('team_id', $teamId)
            ->latest()
            ->first();

        if (! $latest) {
            $this->resetGenerationPreview();

            return;
        }

        $this->latestGeneration = [
            'path' => $latest->storage_path,
            'disk' => $latest->storage_disk,
            'response_id' => $latest->response_id,
        ];

        $this->generatedImageUrl = $this->resolveDiskUrl($latest->storage_disk, $latest->storage_path);
        $this->latestObservedGenerationId = $latest->id;
    }

    private function refreshProductGallery(): void
    {
        $this->productGallery = [];
        $this->galleryTotal = 0;

        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            return;
        }

        $search = trim($this->gallerySearch);

        $query = PhotoStudioGeneration::query()
            ->where('team_id', $teamId);

        $this->galleryTotal = (clone $query)->count();

        if ($search !== '') {
            $normalizedSearch = Str::lower($search);

            $query->where(function ($builder) use ($normalizedSearch): void {
                $builder
                    ->whereRaw('LOWER(prompt) LIKE ?', ['%'.$normalizedSearch.'%'])
                    ->orWhereHas('product', function ($productQuery) use ($normalizedSearch): void {
                        $productQuery
                            ->whereRaw('LOWER(title) LIKE ?', ['%'.$normalizedSearch.'%'])
                            ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.$normalizedSearch.'%']);
                    });
            });
        }

        $generations = $query
            ->with(['product:id,title,sku,brand', 'parent', 'children'])
            ->latest()
            ->get();

        $this->productGallery = $generations
            ->map(function (PhotoStudioGeneration $generation): array {
                $product = $generation->product;
                $productLabel = null;
                $productMeta = null;

                if ($product) {
                    $productLabel = $product->title ?: 'Untitled product #'.$product->id;
                    $metaParts = array_filter([$product->brand, $product->sku]);
                    $productMeta = empty($metaParts) ? null : implode(' • ', $metaParts);
                }

                // Build complete generation tree (ancestors, current, descendants)
                $tree = $generation->fullTree();

                $ancestors = $tree['ancestors']->map(function ($gen) {
                    return [
                        'id' => $gen->id,
                        'url' => $this->resolveDiskUrl($gen->storage_disk, $gen->storage_path),
                        'edit_instruction' => $gen->edit_instruction,
                        'created_at_human' => optional($gen->created_at)->diffForHumans(),
                    ];
                })->toArray();

                $descendants = $tree['descendants']->map(function ($gen) {
                    return [
                        'id' => $gen->id,
                        'url' => $this->resolveDiskUrl($gen->storage_disk, $gen->storage_path),
                        'edit_instruction' => $gen->edit_instruction,
                        'created_at_human' => optional($gen->created_at)->diffForHumans(),
                    ];
                })->toArray();

                return [
                    'id' => $generation->id,
                    'url' => $this->resolveDiskUrl($generation->storage_disk, $generation->storage_path),
                    'disk' => $generation->storage_disk,
                    'path' => $generation->storage_path,
                    'prompt' => $generation->prompt,
                    'edit_instruction' => $generation->edit_instruction,
                    'model' => $generation->model,
                    'download_url' => route('photo-studio.gallery.download', $generation),
                    'created_at' => optional($generation->created_at)->toDateTimeString(),
                    'created_at_human' => optional($generation->created_at)->diffForHumans(),
                    'product' => $product ? [
                        'id' => $product->id,
                        'title' => $productLabel,
                        'sku' => $product->sku,
                        'brand' => $product->brand,
                    ] : null,
                    'product_label' => $productLabel,
                    'product_meta' => $productMeta,
                    'product_brand' => $product?->brand,
                    'product_sku' => $product?->sku,
                    'parent_id' => $generation->parent_id,
                    'children_count' => $generation->children()->count(),
                    'ancestors' => $ancestors,
                    'descendants' => $descendants,
                    'has_history' => count($ancestors) > 0 || count($descendants) > 0,
                ];
            })
            ->toArray();
    }

    private function extractResponseContent(array $response): string
    {
        $content = Arr::get($response, 'choices.0.message.content');

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $text = collect($content)
                ->map(static function ($segment): string {
                    if (is_array($segment) && isset($segment['text'])) {
                        return (string) $segment['text'];
                    }

                    return is_string($segment) ? $segment : '';
                })
                ->implode("\n");

            return trim($text);
        }

        return '';
    }

    private function syncSelectedProductPreview(): void
    {
        $this->productImagePreview = null;

        if (! $this->productId) {
            return;
        }

        $teamId = Auth::user()?->currentTeam?->id;

        $product = Product::query()
            ->select('id', 'team_id', 'image_link')
            ->where('team_id', $teamId)
            ->find($this->productId);

        $this->productImagePreview = $product?->image_link;
    }

    private function refreshProductOptions(): void
    {
        $team = Auth::user()?->currentTeam;

        $this->products = [];

        if (! $team) {
            return;
        }

        $search = trim($this->productSearch);

        $query = Product::query()
            ->where('team_id', $team->id);

        if ($search !== '') {
            $normalized = Str::lower($search);

            $query->where(static function ($builder) use ($normalized): void {
                $like = '%'.$normalized.'%';

                $builder
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(brand) LIKE ?', [$like]);
            });
        }

        $productRecords = $query
            ->orderBy('title')
            ->limit(self::PRODUCT_SEARCH_LIMIT)
            ->get(['id', 'title', 'sku', 'brand', 'image_link']);

        $this->products = $productRecords
            ->map(static function (Product $product): array {
                return [
                    'id' => $product->id,
                    'title' => $product->title ?: 'Untitled product #'.$product->id,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'image_link' => $product->image_link,
                ];
            })
            ->toArray();

        if ($this->productId && ! collect($this->products)->firstWhere('id', $this->productId)) {
            $selectedProduct = Product::query()
                ->where('team_id', $team->id)
                ->find($this->productId, ['id', 'title', 'sku', 'brand', 'image_link']);

            if ($selectedProduct) {
                $this->products[] = [
                    'id' => $selectedProduct->id,
                    'title' => $selectedProduct->title ?: 'Untitled product #'.$selectedProduct->id,
                    'sku' => $selectedProduct->sku,
                    'brand' => $selectedProduct->brand,
                    'image_link' => $selectedProduct->image_link,
                ];
            }
        }
    }
}
