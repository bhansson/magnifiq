@php
    use Illuminate\Support\Str;

    $templateItems = collect($templatePayload ?? []);

    $latestGenerationUpdatedAt = $templateItems
        ->map(fn ($item) => $item['latest']?->updated_at)
        ->filter()
        ->reduce(function ($carry, $timestamp) {
            if (! $carry) {
                return $timestamp;
            }

            return $timestamp->gt($carry) ? $timestamp : $carry;
        });

    $latestGenerationUpdatedText = $latestGenerationUpdatedAt
        ? $latestGenerationUpdatedAt->diffForHumans()
        : null;
@endphp

<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
            <div class="px-6 py-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex-1 space-y-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $product->title ?: 'Untitled product' }}
                            </h1>
                            <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500 dark:text-zinc-500">
                                <span>Brand: {{ $product->brand ?: '—' }}</span>
                                <span>SKU: {{ $product->sku ?: '—' }}</span>
                                <span>GTIN: {{ $product->gtin ?: '—' }}</span>
                                <span>Updated {{ $product->updated_at->diffForHumans() }}</span>
                                <span>Created {{ $product->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($languageVersions->count() > 1)
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium text-gray-500 dark:text-zinc-500 uppercase tracking-wide">Language:</span>
                                    @foreach ($languageVersions as $version)
                                        @php
                                            $versionLabel = $languageLabels[$version['language']] ?? Str::upper($version['language']);
                                        @endphp
                                        @if ($version['is_current'])
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-500/30" title="Currently viewing {{ $versionLabel }}">
                                                {{ Str::upper($version['language']) }}
                                                <span class="ml-1.5 text-amber-600 dark:text-amber-500">•</span>
                                            </span>
                                        @else
                                            <a href="{{ $version['url'] }}"
                                               class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400 hover:bg-gray-200 dark:hover:bg-zinc-600 hover:text-gray-900 dark:hover:text-zinc-200 transition-colors"
                                               title="View {{ $versionLabel }} version"
                                            >
                                                {{ Str::upper($version['language']) }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm text-gray-600 dark:text-zinc-400 space-y-1">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-zinc-300">Brand:</span>
                                    <span>{{ $product->brand ?: '—' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-zinc-300">Feed:</span>
                                    <span>{{ $product->feed?->name ?: '—' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-zinc-300">Language:</span>
                                    @php
                                        $feedLanguageCode = $product->feed?->language;
                                        $feedLanguageLabel = $feedLanguageCode ? ($languageLabels[$feedLanguageCode] ?? Str::upper($feedLanguageCode)) : null;
                                    @endphp
                                    <span>{{ $feedLanguageLabel ? $feedLanguageLabel.' ('.Str::upper($feedLanguageCode).')' : '—' }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                @if ($product->url)
                                    <a href="{{ $product->url }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 border border-amber-200 dark:border-amber-500/30 rounded-full text-sm font-medium text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 hover:border-amber-300 dark:hover:border-amber-500/50">
                                        Visit product page
                                        <svg class="ml-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M17.25 6.75L6.75 17.25M17.25 6.75H9.75M17.25 6.75v7.5"/>
                                        </svg>
                                    </a>
                                @endif
                                @if ($product->feed?->feed_url)
                                    <a href="{{ $product->feed->feed_url }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 border border-gray-200 dark:border-zinc-700 rounded-full text-sm font-medium text-gray-600 dark:text-zinc-400 hover:text-gray-800 dark:hover:text-zinc-200 hover:border-gray-300 dark:hover:border-zinc-600">
                                        Open feed source
                                        <svg class="ml-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M17.25 6.75L6.75 17.25M17.25 6.75H9.75M17.25 6.75v7.5"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($product->image_link)
                        <div class="lg:w-56 flex justify-start lg:justify-end">
                            <x-product-image-switcher
                                :primary-src="$product->image_link"
                                :secondary-src="$product->additional_image_link"
                                :alt="$product->title ? 'Preview of '.$product->title : 'Product image preview'"
                                size="w-40 h-40 sm:w-48 sm:h-48"
                                class="mx-auto lg:mx-0"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Product Information</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                            Key attributes pulled from your product feed.
                        </p>
                    </div>
                    <div class="px-6 py-6 space-y-6 text-sm text-gray-700 dark:text-zinc-300">
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-500">SKU</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-500">Brand</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $product->brand ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-500">GTIN</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $product->gtin ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-500">Product URL</dt>
                                <dd class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                                    @if ($product->url)
                                        <a href="{{ $product->url }}" target="_blank" rel="noopener noreferrer"
                                           class="hover:text-amber-800 dark:hover:text-amber-300 break-all">{{ $product->url }}</a>
                                    @else
                                        <span class="text-gray-900 dark:text-white">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Original description</h3>
                                @if ($product->description)
                                    <x-copy-button :text="$product->description" />
                                @endif
                            </div>
                            <div class="mt-2 text-gray-700 dark:text-zinc-300 prose prose-sm dark:prose-invert max-w-none [&>p]:my-2 [&>ul]:my-2 [&>ol]:my-2">
                                    {!! $product->sanitized_description ?: '<p class="text-gray-500 dark:text-zinc-500">No description provided.</p>' !!}
                                </div>
                        </div>
                    </div>
                </div>

                @if ($templateItems->isNotEmpty())
                    <div class="bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
                        <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">AI Generated Content</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                                Latest outputs created for this product.
                                @if ($latestGenerationUpdatedText)
                                    Last updated {{ $latestGenerationUpdatedText }}.
                                @endif
                            </p>
                        </div>
                        <div class="px-6 py-6 text-sm text-gray-700 dark:text-zinc-300"
                             x-data="{
                                activeKey: '{{ $templateItems->first()['key'] ?? '' }}',
                                selectKey(event) {
                                    this.activeKey = event.target.value;
                                }
                             }">
                            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <label for="template-selection" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Selected template</label>
                                    <select id="template-selection"
                                            x-model="activeKey"
                                            class="mt-1 w-64 rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 text-sm shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20">
                                        @foreach ($templateItems as $item)
                                            @php
                                                $template = $item['template'];
                                                $key = $item['key'];
                                            @endphp
                                            <option value="{{ $key }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @foreach ($templateItems as $item)
                                @php
                                    $template = $item['template'];
                                    $key = $item['key'];
                                    $latest = $item['latest'];
                                    $historyItems = $item['history'];
                                    $isUnpublished = $item['is_unpublished'] ?? false;
                                    $hasStoreConnection = $item['has_store_connection'] ?? false;
                                    $status = $generationStatus[$key] ?? null;
                                    $error = $generationError[$key] ?? null;
                                    $isLoading = $generationLoading[$key] ?? false;
                                    $contentType = $template->contentType();
                                    $latestContent = $generationContent[$key] ?? $latest?->content;
                                    $latestTimestamp = $latest?->updated_at ?? $latest?->created_at;
                                    $latestModel = data_get($latest?->meta, 'model');
                                @endphp
                                <section x-show="activeKey === '{{ $key }}'" x-cloak class="space-y-3">
                                        <div class="space-y-2">
                                            @php
                                                $templateDescription = trim((string) $template->description);
                                            @endphp
                                            <p class="text-sm text-gray-700 dark:text-zinc-300">
                                                {{ $templateDescription !== '' ? $templateDescription : 'No description available for this template.' }}
                                            </p>
                                            <div class="space-y-2">
                                                <x-button type="button"
                                                          wire:click="queueGeneration({{ $template->id }})"
                                                          wire:loading.attr="disabled"
                                                          wire:target="queueGeneration">
                                                    <span wire:loading.remove
                                                          wire:target="queueGeneration">Generate</span>
                                                    <span wire:loading wire:target="queueGeneration">Processing…</span>
                                                </x-button>

                                                @if ($isLoading)
                                                    <p class="text-xs text-gray-500 dark:text-zinc-500">Processing request…</p>
                                                @endif

                                                @if ($error)
                                                    <p class="text-sm text-red-600 dark:text-red-400" aria-live="polite">{{ $error }}</p>
                                                @endif

                                                @if ($status)
                                                    <p class="text-sm text-amber-600 dark:text-amber-400"
                                                       aria-live="polite">{{ $status }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @php
                                            // Prepare copyable text based on content type
                                            $copyableText = '';
                                            if ($contentType === 'usps') {
                                                $uspItems = collect(is_array($latestContent) ? $latestContent : [])
                                                    ->map(function ($value) {
                                                        if (is_array($value)) {
                                                            $value = implode(' ', $value);
                                                        }
                                                        return trim((string) $value);
                                                    })
                                                    ->filter()
                                                    ->values();
                                                $copyableText = $uspItems->map(fn ($usp) => '• ' . $usp)->implode("\n");
                                                $hasContent = $uspItems->isNotEmpty();
                                            } elseif ($contentType === 'faq') {
                                                $faqEntries = collect(is_array($latestContent) ? $latestContent : [])
                                                    ->map(function ($entry) {
                                                        if (is_array($entry)) {
                                                            $question = trim((string) ($entry['question'] ?? ''));
                                                            $answer = trim((string) ($entry['answer'] ?? ''));
                                                        } else {
                                                            $question = '';
                                                            $answer = trim((string) $entry);
                                                        }
                                                        if ($question === '' && $answer === '') {
                                                            return null;
                                                        }
                                                        return [
                                                            'question' => $question !== '' ? $question : 'Question',
                                                            'answer' => $answer !== '' ? $answer : 'Answer forthcoming.',
                                                        ];
                                                    })
                                                    ->filter()
                                                    ->values();
                                                $copyableText = $faqEntries->map(fn ($faq) => "Q: {$faq['question']}\nA: {$faq['answer']}")->implode("\n\n");
                                                $hasContent = $faqEntries->isNotEmpty();
                                            } else {
                                                $textContent = trim(is_string($latestContent) ? $latestContent : '');
                                                $copyableText = $textContent;
                                                $hasContent = $textContent !== '';
                                            }
                                        @endphp

                                        <div class="space-y-3">
                                            @if ($hasContent)
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                                        @if ($isUnpublished)
                                                            <span class="text-gray-400 dark:text-zinc-600 line-through">Published</span>
                                                        @else
                                                            Published
                                                        @endif
                                                    </h4>
                                                    @if ($isUnpublished)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400">
                                                            Hidden in store
                                                        </span>
                                                    @endif
                                                    @if ($copyableText)
                                                        <x-copy-button :text="$copyableText" />
                                                    @endif
                                                </div>
                                                @if ($latest && $hasStoreConnection)
                                                    <button
                                                        type="button"
                                                        wire:click="togglePublishState({{ $latest->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="togglePublishState"
                                                        class="inline-flex items-center gap-1.5 border rounded-full px-3 py-1 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-amber-500/20 disabled:opacity-50
                                                            {{ $isUnpublished
                                                                ? 'border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:border-emerald-300 dark:hover:border-emerald-500/50 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'
                                                                : 'border-gray-200 dark:border-zinc-700 text-gray-500 dark:text-zinc-400 hover:border-gray-300 dark:hover:border-zinc-600 hover:bg-gray-50 dark:hover:bg-zinc-800'
                                                            }}"
                                                        title="{{ $isUnpublished ? 'Republish to store' : 'Hide from store' }}"
                                                    >
                                                        @if ($isUnpublished)
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                            <span wire:loading.remove wire:target="togglePublishState">Republish</span>
                                                        @else
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                            </svg>
                                                            <span wire:loading.remove wire:target="togglePublishState">Unpublish</span>
                                                        @endif
                                                        <span wire:loading wire:target="togglePublishState">...</span>
                                                    </button>
                                                @endif
                                            </div>
                                            @endif
                                            @switch($contentType)
                                                @case('usps')
                                                    @if ($uspItems->isNotEmpty())
                                                        <ul class="grid gap-2">
                                                            @foreach ($uspItems as $usp)
                                                                <li class="flex items-start gap-2">
                                                                    <span class="mt-1 size-1.5 rounded-full bg-amber-500"></span>
                                                                    <span class="text-gray-700 dark:text-zinc-300">{{ $usp }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <p class="text-gray-500 dark:text-zinc-500">No unique selling points generated yet. Use the button above to queue AI jobs.</p>
                                                    @endif
                                                    @break

                                                @case('faq')
                                                    @if ($faqEntries->isNotEmpty())
                                                        <div class="space-y-4 text-gray-700 dark:text-zinc-300">
                                                            @foreach ($faqEntries as $faq)
                                                                <div class="space-y-1">
                                                                    <p class="font-medium text-gray-900 dark:text-white">Q: {{ $faq['question'] }}</p>
                                                                    <p>A: {{ $faq['answer'] }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-gray-500 dark:text-zinc-500">No FAQ entries generated yet. Use the button above to queue AI jobs.</p>
                                                    @endif
                                                    @break

                                                @default
                                                    @if ($textContent !== '')
                                                        <p class="text-gray-700 dark:text-zinc-300">{{ $textContent }}</p>
                                                    @else
                                                        <p class="text-gray-500 dark:text-zinc-500">No content generated yet. Use the button above
                                                            to queue AI jobs.</p>
                                                    @endif
                                            @endswitch
                                        </div>

                                        @if ($hasContent)
                                        <p class="text-xs text-gray-500 dark:text-zinc-500">
                                            @if ($latestTimestamp)
                                                Generated {{ $latestTimestamp->diffForHumans() }}
                                            @else
                                                Generated date unavailable
                                            @endif
                                            @if (! empty($latestModel))
                                                ({{ Str::upper($latestModel) }})
                                            @endif
                                        </p>
                                        @endif

                                        @if ($historyItems->isNotEmpty())
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">History</h4>
                                                <div class="space-y-3">
                                                    @foreach ($historyItems as $history)
                                                        @php
                                                            $historyContent = $history->content ?? '';

                                                            if (is_array($historyContent)) {
                                                                if ($contentType === 'usps') {
                                                                    $historyContent = collect($historyContent)
                                                                        ->map(function ($value) {
                                                                            if (is_array($value)) {
                                                                                $value = implode(' ', $value);
                                                                            }

                                                                            return '• '.trim((string) $value);
                                                                        })
                                                                        ->filter()
                                                                        ->implode("\n");
                                                                } elseif ($contentType === 'faq') {
                                                                    $historyContent = collect($historyContent)
                                                                        ->map(function ($entry) {
                                                                            if (is_array($entry)) {
                                                                                $question = trim((string) ($entry['question'] ?? 'Question'));
                                                                                $answer = trim((string) ($entry['answer'] ?? 'Answer forthcoming.'));
                                                                            } else {
                                                                                $question = 'Question';
                                                                                $answer = trim((string) $entry) ?: 'Answer forthcoming.';
                                                                            }

                                                                            return "Q: {$question}\nA: {$answer}";
                                                                        })
                                                                        ->filter()
                                                                        ->implode("\n\n");
                                                                } else {
                                                                    $historyContent = json_encode($historyContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                                                }
                                                            }

                                                            $historyContent = trim((string) $historyContent);
                                                            $truncatedContent = Str::limit($historyContent, 100, '');
                                                            $hasMoreContent = Str::length($historyContent) > Str::length($truncatedContent);
                                                            $historyModel = data_get($history->meta, 'model');
                                                            $historyTimestamp = $history->updated_at ?? $history->created_at;
                                                        @endphp
                                                        <article class="rounded-xl border border-gray-200 dark:border-zinc-700 p-4 space-y-3"
                                                                 x-data="{ isExpanded: false }">
                                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                                <span class="text-xs text-gray-500 dark:text-zinc-500">
                                                                    {{ $historyTimestamp ? $historyTimestamp->format('M j, Y H:i') : 'Unknown' }}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    wire:click="promoteGeneration({{ $history->id }})"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="promoteGeneration"
                                                                    class="inline-flex items-center gap-1 border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 rounded-xl px-3 py-1 text-xs font-medium text-gray-500 dark:text-zinc-400 transition hover:border-gray-300 dark:hover:border-zinc-600 hover:text-gray-700 dark:hover:text-zinc-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-amber-500/20 disabled:opacity-50"
                                                                >
                                                                    <span wire:loading.remove wire:target="promoteGeneration">Publish</span>
                                                                    <span wire:loading wire:target="promoteGeneration">Publishing…</span>
                                                                </button>
                                                            </div>
                                                            <div class="space-y-2">
                                                                <p class="text-xs text-gray-500 dark:text-zinc-500">
                                                                    @if ($historyTimestamp)
                                                                        Generated {{ $historyTimestamp->diffForHumans() }}
                                                                    @else
                                                                        Generated date unavailable
                                                                    @endif
                                                                    @if ($historyModel)
                                                                        ({{ Str::upper($historyModel) }})
                                                                    @endif
                                                                </p>
                                                                <p class="text-gray-700 dark:text-zinc-300" x-show="!isExpanded">
                                                                    {{ $hasMoreContent ? Str::of($truncatedContent)->trim()->append('…') : $historyContent }}
                                                                </p>
                                                                @if ($hasMoreContent)
                                                                    <template x-if="isExpanded">
                                                                        <p class="text-gray-700 dark:text-zinc-300">{{ $historyContent }}</p>
                                                                    </template>
                                                                    <button type="button"
                                                                            class="text-sm font-medium text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                                                            @click="isExpanded = !isExpanded">
                                                                        <span x-show="!isExpanded">Show more</span>
                                                                        <span x-show="isExpanded">Show less</span>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                </section>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="space-y-8">
                <div class="bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Metadata</h2>
                    </div>
                    <div class="px-6 py-6 space-y-4 text-sm text-gray-700 dark:text-zinc-300">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-zinc-500">Imported from feed</span>
                            <span class="font-medium dark:text-white">
                                {{ $product->feed?->name ?: '—' }}
                            </span>
                        </div>
                        @if ($product->feed?->catalog)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-zinc-500">Catalog</span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-400 font-medium text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                    </svg>
                                    {{ $product->feed->catalog->name }}
                                </span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-zinc-500">Created</span>
                            <span class="font-medium dark:text-white">{{ $product->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-zinc-500">Last updated</span>
                            <span class="font-medium dark:text-white">{{ $product->updated_at->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>
                </div>

                @if ($hasStoreConnection)
                    @php
                        $storeIdentifier = $product->feed?->storeConnection?->store_identifier;
                        $storeName = $storeIdentifier ? Str::before($storeIdentifier, '.myshopify.com') : null;
                        $themeEditorUrl = $storeName ? "https://admin.shopify.com/store/{$storeName}/themes/current/editor?context=apps" : null;
                    @endphp
                    <div class="bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
                        <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Theme Integration
                            </h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                                Add Magnifiq blocks to your theme via the Shopify theme editor. No code required!
                            </p>
                        </div>
                        <div class="px-6 py-6 space-y-5">
                            {{-- Steps --}}
                            <div class="space-y-3">
                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                    <span class="flex-shrink-0 w-6 h-6 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-full flex items-center justify-center text-sm font-medium">1</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Open Theme Editor</p>
                                        <p class="text-sm text-gray-600 dark:text-zinc-400">Online Store → Themes → Customize</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                    <span class="flex-shrink-0 w-6 h-6 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-full flex items-center justify-center text-sm font-medium">2</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Navigate to Product Page</p>
                                        <p class="text-sm text-gray-600 dark:text-zinc-400">Select a product template from the top dropdown: Home → Products </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                    <span class="flex-shrink-0 w-6 h-6 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-full flex items-center justify-center text-sm font-medium">3</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Add Magnifiq Blocks</p>
                                        <p class="text-sm text-gray-600 dark:text-zinc-400">Click "Add block" and find Magnifiq under Apps</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Action buttons --}}
                            @if ($themeEditorUrl)
                                <a href="{{ $themeEditorUrl }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors text-sm font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open Theme Editor
                                </a>
                            @endif

                            {{-- Available Blocks --}}
                            <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-zinc-300 mb-3">Available Blocks</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach([
                                        ['name' => 'FAQ Accordion', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'key' => 'faq'],
                                        ['name' => 'USPs List', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'key' => 'usps'],
                                        ['name' => 'AI Description', 'icon' => 'M4 6h16M4 12h16M4 18h7', 'key' => 'description'],
                                        ['name' => 'Summary', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'key' => 'description_summary'],
                                    ] as $block)
                                        <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-zinc-800/50 rounded-lg text-sm">
                                            <svg class="w-4 h-4 text-gray-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $block['icon'] }}"/>
                                            </svg>
                                            <span class="text-gray-700 dark:text-zinc-300">{{ $block['name'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>
