<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">User Details</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Viewing {{ $user->name }}</p>
        </div>
        <a href="{{ route('admin.users') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
            &larr; Back to Users
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Info Card -->
        <div class="lg:col-span-1 bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <div class="text-center">
                <img class="h-24 w-24 rounded-full object-cover mx-auto" src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
                <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ $user->name }}</h3>
                <p class="text-sm text-gray-500 dark:text-zinc-400">{{ $user->email }}</p>

                @php
                    $roleColors = [
                        'superadmin' => 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-400',
                        'admin' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400',
                        'user' => 'bg-gray-100 text-gray-800 dark:bg-zinc-700 dark:text-zinc-300',
                    ];
                @endphp
                <span class="mt-2 inline-block px-3 py-1 text-xs font-medium rounded-full {{ $roleColors[$user->role] ?? $roleColors['user'] }}">
                    {{ ucfirst($user->role) }}
                </span>
            </div>

            <dl class="mt-6 space-y-4 border-t border-gray-200 dark:border-zinc-800 pt-6">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">User ID</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">{{ $user->id }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Email Verified</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">
                        @if ($user->email_verified_at)
                            <span class="text-green-600 dark:text-green-400">{{ $user->email_verified_at->format('M j, Y') }}</span>
                        @else
                            <span class="text-red-600 dark:text-red-400">Not verified</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Two-Factor Auth</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">
                        @if ($user->two_factor_secret)
                            <span class="text-green-600 dark:text-green-400">Enabled</span>
                        @else
                            <span class="text-gray-400 dark:text-zinc-500">Disabled</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Created</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">{{ $user->created_at->format('M j, Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Last Updated</dt>
                    <dd class="text-sm text-gray-900 dark:text-zinc-100">{{ $user->updated_at->diffForHumans() }}</dd>
                </div>
            </dl>
        </div>

        <!-- Teams & Activity -->
        <div class="lg:col-span-2 space-y-6">
            <!-- AI Jobs Summary -->
            <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">AI Jobs Summary</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalJobs }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400">Total Jobs</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-500/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $jobStats['completed'] ?? 0 }}</div>
                        <div class="text-xs text-green-600 dark:text-green-500">Completed</div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-500/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ ($jobStats['queued'] ?? 0) + ($jobStats['processing'] ?? 0) }}</div>
                        <div class="text-xs text-blue-600 dark:text-blue-500">In Progress</div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-500/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $jobStats['failed'] ?? 0 }}</div>
                        <div class="text-xs text-red-600 dark:text-red-500">Failed</div>
                    </div>
                </div>
            </div>

            <!-- Teams Membership -->
            <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Teams ({{ $user->teams->count() }})</h3>

                @if ($user->teams->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($user->teams as $team)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $team->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">
                                            @if ($team->user_id === $user->id)
                                                <span class="text-amber-600 dark:text-amber-400">Owner</span>
                                            @else
                                                Member
                                            @endif
                                            &middot;
                                            <span class="capitalize">{{ $team->type }}</span>
                                        </div>
                                    </div>
                                </div>
                                <a
                                    href="{{ route('admin.teams.show', $team) }}"
                                    class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                >
                                    View Team
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-zinc-400">This user is not a member of any teams.</p>
                @endif
            </div>

            <!-- Owned Teams -->
            @if ($user->ownedTeams->isNotEmpty())
                <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Owned Teams ({{ $user->ownedTeams->count() }})</h3>

                    <div class="space-y-3">
                        @foreach ($user->ownedTeams as $team)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $team->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        <span class="capitalize">{{ $team->type }}</span>
                                        &middot;
                                        Created {{ $team->created_at->format('M j, Y') }}
                                    </div>
                                </div>
                                <a
                                    href="{{ route('admin.teams.show', $team) }}"
                                    class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                >
                                    View Team
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
