@use('Illuminate\Support\Facades\Storage')

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Admin Dashboard</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">System overview and statistics</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <!-- Environment & AI Models Card -->
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Environment & AI Models
            </h3>

            <dl class="space-y-3">
                <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Environment</dt>
                    <dd>
                        @php
                            $envColors = [
                                'production' => 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400',
                                'staging' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-400',
                                'local' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400',
                            ];
                            $envColor = $envColors[$envConfig['environment']] ?? 'bg-gray-100 text-gray-800 dark:bg-zinc-700 dark:text-zinc-300';
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $envColor }}">
                            {{ ucfirst($envConfig['environment']) }}
                        </span>
                    </dd>
                </div>

                <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Debug Mode</dt>
                    <dd>
                        @if ($envConfig['debug'])
                            <span class="flex items-center gap-1 text-sm text-yellow-600 dark:text-yellow-400">
                                <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                                On
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                Off
                            </span>
                        @endif
                    </dd>
                </div>

                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3">
                    <dt class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">AI Models</dt>
                </div>

                <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Text</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">
                        <span class="text-gray-400 dark:text-zinc-500">{{ $envConfig['chat_driver'] ?? '—' }}/</span>{{ $envConfig['chat_model'] ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Vision</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">
                        <span class="text-gray-400 dark:text-zinc-500">{{ $envConfig['vision_driver'] ?? '—' }}/</span>{{ $envConfig['vision_model'] ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between items-center">
                    <dt class="text-sm text-gray-500 dark:text-zinc-400">Default Image Model</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-zinc-100">{{ $envConfig['default_image_model'] ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Users Statistics Card -->
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Users
                </h3>
                <a href="{{ route('admin.users') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
                    View All &rarr;
                </a>
            </div>

            <div class="flex items-baseline gap-2 mb-4">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($userStats['total']) }}</span>
                <span class="text-sm text-gray-500 dark:text-zinc-400">total users</span>
            </div>

            <dl class="space-y-2 mb-4">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Superadmins</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $userStats['by_role']['superadmin'] }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Admins</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $userStats['by_role']['admin'] }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Users</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $userStats['by_role']['user'] }}</dd>
                </div>
            </dl>

            <div class="border-t border-gray-200 dark:border-zinc-800 pt-3">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-500 dark:text-zinc-400">This week</span>
                    <span class="text-green-600 dark:text-green-400">+{{ $userStats['created_this_week'] }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-zinc-400">This month</span>
                    <span class="text-green-600 dark:text-green-400">+{{ $userStats['created_this_month'] }}</span>
                </div>
            </div>

            @if ($userStats['recent']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                    <h4 class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Recent Registrations</h4>
                    <ul class="space-y-2">
                        @foreach ($userStats['recent'] as $user)
                            <li>
                                <a href="{{ route('admin.users.show', $user) }}" class="flex justify-between items-center text-sm hover:bg-gray-50 dark:hover:bg-zinc-800 -mx-2 px-2 py-1 rounded transition-colors">
                                    <span class="text-gray-900 dark:text-zinc-100 truncate">{{ $user->name }}</span>
                                    <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ $user->created_at->diffForHumans() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Teams Statistics Card -->
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Teams
                </h3>
                <a href="{{ route('admin.teams') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
                    View All &rarr;
                </a>
            </div>

            <div class="flex items-baseline gap-2 mb-4">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($teamStats['total']) }}</span>
                <span class="text-sm text-gray-500 dark:text-zinc-400">total teams</span>
            </div>

            <dl class="space-y-2 mb-4">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Customer teams</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $teamStats['by_type']['customer'] }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Partner teams</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $teamStats['by_type']['partner'] }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">With product feeds</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $teamStats['with_product_feeds'] }}</dd>
                </div>
            </dl>

            <div class="border-t border-gray-200 dark:border-zinc-800 pt-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-zinc-400">Created this week</span>
                    <span class="text-green-600 dark:text-green-400">+{{ $teamStats['created_this_week'] }}</span>
                </div>
            </div>

            @if ($teamStats['recent']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                    <h4 class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Recent Teams</h4>
                    <ul class="space-y-2">
                        @foreach ($teamStats['recent'] as $team)
                            <li>
                                <a href="{{ route('admin.teams.show', $team) }}" class="flex justify-between items-center text-sm hover:bg-gray-50 dark:hover:bg-zinc-800 -mx-2 px-2 py-1 rounded transition-colors">
                                    <span class="text-gray-900 dark:text-zinc-100 truncate">{{ $team->name }}</span>
                                    <span class="px-1.5 py-0.5 text-xs rounded {{ $team->type === 'partner' ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400' : 'bg-gray-100 text-gray-600 dark:bg-zinc-700 dark:text-zinc-400' }}">
                                        {{ $team->type }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- AI Jobs Statistics Card -->
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    AI Jobs
                </h3>
                <a href="{{ route('admin.jobs') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
                    View All &rarr;
                </a>
            </div>

            <div class="flex items-baseline gap-2 mb-4">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($jobStats['total']) }}</span>
                <span class="text-sm text-gray-500 dark:text-zinc-400">total jobs</span>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-yellow-50 dark:bg-yellow-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-yellow-700 dark:text-yellow-400">{{ $jobStats['by_status']['queued'] }}</div>
                    <div class="text-xs text-yellow-600 dark:text-yellow-500">Queued</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-blue-700 dark:text-blue-400">{{ $jobStats['by_status']['processing'] }}</div>
                    <div class="text-xs text-blue-600 dark:text-blue-500">Processing</div>
                </div>
                <div class="bg-green-50 dark:bg-green-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-green-700 dark:text-green-400">{{ $jobStats['by_status']['completed'] }}</div>
                    <div class="text-xs text-green-600 dark:text-green-500">Completed</div>
                </div>
                <div class="bg-red-50 dark:bg-red-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-red-700 dark:text-red-400">{{ $jobStats['by_status']['failed'] }}</div>
                    <div class="text-xs text-red-600 dark:text-red-500">Failed</div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-zinc-800 pt-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-zinc-400">Created today</span>
                    <span class="font-medium text-gray-900 dark:text-zinc-100">{{ $jobStats['created_today'] }}</span>
                </div>
            </div>

            @if ($jobStats['recent_failed']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                    <h4 class="text-xs text-red-500 dark:text-red-400 uppercase tracking-wider mb-2">Recent Failures</h4>
                    <ul class="space-y-2">
                        @foreach ($jobStats['recent_failed'] as $job)
                            <li class="text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-900 dark:text-zinc-100">{{ ucfirst(str_replace('_', ' ', $job->job_type)) }}</span>
                                    <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ $job->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($job->last_error)
                                    <p class="text-xs text-red-500 dark:text-red-400 truncate mt-0.5">{{ Str::limit($job->last_error, 50) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Store Sync Jobs Card -->
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Store Sync Jobs
            </h3>

            <div class="flex items-baseline gap-2 mb-4">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($storeSyncStats['total']) }}</span>
                <span class="text-sm text-gray-500 dark:text-zinc-400">total syncs</span>
            </div>

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-green-50 dark:bg-green-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-green-700 dark:text-green-400">{{ $storeSyncStats['by_status']['completed'] }}</div>
                    <div class="text-xs text-green-600 dark:text-green-500">Completed</div>
                </div>
                <div class="bg-red-50 dark:bg-red-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-red-700 dark:text-red-400">{{ $storeSyncStats['by_status']['failed'] }}</div>
                    <div class="text-xs text-red-600 dark:text-red-500">Failed</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-500/10 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-blue-700 dark:text-blue-400">{{ $storeSyncStats['by_status']['processing'] }}</div>
                    <div class="text-xs text-blue-600 dark:text-blue-500">Processing</div>
                </div>
            </div>

            <dl class="space-y-2 mb-4">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Syncs today</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $storeSyncStats['synced_today'] }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Products synced today</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ number_format($storeSyncStats['products_synced_today']) }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500 dark:text-zinc-400">Syncs this week</dt>
                    <dd class="font-medium text-gray-900 dark:text-zinc-100">{{ $storeSyncStats['synced_this_week'] }}</dd>
                </div>
            </dl>

            @if ($storeSyncStats['recent_failed']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                    <h4 class="text-xs text-red-500 dark:text-red-400 uppercase tracking-wider mb-2">Recent Failures</h4>
                    <ul class="space-y-2">
                        @foreach ($storeSyncStats['recent_failed'] as $job)
                            <li class="text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-900 dark:text-zinc-100 truncate">{{ $job->storeConnection?->store_identifier ?? 'Unknown' }}</span>
                                    <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ $job->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($job->error_message)
                                    <p class="text-xs text-red-500 dark:text-red-400 truncate mt-0.5" title="{{ $job->error_message }}">{{ Str::limit($job->error_message, 60) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($storeSyncStats['recent']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                    <h4 class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Recent Syncs</h4>
                    <ul class="space-y-1.5 max-h-48 overflow-y-auto">
                        @foreach ($storeSyncStats['recent'] as $job)
                            <li class="flex items-center justify-between text-sm py-1">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if ($job->status === 'completed')
                                        <span class="w-2 h-2 bg-green-500 rounded-full flex-shrink-0"></span>
                                    @elseif ($job->status === 'failed')
                                        <span class="w-2 h-2 bg-red-500 rounded-full flex-shrink-0"></span>
                                    @else
                                        <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse flex-shrink-0"></span>
                                    @endif
                                    <span class="text-gray-900 dark:text-zinc-100 truncate">{{ $job->storeConnection?->store_identifier ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400 flex-shrink-0">
                                    <span>{{ $job->products_synced ?? 0 }} products</span>
                                    <span>{{ $job->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Photo Studio Statistics Card -->
        <div wire:poll.visible.5s="refreshPhotoStudio" class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl p-6 lg:col-span-2 xl:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Photo Studio
            </h3>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($photoStudioStats['total']) }}</div>
                    <div class="text-sm text-gray-500 dark:text-zinc-400">Total generations</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ number_format($photoStudioStats['created_this_week']) }}</div>
                    <div class="text-sm text-gray-500 dark:text-zinc-400">This week</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($photoStudioStats['created_this_month']) }}</div>
                    <div class="text-sm text-gray-500 dark:text-zinc-400">This month</div>
                </div>
            </div>

            @if ($photoStudioStats['recent']->isNotEmpty())
                <div class="border-t border-gray-200 dark:border-zinc-800 pt-4">
                    <h4 class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-3">Recent Generations</h4>
                    <div class="grid grid-cols-5 gap-3">
                        @foreach ($photoStudioStats['recent'] as $generation)
                            <div class="text-center">
                                <div class="aspect-square bg-gray-100 dark:bg-zinc-800 rounded-lg overflow-hidden mb-1">
                                    @php
                                        $imageUrl = null;
                                        if ($generation->storage_path) {
                                            try {
                                                $imageUrl = Storage::disk($generation->storage_disk ?? 's3')->url($generation->storage_path);
                                            } catch (\Exception $e) {
                                                // Storage disk not configured
                                            }
                                        }
                                    @endphp
                                    @if ($imageUrl)
                                        <img
                                            src="{{ $imageUrl }}"
                                            alt="Generation"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-zinc-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400 truncate" title="{{ $generation->model ?? 'Unknown' }}">{{ $generation->model ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400 truncate" title="{{ $generation->team?->name ?? 'Unknown team' }}">{{ $generation->team?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-400 dark:text-zinc-500">{{ $generation->created_at->diffForHumans() }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
