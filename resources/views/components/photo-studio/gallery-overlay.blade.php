@props([
    'productGallery' => [],
])

<div
    x-cloak
    x-show="overlayOpen"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 sm:px-6"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-gray-900/80 dark:bg-black/80" @click="closeOverlay()" aria-hidden="true"></div>

    <div
        class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl"
        @click.stop
    >
        <button
            type="button"
            class="absolute right-4 top-4 z-10 inline-flex size-9 items-center justify-center rounded-full bg-white/90 dark:bg-zinc-800/90 text-gray-700 dark:text-zinc-300 shadow ring-1 ring-black/10 dark:ring-zinc-700 transition hover:bg-white dark:hover:bg-zinc-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
            @click="closeOverlay()"
        >
            <span class="sr-only">Close gallery details</span>
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <div class="flex min-h-0 flex-1 flex-col">
            {{-- Top Section: Image and Details in 2 columns --}}
            <div class="grid min-h-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="bg-gray-100 dark:bg-zinc-800 min-h-0 flex items-center justify-center">
                    <img
                        :src="selectedEntry ? selectedEntry.url : ''"
                        :alt="selectedEntry ? 'Generated render' : ''"
                        class="max-h-full w-full object-contain bg-gray-900/5"
                    />
                </div>
                <div class="overflow-y-auto space-y-5 p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Image details</p>
                        <p class="text-xs text-gray-500 dark:text-zinc-500" x-text="selectedEntry && selectedEntry.created_at ? selectedEntry.created_at : ''"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Product</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white" x-text="selectedEntry && selectedEntry.product_label ? selectedEntry.product_label : 'Generated without a catalog product'"></p>
                        <p
                            class="text-xs text-gray-500 dark:text-zinc-500"
                            x-show="selectedEntry && (selectedEntry.product_brand || selectedEntry.product_sku)"
                        >
                            <template x-if="selectedEntry && selectedEntry.product_brand">
                                <span x-text="selectedEntry.product_brand"></span>
                            </template>
                            <template x-if="selectedEntry && selectedEntry.product_brand && selectedEntry.product_sku">
                                <span class="mx-1 text-gray-400 dark:text-zinc-600">&middot;</span>
                            </template>
                            <template x-if="selectedEntry && selectedEntry.product_sku">
                                <span class="text-gray-400 dark:text-zinc-500" x-text="selectedEntry.product_sku"></span>
                            </template>
                        </p>
                    </div>

                    {{-- Composition Source Images --}}
                    <div x-show="selectedEntry && selectedEntry.composition_mode && selectedEntry.has_viewable_sources">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Source Images</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">
                            <span x-text="selectedEntry && selectedEntry.composition_image_count ? selectedEntry.composition_image_count + ' images combined' : ''"></span>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template x-for="(sourceImg, idx) in (selectedEntry && selectedEntry.source_images ? selectedEntry.source_images : [])" :key="idx">
                                <div
                                    class="group relative"
                                    x-show="sourceImg.url"
                                    :title="sourceImg.title"
                                >
                                    <img
                                        :src="sourceImg.url"
                                        :alt="sourceImg.title"
                                        class="size-16 rounded-lg border border-gray-200 dark:border-zinc-700 object-cover transition group-hover:ring-2 group-hover:ring-amber-400"
                                        loading="lazy"
                                    />
                                    <span
                                        class="absolute bottom-0.5 left-0.5 rounded bg-black/60 px-1 py-0.5 text-[9px] font-medium text-white"
                                        x-text="sourceImg.type === 'product' ? 'Product' : 'Upload'"
                                    ></span>
                                </div>
                            </template>
                        </div>
                        {{-- Note for legacy uploads --}}
                        <p
                            class="mt-2 text-xs text-gray-400 dark:text-zinc-500 italic"
                            x-show="selectedEntry && selectedEntry.source_references && selectedEntry.source_references.some(r => r.type === 'upload' && (!r.source_reference || !r.source_reference.includes('/')))"
                        >
                            Some uploaded images were not preserved (created before this feature).
                        </p>
                    </div>

                    <div x-show="selectedEntry && selectedEntry.edit_instruction">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Edit Instruction</p>
                        <div class="mt-2 rounded-lg border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-3">
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                <p class="text-sm text-amber-900 dark:text-amber-400" x-text="selectedEntry ? selectedEntry.edit_instruction : ''"></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Original Prompt</p>
                        <p
                            class="mt-2 max-h-48 whitespace-pre-line overflow-y-auto rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-800 dark:text-zinc-200"
                            x-text="selectedEntry && selectedEntry.prompt ? selectedEntry.prompt : 'Prompt unavailable for this render.'"
                        ></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">Model</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="selectedEntry && selectedEntry.model ? selectedEntry.model : 'Unknown model'"></p>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-3 pt-1">
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 shadow-sm transition hover:border-gray-300 dark:hover:border-zinc-600 hover:text-gray-900 dark:hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                            x-on:click.prevent="if (!selectedEntry) { return; } $wire.openEditModal(selectedEntry.id); closeOverlay();"
                            title="Edit this image"
                        >
                            <span class="sr-only">Edit image</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <a
                            :href="selectedEntry ? selectedEntry.url : '#'"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 shadow-sm transition hover:border-gray-300 dark:hover:border-zinc-600 hover:text-gray-900 dark:hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                        >
                            <span class="sr-only">View full size</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M18 10s-3-4-8-4-8 4-8 4 3 4 8 4 8-4 8-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M10 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <a
                            :href="selectedEntry ? selectedEntry.download_url : '#'"
                            download
                            class="inline-flex size-10 items-center justify-center rounded-full bg-gradient-to-r from-amber-400 to-orange-500 text-black shadow-sm transition hover:from-amber-300 hover:to-orange-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                        >
                            <span class="sr-only">Download image</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 3v8m0 0 3-3m-3 3-3-3M4.5 13.5v1.25A1.25 1.25 0 0 0 5.75 16h8.5a1.25 1.25 0 0 0 1.25-1.25V13.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 shadow-sm transition hover:bg-red-50 dark:hover:bg-red-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 disabled:opacity-60"
                            wire:loading.attr="disabled"
                            wire:target="deleteGeneration"
                            x-on:click.prevent="if (!selectedEntry) { return; } if (!confirm('Delete this image from the gallery?')) { return; } $wire.deleteGeneration(selectedEntry.id).then(() => { closeOverlay(); });"
                        >
                            <span class="sr-only">Delete image</span>
                            <span class="flex items-center justify-center" wire:loading.remove wire:target="deleteGeneration">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m7 5 .867-1.3A1 1 0 0 1 8.7 3h2.6a1 1 0 0 1 .833.7L13 5m4 0H3m1 0 .588 11.18A1 1 0 0 0 5.587 17h8.826a1 1 0 0 0 .999-.82L16 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="flex items-center justify-center" wire:loading.flex wire:target="deleteGeneration">
                                <x-loading-spinner class="size-4" />
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Bottom Section: History Timeline (Horizontal) --}}
            <div x-show="selectedEntry && selectedEntry.has_history" class="border-t border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/50 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="h-4 w-4 text-gray-500 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Version History</h4>
                </div>

                <div class="overflow-x-auto">
                    <div class="flex gap-4 pb-2">
                        {{-- Ancestors (Previous Versions) --}}
                        <template x-for="(ancestor, index) in (selectedEntry ? selectedEntry.ancestors : [])" :key="ancestor.id">
                            <button
                                type="button"
                                @click="
                                    const gallery = @js($productGallery);
                                    const foundEntry = gallery.find(g => g.id === ancestor.id);
                                    if (foundEntry) {
                                        selectedEntry = foundEntry;
                                    }
                                "
                                class="flex-shrink-0 w-48 rounded-lg border border-gray-200 bg-white p-3 text-left transition hover:border-gray-300 hover:shadow-md"
                            >
                                <img
                                    :src="ancestor.url"
                                    :alt="'Previous version ' + (index + 1)"
                                    class="w-full h-32 rounded object-cover mb-2 opacity-75"
                                />
                                <div class="space-y-1">
                                    <p class="text-xs font-medium text-gray-600" x-text="'Version ' + (index + 1)"></p>
                                    <p class="text-xs text-gray-500" x-text="ancestor.created_at_human"></p>
                                    <p class="text-xs text-gray-700 line-clamp-2" x-show="ancestor.edit_instruction" x-text="ancestor.edit_instruction"></p>
                                </div>
                            </button>
                        </template>

                        {{-- Current Version --}}
                        <div class="flex-shrink-0 w-48 rounded-lg border-2 border-indigo-400 bg-indigo-50 p-3 shadow-md">
                            <div class="relative">
                                <img
                                    :src="selectedEntry ? selectedEntry.url : ''"
                                    alt="Current version"
                                    class="w-full h-32 rounded object-cover mb-2 ring-2 ring-indigo-300"
                                />
                                <div class="absolute -top-1 -right-1 bg-indigo-600 text-white rounded-full p-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-semibold text-indigo-900" x-text="'Version ' + ((selectedEntry && selectedEntry.ancestors ? selectedEntry.ancestors.length : 0) + 1)"></p>
                                <p class="text-xs text-indigo-700" x-text="selectedEntry ? selectedEntry.created_at_human : ''"></p>
                                <p class="text-xs text-indigo-800 line-clamp-2" x-show="selectedEntry && selectedEntry.edit_instruction" x-text="selectedEntry ? selectedEntry.edit_instruction : ''"></p>
                            </div>
                        </div>

                        {{-- Descendants (Future Edits) --}}
                        <template x-for="(descendant, index) in (selectedEntry ? selectedEntry.descendants : [])" :key="descendant.id">
                            <button
                                type="button"
                                @click="
                                    const gallery = @js($productGallery);
                                    const foundEntry = gallery.find(g => g.id === descendant.id);
                                    if (foundEntry) {
                                        selectedEntry = foundEntry;
                                    }
                                "
                                class="flex-shrink-0 w-48 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-left transition hover:border-emerald-300 hover:shadow-md"
                            >
                                <img
                                    :src="descendant.url"
                                    :alt="'Future version ' + (index + 1)"
                                    class="w-full h-32 rounded object-cover mb-2"
                                />
                                <div class="space-y-1">
                                    <p class="text-xs font-medium text-emerald-800" x-text="'Version ' + ((selectedEntry && selectedEntry.ancestors ? selectedEntry.ancestors.length : 0) + index + 2)"></p>
                                    <p class="text-xs text-emerald-600" x-text="descendant.created_at_human"></p>
                                    <p class="text-xs text-emerald-700 line-clamp-2" x-show="descendant.edit_instruction" x-text="descendant.edit_instruction"></p>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
