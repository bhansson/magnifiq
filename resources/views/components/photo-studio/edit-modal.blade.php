@props([
    'editingGenerationId' => null,
    'productGallery' => [],
    'editInstruction' => '',
    'editSubmitting' => false,
    'editGenerating' => false,
])

<div
        x-data="{
            show: true,
            countdown: 10,
            countdownInterval: null,
            startCountdown() {
                this.countdown = 10;
                if (this.countdownInterval) clearInterval(this.countdownInterval);
                this.countdownInterval = setInterval(() => {
                    if (this.countdown > 0) {
                        this.countdown--;
                    }
                }, 1000);
            },
            stopCountdown() {
                if (this.countdownInterval) {
                    clearInterval(this.countdownInterval);
                    this.countdownInterval = null;
                }
            }
        }"
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center px-2 py-4"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="$wire.closeEditModal()"
        x-init="$watch('$wire.editGenerating', value => { if (value) startCountdown(); else stopCountdown(); })"
    >
        <div class="absolute inset-0 bg-gray-900/80 dark:bg-black/80" @click="$wire.closeEditModal()" aria-hidden="true"></div>

        <div
            class="relative z-10 flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-zinc-800 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Image</h3>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 dark:text-zinc-500 transition hover:bg-gray-100 dark:hover:bg-zinc-800 hover:text-gray-600 dark:hover:text-zinc-300"
                    wire:click="closeEditModal"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                @if ($editingGenerationId)
                    @php
                        $editingGeneration = collect($productGallery)->firstWhere('id', $editingGenerationId);

                        // Build history stack - include current generation when generating
                        $historyStack = $editingGeneration['ancestors'] ?? [];
                        if ($editGenerating) {
                            $historyStack[] = [
                                'id' => $editingGeneration['id'],
                                'url' => $editingGeneration['url'],
                                'edit_instruction' => $editingGeneration['edit_instruction'] ?? 'Original',
                            ];
                        }

                        // Show only the 5 most recent history items
                        $historyStack = array_slice($historyStack, -5);
                    @endphp

                    @if ($editingGeneration)
                        {{-- Top: Image Preview Row --}}
                        <div class="mb-6">
                            <div class="flex items-start gap-8">
                                {{-- Main Photo Area (Current/Countdown) --}}
                                <div class="flex-shrink-0">
                                    @if ($editGenerating)
                                        {{-- Countdown Spinner --}}
                                        <div class="w-96 overflow-hidden rounded-2xl border-4 border-amber-400 dark:border-amber-500 bg-white dark:bg-zinc-800 shadow-2xl" wire:poll.2s="pollEditGeneration">
                                            <div class="flex h-96 flex-col items-center justify-center bg-amber-50 dark:bg-amber-500/10 p-6">
                                                <div class="relative">
                                                    <svg class="h-24 w-24 animate-spin text-amber-500" viewBox="0 0 100 100">
                                                        <circle class="stroke-current opacity-25" cx="50" cy="50" r="40" stroke-width="8" fill="none" />
                                                        <circle class="stroke-current" cx="50" cy="50" r="40" stroke-width="8" fill="none" stroke-dasharray="60 200" stroke-linecap="round" />
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span x-show="countdown > 0" x-text="countdown" class="text-3xl font-bold text-amber-600 dark:text-amber-400"></span>
                                                        <span x-show="countdown === 0" class="text-lg font-semibold text-amber-600 dark:text-amber-400">...</span>
                                                    </div>
                                                </div>
                                                <p class="mt-6 text-center text-sm font-semibold text-amber-900 dark:text-amber-300">
                                                    <span x-show="countdown > 0">Generating your edit...</span>
                                                    <span x-show="countdown === 0">Finalizing...</span>
                                                </p>
                                                <p class="mt-2 text-center text-xs text-amber-700 dark:text-amber-400">This usually takes 5-15 seconds</p>
                                            </div>
                                            <div class="bg-white dark:bg-zinc-800 px-5 py-4">
                                                <p class="line-clamp-2 text-sm font-semibold text-amber-700 dark:text-amber-400">Generating...</p>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Current Image Being Edited --}}
                                        <div class="w-96 overflow-hidden rounded-2xl border-4 border-white dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-2xl">
                                            <img
                                                src="{{ $editingGeneration['url'] }}"
                                                alt="Current version"
                                                class="h-96 w-full object-contain bg-gray-900/5 dark:bg-zinc-900"
                                            />
                                            <div class="bg-white dark:bg-zinc-800 px-5 py-4">
                                                <p class="line-clamp-2 text-sm font-semibold text-gray-700 dark:text-zinc-300">{{ $editingGeneration['edit_instruction'] ?? 'Original' }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- History Stack (Horizontal) --}}
                                @if (!empty($historyStack))
                                    <div class="flex-1">
                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-500">History</p>
                                        <div
                                            class="relative"
                                            style="height: 450px;"
                                            x-data="{
                                                hoveredIndex: null,
                                                history: @js($historyStack)
                                            }"
                                        >
                                            <template x-for="(item, index) in history" :key="item.id">
                                                <div
                                                    class="absolute top-0 w-80 cursor-pointer overflow-hidden rounded-2xl border-4 border-white dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-2xl transition-all duration-200"
                                                    :style="`
                                                        left: ${index * 70}px;
                                                        z-index: ${hoveredIndex === index ? 100 : index + 1};
                                                        transform: ${hoveredIndex === index ? 'translateY(-16px) scale(1.05)' : 'translateY(0)'};
                                                    `"
                                                    @mouseenter="hoveredIndex = index"
                                                    @mouseleave="hoveredIndex = null"
                                                >
                                                    <img
                                                        :src="item.url"
                                                        :alt="'Version ' + (index + 1)"
                                                        class="h-80 w-full object-contain bg-gray-50 dark:bg-zinc-900"
                                                    />
                                                    <div class="bg-white dark:bg-zinc-800 px-4 py-3">
                                                        <p class="line-clamp-2 text-xs font-semibold text-gray-700 dark:text-zinc-300" x-text="item.edit_instruction || 'Original'"></p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Bottom: Edit Form (Always Visible) --}}
                        <div class="border-t border-gray-200 dark:border-zinc-800 pt-6">
                            <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-zinc-300">What would you like to change?</p>
                            <div class="space-y-4">
                                <div>
                                    <textarea
                                        wire:model.defer="editInstruction"
                                        rows="6"
                                        {{ ($editSubmitting || $editGenerating) ? 'disabled' : '' }}
                                        class="block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-800 disabled:text-gray-500 dark:disabled:text-zinc-500"
                                        placeholder="Example: Change the background to a sunset scene, add warmer lighting, remove the..."
                                    ></textarea>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
                                        Keep in mind that every edit can decrease the quality of the details.
                                    </p>

                                    @error('editInstruction')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($editSubmitting)
                                    <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4">
                                        <div class="flex items-center gap-3">
                                            <x-loading-spinner class="size-5 text-amber-600 dark:text-amber-500" />
                                            <div>
                                                <p class="font-semibold text-amber-900 dark:text-amber-300">Queuing your edit...</p>
                                                <p class="text-sm text-amber-700 dark:text-amber-400">This will take just a moment.</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-zinc-800 pt-4">
                                    <button
                                        type="button"
                                        wire:click="closeEditModal"
                                        {{ ($editSubmitting || $editGenerating) ? 'disabled' : '' }}
                                        class="rounded-full border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-zinc-300 transition hover:bg-gray-50 dark:hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Close
                                    </button>
                                    <x-button
                                        type="button"
                                        wire:click="submitEdit"
                                        :disabled="$editSubmitting || $editGenerating"
                                        class="inline-flex items-center gap-2"
                                    >
                                        @if ($editSubmitting)
                                            <x-loading-spinner class="size-4" />
                                            <span>Queuing...</span>
                                        @elseif ($editGenerating)
                                            <x-loading-spinner class="size-4" />
                                            <span>Generating...</span>
                                        @else
                                            <span>Generate Edit</span>
                                        @endif
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

