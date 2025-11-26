@use('Illuminate\Support\Facades\Storage')

<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Partners</h2>
        <x-button
            wire:click="$set('showCreateModal', true)"
        >
            Create Partner
        </x-button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400 rounded-xl">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Owned Teams</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900/50 divide-y divide-gray-200 dark:divide-zinc-800">
                @forelse ($partners as $partner)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            {{ $partner->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $partner->partner_slug ?: '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $partner->owned_teams_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $partner->created_at->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button
                                wire:click="openEditModal({{ $partner->id }})"
                                class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                            >
                                Edit
                            </button>
                            <button
                                wire:click="deletePartner({{ $partner->id }})"
                                wire:confirm="Are you sure you want to delete this partner?"
                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-zinc-400">
                            No partners found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $partners->links() }}
    </div>

    <!-- Create Partner Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-gray-900/80 dark:bg-black/80 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Create New Partner</h3>

                <form wire:submit="createPartner">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Partner Name</label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        @error('name') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_slug" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Slug (optional)</label>
                        <input
                            type="text"
                            id="partner_slug"
                            wire:model="partner_slug"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        @error('partner_slug') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_share_percent" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Revenue Share %</label>
                        <input
                            type="number"
                            step="0.01"
                            id="partner_share_percent"
                            wire:model="partner_share_percent"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        @error('partner_share_percent') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Logo (optional)</label>
                        <input
                            type="file"
                            id="logo"
                            wire:model="logo"
                            accept="image/*"
                            class="mt-1 block w-full text-gray-900 dark:text-zinc-100"
                        />
                        @error('logo') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror

                        @if ($logo && in_array($logo->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="mt-2">
                                <img src="{{ $logo->temporaryUrl() }}" class="h-20 w-auto rounded-lg" alt="Logo preview">
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button
                            type="button"
                            wire:click="$set('showCreateModal', false)"
                        >
                            Cancel
                        </x-secondary-button>
                        <x-button type="submit">
                            Create
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Partner Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 bg-gray-900/80 dark:bg-black/80 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Edit Partner</h3>

                <form wire:submit="updatePartner">
                    <div class="mb-4">
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Partner Name</label>
                        <input
                            type="text"
                            id="edit_name"
                            wire:model="name"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        @error('name') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="edit_partner_slug" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Slug (optional)</label>
                        <input
                            type="text"
                            id="edit_partner_slug"
                            wire:model="partner_slug"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
                        />
                        @error('partner_slug') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-2">Current Logo</label>
                        @if ($existingLogoPath)
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ asset('storage/' . $existingLogoPath) }}" class="h-16 w-auto border dark:border-zinc-700 rounded-lg" alt="Current logo">
                                <button
                                    type="button"
                                    wire:click="removeLogo"
                                    wire:confirm="Remove this logo?"
                                    class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                >
                                    Remove Logo
                                </button>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-zinc-500 mb-2">No logo uploaded</p>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label for="edit_logo" class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Upload New Logo (optional)</label>
                        <input
                            type="file"
                            id="edit_logo"
                            wire:model="logo"
                            accept="image/*"
                            class="mt-1 block w-full text-gray-900 dark:text-zinc-100"
                        />
                        @error('logo') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror

                        @if ($logo && in_array($logo->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="mt-2">
                                <img src="{{ $logo->temporaryUrl() }}" class="h-20 w-auto rounded-lg" alt="Logo preview">
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button
                            type="button"
                            wire:click="$set('showEditModal', false)"
                        >
                            Cancel
                        </x-secondary-button>
                        <x-button type="submit">
                            Update
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
