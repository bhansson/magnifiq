@props([
    'image' => null,
])

<div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 p-4 shadow-sm dark:shadow-none">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Upload an image</p>
        @if ($image)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Selected
            </span>
        @endif
    </div>
    <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
        Preferred for ad-hoc shots or items not yet in your catalog.
    </p>

    <label for="photo-studio-upload" class="mt-4 block text-sm font-medium text-gray-700 dark:text-zinc-300">
        Image file
    </label>
    <input
        id="photo-studio-upload"
        type="file"
        wire:model="image"
        accept="image/*"
        class="mt-2 block w-full rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-sm text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
    />
    <p class="mt-2 text-xs text-gray-500 dark:text-zinc-500">
        JPG, PNG or WEBP up to 8&nbsp;MB.
    </p>

    @error('image')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div wire:loading.flex wire:target="image" class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400">
        <x-loading-spinner class="size-4" />
        Uploading…
    </div>

    @if ($image)
        <img
            src="{{ $image->temporaryUrl() }}"
            alt="Uploaded preview"
            class="mt-4 max-h-48 w-auto max-w-full rounded-xl border border-gray-200 dark:border-zinc-700 object-cover"
        />
    @endif
</div>
