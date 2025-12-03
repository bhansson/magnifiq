<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Teams</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Manage all teams in the system</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by team name..."
                class="w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
            >
        </div>
        <div>
            <select
                wire:model.live="type"
                class="w-full sm:w-auto border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
            >
                <option value="">All Types</option>
                <option value="customer">Customer</option>
                <option value="partner">Partner</option>
            </select>
        </div>
    </div>

    <!-- Teams Table -->
    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Team</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Owner</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Members</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Feeds</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900/50 divide-y divide-gray-200 dark:divide-zinc-800">
                @forelse ($teams as $team)
                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/30">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $team->name }}</div>
                            @if ($team->partner_slug)
                                <div class="text-xs text-gray-500 dark:text-zinc-400">Slug: {{ $team->partner_slug }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($team->type === 'partner')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-400">
                                    Partner
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-zinc-700 dark:text-zinc-300">
                                    Customer
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($team->owner)
                                <div class="text-sm text-gray-900 dark:text-white">{{ $team->owner->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $team->owner->email }}</div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $team->users_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $team->product_feeds_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $team->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a
                                href="{{ route('admin.teams.show', $team) }}"
                                class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                            >
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-zinc-400">
                            No teams found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $teams->links() }}
    </div>
</div>
