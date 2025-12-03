<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Team Details</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Viewing {{ $team->name }}</p>
        </div>
        <a href="{{ route('admin.teams') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
            &larr; Back to Teams
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Team Info Card -->
        <div class="lg:col-span-1 bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <div class="text-center">
                @if ($team->logo_path)
                    <img class="h-24 w-24 rounded-xl object-cover mx-auto" src="{{ asset('storage/' . $team->logo_path) }}" alt="{{ $team->name }}">
                @else
                    <div class="h-24 w-24 rounded-xl bg-amber-100 dark:bg-amber-500/20 mx-auto flex items-center justify-center">
                        <span class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ substr($team->name, 0, 2) }}</span>
                    </div>
                @endif
                <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ $team->name }}</h3>

                @if ($team->type === 'partner')
                    <span class="mt-2 inline-block px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-400">
                        Partner
                    </span>
                @else
                    <span class="mt-2 inline-block px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-zinc-700 dark:text-zinc-300">
                        Customer
                    </span>
                @endif
            </div>

            <dl class="mt-6 space-y-4 border-t border-gray-200 dark:border-zinc-800 pt-6">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Team ID</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">{{ $team->id }}</dd>
                </div>
                @if ($team->partner_slug)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-zinc-400">Partner Slug</dt>
                        <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">{{ $team->partner_slug }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Public Hash</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100 truncate max-w-32" title="{{ $team->public_hash }}">{{ $team->public_hash }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Personal Team</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">{{ $team->personal_team ? 'Yes' : 'No' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Created</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">{{ $team->created_at->format('M j, Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Last Updated</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">{{ $team->updated_at->diffForHumans() }}</dd>
                </div>
            </dl>

            <!-- Owner -->
            @if ($team->owner)
                <div class="mt-6 border-t border-gray-200 dark:border-zinc-800 pt-6">
                    <h4 class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-3">Owner</h4>
                    <div class="flex items-center gap-3">
                        <img class="h-10 w-10 rounded-full object-cover" src="{{ $team->owner->profile_photo_url }}" alt="{{ $team->owner->name }}">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $team->owner->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $team->owner->email }}</div>
                        </div>
                    </div>
                    <a
                        href="{{ route('admin.users.show', $team->owner) }}"
                        class="mt-3 block text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                    >
                        View Owner Profile &rarr;
                    </a>
                </div>
            @endif
        </div>

        <!-- Stats & Members -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Stats Summary -->
            <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Activity Summary</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $team->productFeeds->count() }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400">Product Feeds</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalJobs }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400">AI Jobs</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $photoStudioCount }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400">Photo Studio</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $team->users->count() }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400">Members</div>
                    </div>
                </div>

                <!-- Job Status Breakdown -->
                @if ($totalJobs > 0)
                    <div class="mt-4 grid grid-cols-4 gap-2">
                        <div class="text-center p-2 bg-green-50 dark:bg-green-500/10 rounded">
                            <div class="text-sm font-bold text-green-700 dark:text-green-400">{{ $jobStats['completed'] ?? 0 }}</div>
                            <div class="text-xs text-green-600 dark:text-green-500">Completed</div>
                        </div>
                        <div class="text-center p-2 bg-yellow-50 dark:bg-yellow-500/10 rounded">
                            <div class="text-sm font-bold text-yellow-700 dark:text-yellow-400">{{ $jobStats['queued'] ?? 0 }}</div>
                            <div class="text-xs text-yellow-600 dark:text-yellow-500">Queued</div>
                        </div>
                        <div class="text-center p-2 bg-blue-50 dark:bg-blue-500/10 rounded">
                            <div class="text-sm font-bold text-blue-700 dark:text-blue-400">{{ $jobStats['processing'] ?? 0 }}</div>
                            <div class="text-xs text-blue-600 dark:text-blue-500">Processing</div>
                        </div>
                        <div class="text-center p-2 bg-red-50 dark:bg-red-500/10 rounded">
                            <div class="text-sm font-bold text-red-700 dark:text-red-400">{{ $jobStats['failed'] ?? 0 }}</div>
                            <div class="text-xs text-red-600 dark:text-red-500">Failed</div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Team Members -->
            <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Members ({{ $team->users->count() }})</h3>

                @if ($team->users->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($team->users as $user)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <img class="h-10 w-10 rounded-full object-cover" src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">
                                            {{ $user->email }}
                                            @if ($user->id === $team->user_id)
                                                <span class="ml-1 text-amber-600 dark:text-amber-400">(Owner)</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <a
                                    href="{{ route('admin.users.show', $user) }}"
                                    class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                >
                                    View
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-zinc-400">This team has no members.</p>
                @endif
            </div>

            <!-- Product Feeds -->
            @if ($team->productFeeds->isNotEmpty())
                <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Product Feeds ({{ $team->productFeeds->count() }})</h3>

                    <div class="space-y-3">
                        @foreach ($team->productFeeds as $feed)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $feed->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        {{ $feed->language ?? 'Unknown language' }}
                                        &middot;
                                        {{ $feed->products_count ?? 0 }} products
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Partner Relationships -->
            @if ($team->type === 'partner' && $team->ownedTeams->isNotEmpty())
                <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Owned Customer Teams ({{ $team->ownedTeams->count() }})</h3>

                    <div class="space-y-3">
                        @foreach ($team->ownedTeams as $ownedTeam)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $ownedTeam->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        Created {{ $ownedTeam->created_at->format('M j, Y') }}
                                    </div>
                                </div>
                                <a
                                    href="{{ route('admin.teams.show', $ownedTeam) }}"
                                    class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                >
                                    View
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($team->parentTeam)
                <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Parent Partner</h3>

                    <div class="flex items-center justify-between p-3 bg-purple-50 dark:bg-purple-500/10 rounded-lg">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $team->parentTeam->name }}</div>
                            <div class="text-xs text-purple-600 dark:text-purple-400">Partner</div>
                        </div>
                        <a
                            href="{{ route('admin.teams.show', $team->parentTeam) }}"
                            class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                        >
                            View
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
