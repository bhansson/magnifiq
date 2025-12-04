@use('App\Livewire\AdminJobs')

<div wire:poll.visible.5s>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Queue Jobs</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Monitor pending and failed queue jobs</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
            &larr; Back to Dashboard
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-6 bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-xl">
            {{ session('message') }}
        </div>
    @endif

    {{-- Stats Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-yellow-50 dark:bg-yellow-500/10 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ $stats['pending_total'] }}</div>
            <div class="text-sm text-yellow-600 dark:text-yellow-500">Pending Jobs</div>
        </div>
        <div class="bg-red-50 dark:bg-red-500/10 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $stats['failed_total'] }}</div>
            <div class="text-sm text-red-600 dark:text-red-500">Failed Jobs</div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-500/10 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ count($stats['pending_by_queue']) }}</div>
            <div class="text-sm text-blue-600 dark:text-blue-500">Active Queues</div>
        </div>
        <div class="bg-gray-50 dark:bg-zinc-800/50 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-gray-700 dark:text-zinc-300">{{ $stats['pending_total'] + $stats['failed_total'] }}</div>
            <div class="text-sm text-gray-600 dark:text-zinc-400">Total Jobs</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200 dark:border-zinc-800">
        <nav class="-mb-px flex space-x-8">
            <button
                wire:click="$set('tab', 'pending')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $tab === 'pending' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
            >
                Pending Jobs
                @if ($stats['pending_total'] > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400">
                        {{ $stats['pending_total'] }}
                    </span>
                @endif
            </button>
            <button
                wire:click="$set('tab', 'failed')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $tab === 'failed' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
            >
                Failed Jobs
                @if ($stats['failed_total'] > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400">
                        {{ $stats['failed_total'] }}
                    </span>
                @endif
            </button>
        </nav>
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search in job payload..."
                class="w-full border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
            >
        </div>
        <div>
            <select
                wire:model.live="queueFilter"
                class="w-full sm:w-auto border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"
            >
                <option value="">All Queues</option>
                @foreach ($queues as $queue)
                    <option value="{{ $queue }}">{{ $queue }}</option>
                @endforeach
            </select>
        </div>
        @if ($tab === 'failed' && $stats['failed_total'] > 0)
            <div class="flex gap-2">
                <button
                    wire:click="retryAllFailedJobs"
                    wire:confirm="Are you sure you want to retry all failed jobs?"
                    class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-xl transition-colors"
                >
                    Retry All
                </button>
                <button
                    wire:click="flushFailedJobs"
                    wire:confirm="Are you sure you want to delete all failed jobs? This cannot be undone."
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors"
                >
                    Flush All
                </button>
            </div>
        @endif
    </div>

    {{-- Pending Jobs Table --}}
    @if ($tab === 'pending')
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                <thead class="bg-gray-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Job</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Queue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Attempts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Available At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900/50 divide-y divide-gray-200 dark:divide-zinc-800">
                    @forelse ($pendingJobs as $job)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/30" wire:key="pending-{{ $job->id }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-zinc-100">
                                {{ $job->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ AdminJobs::parseJobClass($job->payload) }}
                                </div>
                                @php $jobData = AdminJobs::parseJobData($job->payload); @endphp
                                @if ($jobData && ($jobData['maxTries'] || $jobData['timeout']))
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        @if ($jobData['maxTries'])
                                            Max tries: {{ $jobData['maxTries'] }}
                                        @endif
                                        @if ($jobData['timeout'])
                                            • Timeout: {{ $jobData['timeout'] }}s
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400">
                                    {{ $job->queue }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                                {{ $job->attempts }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                                @if ($job->reserved_at)
                                    <span class="text-amber-600 dark:text-amber-400" title="Currently reserved">
                                        Reserved {{ AdminJobs::timeAgo($job->reserved_at) }}
                                    </span>
                                @else
                                    {{ AdminJobs::formatTimestamp($job->available_at) }}
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                                <span title="{{ AdminJobs::formatTimestamp($job->created_at) }}">
                                    {{ AdminJobs::timeAgo($job->created_at) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-zinc-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                No pending jobs in the queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pendingJobs && $pendingJobs->hasPages())
            <div class="mt-4">
                {{ $pendingJobs->links() }}
            </div>
        @endif
    @endif

    {{-- Failed Jobs Table --}}
    @if ($tab === 'failed')
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                <thead class="bg-gray-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Job</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Queue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Exception</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Failed At</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900/50 divide-y divide-gray-200 dark:divide-zinc-800">
                    @forelse ($failedJobs as $job)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/30" wire:key="failed-{{ $job->id }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-mono text-gray-900 dark:text-zinc-100">{{ $job->id }}</div>
                                <div class="text-xs font-mono text-gray-500 dark:text-zinc-400 truncate max-w-[100px]" title="{{ $job->uuid }}">
                                    {{ Str::limit($job->uuid, 8) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ AdminJobs::parseJobClass($job->payload) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400">
                                    {{ $job->connection }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400">
                                    {{ $job->queue }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div x-data="{ expanded: false }">
                                    <button
                                        @click="expanded = !expanded"
                                        class="text-left max-w-xs"
                                    >
                                        <p class="text-sm text-red-600 dark:text-red-400" :class="{ 'truncate': !expanded }">
                                            {{ Str::limit($job->exception, 100) }}
                                        </p>
                                        <span class="text-xs text-gray-500 dark:text-zinc-400 hover:underline" x-text="expanded ? 'Show less' : 'Show more'"></span>
                                    </button>
                                    <div x-show="expanded" x-cloak class="mt-2">
                                        <pre class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 p-3 rounded-lg overflow-x-auto max-h-60 whitespace-pre-wrap break-words">{{ $job->exception }}</pre>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                                <span title="{{ $job->failed_at }}">
                                    {{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    <button
                                        wire:click="retryJob('{{ $job->uuid }}')"
                                        class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                        title="Retry this job"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button
                                        wire:click="deleteFailedJob({{ $job->id }})"
                                        wire:confirm="Are you sure you want to delete this failed job?"
                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                        title="Delete this job"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-zinc-400">
                                <svg class="mx-auto h-12 w-12 text-green-400 dark:text-green-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                No failed jobs. Everything is running smoothly!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($failedJobs && $failedJobs->hasPages())
            <div class="mt-4">
                {{ $failedJobs->links() }}
            </div>
        @endif
    @endif
</div>
