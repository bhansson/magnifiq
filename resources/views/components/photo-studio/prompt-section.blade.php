@props([
    'creativeBrief' => '',
    'promptResult' => '',
    'errorMessage' => null,
    'aspectRatio' => 'match_input',
    'detectedAspectRatio' => null,
    'generationStatus' => null,
    'isProcessing' => false,
    'isAwaitingGeneration' => false,
    'isAwaitingVisionJob' => false,
    'visionJobStatus' => null,
    'canGenerate' => false,
    'compositionImageCount' => 0,
    'minImages' => 1,
])

@php
    $hasPromptText = filled($promptResult);
    $aspectRatios = config('photo-studio.aspect_ratios.available', []);
    $currentAspectRatio = $aspectRatio ?? 'match_input';
    $detectedRatioLabel = $detectedAspectRatio ? $aspectRatios[$detectedAspectRatio]['label'] ?? $detectedAspectRatio : null;
    $needsMoreImages = $compositionImageCount < $minImages;
@endphp

{{-- Creative Direction --}}
<div>
    <label for="photo-studio-brief" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
        Creative direction (optional)
    </label>
    <textarea
        id="photo-studio-brief"
        wire:model.defer="creativeBrief"
        rows="3"
        class="mt-2 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
        placeholder="Example: Emphasise natural window lighting and add subtle studio props like folded towels."
    ></textarea>
    <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
        Up to 600 characters. These notes are added to the AI request for extra guidance.
    </p>

    @error('creativeBrief')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

{{-- Reference Status Card --}}
<div class="flex flex-col gap-4 rounded-2xl border border-dashed border-amber-200 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-start gap-3 text-sm text-amber-900 dark:text-amber-400">
        @if ($canGenerate)
            <svg class="mt-1 h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        @else
            <svg class="mt-1 h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M10 3.333 3.333 16.667h13.334L10 3.333Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                <path d="m10 8.333.008 3.334" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M9.992 13.333h.016" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        @endif
        <div>
            @if ($canGenerate)
                <p class="font-semibold dark:text-amber-300">
                    {{ $compositionImageCount === 1 ? '1 image ready.' : "{$compositionImageCount} images ready." }}
                </p>
                <p class="text-amber-900/80 dark:text-amber-400/80">
                    We'll analyse {{ $compositionImageCount === 1 ? 'the image' : 'all images' }} to craft a prompt.
                </p>
            @elseif ($needsMoreImages)
                <p class="font-semibold dark:text-amber-300">
                    {{ $minImages === 1 ? 'Add an image to continue.' : "Add at least {$minImages} images to continue." }}
                </p>
                <p class="text-amber-900/80 dark:text-amber-400/80">
                    Upload a file or select from your catalog.
                </p>
            @else
                <p class="font-semibold dark:text-amber-300">
                    Ready to extract prompt.
                </p>
                <p class="text-amber-900/80 dark:text-amber-400/80">
                    Click the button to analyse your image(s).
                </p>
            @endif
        </div>
    </div>
    <x-button
        type="button"
        wire:click="extractPrompt"
        wire:loading.attr="disabled"
        :disabled="! $canGenerate || $isAwaitingVisionJob"
        class="flex items-center gap-2 whitespace-nowrap"
    >
        <span wire:loading.remove wire:target="extractPrompt,compositionUploads">
            @if ($isAwaitingVisionJob)
                Analyzing…
            @else
                Craft prompt
            @endif
        </span>
        <span wire:loading.flex wire:target="extractPrompt" class="flex items-center gap-2">
            <x-loading-spinner class="size-4" />
            Processing...
        </span>
    </x-button>
</div>

{{-- Vision Job Status (with fast polling) --}}
@if ($isAwaitingVisionJob)
    <div class="rounded-md bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-400" wire:poll.500ms="pollVisionJobStatus">
        <div class="flex items-center gap-2">
            <x-loading-spinner class="size-4" />
            <span>{{ $visionJobStatus ?? 'Analyzing images…' }}</span>
        </div>
    </div>
@elseif ($visionJobStatus && ! $errorMessage)
    <div class="rounded-md bg-emerald-50 dark:bg-emerald-500/10 p-4 text-sm text-emerald-800 dark:text-emerald-400">
        <div class="flex items-center gap-2">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <span>{{ $visionJobStatus }}</span>
        </div>
    </div>
@endif

{{-- Prompt Workspace --}}
<div class="space-y-5 border-t border-gray-100 dark:border-zinc-800 pt-6">
    {{-- Error Message --}}
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 dark:bg-red-500/10 p-4">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="size-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 5a1 1 0 012 0v5a1 1 0 01-2 0V5zm1 8a1.25 1.25 0 100 2.5A1.25 1.25 0 0010 13z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ms-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-400">
                        {{ $errorMessage }}
                    </h3>
                </div>
            </div>
        </div>
    @endif

    {{-- Prompt Textarea --}}
    <div>
        <label for="photo-studio-prompt" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
            Prompt text
        </label>
        <textarea
            id="photo-studio-prompt"
            wire:model.live.debounce.500ms="promptResult"
            rows="6"
            class="mt-2 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
            placeholder="Paste or craft a prompt here if you'd like to skip extraction."
        ></textarea>
        <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
            This prompt is sent to the image model when you choose Generate image.
        </p>

        {{-- Aspect Ratio Select --}}
        <div class="mt-4">
            <label for="photo-studio-aspect-ratio" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                Output aspect ratio
            </label>
            <div class="mt-1 flex items-center gap-3">
                <select
                    id="photo-studio-aspect-ratio"
                    wire:model.live="aspectRatio"
                    class="block w-full max-w-xs rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                >
                    @foreach ($aspectRatios as $ratio => $config)
                        <option value="{{ $ratio }}">
                            {{ $config['label'] }}
                        </option>
                    @endforeach
                </select>
                @if ($currentAspectRatio === 'match_input' && $detectedRatioLabel)
                    <span class="text-xs text-gray-500 dark:text-zinc-500">
                        Detected: <span class="font-medium text-amber-600 dark:text-amber-400">{{ $detectedRatioLabel }}</span>
                    </span>
                @endif
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                @if ($currentAspectRatio === 'match_input')
                    The output will match your source image's aspect ratio.
                @else
                    {{ $aspectRatios[$currentAspectRatio]['description'] ?? '' }}
                @endif
            </p>
        </div>

        {{-- Generation Status --}}
        @if ($generationStatus)
            <div class="mt-4 rounded-md bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-400" @if ($isAwaitingGeneration) wire:poll.3s="pollGenerationStatus" @endif>
                <div class="flex items-center gap-2">
                    @if ($isAwaitingGeneration)
                        <x-loading-spinner class="size-4" />
                    @else
                        <svg class="size-4 text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    @endif
                    <span>{{ $generationStatus }}</span>
                </div>
            </div>
        @elseif ($isAwaitingGeneration)
            <div class="mt-4 rounded-md bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-400" wire:poll.3s="pollGenerationStatus">
                <div class="flex items-center gap-2">
                    <x-loading-spinner class="size-4" />
                    <span>Image generation in progress...</span>
                </div>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-button
                type="button"
                wire:click="generateImage"
                wire:loading.attr="disabled"
                :disabled="! $hasPromptText"
                class="flex items-center gap-2 whitespace-nowrap"
            >
                <span wire:loading.remove wire:target="generateImage">
                    Generate image
                </span>
                <span wire:loading.flex wire:target="generateImage" class="flex items-center gap-2">
                    <x-loading-spinner class="size-4" />
                    Generating...
                </span>
            </x-button>
            <button
                type="button"
                class="inline-flex items-center rounded-full border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-zinc-300 shadow-sm transition hover:bg-gray-50 dark:hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                x-data="{ copied: false }"
                x-on:click="if (@js($hasPromptText)) { navigator.clipboard.writeText(@js($promptResult)).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }"
                :disabled="! @js($hasPromptText)"
            >
                <svg class="me-2 h-4 w-4 text-gray-500 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16.5v2.25A2.25 2.25 0 0010.25 21h7.5A2.25 2.25 0 0020 18.75v-7.5A2.25 2.25 0 0017.75 9h-2.25M8 16.5h-2.25A2.25 2.25 0 013.5 14.25v-7.5A2.25 2.25 0 015.75 4.5h7.5A2.25 2.25 0 0115.5 6.75V9M8 16.5h6.75A2.25 2.25 0 0017 14.25V7.5M8 16.5A2.25 2.25 0 015.75 14.25V7.5" />
                </svg>
                <span x-text="copied ? 'Copied!' : 'Copy prompt'"></span>
            </button>
        </div>
    </div>
</div>
