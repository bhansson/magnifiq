@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
            <div class="px-6 py-6 border-b border-gray-200 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                    Submit Google Product Feed
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                    Provide a feed URL or upload an XML or CSV file. Once parsed, choose how each attribute maps to your products.
                </p>
            </div>

            <div class="px-6 py-6 space-y-6">
                @if ($statusMessage)
                    <div class="rounded-xl bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-4 text-sm text-green-700 dark:text-green-400">
                        {{ $statusMessage }}
                    </div>
                @endif

                @if ($errorMessage)
                    <div class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-4 text-sm text-red-700 dark:text-red-400">
                        {{ $errorMessage }}
                    </div>
                @endif

                {{-- Step 1: Feed URL Input --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-label for="feedUrl" value="Feed URL" />
                            <x-input id="feedUrl" type="text" class="w-full" placeholder="https://example.com/products.xml" wire:model.defer="feedUrl" />
                            <x-input-error for="feedUrl" />
                        </div>

                        <div
                            class="space-y-2"
                            x-data="{
                                uploading: false,
                                progress: 0,
                                uploadError: null,
                                maxSize: 20 * 1024 * 1024,
                                fileValid: false,
                                validateFile(event) {
                                    const file = event.target.files[0];
                                    if (file && file.size > this.maxSize) {
                                        this.uploadError = 'File is too large. Maximum size is 20MB, but your file is ' + (file.size / 1024 / 1024).toFixed(1) + 'MB.';
                                        this.fileValid = false;
                                        event.target.value = '';
                                        return;
                                    }
                                    this.uploadError = null;
                                    this.fileValid = true;
                                }
                            }"
                            x-on:livewire-upload-start="if (fileValid) { uploading = true; uploadError = null; }"
                            x-on:livewire-upload-finish="uploading = false"
                            x-on:livewire-upload-cancel="uploading = false"
                            x-on:livewire-upload-error="uploading = false; uploadError = 'File upload failed. The file may be too large or the server rejected it.'"
                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                        >
                            <x-label for="feedFile" value="Or Upload Feed File" />
                            <input id="feedFile" type="file" wire:model="feedFile" x-on:change="validateFile($event)" class="block w-full text-sm text-gray-500 dark:text-zinc-400 file:mr-4 file:rounded-full file:border-0 file:bg-amber-500/10 dark:file:bg-amber-500/20 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-600 dark:file:text-amber-400 hover:file:bg-amber-500/20 dark:hover:file:bg-amber-500/30 transition-colors" />
                            <div x-show="uploading" class="mt-2">
                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-zinc-400">
                                    <progress max="100" x-bind:value="progress" class="h-2 w-full rounded-full overflow-hidden [&::-webkit-progress-bar]:bg-gray-200 dark:[&::-webkit-progress-bar]:bg-zinc-700 [&::-webkit-progress-value]:bg-amber-500 [&::-moz-progress-bar]:bg-amber-500"></progress>
                                    <span x-text="progress + '%'" class="min-w-[3rem] text-right"></span>
                                </div>
                            </div>
                            <template x-if="uploadError">
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="uploadError"></p>
                            </template>
                            <x-input-error for="feedFile" />
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-zinc-500">
                        XML or CSV feeds up to 20MB are supported. If both a URL and file are provided, the uploaded file will be used.
                    </p>

                    @if (! $showMapping)
                        <div class="flex items-center space-x-3">
                            <x-button type="button" wire:click="fetchFields" wire:loading.attr="disabled" wire:target="feedFile, fetchFields">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Load Feed
                            </x-button>
                            <span class="text-sm text-gray-500 dark:text-zinc-400" wire:loading wire:target="fetchFields">
                                Loading feed…
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Step 2: Configure Feed (shown after successful fetch) --}}
                @if ($showMapping)
                    <div class="border-t border-gray-200 dark:border-zinc-800 pt-6 space-y-6">
                        {{-- Feed Settings --}}
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white">
                                Feed Settings
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-zinc-400 mt-1">
                                Review and adjust the auto-detected settings for this feed.
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div class="space-y-2">
                                    <x-label for="feedName" value="Feed Name" />
                                    <x-input id="feedName" type="text" class="w-full" wire:model.defer="feedName" placeholder="My Product Feed" required />
                                    <x-input-error for="feedName" />
                                </div>

                                <div class="space-y-2">
                                    <x-label for="feedLanguage" value="Language" />
                                    <select id="feedLanguage" wire:model.defer="language" class="block w-full px-4 py-3 bg-white dark:bg-zinc-800/50 border border-gray-300 dark:border-zinc-700 rounded-xl text-gray-900 dark:text-zinc-100 focus:border-amber-500 dark:focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 transition-colors duration-200 text-sm">
                                        @foreach ($languageOptions as $code => $label)
                                            <option value="{{ $code }}">{{ $label }} ({{ Str::upper($code) }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="language" />
                                </div>

                                <div class="space-y-2 lg:col-span-2">
                                    <x-label for="catalogOption" value="Catalog" />
                                    <select id="catalogOption" wire:model.defer="catalogOption" class="block w-full px-4 py-3 bg-white dark:bg-zinc-800/50 border border-gray-300 dark:border-zinc-700 rounded-xl text-gray-900 dark:text-zinc-100 focus:border-amber-500 dark:focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 transition-colors duration-200 text-sm">
                                        <option value="new">+ Create new catalog (uses feed name)</option>
                                        @foreach ($catalogs as $catalog)
                                            <option value="{{ $catalog->id }}">{{ $catalog->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="catalogOption" />
                                </div>
                            </div>
                        </div>

                        {{-- Field Mapping --}}
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white">
                                Field Mapping
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-zinc-400 mt-1">
                                Map feed elements to product attributes. SKU, Title, and URL are required.
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                @foreach ($mapping as $attribute => $value)
                                    <div class="space-y-2">
                                        <x-label :for="'mapping_'.$attribute">
                                            {{ Str::headline($attribute) }}
                                            @if (in_array($attribute, ['sku', 'title', 'url']))
                                                <span class="text-red-500">*</span>
                                            @endif
                                        </x-label>
                                        <select id="mapping_{{ $attribute }}" wire:model="mapping.{{ $attribute }}" class="block w-full px-4 py-3 bg-white dark:bg-zinc-800/50 border border-gray-300 dark:border-zinc-700 rounded-xl text-gray-900 dark:text-zinc-100 focus:border-amber-500 dark:focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 transition-colors duration-200 text-sm">
                                            <option value="">-- Select field --</option>
                                            @foreach ($availableFields as $field)
                                                <option value="{{ $field }}">{{ $field }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-zinc-800">
                            <button
                                type="button"
                                wire:click="$set('showMapping', false)"
                                class="text-sm text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-300 transition-colors">
                                ← Load different feed
                            </button>
                            <div class="flex items-center space-x-3">
                                <span class="text-sm text-gray-500 dark:text-zinc-400" wire:loading wire:target="importFeed">
                                    Importing products…
                                </span>
                                <x-button type="button" wire:click="importFeed" wire:loading.attr="disabled" wire:target="importFeed">
                                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Import Products
                                </x-button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Product Catalogs Section --}}
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
            <div class="px-6 py-6 border-b border-gray-200 dark:border-zinc-800 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Product Catalogs
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                        Group feeds by market to connect products across languages.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="toggleCreateCatalog"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-xl {{ $showCreateCatalog ? 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300' : 'bg-amber-500 hover:bg-amber-600 text-white' }} transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                    @if ($showCreateCatalog)
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    @else
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Catalog
                    @endif
                </button>
            </div>

            <div class="px-6 py-6">
                {{-- Create Catalog Form --}}
                @if ($showCreateCatalog)
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-zinc-800/50 rounded-xl border border-gray-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Create New Catalog</h3>
                        <div class="flex items-end gap-3">
                            <div class="flex-1 space-y-2">
                                <x-label for="newCatalogName" value="Catalog Name" />
                                <x-input
                                    id="newCatalogName"
                                    type="text"
                                    class="w-full"
                                    wire:model.defer="newCatalogName"
                                    placeholder="e.g., Main Store, Outlet, B2B"
                                    wire:keydown.enter="createCatalog" />
                                <x-input-error for="newCatalogName" />
                            </div>
                            <x-button type="button" wire:click="createCatalog" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="createCatalog">Create Catalog</span>
                                <span wire:loading wire:target="createCatalog">Creating…</span>
                            </x-button>
                        </div>
                    </div>
                @endif

                {{-- Catalogs List --}}
                @if ($catalogs->isEmpty())
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            No catalogs yet. Create a catalog to group feeds by market.
                        </p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($catalogs as $catalog)
                            <div class="border border-gray-200 dark:border-zinc-700 rounded-xl overflow-hidden" wire:key="catalog-{{ $catalog->id }}">
                                {{-- Catalog Header --}}
                                <div class="px-4 py-3 bg-gray-50 dark:bg-zinc-800/50 flex items-center justify-between">
                                    @if ($editingCatalogId === $catalog->id)
                                        <div class="flex items-center gap-3 flex-1">
                                            <x-input
                                                type="text"
                                                class="text-sm"
                                                wire:model.defer="editingCatalogName"
                                                wire:keydown.enter="updateCatalog"
                                                wire:keydown.escape="cancelEditCatalog" />
                                            <button
                                                type="button"
                                                wire:click="updateCatalog"
                                                class="text-sm text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 font-medium">
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="cancelEditCatalog"
                                                class="text-sm text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-300">
                                                Cancel
                                            </button>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                            </svg>
                                            <span class="font-semibold text-gray-800 dark:text-white">{{ $catalog->name }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400">
                                                {{ $catalog->feeds_count }} {{ Str::plural('feed', $catalog->feeds_count) }}
                                            </span>
                                            @if ($catalog->feeds->isNotEmpty())
                                                <div class="flex items-center gap-1 ml-2">
                                                    @foreach ($catalog->feeds->pluck('language')->unique() as $lang)
                                                        <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 font-medium">
                                                            {{ Str::upper($lang) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                wire:click="startEditCatalog({{ $catalog->id }})"
                                                class="text-sm text-gray-500 dark:text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                x-on:click.prevent="if(window.confirm('Delete this catalog? Feeds will be moved to uncategorized.')) { $wire.deleteCatalog({{ $catalog->id }}) }"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteCatalog({{ $catalog->id }})"
                                                class="text-sm text-gray-500 dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Catalog Feeds --}}
                                @if ($catalog->feeds->isNotEmpty())
                                    <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                                        @foreach ($catalog->feeds as $feed)
                                            <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors group" wire:key="catalog-feed-{{ $feed->id }}">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400 font-medium">
                                                        {{ Str::upper($feed->language) }}
                                                    </span>
                                                    <span class="text-sm text-gray-800 dark:text-zinc-200">{{ $feed->name }}</span>
                                                    @if ($feed->feed_url)
                                                        <a href="{{ $feed->feed_url }}"
                                                           target="_blank"
                                                           rel="noopener noreferrer"
                                                           class="text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 transition-colors"
                                                           title="{{ $feed->feed_url }}">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                            </svg>
                                                        </a>
                                                    @endif
                                                    <span class="text-xs text-gray-500 dark:text-zinc-500">
                                                        {{ $feed->products_count }} products
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button
                                                        type="button"
                                                        wire:click="startMoveFeed({{ $feed->id }})"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 focus:outline-none transition-all"
                                                        title="Move to Catalog">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                                        </svg>
                                                    </button>
                                                    @if ($feed->feed_url)
                                                        <button
                                                            type="button"
                                                            wire:click="refreshFeed({{ $feed->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="refreshFeed({{ $feed->id }})"
                                                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 focus:outline-none transition-all disabled:opacity-50"
                                                            title="Re-import products">
                                                            <svg class="w-4 h-4" wire:loading.remove wire:target="refreshFeed({{ $feed->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                            <svg class="w-4 h-4 animate-spin" wire:loading wire:target="refreshFeed({{ $feed->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        x-on:click.prevent="if(window.confirm('Delete this feed? All imported products from this feed will be removed.')) { $wire.deleteFeed({{ $feed->id }}) }"
                                                        wire:loading.attr="disabled"
                                                        wire:target="deleteFeed({{ $feed->id }})"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 focus:outline-none transition-all disabled:opacity-50"
                                                        title="Delete Feed">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="px-4 py-4 text-center">
                                        <p class="text-sm text-gray-500 dark:text-zinc-500">
                                            No feeds in this catalog yet.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        {{-- Uncategorized Feeds --}}
                        @php
                            $uncategorizedFeeds = $feeds->whereNull('product_catalog_id');
                        @endphp
                        @if ($uncategorizedFeeds->isNotEmpty())
                            <div class="rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden" wire:key="uncategorized-feeds">
                                <div class="px-4 py-3 bg-gray-50/50 dark:bg-zinc-800/30 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                        </svg>
                                        <span class="font-semibold text-gray-600 dark:text-zinc-400">Uncategorized</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400">
                                            {{ $uncategorizedFeeds->count() }} {{ Str::plural('feed', $uncategorizedFeeds->count()) }}
                                        </span>
                                        @if ($uncategorizedFeeds->isNotEmpty())
                                            <div class="flex items-center gap-1 ml-2">
                                                @foreach ($uncategorizedFeeds->pluck('language')->unique() as $lang)
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 font-medium">
                                                        {{ Str::upper($lang) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                                    @foreach ($uncategorizedFeeds as $feed)
                                        <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors group" wire:key="uncategorized-feed-{{ $feed->id }}">
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400 font-medium">
                                                    {{ Str::upper($feed->language) }}
                                                </span>
                                                <span class="text-sm text-gray-800 dark:text-zinc-200">{{ $feed->name }}</span>
                                                @if ($feed->feed_url)
                                                    <a href="{{ $feed->feed_url }}"
                                                       target="_blank"
                                                       rel="noopener noreferrer"
                                                       class="text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 transition-colors"
                                                       title="{{ $feed->feed_url }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                        </svg>
                                                    </a>
                                                @endif
                                                <span class="text-xs text-gray-500 dark:text-zinc-500">
                                                    {{ $feed->products_count }} products
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    type="button"
                                                    wire:click="startMoveFeed({{ $feed->id }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 focus:outline-none transition-all"
                                                    title="Move to Catalog">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                                    </svg>
                                                </button>
                                                @if ($feed->feed_url)
                                                    <button
                                                        type="button"
                                                        wire:click="refreshFeed({{ $feed->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="refreshFeed({{ $feed->id }})"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 focus:outline-none transition-all disabled:opacity-50"
                                                        title="Re-import products">
                                                        <svg class="w-4 h-4" wire:loading.remove wire:target="refreshFeed({{ $feed->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        <svg class="w-4 h-4 animate-spin" wire:loading wire:target="refreshFeed({{ $feed->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                    </button>
                                                @endif
                                                <button
                                                    type="button"
                                                    x-on:click.prevent="if(window.confirm('Delete this feed? All imported products from this feed will be removed.')) { $wire.deleteFeed({{ $feed->id }}) }"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteFeed({{ $feed->id }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 focus:outline-none transition-all disabled:opacity-50"
                                                    title="Delete Feed">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Move Feed Modal --}}
    @if ($movingFeedId)
        @php
            $movingFeed = $feeds->firstWhere('id', $movingFeedId);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-zinc-900/75 transition-opacity" wire:click="cancelMoveFeed"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-zinc-800 rounded-xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-500/20">
                            <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                Move Feed to Catalog
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-zinc-400">
                                    Moving <span class="font-medium text-gray-800 dark:text-zinc-200">{{ $movingFeed?->name }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5">
                        <x-label for="moveToCatalogId" value="Select Catalog" />
                        <select
                            id="moveToCatalogId"
                            wire:model.defer="moveToCatalogId"
                            class="mt-1 block w-full px-4 py-3 bg-white dark:bg-zinc-700 border border-gray-300 dark:border-zinc-600 rounded-xl text-gray-900 dark:text-zinc-100 focus:border-amber-500 dark:focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 transition-colors duration-200 text-sm">
                            <option value="">Uncategorized (Standalone)</option>
                            @foreach ($catalogs as $catalog)
                                <option value="{{ $catalog->id }}">{{ $catalog->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <x-button
                            type="button"
                            wire:click="confirmMoveFeed"
                            wire:loading.attr="disabled"
                            class="w-full justify-center sm:col-start-2">
                            <span wire:loading.remove wire:target="confirmMoveFeed">Move Feed</span>
                            <span wire:loading wire:target="confirmMoveFeed">Moving…</span>
                        </x-button>
                        <button
                            type="button"
                            wire:click="cancelMoveFeed"
                            class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 dark:border-zinc-600 shadow-sm px-4 py-2 bg-white dark:bg-zinc-700 text-base font-medium text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:col-start-1 sm:text-sm transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
