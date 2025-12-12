@props([
    'productGallery' => [],
    'gallerySearch' => '',
    'galleryTotal' => 0,
    'compositionModes' => [],
    'isAwaitingGeneration' => false,
])

@php
    $galleryTotalCount = $galleryTotal ?? 0;
    $filteredGalleryCount = count($productGallery);
    $galleryHasEntries = $galleryTotalCount > 0;
    $hasFilteredEntries = $filteredGalleryCount > 0;
    $hasGallerySearch = filled($gallerySearch ?? '');
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
                                class="search-icon-custom block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 py-2 pl-3 pr-10 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                            />
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400 dark:text-zinc-500">
                                <svg class="size-[18px]" viewBox="0 0 20 20" fill="none" aria-hidden="true">
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
                                    <div class="group relative aspect-square max-h-72">
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

                                {{-- Push to Store Section --}}
                                @if (! empty($entry['can_push_to_store']))
                                    <div class="mt-3" x-data="{ pushing: false, queued: false }">
                                        <button
                                            type="button"
                                            x-on:click.prevent="
                                                if (pushing) return;
                                                pushing = true;
                                                queued = false;
                                                $wire.pushToStore({{ $entry['id'] }}).then(() => {
                                                    setTimeout(() => {
                                                        pushing = false;
                                                        queued = true;
                                                        setTimeout(() => { queued = false; }, 3000);
                                                    }, 1500);
                                                });
                                            "
                                            :disabled="pushing"
                                            :class="queued ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : ''"
                                            @class([
                                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50',
                                                'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 focus-visible:ring-emerald-500/20' => ! empty($entry['is_pushed_to_store']),
                                                'border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-500/20 focus-visible:ring-amber-500/20' => empty($entry['is_pushed_to_store']),
                                            ])
                                            title="{{ ! empty($entry['is_pushed_to_store']) ? 'Added ' . ($entry['pushed_to_store_at'] ?? '') . ' — click to add again' : 'Add image to store' }}"
                                        >
                                            {{-- Pushing state --}}
                                            <span x-show="pushing" class="flex items-center gap-1.5">
                                                <x-loading-spinner class="size-3.5" />
                                                <span>Adding...</span>
                                            </span>
                                            {{-- Queued success state --}}
                                            <span x-show="queued && !pushing" x-cloak class="flex items-center gap-1.5">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>Queued!</span>
                                            </span>
                                            {{-- Default state --}}
                                            <span x-show="!pushing && !queued" class="flex items-center gap-1.5">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                                <span>{{ ! empty($entry['is_pushed_to_store']) ? 'Add Again' : 'Add to Store' }}</span>
                                            </span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
