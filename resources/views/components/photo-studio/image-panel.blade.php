@props([
    'compositionMode' => 'scene_composition',
    'compositionImages' => [],
    'compositionHeroIndex' => 0,
    'products' => [],
    'productSearch' => '',
    'maxImages' => 14,
    'minImages' => 1,
])

@php
    $compositionImageCount = count($compositionImages ?? []);
    $canAddMore = $compositionImageCount < $maxImages;
    $isSingleImageMode = $maxImages === 1;
@endphp

<div class="space-y-6">
    {{-- Selected Images Grid --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                Input images
            </p>
            <div class="flex items-center gap-3">
                @if (!$isSingleImageMode)
                    <span class="text-xs text-gray-500 dark:text-zinc-500">{{ $compositionImageCount }}/{{ $maxImages }}</span>
                @endif
                @if ($compositionImageCount > 0)
                    <button
                        type="button"
                        wire:click="clearComposition"
                        class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                    >
                        {{ $isSingleImageMode ? 'Remove' : 'Clear all' }}
                    </button>
                @endif
            </div>
        </div>

        @if ($compositionImageCount === 0)
            <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-8 text-center">
                <svg class="mx-auto size-12 text-gray-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                <p class="mt-3 text-sm text-gray-500 dark:text-zinc-400">
                    @if ($minImages === 1)
                        Add an image to get started
                    @else
                        Add at least {{ $minImages }} images for this mode
                    @endif
                </p>
                <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Upload an image or select from your catalog below</p>
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

            @if ($compositionMode === 'reference_hero' && $compositionImageCount > 1)
                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                    <svg class="inline size-3 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                    Click the star to set the hero product. Others will be used as style reference.
                </p>
            @endif
        @endif
    </div>

    {{-- Add Images Panel (only show if can add more) --}}
    @if ($canAddMore)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            {{-- Upload Column (Dropzone) --}}
            <div
                class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4"
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
            >
                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Upload image</p>
                <div
                    class="relative cursor-pointer rounded-xl border-2 border-dashed transition-colors"
                    :class="isDragging ? 'border-amber-400 bg-amber-50 dark:bg-amber-500/10' : 'border-gray-300 dark:border-zinc-600 hover:border-amber-400 dark:hover:border-amber-500'"
                    @click="$refs.fileInput.click()"
                >
                    <input
                        type="file"
                        wire:model="compositionUploads"
                        accept="image/*"
                        {{ $isSingleImageMode ? '' : 'multiple' }}
                        class="sr-only"
                        x-ref="fileInput"
                    />
                    <div class="flex flex-col items-center justify-center py-6 px-4" wire:loading.remove wire:target="compositionUploads">
                        <svg
                            class="size-10 transition-colors"
                            :class="isDragging ? 'text-amber-500' : 'text-gray-400 dark:text-zinc-500'"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-zinc-300" x-text="isDragging ? 'Drop to upload' : 'Drop files here'"></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">or <span class="text-amber-600 dark:text-amber-400 font-medium">browse</span></p>
                    </div>
                    <div wire:loading.flex wire:target="compositionUploads" class="flex flex-col items-center justify-center py-6 px-4">
                        <x-loading-spinner class="size-8 text-amber-500" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">Processing upload{{ $isSingleImageMode ? '' : 's' }}...</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500 text-center">
                    JPG, PNG, WEBP, or AVIF up to 8MB{{ $isSingleImageMode ? '' : '. Select multiple files.' }}
                </p>
                @error('compositionUploads.*')
                    <p class="mt-2 text-xs text-red-600 dark:text-red-400 text-center">{{ $message }}</p>
                @enderror
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
                            {{ $alreadyAdded ? 'disabled' : '' }}
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left text-sm transition {{ $alreadyAdded ? 'bg-emerald-50 dark:bg-emerald-500/10 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-zinc-700' }} disabled:opacity-50"
                        >
                            <x-photo-studio.product-item :product="$product" />
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
    @endif
</div>
