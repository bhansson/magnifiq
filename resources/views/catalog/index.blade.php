<x-app-layout>
    <div class="py-12 space-y-8">
        {{-- Store Connections Section --}}
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
                <div class="px-6 py-6">
                    <livewire:manage-store-connections />
                </div>
            </div>
        </div>

        {{-- Product Feeds Section --}}
        <livewire:manage-product-feeds />
    </div>
</x-app-layout>
