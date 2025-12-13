@props([
    'src' => null,
    'alt' => 'Product image preview',
    'size' => 'w-16 h-16',
])

@if ($src)
    <div
        data-image-wrapper
        wire:ignore
        {{ $attributes->class([
            'relative flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800',
            $size,
        ]) }}
    >
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            title="{{ $alt }}"
            loading="lazy"
            decoding="async"
            referrerpolicy="no-referrer"
            class="h-full w-full object-cover opacity-0 transition-opacity duration-300"
            x-data
            x-init="
                const showImage = () => $el.classList.remove('opacity-0');
                if ($el.complete) {
                    showImage();
                } else {
                    $el.addEventListener('load', showImage);
                }
            "
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
        />
        {{-- Fallback placeholder shown on image load error --}}
        <div class="absolute inset-0 hidden items-center justify-center bg-gray-100 dark:bg-zinc-800">
            <svg class="w-6 h-6 text-gray-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>
@else
    {{-- Placeholder when no image URL provided --}}
    <div
        {{ $attributes->class([
            'flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-zinc-700 bg-gray-100 dark:bg-zinc-800 flex items-center justify-center',
            $size,
        ]) }}
    >
        <svg class="w-6 h-6 text-gray-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
    </div>
@endif
