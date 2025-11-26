<a href="/" class="flex items-center gap-3 group">
    @if(isset($partnerTeam) && $partnerTeam && $partnerTeam->logo_path)
        <img src="{{ asset('storage/' . $partnerTeam->logo_path) }}" alt="{{ $partnerTeam->name }}" class="h-32 w-auto max-w-xs object-contain" />
    @else
        <div class="relative">
            <div class="w-14 h-14 flex items-center justify-center">
                <svg class="w-14 h-14" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="authBrandGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#FBBF24"/>
                            <stop offset="100%" style="stop-color:#F97316"/>
                        </linearGradient>
                    </defs>
                    <!-- Rounded square background -->
                    <rect x="2" y="2" width="28" height="28" rx="6" ry="6" fill="url(#authBrandGradient)"/>
                    <!-- Stylized "M" representing Magnifiq -->
                    <path d="M8 22V12l4 6 4-6v10" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <!-- AI sparkle/star accent -->
                    <circle cx="22" cy="10" r="1.5" fill="white"/>
                    <path d="M22 7v6M19 10h6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    <!-- Small decorative dots suggesting data/AI -->
                    <circle cx="22" cy="18" r="1" fill="rgba(255,255,255,0.7)"/>
                    <circle cx="22" cy="22" r="1" fill="rgba(255,255,255,0.5)"/>
                </svg>
            </div>
            <div class="absolute inset-0 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 blur-xl opacity-50 group-hover:opacity-75 transition-opacity"></div>
        </div>
        <span class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
            Magnifiq
        </span>
    @endif
</a>
