<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50 dark:bg-surface-dark relative overflow-hidden">
    <!-- Subtle background pattern (dark mode only) -->
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] dark:block hidden"></div>

    <!-- Ambient glow effect (dark mode only) -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] hidden dark:block">
        <div class="absolute inset-0 bg-gradient-to-r from-amber-500/5 via-orange-500/5 to-amber-500/5 blur-3xl rounded-full"></div>
    </div>

    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-6 px-6 py-8 bg-white dark:bg-zinc-900/80 dark:backdrop-blur-xl shadow-xl dark:shadow-none overflow-hidden sm:rounded-2xl border border-gray-200 dark:border-zinc-800">
        <!-- Subtle top gradient shine (dark mode) -->
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-zinc-600 to-transparent hidden dark:block"></div>
        {{ $slot }}
    </div>

    <!-- Theme toggle in corner -->
    <div class="absolute top-4 right-4 z-20">
        <x-theme-toggle />
    </div>
</div>
