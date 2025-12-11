@props([
    'primarySrc' => null,
    'secondarySrc' => null,
    'alt' => 'Product image preview',
    'size' => 'w-16 h-16',
    'initialIndex' => 0,
])

@php
    $hasMultipleImages = $primarySrc && $secondarySrc;
    $images = array_values(array_filter([$primarySrc, $secondarySrc]));
    $imageCount = count($images);
@endphp

@if ($imageCount > 0)
    <div
        x-data="{
            currentIndex: {{ $initialIndex }},
            images: @js($images),
            get currentSrc() { return this.images[this.currentIndex] || this.images[0]; },
            toggle() {
                if (this.images.length > 1) {
                    this.currentIndex = this.currentIndex === 0 ? 1 : 0;
                }
            }
        }"
        {{ $attributes->class(['relative inline-block']) }}
    >
        {{-- Image container --}}
        <div
            data-image-wrapper
            wire:ignore
            @class([
                'relative flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800',
                $size,
            ])
        >
            <img
                x-bind:src="currentSrc"
                alt="{{ $alt }}"
                x-bind:title="'{{ addslashes($alt) }}' + (images.length > 1 ? ' (Image ' + (currentIndex + 1) + ' of ' + images.length + ')' : '')"
                loading="lazy"
                decoding="async"
                referrerpolicy="no-referrer"
                class="h-full w-full object-cover transition-opacity duration-200"
                x-init="
                    const showImage = () => $el.classList.remove('opacity-0');
                    $el.classList.add('opacity-0');
                    if ($el.complete) { showImage(); }
                    else { $el.addEventListener('load', showImage, { once: true }); }
                "
                x-effect="
                    $el.classList.add('opacity-0');
                    $el.onload = () => $el.classList.remove('opacity-0');
                "
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            />
            {{-- Fallback placeholder shown on image load error --}}
            <div class="absolute inset-0 hidden items-center justify-center bg-gray-100 dark:bg-zinc-800">
                <svg class="w-8 h-8 text-gray-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>

        {{-- Multi-image indicator badge --}}
        @if ($hasMultipleImages)
            <button
                type="button"
                @click.stop.prevent="toggle()"
                class="absolute top-1 left-1/2 -translate-x-1/2 flex items-center gap-0.5 rounded-full bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm transition hover:bg-black/80 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                x-bind:title="'Click to view image ' + (currentIndex === 0 ? '2' : '1') + ' of ' + images.length"
            >
                {{-- Stack/layers icon --}}
                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                <span x-text="(currentIndex + 1) + '/' + images.length"></span>
            </button>
        @endif
    </div>
@else
    {{-- Placeholder when no image URL provided --}}
    <div
        {{ $attributes->class(['relative inline-block']) }}
    >
        <div
            @class([
                'flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-zinc-700 bg-gray-100 dark:bg-zinc-800 flex items-center justify-center',
                $size,
            ])
        >
            <svg class="w-8 h-8 text-gray-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>
@endif
