<nav x-data="{ scrolled: false, mobileMenuOpen: false }"
     @scroll.window="scrolled = window.pageYOffset > 50"
     :class="scrolled ? 'bg-black/80 backdrop-blur-xl border-b border-white/5' : 'bg-transparent'"
     class="fixed top-0 left-0 right-0 z-50 transition-all duration-500">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="flex items-center justify-between py-5">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="relative">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="absolute inset-0 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 blur-lg opacity-50 group-hover:opacity-75 transition-opacity"></div>
                    </div>
                    <span class="text-xl font-semibold text-white tracking-tight">
                        Magnifiq
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm text-zinc-400 hover:text-white transition-colors duration-200">
                    Features
                </a>
                <a href="#how-it-works" class="text-sm text-zinc-400 hover:text-white transition-colors duration-200">
                    How It Works
                </a>
                <a href="#use-cases" class="text-sm text-zinc-400 hover:text-white transition-colors duration-200">
                    Use Cases
                </a>
            </div>

            <!-- Auth Links -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden md:inline-flex items-center px-5 py-2.5 text-sm font-medium text-black bg-gradient-to-r from-amber-400 to-orange-400 rounded-full hover:from-amber-300 hover:to-orange-300 transition-all duration-200 shadow-lg shadow-amber-500/25">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden md:block text-sm text-zinc-400 hover:text-white transition-colors duration-200">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-black bg-white rounded-full hover:bg-zinc-100 transition-all duration-200 border border-white/20">
                        Sign up
                    </a>
                @endauth

                <!-- Mobile Menu Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-zinc-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden py-4 border-t border-white/10">
            <div class="flex flex-col gap-4">
                <a href="#features" @click="mobileMenuOpen = false" class="text-sm text-zinc-400 hover:text-white transition-colors">
                    Features
                </a>
                <a href="#how-it-works" @click="mobileMenuOpen = false" class="text-sm text-zinc-400 hover:text-white transition-colors">
                    How It Works
                </a>
                <a href="#use-cases" @click="mobileMenuOpen = false" class="text-sm text-zinc-400 hover:text-white transition-colors">
                    Use Cases
                </a>
                @guest
                    <a href="{{ route('login') }}" class="text-sm text-zinc-400 hover:text-white transition-colors">
                        Log in
                    </a>
                @endguest
            </div>
        </div>
    </div>
</nav>
