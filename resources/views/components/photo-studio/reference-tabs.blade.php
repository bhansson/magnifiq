@props([
    'activeTab' => 'upload',
])

<fieldset class="space-y-4">
    <legend class="text-sm font-semibold text-gray-900 dark:text-white">Provide your reference</legend>
    <div class="inline-flex flex-wrap rounded-full border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-1 text-sm font-semibold text-gray-600" role="tablist">
        <button
            type="button"
            wire:click="$set('activeTab', 'upload')"
            class="rounded-full px-4 py-2 transition {{ $activeTab === 'upload' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'upload' ? 'true' : 'false' }}"
        >
            Upload image
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'catalog')"
            class="rounded-full px-4 py-2 transition {{ $activeTab === 'catalog' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'catalog' ? 'true' : 'false' }}"
        >
            Catalog product
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'composition')"
            class="rounded-full px-4 py-2 transition {{ $activeTab === 'composition' ? 'bg-white dark:bg-zinc-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-zinc-400' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'composition' ? 'true' : 'false' }}"
        >
            Composition
            <span class="ml-1 rounded-full bg-amber-100 dark:bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:text-amber-400">New</span>
        </button>
    </div>
