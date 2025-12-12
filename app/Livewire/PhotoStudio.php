<?php

namespace App\Livewire;

use App\DTO\AI\ChatRequest;
use App\DTO\AI\ContentPart;
use App\Facades\AI;
use App\Jobs\ExtractVisionPromptJob;
use App\Jobs\GeneratePhotoStudioImage;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Services\ImageProcessor;
use App\Services\PhotoStudioSourceStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class PhotoStudio extends Component
{
    use Concerns\WithTeamContext;
    use WithFileUploads;

    private const PRODUCT_SEARCH_LIMIT = 50;

    public string $creativeBrief = '';

    public ?string $promptResult = null;

    public ?string $errorMessage = null;

    public bool $isProcessing = false;

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
     * Convenience property for backward compatibility with tests.
     * Setting this will automatically add the product to the composition.
     */
    public ?int $productId = null;

    /**
     * Backward compatibility: stores the preview URL when productId is set.
     */
    public ?string $productImagePreview = null;

    /**
     * Backward compatibility: tab selection for tests.
     * The component now uses composition mode directly.
     */
    public string $activeTab = 'composition';

    /**
     * Light-weight product catalogue for the select element.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $products = [];

    public string $productSearch = '';

    public int $productResultsLimit = self::PRODUCT_SEARCH_LIMIT;

    /**
     * Composition mode: 'scene_composition', 'products_together', 'lifestyle_context', or 'reference_hero'
     */
    public string $compositionMode = 'scene_composition';

    /**
     * Images added to the composition.
     * Each entry: ['type' => 'product'|'upload', 'product_id' => ?int, 'title' => string, 'preview_url' => string, 'data_uri' => ?string]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $compositionImages = [];

    /**
     * Index of the hero image for 'reference_hero' mode (0-based).
     */
    public int $compositionHeroIndex = 0;

    /**
     * Temporary uploaded files for composition (multi-file upload).
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $compositionUploads = [];

    public ?int $editingGenerationId = null;

    public string $editInstruction = '';

    public bool $showEditModal = false;

    public bool $editSubmitting = false;

    public ?string $editSuccessMessage = null;

    public bool $editGenerating = false;

    public ?int $editBaselineGenerationId = null;

    public ?int $editNewGenerationId = null;

    /**
     * Selected aspect ratio for image generation.
     * 'match_input' will auto-detect from the input image.
     */
    public string $aspectRatio = 'match_input';

    /**
     * Detected aspect ratio from the input image (when aspectRatio is 'match_input').
     */
    public ?string $detectedAspectRatio = null;

    /**
     * Selected AI model for image generation.
     */
    public string $selectedModel = '';

    /**
     * Selected output resolution (for models that support it).
     */
    public ?string $selectedResolution = null;

    /**
     * ID of the pending vision/prompt extraction job for polling.
     */
    public ?int $pendingVisionJobId = null;

    /**
     * Whether we're waiting for a vision job to complete.
     */
    public bool $isAwaitingVisionJob = false;

    /**
     * Status message shown while extracting prompt.
     */
    public ?string $visionJobStatus = null;

    public function mount(): void
    {
        $this->getTeam(); // Ensures team context is available

        // Initialize model selection
        $availableModels = $this->getAvailableModels();
        $defaultModel = config('photo-studio.default_image_model');

        // Use default if available, otherwise first model in list
        if ($defaultModel && isset($availableModels[$defaultModel])) {
            $this->selectedModel = $defaultModel;
        } elseif (! empty($availableModels)) {
            $this->selectedModel = array_key_first($availableModels);
        }

        // Initialize resolution to model's default
        $this->selectedResolution = $this->getDefaultResolution();

        $this->refreshProductOptions();
        $this->refreshLatestGeneration();
        $this->refreshProductGallery();
    }

    /**
     * Check if the current mode requirements are met for generation.
     */
    public function canGenerate(): bool
    {
        $imageCount = count($this->compositionImages);

        if ($imageCount === 0) {
            return false;
        }

        $modes = config('photo-studio.composition.modes', []);
        $modeConfig = $modes[$this->compositionMode] ?? [];

        $minImages = $modeConfig['min_images'] ?? 1;
        $maxImages = $modeConfig['max_images'] ?? null;

        if ($imageCount < $minImages) {
            return false;
        }

        if ($maxImages !== null && $imageCount > $maxImages) {
            return false;
        }

        return true;
    }

    /**
     * Get the maximum number of images allowed for the current mode.
     */
    public function getMaxImagesForCurrentMode(): int
    {
        $modes = config('photo-studio.composition.modes', []);
        $modeConfig = $modes[$this->compositionMode] ?? [];

        return $modeConfig['max_images'] ?? config('photo-studio.composition.max_images', 14);
    }

    /**
     * Get the minimum number of images required for the current mode.
     */
    public function getMinImagesForCurrentMode(): int
    {
        $modes = config('photo-studio.composition.modes', []);
        $modeConfig = $modes[$this->compositionMode] ?? [];

        return $modeConfig['min_images'] ?? 1;
    }

    /**
     * Reset the stored prompt when the creative direction changes.
     */
    public function updatedCreativeBrief(): void
    {
        $this->promptResult = null;
        $this->resetGenerationPreview();
    }

    public function updatedProductSearch(): void
    {
        $this->refreshProductOptions();
    }

    public function updatedGallerySearch(): void
    {
        $this->refreshProductGallery();
    }

    /**
     * Reset resolution to model's default when model changes.
     */
    public function updatedSelectedModel(): void
    {
        $this->selectedResolution = $this->getDefaultResolution();
    }

    /**
     * Get all available image generation models from config.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableModels(): array
    {
        return config('photo-studio.image_models', []);
    }

    /**
     * Get the configuration for the currently selected model.
     *
     * @return array<string, mixed>|null
     */
    public function getSelectedModelConfig(): ?array
    {
        if (! $this->selectedModel) {
            return null;
        }

        // Use direct array access because model keys contain forward slashes
        // which break Laravel's dot notation config lookup
        $models = config('photo-studio.image_models', []);

        return $models[$this->selectedModel] ?? null;
    }

    /**
     * Check if the selected model's provider has an API key configured.
     *
     * Each model specifies its provider (e.g., 'replicate') in the config.
     * Falls back to 'replicate' for any models without an explicit provider.
     */
    public function hasApiKeyForSelectedModel(): bool
    {
        $config = $this->getSelectedModelConfig();
        $provider = $config['provider'] ?? 'replicate';

        return AI::hasApiKeyForDriver($provider);
    }

    /**
     * Check if the selected model supports resolution selection.
     */
    public function modelSupportsResolution(): bool
    {
        $config = $this->getSelectedModelConfig();

        return $config['supports_resolution'] ?? false;
    }

    /**
     * Get available resolutions for the selected model.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableResolutions(): array
    {
        $config = $this->getSelectedModelConfig();

        return $config['resolutions'] ?? [];
    }

    /**
     * Get the default resolution for the selected model.
     */
    public function getDefaultResolution(): ?string
    {
        $config = $this->getSelectedModelConfig();

        return $config['default_resolution'] ?? null;
    }

    /**
     * Calculate estimated cost for the current model/resolution selection.
     */
    public function getEstimatedCost(): ?float
    {
        $config = $this->getSelectedModelConfig();

        if (! $config) {
            return null;
        }

        $pricing = $config['pricing'] ?? [];
        $type = $pricing['type'] ?? 'per_image';

        return match ($type) {
            'per_image' => $pricing['cost'] ?? null,
            'per_resolution' => $this->selectedResolution
                ? ($pricing['costs'][$this->selectedResolution] ?? null)
                : null,
            'per_megapixel' => $this->calculateMegapixelCost($pricing),
            default => null,
        };
    }

    /**
     * Calculate cost for megapixel-based pricing (e.g., FLUX models).
     */
    private function calculateMegapixelCost(array $pricing): ?float
    {
        if (! $this->selectedResolution) {
            return null;
        }

        $resolutions = $this->getAvailableResolutions();
        $resConfig = $resolutions[$this->selectedResolution] ?? null;

        if (! $resConfig || ! isset($resConfig['megapixels'])) {
            return null;
        }

        // Cost is based on input + output megapixels
        $mp = $resConfig['megapixels'];
        $costPerMp = $pricing['cost_per_mp'] ?? 0;

        // Approximate: input ~1MP + output at selected resolution
        return ($mp * 2) * $costPerMp;
    }

    /**
     * Get formatted cost string for display.
     */
    public function getFormattedCost(): ?string
    {
        $cost = $this->getEstimatedCost();

        if ($cost === null) {
            return null;
        }

        return '$'.number_format($cost, 3);
    }

    /**
     * Backward compatibility: when productId is set, add the product to composition.
     */
    public function updatedProductId(?int $value): void
    {
        if ($value === null) {
            $this->productImagePreview = null;

            return;
        }

        // Clear existing composition and add this product
        $this->compositionImages = [];
        $this->addProductToComposition($value);

        // Set the preview URL for backward compatibility
        if (! empty($this->compositionImages)) {
            $this->productImagePreview = $this->compositionImages[0]['preview_url'] ?? null;
        }
    }

    /**
     * When composition mode changes, clear images if they exceed the new mode's limits.
     */
    public function updatedCompositionMode(): void
    {
        $maxImages = $this->getMaxImagesForCurrentMode();

        // If current images exceed new mode's max, trim the array
        if (count($this->compositionImages) > $maxImages) {
            $this->compositionImages = array_slice($this->compositionImages, 0, $maxImages);
            $this->compositionHeroIndex = min($this->compositionHeroIndex, count($this->compositionImages) - 1);
        }

        $this->promptResult = null;
        $this->resetGenerationPreview();
    }

    /**
     * Extract prompt from composition images.
     * Unified method that works for all modes (single image or multiple).
     */
    public function extractPrompt(): void
    {
        $this->extractCompositionPrompt();
    }

    /**
     * Generate image from composition images.
     * Unified method that works for all modes (single image or multiple).
     */
    public function generateImage(): void
    {
        $this->generateCompositionImage();
    }

    public function pollGenerationStatus(): void
    {
        if (! $this->isAwaitingGeneration) {
            return;
        }

        $team = $this->getTeamOrNull();

        if (! $team) {
            $this->isAwaitingGeneration = false;

            return;
        }

        $teamId = $team->id;

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
        $team = $this->getTeam();

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

    public function pushToStore(int $generationId): void
    {
        $team = $this->getTeam();

        $generation = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->find($generationId);

        if (! $generation) {
            $this->errorMessage = 'Generation not found.';

            return;
        }

        if ($generation->isPushedToStore()) {
            $this->errorMessage = 'This image has already been pushed to the store.';

            return;
        }

        if (! $generation->canPushToStore()) {
            $this->errorMessage = 'Cannot push this image to the store. Ensure the product has a connected store.';

            return;
        }

        try {
            \App\Jobs\PushImageToStore::dispatch($generation->id);

            $this->generationStatus = 'Image queued for upload to store. This may take a moment.';
            $this->refreshProductGallery();
        } catch (Throwable $e) {
            Log::error('Failed to dispatch PushImageToStore job', [
                'generation_id' => $generationId,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Failed to queue image for store upload.';
        }
    }

    public function openEditModal(int $generationId): void
    {
        $team = $this->getTeam();

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

        $team = $this->getTeamOrNull();

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

        $team = $this->getTeam();

        $parentGeneration = PhotoStudioGeneration::query()
            ->where('team_id', $team->id)
            ->find($this->editingGenerationId);

        if (! $parentGeneration) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'The selected image is no longer available.');

            return;
        }

        if (! $this->hasApiKeyForSelectedModel()) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'Configure an AI provider API key before generating images.');

            return;
        }

        $disk = config('photo-studio.generation_disk', 's3');

        if (! $this->selectedModel) {
            $this->editSubmitting = false;
            $this->addError('editInstruction', 'No image model selected. Please select a model before generating.');

            return;
        }

        // Calculate estimated cost for this generation
        $estimatedCost = $this->getEstimatedCost();

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
                'user_id' => auth()->id(),
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
                    'model' => $this->selectedModel,
                    'resolution' => $this->selectedResolution,
                    'estimated_cost' => $estimatedCost,
                ]),
            ]);

            // Preserve aspect ratio from parent generation if available, otherwise detect from image
            $editAspectRatio = $this->detectAspectRatioFromDimensions(
                $parentGeneration->image_width,
                $parentGeneration->image_height
            );

            GeneratePhotoStudioImage::dispatch(
                productAiJobId: $jobRecord->id,
                teamId: $team->id,
                userId: Auth::id(),
                productId: $parentGeneration->product_id,
                prompt: $newPrompt,
                model: $this->selectedModel,
                disk: $disk,
                imageInput: $imageUrl,
                sourceType: 'edited_generation',
                sourceReference: $parentGeneration->storage_path,
                parentId: $parentGeneration->id,
                editInstruction: $this->editInstruction,
                aspectRatio: $editAspectRatio,
                resolution: $this->selectedResolution,
                estimatedCost: $estimatedCost,
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

    /**
     * Handle composition file uploads.
     */
    public function updatedCompositionUploads(): void
    {
        $maxImages = config('photo-studio.composition.max_images', 14);
        $currentCount = count($this->compositionImages);
        $team = $this->getTeamOrNull();

        if (! $team) {
            return;
        }

        $teamId = $team->id;

        $sourceStorage = app(PhotoStudioSourceStorage::class);

        foreach ($this->compositionUploads as $file) {
            if ($currentCount >= $maxImages) {
                break;
            }

            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            try {
                // Process image to JPEG binary (resized + compressed)
                $processedBinary = $this->processUploadedImageToJpegBinary($file);

                // Store to S3 with private visibility for team-level access
                $storagePath = $sourceStorage->store($processedBinary, $teamId, 'jpg');

                // Create data URI for AI processing from processed binary
                $dataUri = 'data:image/jpeg;base64,'.base64_encode($processedBinary);

                $this->compositionImages[] = [
                    'type' => 'upload',
                    'product_id' => null,
                    'title' => $file->getClientOriginalName() ?: 'Uploaded image',
                    'preview_url' => $file->temporaryUrl(),
                    'data_uri' => $dataUri,
                    'source_reference' => $storagePath,
                    'storage_disk' => $sourceStorage->getDisk(),
                ];
                $currentCount++;
            } catch (Throwable $e) {
                Log::warning('Failed to process composition upload', ['exception' => $e]);
            }
        }

        // Clear temporary uploads after processing
        $this->compositionUploads = [];
        $this->promptResult = null;
    }

    /**
     * Add a product to the composition.
     */
    public function addProductToComposition(int $productId): void
    {
        $maxImages = config('photo-studio.composition.max_images', 14);

        if (count($this->compositionImages) >= $maxImages) {
            return;
        }

        // Check if product is already in composition
        foreach ($this->compositionImages as $img) {
            if ($img['type'] === 'product' && $img['product_id'] === $productId) {
                return;
            }
        }

        $teamId = $this->getTeamOrNull()?->id;

        $product = Product::query()
            ->where('team_id', $teamId)
            ->find($productId, ['id', 'title', 'sku', 'brand', 'image_link', 'additional_image_link']);

        if (! $product || ! $product->image_link) {
            return;
        }

        $this->compositionImages[] = [
            'type' => 'product',
            'product_id' => $product->id,
            'title' => $product->title ?: 'Product #'.$product->id,
            'preview_url' => $product->image_link,
            'data_uri' => null, // Will be fetched during extraction
            'source_reference' => $product->image_link,
            'primary_image_url' => $product->image_link,
            'additional_image_url' => $product->additional_image_link,
            'using_additional_image' => false,
        ];

        $this->promptResult = null;
    }

    /**
     * Remove an image from the composition by index.
     */
    public function removeFromComposition(int $index): void
    {
        if (! isset($this->compositionImages[$index])) {
            return;
        }

        array_splice($this->compositionImages, $index, 1);

        // Adjust hero index if needed
        if ($this->compositionHeroIndex >= count($this->compositionImages)) {
            $this->compositionHeroIndex = max(0, count($this->compositionImages) - 1);
        } elseif ($this->compositionHeroIndex > $index) {
            $this->compositionHeroIndex--;
        }

        $this->promptResult = null;
    }

    /**
     * Toggle between primary and additional image for a product in composition.
     */
    public function toggleProductImage(int $index): void
    {
        if (! isset($this->compositionImages[$index])) {
            return;
        }

        $img = $this->compositionImages[$index];

        // Only toggle if this is a product with an additional image
        if ($img['type'] !== 'product' || empty($img['additional_image_url'])) {
            return;
        }

        $useAdditional = ! ($img['using_additional_image'] ?? false);
        $newUrl = $useAdditional ? $img['additional_image_url'] : $img['primary_image_url'];

        $this->compositionImages[$index]['using_additional_image'] = $useAdditional;
        $this->compositionImages[$index]['preview_url'] = $newUrl;
        $this->compositionImages[$index]['source_reference'] = $newUrl;
        // Clear cached data_uri so it will be re-fetched
        $this->compositionImages[$index]['data_uri'] = null;

        // Clear prompt since input changed
        $this->promptResult = null;
    }

    /**
     * Reorder composition images based on new order array.
     *
     * @param  array<int, int>  $order  Array of old indices in new order
     */
    public function reorderComposition(array $order): void
    {
        $newImages = [];
        $oldHeroProductId = $this->compositionImages[$this->compositionHeroIndex]['product_id'] ?? null;
        $oldHeroType = $this->compositionImages[$this->compositionHeroIndex]['type'] ?? null;
        $oldHeroTitle = $this->compositionImages[$this->compositionHeroIndex]['title'] ?? null;

        foreach ($order as $newIndex => $oldIndex) {
            if (isset($this->compositionImages[$oldIndex])) {
                $newImages[$newIndex] = $this->compositionImages[$oldIndex];
            }
        }

        $this->compositionImages = array_values($newImages);

        // Find the hero's new position
        foreach ($this->compositionImages as $index => $img) {
            if ($img['type'] === $oldHeroType && $img['product_id'] === $oldHeroProductId && $img['title'] === $oldHeroTitle) {
                $this->compositionHeroIndex = $index;
                break;
            }
        }

        $this->promptResult = null;
    }

    /**
     * Set the hero image for reference_hero mode.
     */
    public function setCompositionHero(int $index): void
    {
        if (isset($this->compositionImages[$index])) {
            $this->compositionHeroIndex = $index;
            $this->promptResult = null;
        }
    }

    /**
     * Clear all composition images.
     */
    public function clearComposition(): void
    {
        $this->compositionImages = [];
        $this->compositionHeroIndex = 0;
        $this->compositionUploads = [];
        $this->promptResult = null;
    }

    /**
     * Extract prompt for composition (multiple images).
     */
    public function extractCompositionPrompt(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->promptResult = null;
        $this->visionJobStatus = null;
        $this->resetGenerationPreview();

        if (! $this->canGenerate()) {
            $minImages = $this->getMinImagesForCurrentMode();
            $imageCount = count($this->compositionImages);

            if ($imageCount === 0) {
                $this->errorMessage = 'Add an image to continue.';
            } elseif ($imageCount < $minImages) {
                $this->errorMessage = "Add at least {$minImages} images for this mode.";
            } else {
                $this->errorMessage = 'Cannot generate with the current image selection.';
            }

            return;
        }

        if (! AI::hasApiKeyForFeature('vision')) {
            $this->errorMessage = 'Configure an AI provider API key before extracting prompts.';

            return;
        }

        $team = $this->getTeam();

        $this->isProcessing = true;

        try {
            $imageDataUris = $this->resolveCompositionImageSources();

            // Get first product ID for association (if any products in composition)
            $firstProductId = collect($this->compositionImages)
                ->where('type', 'product')
                ->pluck('product_id')
                ->first();

            $visionModel = config('ai.features.vision.model');
            $visionDriver = AI::getDriverForFeature('vision');

            // Create job record for tracking
            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'user_id' => auth()->id(),
                'product_id' => $firstProductId,
                'sku' => $firstProductId ? Product::find($firstProductId)?->sku : null,
                'product_ai_template_id' => null,
                'job_type' => ProductAiJob::TYPE_VISION_PROMPT,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
                'meta' => [
                    'composition_mode' => $this->compositionMode,
                    'image_count' => count($this->compositionImages),
                    'creative_brief' => $this->creativeBrief,
                    'model' => $visionModel,
                    'ai_driver' => $visionDriver,
                ],
            ]);

            // Dispatch to high-priority vision queue
            ExtractVisionPromptJob::dispatch(
                productAiJobId: $jobRecord->id,
                teamId: $team->id,
                userId: Auth::id(),
                imageDataUris: $imageDataUris,
                compositionMode: $this->compositionMode,
                compositionImages: $this->compositionImages,
                creativeBrief: $this->creativeBrief,
            );

            $this->pendingVisionJobId = $jobRecord->id;
            $this->isAwaitingVisionJob = true;
            $this->visionJobStatus = 'Analyzing images…';
        } catch (Throwable $exception) {
            Log::error('Photo Studio composition prompt extraction dispatch failed', [
                'user_id' => Auth::id(),
                'composition_mode' => $this->compositionMode,
                'image_count' => count($this->compositionImages),
                'exception' => $exception,
            ]);

            $this->errorMessage = 'Unable to start prompt extraction. Please try again.';
            $this->isProcessing = false;
        }
    }

    /**
     * Poll for vision job completion.
     */
    public function pollVisionJobStatus(): void
    {
        if (! $this->isAwaitingVisionJob || ! $this->pendingVisionJobId) {
            return;
        }

        $jobRecord = ProductAiJob::find($this->pendingVisionJobId);

        if (! $jobRecord) {
            $this->resetVisionJobState();
            $this->errorMessage = 'Vision job not found.';

            return;
        }

        if ($jobRecord->status === ProductAiJob::STATUS_COMPLETED) {
            $this->promptResult = $jobRecord->meta['prompt_result'] ?? '';
            $this->visionJobStatus = 'Prompt extracted successfully.';
            $this->resetVisionJobState();

            return;
        }

        if ($jobRecord->status === ProductAiJob::STATUS_FAILED) {
            $this->errorMessage = $jobRecord->meta['error'] ?? 'Prompt extraction failed. Please try again.';
            $this->resetVisionJobState();

            return;
        }

        // Still processing
        $this->visionJobStatus = 'Analyzing images…';
    }

    /**
     * Reset vision job tracking state.
     */
    private function resetVisionJobState(): void
    {
        $this->pendingVisionJobId = null;
        $this->isAwaitingVisionJob = false;
        $this->isProcessing = false;
    }

    /**
     * Generate image from composition.
     */
    public function generateCompositionImage(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->generationStatus = null;

        if (! $this->hasApiKeyForSelectedModel()) {
            $this->errorMessage = 'Configure an AI provider API key before generating images.';

            return;
        }

        if (! $this->promptResult) {
            $this->errorMessage = 'Prompt is missing. Extract a prompt first.';

            return;
        }

        if (! $this->canGenerate()) {
            $minImages = $this->getMinImagesForCurrentMode();
            $this->errorMessage = "Add at least {$minImages} image(s) for this mode.";

            return;
        }

        $disk = config('photo-studio.generation_disk', 's3');
        $availableDisks = config('filesystems.disks', []);

        if (! array_key_exists($disk, $availableDisks)) {
            $this->errorMessage = 'The configured storage disk for Photo Studio is not available.';

            return;
        }

        $team = $this->getTeam();

        try {
            $previousGenerationId = PhotoStudioGeneration::query()
                ->where('team_id', $team->id)
                ->max('id');

            $imageDataUris = $this->resolveCompositionImageSources();

            // Reorder images for reference_hero mode (hero first)
            if ($this->compositionMode === 'reference_hero' && $this->compositionHeroIndex > 0) {
                $hero = $imageDataUris[$this->compositionHeroIndex];
                unset($imageDataUris[$this->compositionHeroIndex]);
                array_unshift($imageDataUris, $hero);

                // Also reorder source_references
                $heroImg = $this->compositionImages[$this->compositionHeroIndex];
                $reorderedImages = $this->compositionImages;
                unset($reorderedImages[$this->compositionHeroIndex]);
                array_unshift($reorderedImages, $heroImg);
            } else {
                $reorderedImages = $this->compositionImages;
            }

            // Build source_references array for storage
            $sourceReferences = collect($reorderedImages)->map(function ($img) {
                $ref = [
                    'type' => $img['type'],
                    'product_id' => $img['product_id'],
                    'title' => $img['title'],
                    'source_reference' => $img['source_reference'],
                ];

                // Include storage_disk for uploaded images that were persisted
                if ($img['type'] === 'upload' && isset($img['storage_disk'])) {
                    $ref['storage_disk'] = $img['storage_disk'];
                }

                return $ref;
            })->values()->toArray();

            if (! $this->selectedModel) {
                $this->errorMessage = 'No image model selected. Please select a model before generating.';

                return;
            }

            // Calculate estimated cost for this generation
            $estimatedCost = $this->getEstimatedCost();

            $this->resetGenerationPreview();

            // Get first product ID for association (if any products in composition)
            $firstProductId = collect($this->compositionImages)
                ->where('type', 'product')
                ->pluck('product_id')
                ->first();

            // For compositions, use the hero/first image's aspect ratio
            $heroImageIndex = $this->compositionMode === 'reference_hero' ? $this->compositionHeroIndex : 0;
            $compositionAspectRatio = $this->resolveEffectiveAspectRatio($imageDataUris[$heroImageIndex] ?? null);

            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'user_id' => auth()->id(),
                'product_id' => $firstProductId,
                'sku' => $firstProductId ? Product::find($firstProductId)?->sku : null,
                'product_ai_template_id' => null,
                'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
                'meta' => array_filter([
                    'source_type' => 'composition',
                    'composition_mode' => $this->compositionMode,
                    'image_count' => count($this->compositionImages),
                    'source_references' => $sourceReferences,
                    'prompt' => $this->promptResult,
                    'model' => $this->selectedModel,
                    'resolution' => $this->selectedResolution,
                    'estimated_cost' => $estimatedCost,
                    'aspect_ratio' => $compositionAspectRatio,
                ]),
            ]);

            GeneratePhotoStudioImage::dispatch(
                productAiJobId: $jobRecord->id,
                teamId: $team->id,
                userId: Auth::id(),
                productId: $firstProductId,
                prompt: $this->promptResult,
                model: $this->selectedModel,
                disk: $disk,
                imageInput: $imageDataUris,
                sourceType: 'composition',
                sourceReference: null,
                parentId: null,
                editInstruction: null,
                compositionMode: $this->compositionMode,
                sourceReferences: $sourceReferences,
                aspectRatio: $compositionAspectRatio,
                resolution: $this->selectedResolution,
                estimatedCost: $estimatedCost,
            );

            $this->pendingGenerationBaselineId = $previousGenerationId ?? 0;
            $this->isAwaitingGeneration = true;
            $this->pendingProductId = $firstProductId;
            $this->generationStatus = 'Composition generation queued. Hang tight while we render your scene.';
        } catch (Throwable $exception) {
            Log::error('Photo Studio composition generation failed', [
                'user_id' => Auth::id(),
                'composition_mode' => $this->compositionMode,
                'exception' => $exception,
            ]);

            $this->errorMessage = 'Unable to generate a composition image right now. Please try again in a moment.';
        }
    }

    /**
     * Resolve all composition images to data URIs.
     *
     * @return array<int, string>
     */
    private function resolveCompositionImageSources(): array
    {
        $dataUris = [];

        foreach ($this->compositionImages as $img) {
            // Use pre-fetched data_uri if available (for any type)
            if (! empty($img['data_uri'])) {
                $dataUris[] = $img['data_uri'];
            } elseif ($img['type'] === 'product' && ! empty($img['preview_url'])) {
                // Fetch and convert external product image
                $dataUris[] = $this->fetchAndConvertExternalImage($img['preview_url']);
            }
        }

        return $dataUris;
    }

    /**
     * Build messages for composition prompt extraction.
     *
     * @param  array<int, string>  $imageDataUris
     */
    private function buildCompositionChatRequest(array $imageDataUris): ChatRequest
    {
        $systemPrompt = config('photo-studio.prompts.extraction.system');
        $modePrompt = config("photo-studio.composition.extraction_prompts.{$this->compositionMode}");

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

        $contextText = $modePrompt;

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

        foreach ($imageDataUris as $dataUri) {
            $contentParts[] = ContentPart::imageUrl($dataUri);
        }

        return ChatRequest::multimodal(
            content: $contentParts,
            systemPrompt: $systemPrompt,
            model: config('ai.features.vision.model'),
            maxTokens: 700,
            temperature: 0.4,
        );
    }

    public function render(): View
    {
        return view('livewire.photo-studio');
    }

    protected function rules(): array
    {
        return [
            'creativeBrief' => ['nullable', 'string', 'max:600'],
            'compositionUploads.*' => [
                'file',
                'max:8192', // 8MB
                'mimes:jpg,jpeg,png,gif,webp,avif',
            ],
        ];
    }

    /**
     * Fetch an external image and convert to JPEG data URI if needed.
     *
     * AI vision APIs typically only support JPEG, PNG, GIF, and WebP.
     * Formats like AVIF need to be converted. All images are resized
     * if they exceed the configured max input dimension.
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
            // Resize if needed while preserving format
            $binary = $this->resizeImageIfNeeded($binary);

            // Re-detect MIME after potential resize
            $contentType = $this->detectMimeFromBinary($binary) ?: $contentType;

            return 'data:'.$contentType.';base64,'.base64_encode($binary);
        }

        // Convert unsupported formats (AVIF, HEIC, BMP, etc.) to JPEG (includes resize)
        $maxDimension = config('photo-studio.input.max_dimension');
        $jpegBinary = app(ImageProcessor::class)->convertToJpeg($binary, $maxDimension);

        return ImageProcessor::toDataUri($jpegBinary);
    }

    /**
     * Calculate new dimensions if image exceeds max input dimension.
     *
     * @return array{0: int, 1: int, 2: bool} [newWidth, newHeight, needsResize]
     */
    private function calculateResizeDimensions(int $width, int $height): array
    {
        $maxDimension = config('photo-studio.input.max_dimension');

        if ($maxDimension === null || $maxDimension <= 0) {
            return [$width, $height, false];
        }

        $maxDimension = (int) $maxDimension;

        // Check if resize is needed
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height, false];
        }

        // Scale proportionally based on the longest edge
        if ($width >= $height) {
            $newWidth = $maxDimension;
            $newHeight = (int) round($height * ($maxDimension / $width));
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round($width * ($maxDimension / $height));
        }

        return [$newWidth, $newHeight, true];
    }

    /**
     * Resize image binary if it exceeds max input dimension, preserving format.
     *
     * Returns the original binary if no resize is needed or if GD is unavailable.
     */
    private function resizeImageIfNeeded(string $binary): string
    {
        $maxDimension = config('photo-studio.input.max_dimension');

        if ($maxDimension === null || $maxDimension <= 0) {
            return $binary;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('getimagesizefromstring')) {
            return $binary;
        }

        $size = @getimagesizefromstring($binary);

        if ($size === false || ! isset($size[0], $size[1])) {
            return $binary;
        }

        $width = $size[0];
        $height = $size[1];

        [$newWidth, $newHeight, $needsResize] = $this->calculateResizeDimensions($width, $height);

        if (! $needsResize) {
            return $binary;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return $binary;
        }

        $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
        imagedestroy($image);

        if ($resized === false) {
            return $binary;
        }

        // Output in original format based on detected type
        $imageType = $size[2] ?? IMAGETYPE_PNG;

        $jpegQuality = (int) config('photo-studio.input.jpeg_quality', 90);
        $webpQuality = (int) config('photo-studio.input.webp_quality', 90);
        $pngCompression = (int) config('photo-studio.input.png_compression', 6);

        ob_start();
        $success = match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($resized, null, $jpegQuality),
            IMAGETYPE_GIF => imagegif($resized),
            IMAGETYPE_WEBP => imagewebp($resized, null, $webpQuality),
            default => imagepng($resized, null, $pngCompression),
        };
        $output = ob_get_clean();

        imagedestroy($resized);

        if (! $success || $output === false) {
            return $binary;
        }

        return $output;
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

    private function encodeUploadedImage(TemporaryUploadedFile $file): string
    {
        $contents = $file->get();

        if ($contents === false || $contents === null) {
            throw new RuntimeException('Failed to read the uploaded image.');
        }

        $mime = $file->getMimeType() ?: 'image/png';

        // Supported formats that don't need conversion
        $supportedFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($mime, $supportedFormats, true)) {
            // Resize if needed while preserving format
            $contents = $this->resizeImageIfNeeded($contents);

            // Re-detect MIME after potential resize
            $mime = $this->detectMimeFromBinary($contents) ?: $mime;

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        // Convert unsupported formats (AVIF, HEIC, BMP, etc.) to JPEG (includes resize)
        $maxDimension = config('photo-studio.input.max_dimension');
        $jpegBinary = app(ImageProcessor::class)->convertToJpeg($contents, $maxDimension);

        return ImageProcessor::toDataUri($jpegBinary);
    }

    /**
     * Process an uploaded image to JPEG binary for storage.
     *
     * This method resizes and converts the image to JPEG format, returning
     * the processed binary data suitable for storage.
     */
    private function processUploadedImageToJpegBinary(TemporaryUploadedFile $file): string
    {
        $contents = $file->get();

        if ($contents === false || $contents === null) {
            throw new RuntimeException('Failed to read the uploaded image.');
        }

        // Always convert to JPEG for consistent storage format
        $maxDimension = config('photo-studio.input.max_dimension');

        return app(ImageProcessor::class)->convertToJpeg($contents, $maxDimension);
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
        $team = $this->getTeamOrNull();

        if (! $team) {
            $this->resetGenerationPreview();

            return;
        }

        $teamId = $team->id;

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

        $team = $this->getTeamOrNull();

        if (! $team) {
            return;
        }

        $teamId = $team->id;

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
            ->with([
                'product:id,title,sku,brand,product_feed_id',
                'product.feed:id,store_connection_id',
                'product.feed.storeConnection',
                'parent',
                'children',
            ])
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
                    'composition_mode' => $generation->composition_mode,
                    'composition_image_count' => $generation->getCompositionImageCount(),
                    'source_references' => $generation->source_references,
                    'source_images' => $generation->getSourceImageUrls(),
                    'has_viewable_sources' => $generation->hasViewableSourceImages(),
                    'is_pushed_to_store' => $generation->isPushedToStore(),
                    'pushed_to_store_at' => optional($generation->pushed_to_store_at)->diffForHumans(),
                    'can_push_to_store' => $generation->canPushToStore(),
                ];
            })
            ->toArray();
    }

    private function refreshProductOptions(): void
    {
        $team = $this->getTeamOrNull();

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
            ->get(['id', 'title', 'sku', 'brand', 'image_link', 'additional_image_link']);

        $this->products = $productRecords
            ->map(static function (Product $product): array {
                return [
                    'id' => $product->id,
                    'title' => $product->title ?: 'Untitled product #'.$product->id,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'image_link' => $product->image_link,
                    'additional_image_link' => $product->additional_image_link,
                ];
            })
            ->toArray();

        // Ensure products in current composition are included in the list
        $compositionProductIds = collect($this->compositionImages)
            ->where('type', 'product')
            ->pluck('product_id')
            ->filter()
            ->toArray();

        $existingProductIds = collect($this->products)->pluck('id')->toArray();
        $missingProductIds = array_diff($compositionProductIds, $existingProductIds);

        if (! empty($missingProductIds)) {
            $missingProducts = Product::query()
                ->where('team_id', $team->id)
                ->whereIn('id', $missingProductIds)
                ->get(['id', 'title', 'sku', 'brand', 'image_link', 'additional_image_link']);

            foreach ($missingProducts as $product) {
                $this->products[] = [
                    'id' => $product->id,
                    'title' => $product->title ?: 'Untitled product #'.$product->id,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'image_link' => $product->image_link,
                    'additional_image_link' => $product->additional_image_link,
                ];
            }
        }
    }

    /**
     * Resolve the effective aspect ratio to use for generation.
     *
     * If 'match_input' is selected, detect from the input image.
     * Otherwise, use the explicitly selected ratio.
     */
    private function resolveEffectiveAspectRatio(?string $imageDataUri): ?string
    {
        if ($this->aspectRatio !== 'match_input') {
            return $this->aspectRatio;
        }

        if (! $imageDataUri) {
            return null;
        }

        return $this->detectAspectRatioFromDataUri($imageDataUri);
    }

    /**
     * Detect the aspect ratio from an image data URI and map to the closest supported ratio.
     */
    private function detectAspectRatioFromDataUri(string $dataUri): ?string
    {
        // Extract binary from data URI
        if (! preg_match('/^data:[^;]+;base64,(.+)$/', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false) {
            return null;
        }

        return $this->detectAspectRatioFromBinary($binary);
    }

    /**
     * Detect aspect ratio from image binary data.
     */
    private function detectAspectRatioFromBinary(string $binary): ?string
    {
        if (! function_exists('getimagesizefromstring')) {
            return null;
        }

        $size = @getimagesizefromstring($binary);

        if ($size === false || ! isset($size[0], $size[1]) || $size[0] === 0 || $size[1] === 0) {
            return null;
        }

        return $this->mapToClosestAspectRatio($size[0], $size[1]);
    }

    /**
     * Detect aspect ratio from known image dimensions.
     */
    private function detectAspectRatioFromDimensions(?int $width, ?int $height): ?string
    {
        if (! $width || ! $height) {
            return null;
        }

        return $this->mapToClosestAspectRatio($width, $height);
    }

    /**
     * Map image dimensions to the closest supported aspect ratio.
     *
     * Reads available ratios from config to adhere to DRY principle.
     */
    private function mapToClosestAspectRatio(int $width, int $height): string
    {
        $inputRatio = $width / $height;

        // Build supported ratios from config, excluding 'match_input'
        $availableRatios = config('photo-studio.aspect_ratios.available', []);
        $supportedRatios = [];

        foreach (array_keys($availableRatios) as $ratio) {
            if ($ratio === 'match_input') {
                continue;
            }

            // Parse ratio string (e.g., "16:9") to decimal
            $parts = explode(':', $ratio);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && (float) $parts[1] !== 0.0) {
                $supportedRatios[$ratio] = (float) $parts[0] / (float) $parts[1];
            }
        }

        // Fallback if config is empty
        if (empty($supportedRatios)) {
            return '1:1';
        }

        $closestRatio = array_key_first($supportedRatios);
        $smallestDiff = PHP_FLOAT_MAX;

        foreach ($supportedRatios as $ratio => $decimal) {
            $diff = abs($inputRatio - $decimal);
            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestRatio = $ratio;
            }
        }

        return $closestRatio;
    }

    /**
     * Update the detected aspect ratio preview from the first composition image.
     */
    public function updateDetectedAspectRatio(): void
    {
        $this->detectedAspectRatio = null;

        if (empty($this->compositionImages)) {
            return;
        }

        $firstImage = $this->compositionImages[0];

        try {
            if (! empty($firstImage['data_uri'])) {
                // Use pre-computed data URI
                $this->detectedAspectRatio = $this->detectAspectRatioFromDataUri($firstImage['data_uri']);
            } elseif ($firstImage['type'] === 'product' && ! empty($firstImage['preview_url'])) {
                // Fetch product image
                $response = Http::timeout(10)->get($firstImage['preview_url']);
                if ($response->successful()) {
                    $this->detectedAspectRatio = $this->detectAspectRatioFromBinary($response->body());
                }
            }
        } catch (Throwable) {
            // Silently ignore detection failures
        }
    }
}
