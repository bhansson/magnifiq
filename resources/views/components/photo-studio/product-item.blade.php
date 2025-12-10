@props([
    'product' => [],
    'showBrand' => true,
])

<div class="relative shrink-0">
    @if ($product['image_link'] ?? null)
        <img src="{{ $product['image_link'] }}" alt="" class="size-10 rounded object-cover" />
    @else
        <div class="size-10 rounded bg-gray-100 dark:bg-zinc-700 flex items-center justify-center">
            <svg class="size-5 text-gray-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
        </div>
    @endif
    {{-- Multi-image indicator badge --}}
    @if (! empty($product['additional_image_link']))
        <span class="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-amber-500 text-[8px] font-bold text-white ring-2 ring-white dark:ring-zinc-800">
            2
        </span>
    @endif
</div>
<div class="flex-1 min-w-0">
    <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $product['title'] ?? 'Untitled' }}</p>
    <p class="truncate text-xs text-gray-500 dark:text-zinc-500">
        SKU: {{ $product['sku'] ?: '—' }}
        @if ($showBrand && ! empty($product['brand']))
            &middot; Brand: {{ $product['brand'] }}
        @endif
    </p>
</div>
