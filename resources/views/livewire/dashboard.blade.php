@php use App\Models\ProductAiJob; use Illuminate\Support\Str; @endphp
@php
    $statusStyles = [
        ProductAiJob::STATUS_QUEUED => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-400',
        ProductAiJob::STATUS_PROCESSING => 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400',
        ProductAiJob::STATUS_COMPLETED => 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400',
        ProductAiJob::STATUS_FAILED => 'bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400',
    ];
@endphp

<div wire:poll.10s class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
            Overview of your team's AI jobs and recent activity.
        </p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-zinc-900/50 rounded-xl p-4 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800">
            <div class="text-sm font-medium text-gray-500 dark:text-zinc-400">Queued</div>
            <div class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ $jobStats['queued'] }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/50 rounded-xl p-4 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800">
            <div class="text-sm font-medium text-gray-500 dark:text-zinc-400">Processing</div>
            <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $jobStats['processing'] }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/50 rounded-xl p-4 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800">
            <div class="text-sm font-medium text-gray-500 dark:text-zinc-400">Completed Today</div>
            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $jobStats['completed_today'] }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/50 rounded-xl p-4 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800">
            <div class="text-sm font-medium text-gray-500 dark:text-zinc-400">Failed Today</div>
            <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ $jobStats['failed_today'] }}</div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div class="grid lg:grid-cols-2 gap-8">
        {{-- Recent Jobs --}}
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 rounded-xl">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent AI Jobs</h2>
                    <a href="{{ route('ai-jobs.index') }}" class="text-sm text-amber-600 dark:text-amber-400 hover:underline">
                        View all
                    </a>
                </div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                @forelse ($recentJobs as $job)
                    <div wire:key="job-{{ $job->id }}" class="px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $job->product?->title ?: 'Unknown product' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-zinc-500">
                                    {{ Str::headline($job->job_type) }} &bull; {{ optional($job->queued_at)->diffForHumans() }}
                                </div>
                            </div>
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$job->status] ?? 'bg-gray-100 dark:bg-zinc-700' }}">
                                {{ Str::headline($job->status) }}
                            </span>
                        </div>
                        @if ($job->status === ProductAiJob::STATUS_PROCESSING)
                            <div class="mt-2">
                                <div class="h-1.5 rounded-full bg-gray-200 dark:bg-zinc-700 overflow-hidden">
                                    <div class="h-1.5 bg-amber-500" style="width: {{ $job->progress }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                        No AI jobs yet. Start by running a template or using Photo Studio.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Activity Feed --}}
        <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 rounded-xl">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-zinc-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Team Activity</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-zinc-800 max-h-[600px] overflow-y-auto">
                @forelse ($activities as $activity)
                    <div wire:key="activity-{{ $activity->id }}" class="px-4 py-3">
                        <div class="flex gap-3">
                            @if ($activity->user)
                                <img class="h-8 w-8 rounded-full object-cover flex-shrink-0"
                                     src="{{ $activity->user->profile_photo_url }}"
                                     alt="{{ $activity->user->name }}">
                            @else
                                <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                                    <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    {{ $activity->description }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-zinc-500">
                                    {{ $activity->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                        No activity yet. Actions like importing feeds, running AI jobs, and team changes will appear here.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
