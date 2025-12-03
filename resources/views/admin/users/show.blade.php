<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('admin-user-detail', ['user' => $user])
        </div>
    </div>
</x-app-layout>
