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
        $galleryTotalCount = $galleryTotal ?? 0;
        $filteredGalleryCount = count($productGallery);
        $galleryHasEntries = $galleryTotalCount > 0;
        $hasFilteredEntries = $filteredGalleryCount > 0;
        $hasGallerySearch = filled($gallerySearch ?? '');
        $productMatchesCount = count($products);
        $hasProductSearch = filled($productSearch ?? '');
        $selectedProductLabel = $selectedProduct
            ? trim(($selectedProduct['title'] ?? 'Untitled product').' '.(($selectedProduct['sku'] ?? null) ? '— '.$selectedProduct['sku'] : ''))
            : '';
        $compositionModes = config('photo-studio.composition.modes', []);
        $maxCompositionImages = config('photo-studio.composition.max_images', 14);
        $compositionImageCount = count($compositionImages ?? []);
    @endphp

    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-2xl">
        <div class="space-y-5 px-6 py-5 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Gallery</h3>
                    <p class="text-sm text-gray-600 dark:text-zinc-400">
                        The Photo Studio Gallery displays all generated product photos.
                    </p>
                </div>
                @if ($galleryHasEntries || $isAwaitingGeneration)
                    <div class="flex flex-col items-end text-right">
                        @if ($galleryHasEntries)
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">
                                Showing {{ $filteredGalleryCount }} of {{ $galleryTotalCount }} image{{ $galleryTotalCount === 1 ? '' : 's' }}
                                @if ($hasGallerySearch)
                                    for &ldquo;{{ $gallerySearch }}&rdquo;
                                @endif
                            </span>
                        @endif
                        @if ($isAwaitingGeneration)
                            <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-amber-50 dark:bg-amber-500/20 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400" wire:poll.3s="pollGenerationStatus">
                                <x-loading-spinner class="size-3" />
                                New render processing
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($galleryHasEntries)
                <div class="flex flex-col gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 bg-gray-50/70 dark:bg-zinc-800/50 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:max-w-xs">
                        <label for="photo-studio-gallery-search" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                            Search photos
                        </label>
                        <div class="mt-1">
                            <div class="relative">
                                <input
                                    type="search"
                                    id="photo-studio-gallery-search"
                                    wire:model.live.debounce.400ms="gallerySearch"
                                    placeholder="By prompt, title, or SKU..."
                                    class="block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 py-2 pl-3 pr-4 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                                />
                                <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400 dark:text-zinc-500">
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m14.5 14.5 3 3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle cx="9.5" cy="9" r="5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (! $galleryHasEntries)
                <div class="rounded-2xl border border-dashed border-amber-200 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/10 p-6 text-center text-sm text-amber-900 dark:text-amber-400">
                    <p class="font-semibold text-amber-900 dark:text-amber-300">This gallery is waiting for its first render.</p>
                    <p class="mt-1">
                        Generate an image to seed the gallery. Each run automatically appears here with download links and prompt context, no matter which product you selected.
                    </p>
                </div>
            @elseif (! $hasFilteredEntries)
                <div class="rounded-2xl border border-dashed border-amber-200 dark:border-amber-500/30 bg-amber-50/80 dark:bg-amber-500/10 p-6 text-center text-sm text-amber-900 dark:text-amber-400">
                    <p class="font-semibold text-amber-900 dark:text-amber-300">No renders match &ldquo;{{ $gallerySearch }}&rdquo;.</p>
                    <p class="mt-1">Update your search terms or clear the filter to browse all {{ $galleryTotalCount }} image{{ $galleryTotalCount === 1 ? '' : 's' }}.</p>
                    <button
                        type="button"
                        wire:click="$set('gallerySearch', '')"
                        class="mt-4 inline-flex items-center gap-2 rounded-full bg-white dark:bg-zinc-800 px-4 py-2 text-sm font-semibold text-amber-900 dark:text-amber-400 shadow-sm ring-1 ring-amber-200 dark:ring-amber-500/30 transition hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="m6 6 8 8M14 6l-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Clear search
                    </button>
                </div>
            @else
                <div
                    class="relative"
                    x-data="{
                        atStart: true,
                        atEnd: false,
                        scrollTrack(direction) {
                            const track = this.$refs.track;
                            if (!track) {
                                return;
                            }

                            const distance = track.clientWidth * 0.85;
                            track.scrollBy({ left: direction === 'next' ? distance : -distance, behavior: 'smooth' });
                        },
                        updateScrollState() {
                            const track = this.$refs.track;
                            if (!track) {
                                return;
                            }

                            const tolerance = 4;
                            this.atStart = track.scrollLeft <= tolerance;
                            this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - tolerance;
                        }
                    }"
                    x-init="updateScrollState()"
                    x-effect="updateScrollState()"
                    x-on:resize.window.debounce.200ms="updateScrollState()"
                >
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-white dark:from-zinc-900/50"></div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-white dark:from-zinc-900/50"></div>

                        <div class="absolute left-0 top-1/2 z-10 -translate-y-1/2">
                            <button
                                type="button"
                                class="rounded-full bg-white/90 dark:bg-zinc-800/90 p-2 text-gray-600 dark:text-zinc-400 shadow ring-1 ring-gray-200 dark:ring-zinc-700 transition hover:bg-white dark:hover:bg-zinc-700"
                                :class="atStart ? 'cursor-not-allowed opacity-40' : ''"
                                @click="scrollTrack('previous')"
                                :disabled="atStart"
                                aria-label="Show previous renders"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m12 5-5 5 5 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>

                        <div class="absolute right-0 top-1/2 z-10 -translate-y-1/2">
                            <button
                                type="button"
                                class="rounded-full bg-white/90 dark:bg-zinc-800/90 p-2 text-gray-600 dark:text-zinc-400 shadow ring-1 ring-gray-200 dark:ring-zinc-700 transition hover:bg-white dark:hover:bg-zinc-700"
                                :class="atEnd ? 'cursor-not-allowed opacity-40' : ''"
                                @click="scrollTrack('next')"
                                :disabled="atEnd"
                                aria-label="Show more renders"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m8 5 5 5-5 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>

                        <div
                            x-ref="track"
                            class="flex snap-x snap-mandatory gap-5 overflow-x-auto pb-4 pl-4 pr-4 scroll-smooth sm:gap-6"
                            @scroll.debounce.100ms="updateScrollState()"
                            tabindex="0"
                            aria-label="Photo Studio gallery slider"
                        >
                            @foreach ($productGallery as $entry)
                                <div class="flex w-64 shrink-0 flex-col rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50 p-5 sm:w-72 lg:w-80" wire:key="gallery-{{ $entry['id'] }}">
                                    @if ($entry['url'])
                                        <div class="group relative aspect-square">
                                            <button
                                                type="button"
                                                class="block h-full w-full overflow-hidden rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                                                @click="openOverlay(@js($entry))"
                                            >
                                                <img
                                                    src="{{ $entry['url'] }}"
                                                    alt="Generated render"
                                                    class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                                                />
                                                <span class="sr-only">Open gallery details for this image</span>
                                            </button>
                                            <a
                                                href="{{ route('photo-studio.gallery.download', $entry['id']) }}"
                                                download
                                                class="absolute right-2 top-2 inline-flex items-center rounded-full border border-white/70 dark:border-zinc-600 bg-white/90 dark:bg-zinc-800/90 p-1 text-gray-600 dark:text-zinc-400 shadow-sm ring-1 ring-black/10 dark:ring-zinc-700 transition hover:bg-white dark:hover:bg-zinc-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                                                title="Download image"
                                            >
                                                <span class="sr-only">Download image</span>
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                    <path d="M10 3v8m0 0 3-3m-3 3-3-3M4.5 13.5v1.25A1.25 1.25 0 0 0 5.75 16h8.5a1.25 1.25 0 0 0 1.25-1.25V13.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </a>
                                        </div>
                                    @else
                                        <div class="flex aspect-square items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 p-4 text-center text-sm text-gray-500 dark:text-zinc-400">
                                            <div>
                                                <p>Stored on {{ $entry['disk'] }}</p>
                                                <p class="mt-1 break-all font-mono text-xs">{{ $entry['path'] }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Composition Badge --}}
                                    @if (! empty($entry['composition_mode']))
                                        <div class="mt-2 flex items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/20 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:text-violet-400">
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                                {{ config('photo-studio.composition.modes.' . $entry['composition_mode'] . '.label', 'Composition') }}
                                            </span>
                                            @if (! empty($entry['composition_image_count']))
                                                <span class="text-[10px] text-gray-400 dark:text-zinc-500">{{ $entry['composition_image_count'] }} images</span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="mt-3 text-xs text-gray-500 dark:text-zinc-500">
                                        @if (! empty($entry['product_label']))
                                            <p class="text-sm font-semibold text-gray-800 dark:text-zinc-200">{{ $entry['product_label'] }}</p>
                                            @if (! empty($entry['product_brand']) || ! empty($entry['product_sku']))
                                                <p class="text-xs text-gray-500 dark:text-zinc-500">
                                                    @if (! empty($entry['product_brand']))
                                                        <span>{{ $entry['product_brand'] }}</span>
                                                    @endif
                                                    @if (! empty($entry['product_brand']) && ! empty($entry['product_sku']))
                                                        <span class="mx-1 text-gray-400 dark:text-zinc-600">&middot;</span>
                                                    @endif
                                                    @if (! empty($entry['product_sku']))
                                                        <span class="text-gray-400 dark:text-zinc-500">{{ $entry['product_sku'] }}</span>
                                                    @endif
                                                </p>
                                            @endif
                                        @else
                                            <span>Generated without a catalog product</span>
                                        @endif
                                    </div>

                                    @if (! empty($entry['prompt']))
                                        <p class="mt-2 text-sm text-gray-700 dark:text-zinc-300" title="{{ $entry['prompt'] }}">
                                            "{{ \Illuminate\Support\Str::limit($entry['prompt'], 110) }}"
                                        </p>
                                    @endif

                                    <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-zinc-500">
                                        <span>{{ $entry['model'] ?: 'Unknown model' }}</span>
                                        @if (! empty($entry['created_at_human']))
                                            <span>{{ $entry['created_at_human'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>


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

            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-gray-900 dark:text-white">Provide your reference</legend>
                <div class="inline-flex flex-wrap rounded-full border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-1 text-sm font-semibold text-gray-600" role="tablist">
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'upload')"
                        class="rounded-full px-4 py-2 transition {{ $activeTab === 'upload' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
                        role="tab"
                        aria-selected="{{ $activeTab === 'upload' ? 'true' : 'false' }}"
                    >
                        Upload image
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'catalog')"
                        class="rounded-full px-4 py-2 transition {{ $activeTab === 'catalog' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
                        role="tab"
                        aria-selected="{{ $activeTab === 'catalog' ? 'true' : 'false' }}"
                    >
                        Catalog product
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'composition')"
                        class="rounded-full px-4 py-2 transition {{ $activeTab === 'composition' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
                        role="tab"
                        aria-selected="{{ $activeTab === 'composition' ? 'true' : 'false' }}"
                    >
                        Composition
                        <span class="ml-1 rounded-full bg-amber-100 dark:bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:text-amber-400">New</span>
                    </button>
                </div>
                <div class="space-y-5">
                    {{-- Upload Tab --}}
                    @if ($activeTab === 'upload')
                    <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4 shadow-sm dark:shadow-none">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Upload an image</p>
                            @if ($image)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Selected
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                            Preferred for ad-hoc shots or items not yet in your catalog.
                        </p>

                        <label for="photo-studio-upload" class="mt-4 block text-sm font-medium text-gray-700 dark:text-zinc-300">
                            Image file
                        </label>
                        <input
                            id="photo-studio-upload"
                            type="file"
                            wire:model="image"
                            accept="image/*"
                            class="mt-2 block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                            JPG, PNG or WEBP up to 8&nbsp;MB.
                        </p>

                        @error('image')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <div wire:loading.flex wire:target="image" class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400">
                            <x-loading-spinner class="size-4" />
                            Uploading…
                        </div>

                        @if ($image)
                            <img
                                src="{{ $image->temporaryUrl() }}"
                                alt="Uploaded preview"
                                class="mt-4 max-h-48 w-auto max-w-full rounded-xl border border-gray-200 dark:border-zinc-700 object-cover"
                            />
                        @endif
                    </div>
                    @endif

                    {{-- Catalog Tab --}}
                    @if ($activeTab === 'catalog')
                    <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4 shadow-sm dark:shadow-none">
                        <div class="flex items-center justify-between">
                            @if ($selectedProduct && ! $image)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Selected
                                </span>
                            @endif
                        </div>

                        <div
                            class="mt-4"
                            x-data="{
                                open: false,
                                search: @entangle('productSearch').live,
                                selectedId: @entangle('productId'),
                                selectedLabel: @js($selectedProductLabel),
                                showList() {
                                    this.open = true;
                                },
                                hideList() {
                                    this.open = false;
                                },
                                handleInput() {
                                    if (! this.open) {
                                        this.showList();
                                    }

                                    if (this.search !== this.selectedLabel && this.selectedId) {
                                        this.selectedId = null;
                                    }

                                    if (this.search === '') {
                                        this.selectedLabel = '';
                                    }
                                },
                                selectProduct(option) {
                                    const id = option.dataset.productId;

                                    if (id) {
                                        this.selectedId = Number(id);
                                    }

                                    this.selectedLabel = option.dataset.label || this.search;
                                    this.search = this.selectedLabel;
                                    this.hideList();
                                },
                                clearSearch() {
                                    this.search = '';
                                    this.selectedId = null;
                                    this.selectedLabel = '';
                                    this.showList();
                                    this.$nextTick(() => this.$refs.productSearch?.focus());
                                },
                            }"
                        >
                            <label for="photo-studio-product-search" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Catalog product
                            </label>
                            <div class="relative mt-2" @click.outside="hideList()">
                                <input
                                    id="photo-studio-product-search"
                                    x-ref="productSearch"
                                    type="search"
                                    x-model.debounce.400ms="search"
                                    @focus="showList()"
                                    @input="handleInput()"
                                    @keydown.escape.stop="hideList()"
                                    placeholder="Search by title, SKU, or brand"
                                    class="block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 py-2 pl-3 pr-4 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                                    role="combobox"
                                    :aria-expanded="open.toString()"
                                    aria-controls="photo-studio-product-options"
                                    autocomplete="off"
                                />
                                <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400 dark:text-zinc-500">
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m14.5 14.5 3 3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle cx="9.5" cy="9" r="5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>

                                <div
                                    x-show="open"
                                    x-transition
                                    x-cloak
                                    class="absolute z-10 mt-2 w-full overflow-hidden rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xl"
                                >
                                    <ul
                                        class="max-h-64 divide-y divide-gray-100 dark:divide-zinc-700 overflow-y-auto"
                                        id="photo-studio-product-options"
                                        role="listbox"
                                    >
                                        @forelse ($products as $product)
                                            @php
                                                $productLabel = trim(($product['title'] ?? 'Untitled product').' '.(! empty($product['sku']) ? '— '.$product['sku'] : ''));
                                                $isSelected = (int) ($productId ?? 0) === (int) $product['id'];
                                            @endphp
                                            <li wire:key="photo-studio-product-{{ $product['id'] }}">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-start justify-between gap-3 px-4 py-3 text-left text-sm text-gray-900 dark:text-zinc-100 transition hover:bg-amber-50 dark:hover:bg-amber-500/10"
                                                    :class="{ 'bg-amber-50 dark:bg-amber-500/10 text-amber-900 dark:text-amber-300': Number(selectedId) === {{ $product['id'] }} }"
                                                    data-option
                                                    data-label="{{ $productLabel }}"
                                                    data-product-id="{{ $product['id'] }}"
                                                    role="option"
                                                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                                    :aria-selected="(Number(selectedId) === {{ $product['id'] }}) ? 'true' : 'false'"
                                                    x-on:mousedown.prevent
                                                    @click="selectProduct($event.currentTarget)"
                                                    wire:click="$set('productId', {{ $product['id'] }})"
                                                >
                                                    <div class="flex-1">
                                                        <p class="font-semibold">{{ $product['title'] }}</p>
                                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-zinc-500">
                                                            SKU: {{ $product['sku'] ?: '—' }}
                                                            @if (! empty($product['brand']))
                                                                &middot; Brand: {{ $product['brand'] }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <svg
                                                        class="h-4.5 w-4.5 text-emerald-500 {{ $isSelected ? '' : 'hidden' }}"
                                                        :class="{ 'hidden': Number(selectedId) !== {{ $product['id'] }} }"
                                                        viewBox="0 0 20 20"
                                                        fill="none"
                                                        aria-hidden="true"
                                                    >
                                                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                </button>
                                            </li>
                                        @empty
                                            <li class="px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">
                                                No products match your search. Try another term.
                                            </li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>

                            <div wire:loading.flex wire:target="productSearch" class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400">
                                <x-loading-spinner class="size-4" />
                                Searching catalog…
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                                Showing {{ $productMatchesCount }} product{{ $productMatchesCount === 1 ? '' : 's' }}
                                @if ($hasProductSearch)
                                    for &ldquo;{{ $productSearch }}&rdquo;
                                @endif
                                . Results are limited to {{ $productResultsLimit }} at a time.
                            </p>
                        </div>

                        @error('productId')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @if ($selectedProduct)
                            <div class="mt-4 rounded-xl border border-gray-100 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50 p-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="shrink-0 w-full sm:max-w-xs">
                                        @if ($productImagePreview)
                                            <img
                                                src="{{ $productImagePreview }}"
                                                alt="Product preview"
                                                class="w-full rounded-lg border border-gray-200 dark:border-zinc-700 object-cover"
                                            />
                                        @else
                                            <div class="flex h-32 items-center justify-center rounded-lg border border-dashed border-amber-200 dark:border-amber-500/30 bg-white dark:bg-zinc-900 px-3 text-center text-sm text-amber-700 dark:text-amber-400">
                                                This product does not have an image yet.
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 space-y-3">
                                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                                            {{ $selectedProduct['title'] }}
                                        </p>
                                        <dl class="space-y-3 text-sm text-gray-700 dark:text-zinc-300">
                                            <div>
                                                <dt class="font-medium text-gray-500 dark:text-zinc-500">SKU</dt>
                                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $selectedProduct['sku'] ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500 dark:text-zinc-500">Brand</dt>
                                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $selectedProduct['brand'] ?: '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- Composition Tab --}}
                    @if ($activeTab === 'composition')
                    <div class="space-y-6">
                        {{-- Mode Selection --}}
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose composition style</p>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                @foreach ($compositionModes as $modeKey => $modeConfig)
                                    <button
                                        type="button"
                                        wire:click="$set('compositionMode', '{{ $modeKey }}')"
                                        @class([
                                            'relative rounded-2xl border-2 p-4 text-left transition-all',
                                            'border-amber-400 dark:border-amber-500 bg-amber-50 dark:bg-amber-500/10 ring-2 ring-amber-400/50' => $compositionMode === $modeKey,
                                            'border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 hover:border-gray-300 dark:hover:border-zinc-600' => $compositionMode !== $modeKey,
                                        ])
                                    >
                                        <div class="flex items-start gap-3">
                                            <div @class([
                                                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                                'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400' => $compositionMode === $modeKey,
                                                'bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400' => $compositionMode !== $modeKey,
                                            ])>
                                                @if ($modeConfig['icon'] === 'user-group')
                                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                                                @elseif ($modeConfig['icon'] === 'squares-2x2')
                                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                                @elseif ($modeConfig['icon'] === 'star')
                                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p @class([
                                                    'font-semibold',
                                                    'text-amber-900 dark:text-amber-300' => $compositionMode === $modeKey,
                                                    'text-gray-900 dark:text-white' => $compositionMode !== $modeKey,
                                                ])>{{ $modeConfig['label'] }}</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $modeConfig['description'] }}</p>
                                            </div>
                                        </div>
                                        @if ($compositionMode === $modeKey)
                                            <div class="absolute -right-1 -top-1">
                                                <span class="flex size-5 items-center justify-center rounded-full bg-amber-500 text-white">
                                                    <svg class="size-3" viewBox="0 0 20 20" fill="none"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </span>
                                            </div>
                                        @endif
                                        @if (!empty($modeConfig['example_image']))
                                            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-700">
                                                <img src="{{ asset($modeConfig['example_image']) }}" alt="{{ $modeConfig['label'] }} example" class="w-full h-20 object-cover" />
                                            </div>
                                        @endif
                                        <p class="mt-2 text-xs text-gray-400 dark:text-zinc-500 italic">{{ $modeConfig['example_hint'] }}</p>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Selected Images Grid --}}
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Selected images</p>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500 dark:text-zinc-500">{{ $compositionImageCount }}/{{ $maxCompositionImages }}</span>
                                    @if ($compositionImageCount > 0)
                                        <button
                                            type="button"
                                            wire:click="clearComposition"
                                            class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                        >
                                            Clear all
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if ($compositionImageCount === 0)
                                <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-8 text-center">
                                    <svg class="mx-auto size-12 text-gray-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    <p class="mt-3 text-sm text-gray-500 dark:text-zinc-400">Add at least 2 images to create a composition</p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Upload images or select from your catalog below</p>
                                </div>
                            @else
                                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                                    @foreach ($compositionImages as $index => $img)
                                        <div
                                            class="group relative aspect-square rounded-xl border-2 overflow-hidden {{ $compositionMode === 'reference_hero' && $compositionHeroIndex === $index ? 'border-amber-400 dark:border-amber-500 ring-2 ring-amber-400/50' : 'border-gray-200 dark:border-zinc-700' }}"
                                            wire:key="composition-img-{{ $index }}"
                                        >
                                            <img
                                                src="{{ $img['preview_url'] }}"
                                                alt="{{ $img['title'] }}"
                                                class="size-full object-cover"
                                            />

                                            {{-- Hero Star (only for reference_hero mode) --}}
                                            @if ($compositionMode === 'reference_hero')
                                                <button
                                                    type="button"
                                                    wire:click="setCompositionHero({{ $index }})"
                                                    class="absolute left-1 top-1 rounded-full p-1 transition {{ $compositionHeroIndex === $index ? 'bg-amber-400 text-white' : 'bg-white/80 dark:bg-zinc-800/80 text-gray-400 dark:text-zinc-500 hover:text-amber-500' }}"
                                                    title="{{ $compositionHeroIndex === $index ? 'Hero product' : 'Set as hero' }}"
                                                >
                                                    <svg class="size-4" fill="{{ $compositionHeroIndex === $index ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                                </button>
                                            @endif

                                            {{-- Remove Button --}}
                                            <button
                                                type="button"
                                                wire:click="removeFromComposition({{ $index }})"
                                                class="absolute right-1 top-1 rounded-full bg-red-500 p-1 text-white opacity-0 transition group-hover:opacity-100"
                                                title="Remove"
                                            >
                                                <svg class="size-3" viewBox="0 0 20 20" fill="none"><path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                            </button>

                                            {{-- Type Badge --}}
                                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1">
                                                <p class="truncate text-[10px] font-medium text-white">{{ $img['title'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($compositionMode === 'reference_hero')
                                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                        <svg class="inline size-3 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                        Click the star to set the hero product. Others will be used as style reference.
                                    </p>
                                @endif
                            @endif
                        </div>

                        {{-- Add Images Panel --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {{-- Upload Column --}}
                            <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Upload images</p>
                                <input
                                    type="file"
                                    wire:model="compositionUploads"
                                    accept="image/*"
                                    multiple
                                    class="block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                                    {{ $compositionImageCount >= $maxCompositionImages ? 'disabled' : '' }}
                                />
                                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">JPG, PNG or WEBP. Select multiple files at once.</p>
                                <div wire:loading.flex wire:target="compositionUploads" class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400">
                                    <x-loading-spinner class="size-4" />
                                    Processing uploads…
                                </div>
                            </div>

                            {{-- Catalog Column --}}
                            <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">From catalog</p>
                                <input
                                    type="search"
                                    wire:model.live.debounce.400ms="productSearch"
                                    placeholder="Search products..."
                                    class="block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 py-2 px-3 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                                />
                                <div class="mt-3 max-h-48 overflow-y-auto space-y-1">
                                    @forelse ($products as $product)
                                        @php
                                            $alreadyAdded = collect($compositionImages)->contains(fn($img) => $img['type'] === 'product' && $img['product_id'] === $product['id']);
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="addProductToComposition({{ $product['id'] }})"
                                            {{ $alreadyAdded || $compositionImageCount >= $maxCompositionImages ? 'disabled' : '' }}
                                            class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left text-sm transition {{ $alreadyAdded ? 'bg-emerald-50 dark:bg-emerald-500/10 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-zinc-700' }} disabled:opacity-50"
                                        >
                                            @if ($product['image_link'])
                                                <img src="{{ $product['image_link'] }}" alt="" class="size-8 rounded object-cover" />
                                            @else
                                                <div class="size-8 rounded bg-gray-100 dark:bg-zinc-700"></div>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ $product['title'] }}</p>
                                                <p class="truncate text-xs text-gray-500 dark:text-zinc-500">{{ $product['sku'] ?: 'No SKU' }}</p>
                                            </div>
                                            @if ($alreadyAdded)
                                                <svg class="size-4 text-emerald-500" viewBox="0 0 20 20" fill="none"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                            @endif
                                        </button>
                                    @empty
                                        <p class="py-4 text-center text-sm text-gray-500 dark:text-zinc-400">No products found</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </fieldset>

            <div>
                <label for="photo-studio-brief" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                    Creative direction (optional)
                </label>
                <textarea
                    id="photo-studio-brief"
                    wire:model.defer="creativeBrief"
                    rows="3"
                    class="mt-2 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                    placeholder="Example: Emphasise natural window lighting and add subtle studio props like folded towels."
                ></textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                    Up to 600 characters. These notes are added to the AI request for extra guidance.
                </p>

                @error('creativeBrief')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-4 rounded-2xl border border-dashed border-amber-200 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3 text-sm text-amber-900 dark:text-amber-400">
                    <svg class="mt-1 h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div>
                        @if ($isCompositionMode)
                            <p class="font-semibold dark:text-amber-300">
                                {{ $hasReferenceSource ? $compositionImageCount . ' images ready for composition.' : 'Add at least 2 images to continue.' }}
                            </p>
                            <p class="text-amber-900/80 dark:text-amber-400/80">
                                {{ $hasReferenceSource ? "We'll analyse all images together to craft a unified prompt." : 'Upload files or select products from your catalog.' }}
                            </p>
                        @else
                            <p class="font-semibold dark:text-amber-300">
                                {{ $hasReferenceSource ? 'Reference locked in.' : 'Choose an image source to continue.' }}
                            </p>
                            <p class="text-amber-900/80 dark:text-amber-400/80">
                                {{ $hasReferenceSource ? "We'll analyse the photo to draft a reusable prompt." : 'Upload a file or switch to a catalog product first.' }}
                            </p>
                        @endif
                    </div>
                </div>
                <x-button
                    type="button"
                    wire:click="extractPrompt"
                    wire:loading.attr="disabled"
                    :disabled="! $hasReferenceSource"
                    class="flex items-center gap-2 whitespace-nowrap"
                >
                    <span wire:loading.remove wire:target="extractPrompt,productId,image">
                        Craft prompt
                    </span>
                    <span wire:loading.flex wire:target="extractPrompt" class="flex items-center gap-2">
                        <x-loading-spinner class="size-4" />
                        Processing…
                    </span>
                </x-button>
            </div>

            <div class="space-y-5 border-t border-gray-100 dark:border-zinc-800 pt-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Shape the wording before you generate</h3>
                    <p class="text-sm text-gray-600 dark:text-zinc-400">
                        Prompts extracted from the reference appear below&mdash;edit, combine, or paste your own instructions.
                    </p>
                </div>

                <div
                    @class([
                        'rounded-xl border p-4 text-sm',
                        $hasPromptText ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-900 dark:text-emerald-400' : 'border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-amber-900 dark:text-amber-400',
                    ])
                >
                    <div class="flex items-start gap-3">
                        @if ($hasPromptText)
                            <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div>
                                <p class="font-semibold dark:text-emerald-300">Prompt ready.</p>
                                <p class="text-emerald-900/80 dark:text-emerald-400/80">Preview or refine it below before sending it to the model.</p>
                            </div>
                        @else
                            <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 3.333 3.333 16.667h13.334L10 3.333Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="m10 8.333.008 3.334" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M9.992 13.333h.016" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div>
                                <p class="font-semibold dark:text-amber-300">No prompt yet.</p>
                                <p class="text-amber-900/80 dark:text-amber-400/80">Run craft prompt above or paste your own copy into the workspace.</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($errorMessage)
                    <div class="rounded-md bg-red-50 dark:bg-red-500/10 p-4">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="size-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 5a1 1 0 012 0v5a1 1 0 01-2 0V5zm1 8a1.25 1.25 0 100 2.5A1.25 1.25 0 0010 13z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ms-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-400">
                                    {{ $errorMessage }}
                                </h3>
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <label for="photo-studio-prompt" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Prompt text
                    </label>
                    <textarea
                        id="photo-studio-prompt"
                        wire:model.live.debounce.500ms="promptResult"
                        rows="6"
                        class="mt-2 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        placeholder="Paste or craft a prompt here if you'd like to skip extraction."
                    ></textarea>
                    <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                        This prompt is sent to the image model when you choose Generate image.
                    </p>

                    @if ($generationStatus)
                        <div class="mt-4 rounded-md bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-400" @if ($isAwaitingGeneration) wire:poll.3s="pollGenerationStatus" @endif>
                            <div class="flex items-center gap-2">
                                @if ($isAwaitingGeneration)
                                    <x-loading-spinner class="size-4" />
                                @else
                                    <svg class="size-4 text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                                <span>{{ $generationStatus }}</span>
                            </div>
                        </div>
                    @elseif ($isAwaitingGeneration)
                        <div class="mt-4 rounded-md bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-400" wire:poll.3s="pollGenerationStatus">
                            <div class="flex items-center gap-2">
                                <x-loading-spinner class="size-4" />
                                <span>Image generation in progress…</span>
                            </div>
                        </div>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <x-button
                            type="button"
                            wire:click="generateImage"
                            wire:loading.attr="disabled"
                            :disabled="! $hasPromptText"
                            class="flex items-center gap-2 whitespace-nowrap"
                        >
                            <span wire:loading.remove wire:target="generateImage">
                                Generate image
                            </span>
                            <span wire:loading.flex wire:target="generateImage" class="flex items-center gap-2">
                                <x-loading-spinner class="size-4" />
                                Generating…
                            </span>
                        </x-button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-zinc-300 shadow-sm transition hover:bg-gray-50 dark:hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                            x-data="{ copied: false }"
                            x-on:click="if (@js($hasPromptText)) { navigator.clipboard.writeText(@js($promptResult)).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }"
                            :disabled="! @js($hasPromptText)"
                        >
                            <svg class="me-2 h-4 w-4 text-gray-500 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16.5v2.25A2.25 2.25 0 0010.25 21h7.5A2.25 2.25 0 0020 18.75v-7.5A2.25 2.25 0 0017.75 9h-2.25M8 16.5h-2.25A2.25 2.25 0 013.5 14.25v-7.5A2.25 2.25 0 015.75 4.5h7.5A2.25 2.25 0 0115.5 6.75V9M8 16.5h6.75A2.25 2.25 0 0017 14.25V7.5M8 16.5A2.25 2.25 0 015.75 14.25V7.5" />
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy prompt'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @teleport('body')
    <div
        x-cloak
        x-show="overlayOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 sm:px-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-gray-900/80 dark:bg-black/80" @click="closeOverlay()" aria-hidden="true"></div>

        <div
            class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl"
            @click.stop
        >
            <button
                type="button"
                class="absolute right-4 top-4 z-10 inline-flex size-9 items-center justify-center rounded-full bg-white/90 dark:bg-zinc-800/90 text-gray-700 dark:text-zinc-300 shadow ring-1 ring-black/10 dark:ring-zinc-700 transition hover:bg-white dark:hover:bg-zinc-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                @click="closeOverlay()"
            >
                <span class="sr-only">Close gallery details</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div class="flex min-h-0 flex-1 flex-col">
                {{-- Top Section: Image and Details in 2 columns --}}
                <div class="grid min-h-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="bg-gray-100 dark:bg-zinc-800 min-h-0 flex items-center justify-center">
                        <img
                            :src="selectedEntry ? selectedEntry.url : ''"
                            :alt="selectedEntry ? 'Generated render' : ''"
                            class="max-h-full w-full object-contain bg-gray-900/5"
                        />
                    </div>
                    <div class="overflow-y-auto space-y-5 p-6">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Image details</p>
                            <p class="text-xs text-gray-500 dark:text-zinc-500" x-text="selectedEntry && selectedEntry.created_at ? selectedEntry.created_at : ''"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Product</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white" x-text="selectedEntry && selectedEntry.product_label ? selectedEntry.product_label : 'Generated without a catalog product'"></p>
                            <p
                                class="text-xs text-gray-500 dark:text-zinc-500"
                                x-show="selectedEntry && (selectedEntry.product_brand || selectedEntry.product_sku)"
                            >
                                <template x-if="selectedEntry && selectedEntry.product_brand">
                                    <span x-text="selectedEntry.product_brand"></span>
                                </template>
                                <template x-if="selectedEntry && selectedEntry.product_brand && selectedEntry.product_sku">
                                    <span class="mx-1 text-gray-400 dark:text-zinc-600">&middot;</span>
                                </template>
                                <template x-if="selectedEntry && selectedEntry.product_sku">
                                    <span class="text-gray-400 dark:text-zinc-500" x-text="selectedEntry.product_sku"></span>
                                </template>
                            </p>
                        </div>
                        <div x-show="selectedEntry && selectedEntry.edit_instruction">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Edit Instruction</p>
                            <div class="mt-2 rounded-lg border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-3">
                                <div class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    <p class="text-sm text-amber-900 dark:text-amber-400" x-text="selectedEntry ? selectedEntry.edit_instruction : ''"></p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Original Prompt</p>
                            <p
                                class="mt-2 max-h-48 whitespace-pre-line overflow-y-auto rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-800 dark:text-zinc-200"
                                x-text="selectedEntry && selectedEntry.prompt ? selectedEntry.prompt : 'Prompt unavailable for this render.'"
                            ></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Model</p>
                            <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="selectedEntry && selectedEntry.model ? selectedEntry.model : 'Unknown model'"></p>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-1">
                            <button
                                type="button"
                                class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 shadow-sm transition hover:border-gray-300 dark:hover:border-zinc-600 hover:text-gray-900 dark:hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                                x-on:click.prevent="if (!selectedEntry) { return; } $wire.openEditModal(selectedEntry.id); closeOverlay();"
                                title="Edit this image"
                            >
                                <span class="sr-only">Edit image</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <a
                                :href="selectedEntry ? selectedEntry.url : '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 shadow-sm transition hover:border-gray-300 dark:hover:border-zinc-600 hover:text-gray-900 dark:hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                            >
                                <span class="sr-only">View full size</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M18 10s-3-4-8-4-8 4-8 4 3 4 8 4 8-4 8-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M10 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </a>
                            <a
                                :href="selectedEntry ? selectedEntry.download_url : '#'"
                                download
                                class="inline-flex size-10 items-center justify-center rounded-full bg-gradient-to-r from-amber-400 to-orange-500 text-black shadow-sm transition hover:from-amber-300 hover:to-orange-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                            >
                                <span class="sr-only">Download image</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M10 3v8m0 0 3-3m-3 3-3-3M4.5 13.5v1.25A1.25 1.25 0 0 0 5.75 16h8.5a1.25 1.25 0 0 0 1.25-1.25V13.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </a>
                            <button
                                type="button"
                                class="inline-flex size-10 items-center justify-center rounded-full border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 shadow-sm transition hover:bg-red-50 dark:hover:bg-red-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 disabled:opacity-60"
                                wire:loading.attr="disabled"
                                wire:target="deleteGeneration"
                                x-on:click.prevent="if (!selectedEntry) { return; } if (!confirm('Delete this image from the gallery?')) { return; } $wire.deleteGeneration(selectedEntry.id).then(() => { closeOverlay(); });"
                            >
                                <span class="sr-only">Delete image</span>
                                <span class="flex items-center justify-center" wire:loading.remove wire:target="deleteGeneration">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m7 5 .867-1.3A1 1 0 0 1 8.7 3h2.6a1 1 0 0 1 .833.7L13 5m4 0H3m1 0 .588 11.18A1 1 0 0 0 5.587 17h8.826a1 1 0 0 0 .999-.82L16 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <span class="flex items-center justify-center" wire:loading.flex wire:target="deleteGeneration">
                                    <x-loading-spinner class="size-4" />
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Bottom Section: History Timeline (Horizontal) --}}
                <div x-show="selectedEntry && selectedEntry.has_history" class="border-t border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/50 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="h-4 w-4 text-gray-500 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Version History</h4>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="flex gap-4 pb-2">
                            {{-- Ancestors (Previous Versions) --}}
                            <template x-for="(ancestor, index) in (selectedEntry ? selectedEntry.ancestors : [])" :key="ancestor.id">
                                <button
                                    type="button"
                                    @click="
                                        const gallery = @js($productGallery);
                                        const foundEntry = gallery.find(g => g.id === ancestor.id);
                                        if (foundEntry) {
                                            selectedEntry = foundEntry;
                                        }
                                    "
                                    class="flex-shrink-0 w-48 rounded-lg border border-gray-200 bg-white p-3 text-left transition hover:border-gray-300 hover:shadow-md"
                                >
                                    <img
                                        :src="ancestor.url"
                                        :alt="'Previous version ' + (index + 1)"
                                        class="w-full h-32 rounded object-cover mb-2 opacity-75"
                                    />
                                    <div class="space-y-1">
                                        <p class="text-xs font-medium text-gray-600" x-text="'Version ' + (index + 1)"></p>
                                        <p class="text-xs text-gray-500" x-text="ancestor.created_at_human"></p>
                                        <p class="text-xs text-gray-700 line-clamp-2" x-show="ancestor.edit_instruction" x-text="ancestor.edit_instruction"></p>
                                    </div>
                                </button>
                            </template>

                            {{-- Current Version --}}
                            <div class="flex-shrink-0 w-48 rounded-lg border-2 border-indigo-400 bg-indigo-50 p-3 shadow-md">
                                <div class="relative">
                                    <img
                                        :src="selectedEntry ? selectedEntry.url : ''"
                                        alt="Current version"
                                        class="w-full h-32 rounded object-cover mb-2 ring-2 ring-indigo-300"
                                    />
                                    <div class="absolute -top-1 -right-1 bg-indigo-600 text-white rounded-full p-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-indigo-900" x-text="'Version ' + ((selectedEntry && selectedEntry.ancestors ? selectedEntry.ancestors.length : 0) + 1)"></p>
                                    <p class="text-xs text-indigo-700" x-text="selectedEntry ? selectedEntry.created_at_human : ''"></p>
                                    <p class="text-xs text-indigo-800 line-clamp-2" x-show="selectedEntry && selectedEntry.edit_instruction" x-text="selectedEntry ? selectedEntry.edit_instruction : ''"></p>
                                </div>
                            </div>

                            {{-- Descendants (Future Edits) --}}
                            <template x-for="(descendant, index) in (selectedEntry ? selectedEntry.descendants : [])" :key="descendant.id">
                                <button
                                    type="button"
                                    @click="
                                        const gallery = @js($productGallery);
                                        const foundEntry = gallery.find(g => g.id === descendant.id);
                                        if (foundEntry) {
                                            selectedEntry = foundEntry;
                                        }
                                    "
                                    class="flex-shrink-0 w-48 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-left transition hover:border-emerald-300 hover:shadow-md"
                                >
                                    <img
                                        :src="descendant.url"
                                        :alt="'Future version ' + (index + 1)"
                                        class="w-full h-32 rounded object-cover mb-2"
                                    />
                                    <div class="space-y-1">
                                        <p class="text-xs font-medium text-emerald-800" x-text="'Version ' + ((selectedEntry && selectedEntry.ancestors ? selectedEntry.ancestors.length : 0) + index + 2)"></p>
                                        <p class="text-xs text-emerald-600" x-text="descendant.created_at_human"></p>
                                        <p class="text-xs text-emerald-700 line-clamp-2" x-show="descendant.edit_instruction" x-text="descendant.edit_instruction"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Edit Modal --}}
    @if ($showEditModal)
        @teleport('body')
        <div
            x-data="{
                show: true,
                countdown: 10,
                countdownInterval: null,
                startCountdown() {
                    this.countdown = 10;
                    if (this.countdownInterval) clearInterval(this.countdownInterval);
                    this.countdownInterval = setInterval(() => {
                        if (this.countdown > 0) {
                            this.countdown--;
                        }
                    }, 1000);
                },
                stopCountdown() {
                    if (this.countdownInterval) {
                        clearInterval(this.countdownInterval);
                        this.countdownInterval = null;
                    }
                }
            }"
            x-show="show"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center px-2 py-4"
            role="dialog"
            aria-modal="true"
            @keydown.escape.window="$wire.closeEditModal()"
            x-init="$watch('$wire.editGenerating', value => { if (value) startCountdown(); else stopCountdown(); })"
        >
            <div class="absolute inset-0 bg-gray-900/80 dark:bg-black/80" @click="$wire.closeEditModal()" aria-hidden="true"></div>

        <div
            class="relative z-10 flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-zinc-800 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Image</h3>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 dark:text-zinc-500 transition hover:bg-gray-100 dark:hover:bg-zinc-800 hover:text-gray-600 dark:hover:text-zinc-300"
                    wire:click="closeEditModal"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                @if ($editingGenerationId)
                    @php
                        $editingGeneration = collect($productGallery)->firstWhere('id', $editingGenerationId);

                        // Build history stack - include current generation when generating
                        $historyStack = $editingGeneration['ancestors'] ?? [];
                        if ($editGenerating) {
                            $historyStack[] = [
                                'id' => $editingGeneration['id'],
                                'url' => $editingGeneration['url'],
                                'edit_instruction' => $editingGeneration['edit_instruction'] ?? 'Original',
                            ];
                        }

                        // Show only the 5 most recent history items
                        $historyStack = array_slice($historyStack, -5);
                    @endphp

                    @if ($editingGeneration)
                        {{-- Top: Image Preview Row --}}
                        <div class="mb-6">
                            <div class="flex items-start gap-8">
                                {{-- Main Photo Area (Current/Countdown) --}}
                                <div class="flex-shrink-0">
                                    @if ($editGenerating)
                                        {{-- Countdown Spinner --}}
                                        <div class="w-96 overflow-hidden rounded-2xl border-4 border-amber-400 dark:border-amber-500 bg-white dark:bg-zinc-800 shadow-2xl" wire:poll.2s="pollEditGeneration">
                                            <div class="flex h-96 flex-col items-center justify-center bg-amber-50 dark:bg-amber-500/10 p-6">
                                                <div class="relative">
                                                    <svg class="h-24 w-24 animate-spin text-amber-500" viewBox="0 0 100 100">
                                                        <circle class="stroke-current opacity-25" cx="50" cy="50" r="40" stroke-width="8" fill="none" />
                                                        <circle class="stroke-current" cx="50" cy="50" r="40" stroke-width="8" fill="none" stroke-dasharray="60 200" stroke-linecap="round" />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span x-show="countdown > 0" x-text="countdown" class="text-3xl font-bold text-amber-600 dark:text-amber-400"></span>
                                                        <span x-show="countdown === 0" class="text-lg font-semibold text-amber-600 dark:text-amber-400">...</span>
                                                    </div>
                                                </div>
                                                <p class="mt-6 text-center text-sm font-semibold text-amber-900 dark:text-amber-300">
                                                    <span x-show="countdown > 0">Generating your edit...</span>
                                                    <span x-show="countdown === 0">Finalizing...</span>
                                                </p>
                                                <p class="mt-2 text-center text-xs text-amber-700 dark:text-amber-400">This usually takes 5-15 seconds</p>
                                            </div>
                                            <div class="bg-white dark:bg-zinc-800 px-5 py-4">
                                                <p class="line-clamp-2 text-sm font-semibold text-amber-700 dark:text-amber-400">Generating...</p>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Current Image Being Edited --}}
                                        <div class="w-96 overflow-hidden rounded-2xl border-4 border-white dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-2xl">
                                            <img
                                                src="{{ $editingGeneration['url'] }}"
                                                alt="Current version"
                                                class="h-96 w-full object-contain bg-gray-900/5 dark:bg-zinc-900"
                                            />
                                            <div class="bg-white dark:bg-zinc-800 px-5 py-4">
                                                <p class="line-clamp-2 text-sm font-semibold text-gray-700 dark:text-zinc-300">{{ $editingGeneration['edit_instruction'] ?? 'Original' }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- History Stack (Horizontal) --}}
                                @if (!empty($historyStack))
                                    <div class="flex-1">
                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">History</p>
                                        <div
                                            class="relative"
                                            style="height: 450px;"
                                            x-data="{
                                                hoveredIndex: null,
                                                history: @js($historyStack)
                                            }"
                                        >
                                            <template x-for="(item, index) in history" :key="item.id">
                                                <div
                                                    class="absolute top-0 w-80 cursor-pointer overflow-hidden rounded-2xl border-4 border-white dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-2xl transition-all duration-200"
                                                    :style="`
                                                        left: ${index * 70}px;
                                                        z-index: ${hoveredIndex === index ? 100 : index + 1};
                                                        transform: ${hoveredIndex === index ? 'translateY(-16px) scale(1.05)' : 'translateY(0)'};
                                                    `"
                                                    @mouseenter="hoveredIndex = index"
                                                    @mouseleave="hoveredIndex = null"
                                                >
                                                    <img
                                                        :src="item.url"
                                                        :alt="'Version ' + (index + 1)"
                                                        class="h-80 w-full object-contain bg-gray-50 dark:bg-zinc-900"
                                                    />
                                                    <div class="bg-white dark:bg-zinc-800 px-4 py-3">
                                                        <p class="line-clamp-2 text-xs font-semibold text-gray-700 dark:text-zinc-300" x-text="item.edit_instruction || 'Original'"></p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Bottom: Edit Form (Always Visible) --}}
                        <div class="border-t border-gray-200 dark:border-zinc-800 pt-6">
                            <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-zinc-300">What would you like to change?</p>
                            <div class="space-y-4">
                                <div>
                                    <textarea
                                        wire:model.defer="editInstruction"
                                        rows="6"
                                        {{ ($editSubmitting || $editGenerating) ? 'disabled' : '' }}
                                        class="block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-800 disabled:text-gray-500 dark:disabled:text-zinc-500"
                                        placeholder="Example: Change the background to a sunset scene, add warmer lighting, remove the..."
                                    ></textarea>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                                        Keep in mind that every edit can decrease the quality of the details.
                                    </p>

                                    @error('editInstruction')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($editSubmitting)
                                    <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4">
                                        <div class="flex items-center gap-3">
                                            <x-loading-spinner class="size-5 text-amber-600 dark:text-amber-500" />
                                            <div>
                                                <p class="font-semibold text-amber-900 dark:text-amber-300">Queuing your edit...</p>
                                                <p class="text-sm text-amber-700 dark:text-amber-400">This will take just a moment.</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-zinc-800 pt-4">
                                    <button
                                        type="button"
                                        wire:click="closeEditModal"
                                        {{ ($editSubmitting || $editGenerating) ? 'disabled' : '' }}
                                        class="rounded-full border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-zinc-300 transition hover:bg-gray-50 dark:hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Close
                                    </button>
                                    <x-button
                                        type="button"
                                        wire:click="submitEdit"
                                        :disabled="$editSubmitting || $editGenerating"
                                        class="inline-flex items-center gap-2"
                                    >
                                        @if ($editSubmitting)
                                            <x-loading-spinner class="size-4" />
                                            <span>Queuing...</span>
                                        @elseif ($editGenerating)
                                            <x-loading-spinner class="size-4" />
                                            <span>Generating...</span>
                                        @else
                                            <span>Generate Edit</span>
                                        @endif
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        </div>
        @endteleport
    @endif
</div>
