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
    'selectedModel' => '',
    'selectedResolution' => null,
    'availableModels' => [],
    'modelSupportsResolution' => false,
    'availableResolutions' => [],
    'estimatedCost' => null,
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
        <div class="flex items-center gap-2">
            <label for="photo-studio-prompt" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                Prompt text
            </label>
            <x-copy-button target="photo-studio-prompt" />
        </div>
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

        {{-- Generation Settings Grid --}}
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Model Selector --}}
            <div
                x-data="{
                    storageKey: 'photo-studio-model',
                    init() {
                        const saved = localStorage.getItem(this.storageKey);
                        const availableModels = @js(array_keys($availableModels));

                        if (saved && availableModels.includes(saved) && saved !== @js($selectedModel)) {
                            $wire.set('selectedModel', saved);
                        }
                    }
                }"
                x-effect="localStorage.setItem(storageKey, $wire.selectedModel)"
            >
                <label for="photo-studio-model" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                    AI Model
                </label>
                <select
                    id="photo-studio-model"
                    wire:model.live="selectedModel"
                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                >
                    @foreach ($availableModels as $modelId => $modelConfig)
                        <option value="{{ $modelId }}">{{ $modelConfig['name'] }}</option>
                    @endforeach
                </select>
                @if (! empty($availableModels[$selectedModel]['description']))
                    <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                        {{ $availableModels[$selectedModel]['description'] }}
                    </p>
                @endif
            </div>

            {{-- Resolution Selector (conditional) --}}
            @if ($modelSupportsResolution && ! empty($availableResolutions))
                <div>
                    <label for="photo-studio-resolution" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Output Resolution
                    </label>
                    <select
                        id="photo-studio-resolution"
                        wire:model.live="selectedResolution"
                        class="mt-1 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                    >
                        @foreach ($availableResolutions as $resId => $resConfig)
                            <option value="{{ $resId }}">{{ $resConfig['label'] }}</option>
                        @endforeach
                    </select>
                    @if (! empty($availableResolutions[$selectedResolution]['description']))
                        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                            {{ $availableResolutions[$selectedResolution]['description'] }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Aspect Ratio Select --}}
            <div>
                <label for="photo-studio-aspect-ratio" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                    Aspect Ratio
                </label>
                <select
                    id="photo-studio-aspect-ratio"
                    wire:model.live="aspectRatio"
                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                >
                    @foreach ($aspectRatios as $ratio => $config)
                        <option value="{{ $ratio }}">
                            {{ $config['label'] }}
                        </option>
                    @endforeach
                </select>
                @if ($currentAspectRatio === 'match_input' && $detectedRatioLabel)
                    <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                        Detected: <span class="font-medium text-amber-600 dark:text-amber-400">{{ $detectedRatioLabel }}</span>
                    </p>
                @elseif (isset($aspectRatios[$currentAspectRatio]['description']))
                    <p class="mt-1 text-xs text-gray-500 dark:text-zinc-500">
                        {{ $aspectRatios[$currentAspectRatio]['description'] }}
                    </p>
                @endif
            </div>
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

            {{-- Cost Estimate Badge --}}
            @if ($estimatedCost)
                <div class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 px-3 py-1.5 text-sm">
                    <svg class="size-4 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-amber-800 dark:text-amber-300">
                        Est. <strong>{{ $estimatedCost }}</strong>
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
