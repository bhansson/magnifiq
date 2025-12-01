<x-app-layout>
    <div class="py-12">
        <livewire:product-show
            :productId="$product->id"
            :catalogSlug="$catalog?->slug"
            :currentLanguage="$currentLanguage"
        />
    </div>
</x-app-layout>
