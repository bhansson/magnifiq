@props([
    'compositionMode' => 'scene_composition',
    'compositionModes' => [],
])

<div>
    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose what to create</p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($compositionModes as $modeKey => $modeConfig)
            @php
                $minImages = $modeConfig['min_images'] ?? 1;
                $maxImages = $modeConfig['max_images'] ?? null;
                $imageHint = $maxImages === 1 ? '1 image' : ($minImages === 1 ? '1+ images' : "{$minImages}+ images");
            @endphp
            <button
                type="button"
                wire:click="$set('compositionMode', '{{ $modeKey }}')"
                @class([
                    'relative rounded-2xl border-2 p-4 text-left transition-all',
                    'border-amber-400 dark:border-amber-500 bg-amber-50 dark:bg-amber-500/10 ring-2 ring-amber-400/50' => $compositionMode === $modeKey,
                    'border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 hover:border-gray-300 dark:hover:border-zinc-600' => $compositionMode !== $modeKey,
                ])
            >
                <div class="flex items-start gap-3">
                    <div @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400' => $compositionMode === $modeKey,
                        'bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400' => $compositionMode !== $modeKey,
                    ])>
                        @if ($modeConfig['icon'] === 'sparkles')
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>
                        @elseif ($modeConfig['icon'] === 'user-group')
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        @elseif ($modeConfig['icon'] === 'users')
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                        @elseif ($modeConfig['icon'] === 'viewfinder-circle')
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p @class([
                            'font-semibold',
                            'text-amber-900 dark:text-amber-300' => $compositionMode === $modeKey,
                            'text-gray-900 dark:text-white' => $compositionMode !== $modeKey,
                        ])>{{ $modeConfig['label'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $modeConfig['description'] }}</p>
                        <span @class([
                            'mt-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium',
                            'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' => $compositionMode === $modeKey,
                            'bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-400' => $compositionMode !== $modeKey,
                        ])>
                            {{ $imageHint }}
                        </span>
                    </div>
                </div>
                @if ($compositionMode === $modeKey)
                    <div class="absolute -right-1 -top-1">
                        <span class="flex size-5 items-center justify-center rounded-full bg-amber-500 text-white">
                            <svg class="size-3" viewBox="0 0 20 20" fill="none"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                    </div>
                @endif
            </button>
        @endforeach
    </div>
</div>
