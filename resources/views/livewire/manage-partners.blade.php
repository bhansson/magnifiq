@use('Illuminate\Support\Facades\Storage')

<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-800">Partners</h2>
        <button
            wire:click="$set('showCreateModal', true)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
        >
            Create Partner
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-md">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owned Teams</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($partners as $partner)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $partner->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->partner_slug ?: '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->owned_teams_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->created_at->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button
                                wire:click="openEditModal({{ $partner->id }})"
                                class="text-indigo-600 hover:text-indigo-900"
                            >
                                Edit
                            </button>
                            <button
                                wire:click="deletePartner({{ $partner->id }})"
                                wire:confirm="Are you sure you want to delete this partner?"
                                class="text-red-600 hover:text-red-900"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
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
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Create New Partner</h3>

                <form wire:submit="createPartner">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Partner Name</label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_slug" class="block text-sm font-medium text-gray-700">Slug (optional)</label>
                        <input
                            type="text"
                            id="partner_slug"
                            wire:model="partner_slug"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('partner_slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_share_percent" class="block text-sm font-medium text-gray-700">Revenue Share %</label>
                        <input
                            type="number"
                            step="0.01"
                            id="partner_share_percent"
                            wire:model="partner_share_percent"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('partner_share_percent') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="logo" class="block text-sm font-medium text-gray-700">Logo (optional)</label>
                        <input
                            type="file"
                            id="logo"
                            wire:model="logo"
                            accept="image/*"
                            class="mt-1 block w-full"
                        />
                        @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                        @if ($logo && in_array($logo->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="mt-2">
                                <img src="{{ $logo->temporaryUrl() }}" class="h-20 w-auto" alt="Logo preview">
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            wire:click="$set('showCreateModal', false)"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        >
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Partner Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Edit Partner</h3>

                <form wire:submit="updatePartner">
                    <div class="mb-4">
                        <label for="edit_name" class="block text-sm font-medium text-gray-700">Partner Name</label>
                        <input
                            type="text"
                            id="edit_name"
                            wire:model="name"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="edit_partner_slug" class="block text-sm font-medium text-gray-700">Slug (optional)</label>
                        <input
                            type="text"
                            id="edit_partner_slug"
                            wire:model="partner_slug"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('partner_slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Logo</label>
                        @if ($existingLogoPath)
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ asset('storage/' . $existingLogoPath) }}" class="h-16 w-auto border rounded" alt="Current logo">
                                <button
                                    type="button"
                                    wire:click="removeLogo"
                                    wire:confirm="Remove this logo?"
                                    class="text-sm text-red-600 hover:text-red-800"
                                >
                                    Remove Logo
                                </button>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 mb-2">No logo uploaded</p>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label for="edit_logo" class="block text-sm font-medium text-gray-700">Upload New Logo (optional)</label>
                        <input
                            type="file"
                            id="edit_logo"
                            wire:model="logo"
                            accept="image/*"
                            class="mt-1 block w-full"
                        />
                        @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                        @if ($logo && in_array($logo->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="mt-2">
                                <img src="{{ $logo->temporaryUrl() }}" class="h-20 w-auto" alt="Logo preview">
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            wire:click="$set('showEditModal', false)"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        >
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
