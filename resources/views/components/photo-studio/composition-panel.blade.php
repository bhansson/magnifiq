@props([
    'compositionMode' => 'products_together',
    'compositionModes' => [],
    'compositionImages' => [],
    'compositionHeroIndex' => 0,
    'products' => [],
    'productSearch' => '',
])

@php
    $maxCompositionImages = config('photo-studio.composition.max_images', 14);
    $compositionImageCount = count($compositionImages ?? []);
@endphp

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
                            @elseif ($modeConfig['icon'] === 'users')
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                            @elseif ($modeConfig['icon'] === 'viewfinder-circle')
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
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
                            <img src="{{ asset($modeConfig['example_image']) }}" alt="{{ $modeConfig['label'] }} example" class="w-full h-auto object-contain" />
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
</div>
