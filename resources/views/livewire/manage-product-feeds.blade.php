@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">
                    Submit Google Product Feed
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Provide a feed URL or upload an XML or CSV file. Once parsed, choose how each attribute maps to your products.
                </p>
            </div>

            <div class="px-6 py-6 space-y-6">
                @if ($statusMessage)
                    <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                        {{ $statusMessage }}
                    </div>
                @endif

                @if ($errorMessage)
                    <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <x-label for="feedName" value="Feed Name" />
                        <x-input id="feedName" type="text" class="w-full" wire:model.defer="feedName" />
                        <x-input-error for="feedName" />
                    </div>

                    <div class="space-y-2">
                        <x-label for="feedUrl" value="Feed URL" />
                        <x-input id="feedUrl" type="url" class="w-full" placeholder="https://example.com/products.xml" wire:model.defer="feedUrl" />
                        <x-input-error for="feedUrl" />
                    </div>

                    <div class="space-y-2">
                        <x-label for="feedLanguage" value="Language" />
                        <select id="feedLanguage" wire:model.defer="language" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            @foreach ($languageOptions as $code => $label)
                                <option value="{{ $code }}">{{ $label }} ({{ Str::upper($code) }})</option>
                            @endforeach
                        </select>
                        <x-input-error for="language" />
                        <p class="text-xs text-gray-500">
                            Choose the market language that matches this catalog feed.
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <x-label for="feedFile" value="Or Upload XML Feed" />
                    <input id="feedFile" type="file" wire:model="feedFile" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                    <x-input-error for="feedFile" />
                    <p class="text-xs text-gray-500">
                        XML or CSV feeds up to 5MB are supported. If both a URL and file are provided, the uploaded file will be used.
                    </p>
                </div>

                <div class="flex items-center space-x-3">
                    <x-button type="button" wire:click="fetchFields" wire:loading.attr="disabled">
                        Load Feed Fields
                    </x-button>
                    <span class="text-sm text-gray-500" wire:loading>
                        Loading feed…
                    </span>
                </div>

                @if ($showMapping)
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-base font-semibold text-gray-800">
                            Field Mapping
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Select which feed element populates each product attribute.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($mapping as $attribute => $value)
                                <div class="space-y-2">
                                    <x-label :for="'mapping_'.$attribute" :value="Str::headline($attribute)" />
                                    <select id="mapping_{{ $attribute }}" wire:model="mapping.{{ $attribute }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select field --</option>
                                        @foreach ($availableFields as $field)
                                            <option value="{{ $field }}">{{ $field }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex items-center space-x-3">
                            <x-button type="button" wire:click="importFeed" wire:loading.attr="disabled">
                                Import Products
                            </x-button>
                            <span class="text-sm text-gray-500" wire:loading>
                                Importing products…
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">
                    Imported Feeds
                </h2>
            </div>

            <div class="px-6 py-6">
                @if ($feeds->isEmpty())
                    <p class="text-sm text-gray-600">
                        No feeds imported yet. Submit a feed above to get started.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Name</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Language</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Products</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Feed URL</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Updated</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-700 uppercase tracking-wider text-xs">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($feeds as $feed)
                                    <tr class="group hover:bg-gray-50/50 transition-colors">
                                        <td class="px-4 py-2 font-medium text-gray-900">
                                            {{ $feed->name }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            @php
                                                $languageCode = $feed->language;
                                                $languageLabel = $languageOptions[$languageCode] ?? Str::upper($languageCode);
                                            @endphp
                                            {{ $languageLabel }} <span class="text-xs uppercase text-gray-400">({{ Str::upper($languageCode) }})</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            {{ $feed->products_count }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            @if ($feed->feed_url)
                                                <a href="{{ $feed->feed_url }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">
                                                    {{ Str::limit($feed->feed_url, 60) }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">Uploaded file</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            {{ $feed->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="relative inline-block text-left" x-data="{ open: false }" @click.away="open = false">
                                                <button
                                                    type="button"
                                                    @click="open = !open"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100/80 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all opacity-0 group-hover:opacity-100"
                                                    :class="{ 'opacity-100 text-gray-600 bg-gray-100': open }">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                    </svg>
                                                </button>

                                                <div
                                                    x-show="open"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="transform opacity-0 scale-95"
                                                    x-transition:enter-end="transform opacity-100 scale-100"
                                                    x-transition:leave="transition ease-in duration-75"
                                                    x-transition:leave-start="transform opacity-100 scale-100"
                                                    x-transition:leave-end="transform opacity-0 scale-95"
                                                    class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                                    style="display: none;">
                                                    <div class="py-1">
                                                        <button
                                                            type="button"
                                                            wire:click="refreshFeed({{ $feed->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="refreshFeed({{ $feed->id }})"
                                                            @click="open = false"
                                                            class="group flex items-center w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors disabled:opacity-50">
                                                            <svg class="w-4 h-4 mr-3 text-gray-400 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                            <span wire:loading.remove wire:target="refreshFeed({{ $feed->id }})">Refresh Feed</span>
                                                            <span wire:loading wire:target="refreshFeed({{ $feed->id }})">Refreshing…</span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            x-on:click.prevent="if(window.confirm('Delete this feed? All imported products from this feed will be removed.')) { $wire.deleteFeed({{ $feed->id }}); open = false; }"
                                                            wire:loading.attr="disabled"
                                                            wire:target="deleteFeed({{ $feed->id }})"
                                                            class="group flex items-center w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-colors disabled:opacity-50">
                                                            <svg class="w-4 h-4 mr-3 text-gray-400 group-hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                            <span wire:loading.remove wire:target="deleteFeed({{ $feed->id }})">Delete Feed</span>
                                                            <span wire:loading wire:target="deleteFeed({{ $feed->id }})">Deleting…</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
