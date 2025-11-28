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
        $selectedProduct = collect($products)->firstWhere('id', $productId);
        $isCompositionMode = $activeTab === 'composition';
        $hasReferenceSource = $isCompositionMode
            ? count($compositionImages ?? []) >= 2
            : (bool) ($image || $productId);
        $hasPromptText = filled($promptResult);
        $compositionModes = config('photo-studio.composition.modes', []);
        $compositionImageCount = count($compositionImages ?? []);
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
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Create new product photo
                    </h3>
                    @if ($selectedProduct)
                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 dark:bg-amber-500/20 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ $selectedProduct['title'] }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-gray-600 dark:text-zinc-400">
                    Upload image or choose a product from the catalog.
                </p>
            </div>

            {{-- Reference Tabs --}}
            <x-photo-studio.reference-tabs :active-tab="$activeTab" />

            {{-- Tab Panels --}}
            <div class="space-y-5">
                @if ($activeTab === 'upload')
                    <x-photo-studio.upload-panel :image="$image" />
                @endif

                @if ($activeTab === 'catalog')
                    <x-photo-studio.catalog-panel
                        :products="$products"
                        :product-id="$productId"
                        :product-search="$productSearch"
                        :product-results-limit="$productResultsLimit"
                        :selected-product="$selectedProduct"
                        :product-image-preview="$productImagePreview"
                        :image="$image"
                    />
                @endif

                @if ($activeTab === 'composition')
                    <x-photo-studio.composition-panel
                        :composition-mode="$compositionMode"
                        :composition-modes="$compositionModes"
                        :composition-images="$compositionImages"
                        :composition-hero-index="$compositionHeroIndex"
                        :products="$products"
                        :product-search="$productSearch"
                    />
                @endif
            </div>

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
                :has-reference-source="$hasReferenceSource"
                :is-composition-mode="$isCompositionMode"
                :composition-image-count="$compositionImageCount"
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
