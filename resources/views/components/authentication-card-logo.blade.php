<a href="/" class="flex items-center gap-3 group">
    @if(isset($partnerTeam) && $partnerTeam && $partnerTeam->logo_path)
        <img src="{{ asset('storage/' . $partnerTeam->logo_path) }}" alt="{{ $partnerTeam->name }}" class="h-32 w-auto max-w-xs object-contain" />
    @else
        <div class="relative">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                <svg class="w-8 h-8 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="absolute inset-0 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 blur-xl opacity-50 group-hover:opacity-75 transition-opacity"></div>
        </div>
        <span class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
            Magnifiq
        </span>
    @endif
</a>
