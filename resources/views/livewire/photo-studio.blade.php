<div
    class="space-y-6"
    x-data="{
        overlayOpen: false,
        selectedEntry: null,
        openOverlay(entry) {
            this.selectedEntry = entry;
            this.overlayOpen = true;
            document.body.classList.add('overflow-hidden');
        },
        closeOverlay() {
            this.overlayOpen = false;
            this.selectedEntry = null;
            document.body.classList.remove('overflow-hidden');
        },
    }"
    @keydown.escape.window="closeOverlay()"
>
    @php
        $compositionModes = config('photo-studio.composition.modes', []);
        $compositionImageCount = count($compositionImages ?? []);
        $currentModeConfig = $compositionModes[$compositionMode] ?? [];
        $maxImages = $currentModeConfig['max_images'] ?? config('photo-studio.composition.max_images', 14);
        $minImages = $currentModeConfig['min_images'] ?? 1;
        $canGenerate = $this->canGenerate();
    @endphp

    {{-- Gallery Section --}}
    <x-photo-studio.gallery
        :product-gallery="$productGallery"
        :gallery-search="$gallerySearch"
        :gallery-total="$galleryTotal"
        :composition-modes="$compositionModes"
        :is-awaiting-generation="$isAwaitingGeneration"
    />

    {{-- Create New Product Photo Section --}}
    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-2xl">
        <div class="space-y-6 px-6 py-5 sm:p-8">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Create new product photo
                </h3>
                <p class="text-sm text-gray-600 dark:text-zinc-400">
                    Choose a mode, add your image(s), and let AI create stunning product photos.
                </p>
            </div>

            {{-- Mode Selector --}}
            <x-photo-studio.mode-selector
                :composition-mode="$compositionMode"
                :composition-modes="$compositionModes"
            />

            {{-- Unified Image Panel --}}
            <x-photo-studio.image-panel
                :composition-mode="$compositionMode"
                :composition-images="$compositionImages"
                :composition-hero-index="$compositionHeroIndex"
                :products="$products"
                :product-search="$productSearch"
                :max-images="$maxImages"
                :min-images="$minImages"
            />

            {{-- Prompt Section --}}
            <x-photo-studio.prompt-section
                :creative-brief="$creativeBrief"
                :prompt-result="$promptResult"
                :error-message="$errorMessage"
                :aspect-ratio="$aspectRatio"
                :detected-aspect-ratio="$detectedAspectRatio"
                :generation-status="$generationStatus"
                :is-processing="$isProcessing"
                :is-awaiting-generation="$isAwaitingGeneration"
                :is-awaiting-vision-job="$isAwaitingVisionJob"
                :vision-job-status="$visionJobStatus"
                :can-generate="$canGenerate"
                :composition-image-count="$compositionImageCount"
                :min-images="$minImages"
                :selected-model="$selectedModel"
                :selected-resolution="$selectedResolution"
                :available-models="$this->getAvailableModels()"
                :model-supports-resolution="$this->modelSupportsResolution()"
                :available-resolutions="$this->getAvailableResolutions()"
                :estimated-cost="$this->getFormattedCost()"
            />
        </div>
    </div>

    {{-- Gallery Overlay Modal --}}
    @teleport('body')
        <x-photo-studio.gallery-overlay :product-gallery="$productGallery" />
    @endteleport

    {{-- Edit Modal --}}
    @if ($showEditModal)
        @teleport('body')
            <x-photo-studio.edit-modal
                :editing-generation-id="$editingGenerationId"
                :product-gallery="$productGallery"
                :edit-instruction="$editInstruction"
                :edit-submitting="$editSubmitting"
                :edit-generating="$editGenerating"
            />
        @endteleport
    @endif
</div>
