@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Products</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                    Search your catalog by title, brand, SKU, or GTIN, or narrow by brand below.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:space-x-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none sm:w-72">
                    <input
                        type="search"
                        placeholder="Start typing to search products…"
                        wire:model.live.debounce.400ms="search"
                        class="w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 text-sm pl-10 pr-4 py-2"
                        aria-label="Search products"
                    />
                    <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400 dark:text-zinc-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 013.978 9.25l3.636 3.636a.75.75 0 11-1.06 1.06l-3.636-3.636A5.5 5.5 0 119 3.5zm0 1.5a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd" />
                    </svg>
                </div>

                <div class="flex-1 sm:flex-none sm:w-40">
                    <label for="language-filter" class="sr-only">Filter by language</label>
                    <select
                        id="language-filter"
                        wire:model.live="language"
                        class="w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 text-sm"
                        aria-label="Filter by language"
                    >
                        <option value="">All languages</option>
                        @foreach ($languages as $languageOption)
                            @php
                                $languageLabel = $languageLabels[$languageOption] ?? Str::upper($languageOption);
                            @endphp
                            <option value="{{ $languageOption }}">{{ $languageLabel }} ({{ Str::upper($languageOption) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 sm:flex-none sm:w-44">
                    <label for="catalog-filter" class="sr-only">Filter by catalog</label>
                    <select
                        id="catalog-filter"
                        wire:model.live="catalogId"
                        class="w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 text-sm"
                        aria-label="Filter by catalog"
                    >
                        <option value="">All catalogs</option>
                        @foreach ($catalogs as $catalogOption)
                            <option value="{{ $catalogOption->id }}">{{ $catalogOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 sm:flex-none sm:w-56">
                    <label for="brand-filter" class="sr-only">Filter by brand</label>
                    <select
                        id="brand-filter"
                        wire:model.live="brand"
                        class="w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 text-sm"
                        aria-label="Filter by brand"
                    >
                        <option value="">All brands</option>
                        @foreach ($brands as $brandOption)
                            <option value="{{ $brandOption }}">{{ $brandOption }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="relative h-5 mb-4 text-xs text-gray-500 dark:text-zinc-500">
            <div wire:loading.class="opacity-100" wire:loading.class.remove="opacity-0" wire:target="search,page,brand,language,catalogId" class="absolute inset-0 opacity-0 transition-opacity duration-150">
                Searching…
            </div>
            <div wire:loading.class="opacity-0" wire:loading.class.remove="opacity-100" wire:target="search,page,brand,language,catalogId" class="absolute inset-0 opacity-100 transition-opacity duration-150">
                Showing {{ $products->total() }} {{ Str::plural('result', $products->total()) }}
            </div>
        </div>

        @if ($bulkStatusMessage)
            <div class="mb-4 rounded-xl border border-green-200 dark:border-green-500/20 bg-green-50 dark:bg-green-500/10 px-4 py-3 text-sm text-green-700 dark:text-green-400">
                {{ $bulkStatusMessage }}
            </div>
        @endif

        @if ($bulkErrorMessage)
            <div class="mb-4 rounded-xl border border-rose-200 dark:border-red-500/20 bg-rose-50 dark:bg-red-500/10 px-4 py-3 text-sm text-rose-700 dark:text-red-400">
                {{ $bulkErrorMessage }}
            </div>
        @endif

        @php
            $selectedCount = count($selectedProducts);
            $bulkButtonDisabled = $templates->isEmpty() || $selectedCount === 0 || ! $selectedTemplateId;
        @endphp

        <div class="mb-6 rounded-xl border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 p-4 shadow-sm dark:shadow-none">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600 dark:text-zinc-400">
                    <p>Select products with the checkboxes to queue AI generation in bulk.</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">
                        {{ $selectedCount }} {{ Str::plural('product', $selectedCount) }} selected
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <div class="w-full sm:w-64">
                        <label for="bulk-template" class="sr-only">Choose template</label>
                        <select
                            id="bulk-template"
                            wire:model.live="selectedTemplateId"
                            class="w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 text-sm"
                            @disabled($templates->isEmpty())
                        >
                            <option value="">Select template…</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        type="button"
                        wire:click="bulkGenerate"
                        wire:loading.attr="disabled"
                        wire:target="bulkGenerate"
                        @disabled($bulkButtonDisabled)
                        class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-5 py-2.5 text-sm font-semibold text-black shadow-lg shadow-amber-500/25 hover:from-amber-300 hover:to-orange-400 hover:shadow-amber-500/40 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:cursor-not-allowed disabled:opacity-50 transition-all duration-200"
                    >
                        <span wire:loading.remove wire:target="bulkGenerate">Queue AI Generation</span>
                        <span wire:loading.inline wire:target="bulkGenerate">Queueing…</span>
                    </button>
                </div>
            </div>
            @if ($templates->isEmpty())
                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                    No active AI templates are available yet. Create one to enable bulk generation.
                </p>
            @endif
        </div>

        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
            <div class="divide-y divide-gray-200 dark:divide-zinc-800">
                <div class="hidden px-4 py-3 bg-gray-50 dark:bg-zinc-800/50 text-xs font-semibold uppercase text-gray-600 dark:text-zinc-400 sm:grid sm:grid-cols-12 rounded-t-xl">
                    <div class="col-span-1 flex items-center">
                        <input
                            type="checkbox"
                            wire:model.live="bulkSelectAll"
                            class="size-4 rounded border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-amber-500 focus:ring-amber-500"
                            aria-label="Select all visible products"
                        />
                    </div>
                    <div class="col-span-7">Title</div>
                    <div class="col-span-2">Last AI Generation</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>

                @forelse ($products as $product)
                    @php
                        $latestGenerationRecord = $product->latestAiGeneration;
                        $latestGenerationLabel = $latestGenerationRecord?->template?->name ?? 'AI Generation';
                        $latestGenerationTimestamp = $latestGenerationRecord?->updated_at ?? $latestGenerationRecord?->created_at;
                    @endphp
                    <div wire:key="product-{{ $product->id }}" class="flex flex-col gap-4 px-4 py-5 transition-colors sm:grid sm:grid-cols-12 sm:items-center hover:bg-gray-50 dark:hover:bg-zinc-800/50">
                        <div class="flex items-center sm:col-span-1 sm:justify-center">
                            <input
                                type="checkbox"
                                value="{{ $product->id }}"
                                wire:model.live="selectedProducts"
                                class="size-4 rounded border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-amber-500 focus:ring-amber-500"
                                aria-label="Select {{ $product->title ?: 'Untitled product' }}"
                            />
                        </div>
                        <div class="sm:col-span-7">
                            <div class="flex items-start gap-4">
                                <x-product-image-preview
                                    :src="$product->image_link"
                                    :alt="$product->title ? 'Preview of '.$product->title : 'Product image preview'"
                                    size="w-16 h-16"
                                />
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $product->title ?: 'Untitled product' }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-zinc-500">
                                        <span>Brand: {{ $product->brand ?: '—' }}</span>
                                        <span>SKU: {{ $product->sku ?: '—' }}</span>
                                        <span>GTIN: {{ $product->gtin ?: '—' }}</span>
                                        <span>Updated {{ $product->updated_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                        @php
                                            $productLanguageCode = $product->feed?->language;
                                            $isInCatalog = $product->feed?->product_catalog_id !== null;
                                            $catalogName = $product->feed?->catalog?->name;

                                            // Collect all languages (current + siblings) and sort alphabetically
                                            $allLanguages = collect([$productLanguageCode])
                                                ->when($isInCatalog, fn ($col) => $col->merge(
                                                    $product->siblingProducts()->pluck('feed.language')
                                                ))
                                                ->filter()
                                                ->unique()
                                                ->sort()
                                                ->values();
                                        @endphp
                                        {{-- Language badges in alphabetical order --}}
                                        @foreach ($allLanguages as $lang)
                                            @if ($lang === $productLanguageCode)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400 font-medium">
                                                    {{ Str::upper($lang) }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400 font-medium" title="Also available in {{ $languageLabels[$lang] ?? Str::upper($lang) }}">
                                                    {{ Str::upper($lang) }}
                                                </span>
                                            @endif
                                        @endforeach
                                        {{-- Catalog badge --}}
                                        @if ($catalogName)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-400 font-medium" title="In catalog: {{ $catalogName }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                                </svg>
                                                {{ $catalogName }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($product->feed?->name)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                                            Feed: {{ $product->feed->name }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-2 text-sm text-gray-700 dark:text-zinc-300">
                            @if ($latestGenerationRecord && $latestGenerationTimestamp)
                                <p aria-live="polite">
                                    {{ $latestGenerationLabel }} generated {{ $latestGenerationTimestamp->diffForHumans() }}
                                </p>
                            @else
                                <p class="text-gray-500 dark:text-zinc-500">Never generated</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-2 sm:items-end">
                            <a href="{{ $product->getUrl() }}" class="text-sm font-medium text-amber-600 dark:text-amber-400 hover:text-amber-500 dark:hover:text-amber-300">
                                View details
                                <span class="sr-only">for {{ $product->title ?: 'Untitled product' }}</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-sm text-gray-600 dark:text-zinc-400">
                        @if (trim($search) !== '' || $brand !== '' || $language !== '' || $catalogId !== null)
                            No products match your current filters.
                        @else
                            No products imported yet.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>
