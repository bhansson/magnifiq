@props([
    'products' => [],
    'productId' => null,
    'productSearch' => '',
    'productResultsLimit' => 50,
    'selectedProduct' => null,
    'productImagePreview' => null,
    'image' => null,
])

@php
    $productMatchesCount = count($products);
    $hasProductSearch = filled($productSearch ?? '');
    $selectedProductLabel = $selectedProduct
        ? trim(($selectedProduct['title'] ?? 'Untitled product').' '.(($selectedProduct['sku'] ?? null) ? '— '.$selectedProduct['sku'] : ''))
        : '';
@endphp

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
                class="search-icon-custom block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 py-2 pl-3 pr-10 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                role="combobox"
                :aria-expanded="open.toString()"
                aria-controls="photo-studio-product-options"
                autocomplete="off"
            />
            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400 dark:text-zinc-500">
                <svg class="size-[18px]" viewBox="0 0 20 20" fill="none" aria-hidden="true">
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
                                    class="size-[18px] text-emerald-500 {{ $isSelected ? '' : 'hidden' }}"
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
