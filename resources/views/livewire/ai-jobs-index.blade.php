@php use App\Models\ProductAiJob; use App\Models\ProductAiTemplate; use Illuminate\Support\Str; @endphp
@php
    $statusStyles = [
        ProductAiJob::STATUS_QUEUED => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-400 border-yellow-300 dark:border-yellow-500/30',
        ProductAiJob::STATUS_PROCESSING => 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400 border-blue-300 dark:border-blue-500/30',
        ProductAiJob::STATUS_COMPLETED => 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400 border-green-300 dark:border-green-500/30',
        ProductAiJob::STATUS_FAILED => 'bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400 border-red-300 dark:border-red-500/30',
    ];

    $jobTypeStyles = [
        ProductAiJob::TYPE_TEMPLATE => 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-800 dark:text-indigo-400 border-indigo-300 dark:border-indigo-500/30',
        ProductAiJob::TYPE_PHOTO_STUDIO => 'bg-fuchsia-100 dark:bg-fuchsia-500/20 text-fuchsia-800 dark:text-fuchsia-400 border-fuchsia-300 dark:border-fuchsia-500/30',
    ];

@endphp

<div wire:poll.10s class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">AI Job Progress</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                Monitor queued and running AI generations. Refreshes every 10 seconds.
            </p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:space-x-3">
            <div>
                <label for="filter" class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-1">Scope</label>
                <select wire:model.live="filter" id="filter" class="rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 text-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="perPage" class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-1">Per page</label>
                <select wire:model.live.number="perPage" id="perPage" class="rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20 text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 sm:rounded-xl">
        <div class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
            <div class="grid grid-cols-12 px-4 py-3 bg-gray-50 dark:bg-zinc-800/50 text-xs font-semibold uppercase text-gray-600 dark:text-zinc-400 gap-2 rounded-t-xl">
                <div class="col-span-4">Product</div>
                <div class="col-span-2">Type</div>
                <div class="col-span-2">Status</div>
                <div class="col-span-2">Progress</div>
                <div class="col-span-2 text-right">Queued</div>
            </div>

            @forelse ($jobs as $job)
                <div wire:key="job-{{ $job->id }}" class="grid grid-cols-12 gap-2 px-4 py-3 text-sm text-gray-700 dark:text-zinc-300 border-t border-gray-100 dark:border-zinc-800">
                    <div class="col-span-4">
                        @if ($job->product)
                            @if ($job->product->hasSemanticUrl())
                                <a href="{{ $job->product->getUrl() }}" class="font-medium text-gray-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                    {{ $job->product->title ?: 'Untitled product' }}
                                </a>
                            @else
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $job->product->title ?: 'Untitled product' }}
                                </span>
                            @endif
                        @else
                            <span class="font-medium text-gray-900 dark:text-white">
                                Unknown product
                            </span>
                        @endif
                        <div class="text-xs text-gray-500 dark:text-zinc-500">
                            SKU: {{ $job->sku ?? '—' }}
                        </div>
                    </div>
                    <div class="col-span-2">
                        @php
                            $typeClasses = $jobTypeStyles[$job->job_type] ?? 'bg-gray-100 dark:bg-zinc-700 text-gray-800 dark:text-zinc-300 border-gray-300 dark:border-zinc-600';
                            $typeLabel = match ($job->job_type) {
                                ProductAiJob::TYPE_TEMPLATE => match ($job->template?->slug) {
                                    ProductAiTemplate::SLUG_USPS => 'USP',
                                    default => $job->template?->name ?? Str::headline($job->template?->slug ?? 'AI Template'),
                                },
                                ProductAiJob::TYPE_PHOTO_STUDIO => 'Photo Studio',
                                default => Str::headline($job->job_type),
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium {{ $typeClasses }}">
                            {{ $typeLabel }}
                        </span>
                    </div>
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-medium {{ $statusStyles[$job->status] ?? 'bg-gray-100 dark:bg-zinc-700 text-gray-800 dark:text-zinc-300 border-gray-300 dark:border-zinc-600' }}">
                            {{ Str::headline($job->status) }}
                        </span>
                        @if ($job->status === ProductAiJob::STATUS_FAILED && $job->friendlyErrorMessage())
                            <div class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $job->friendlyErrorMessage() }}
                            </div>
                        @endif
                    </div>
                    <div class="col-span-2">
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 h-2 rounded-full bg-gray-200 dark:bg-zinc-700 overflow-hidden">
                                <div class="h-2 bg-amber-500" style="width: {{ $job->progress }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-zinc-500 w-10 text-right">{{ $job->progress }}%</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-zinc-500 mt-1">
                            Attempts: {{ $job->attempts }}
                        </div>
                    </div>
                    <div class="col-span-2 text-right text-xs text-gray-500 dark:text-zinc-500">
                        <div>Queued {{ optional($job->queued_at)->diffForHumans() ?? 'N/A' }}</div>
                        @php($runtimeLabel = $job->runtimeForHumans())
                        @if ($runtimeLabel)
                            <div>
                                Runtime {{ $runtimeLabel }}@if (! $job->finished_at) <span class="text-gray-400 dark:text-zinc-600">(running)</span>@endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-sm text-gray-600 dark:text-zinc-400">
                    @if ($filter === 'active')
                        No active jobs right now. Trigger an AI request to see it appear here.
                    @elseif ($filter === 'failed')
                        No failed jobs found. If an AI run errors we'll display details here.
                    @elseif ($filter === 'completed')
                        No completed jobs yet. Run an AI generation to see results here.
                    @else
                        No AI jobs found yet.
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $jobs->links() }}
    </div>
</div>
