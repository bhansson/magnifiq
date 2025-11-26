<section class="relative py-32 px-6 lg:px-8 overflow-hidden bg-zinc-950">
    <!-- Background orb glow -->
    <div class="absolute inset-0">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px]">
            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/20 via-orange-500/10 to-transparent blur-3xl rounded-full"></div>
        </div>
    </div>

    <div class="relative mx-auto max-w-4xl text-center">
        <!-- Badge -->
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800/50 border border-zinc-700/50 rounded-full mb-8">
            <div class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
            </div>
            <span class="text-sm text-zinc-400">Limited Time Offer</span>
        </div>

        <h2 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white tracking-tight leading-tight mb-6">
            Ready to scale your catalog with AI?
        </h2>

        <p class="text-xl text-zinc-400 max-w-2xl mx-auto leading-relaxed mb-12">
            Join marketing teams who've already automated their product content. Start free, no credit card required.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
            <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 px-10 py-5 text-lg font-medium text-black bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-300 rounded-full hover:shadow-xl hover:shadow-amber-500/25 transition-all duration-300">
                Start Free Trial
                <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-lg text-zinc-400 hover:text-white transition-colors">
                Already have an account? <span class="underline underline-offset-4">Log in</span>
            </a>
        </div>

        <!-- Trust Indicators -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-8 text-zinc-500">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Free 14-day trial</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>No credit card needed</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Cancel anytime</span>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-[#0a0a0a] border-t border-zinc-800/50 py-16 px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative">
                        <svg class="w-9 h-9" viewBox="0 0 32 32" fill="none">
                            <defs>
                                <linearGradient id="footerBrandGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#FBBF24"/>
                                    <stop offset="100%" style="stop-color:#F97316"/>
                                </linearGradient>
                            </defs>
                            <rect x="2" y="2" width="28" height="28" rx="6" ry="6" fill="url(#footerBrandGradient)"/>
                            <path d="M8 22V12l4 6 4-6v10" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <circle cx="22" cy="10" r="1.5" fill="white"/>
                            <path d="M22 7v6M19 10h6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="22" cy="18" r="1" fill="rgba(255,255,255,0.7)"/>
                            <circle cx="22" cy="22" r="1" fill="rgba(255,255,255,0.5)"/>
                        </svg>
                        <div class="absolute inset-0 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 blur-lg opacity-30"></div>
                    </div>
                    <span class="text-xl font-semibold text-white">Magnifiq</span>
                </div>
                <p class="text-zinc-500 max-w-sm leading-relaxed">
                    AI-powered product catalog management platform. Transform your product data into compelling marketing assets in seconds.
                </p>
            </div>

            <!-- Product -->
            <div>
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Product</h3>
                <ul class="space-y-3 text-zinc-500">
                    <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                    <li><a href="#how-it-works" class="hover:text-white transition-colors">How it Works</a></li>
                    <li><a href="#use-cases" class="hover:text-white transition-colors">Use Cases</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Account</h3>
                <ul class="space-y-3 text-zinc-500">
                    <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Login</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Sign Up</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-16 pt-8 border-t border-zinc-800/50 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-zinc-600 text-sm">
                &copy; {{ date('Y') }} Magnifiq. All rights reserved.
            </p>
            <p class="text-zinc-700 text-sm">
                Trusted by e-commerce teams managing 500K+ products worldwide
            </p>
        </div>
    </div>
</footer>
