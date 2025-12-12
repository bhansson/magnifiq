@if(session('shopify_pending_install'))
    <!-- Shopify Installation Banner -->
    <div class="bg-indigo-600 px-4 py-3 text-white">
        <div class="mx-auto max-w-7xl flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M15.337 7.463a.749.749 0 0 0-.674-.418H8.664l-.174-.87A.75.75 0 0 0 7.754 5.5H4.75a.75.75 0 0 0 0 1.5h2.457l1.466 7.328a.75.75 0 0 0 .735.672h6.342a.75.75 0 0 0 .728-.572l1.524-6a.75.75 0 0 0-.665-.965zm-1.336 6.037H9.873l-.932-4.655h5.604l-1.544 4.655zM10.5 18.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm6 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                </svg>
                <span class="text-sm font-medium">
                    Installing Magnifiq for <strong>{{ session('shopify_pending_install.shop') }}</strong>
                </span>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('login') }}" class="text-sm font-semibold underline hover:no-underline">
                    Log in
                </a>
                <span class="text-indigo-300">or</span>
                <a href="{{ route('register') }}" class="text-sm font-semibold underline hover:no-underline">
                    Create account
                </a>
            </div>
        </div>
    </div>
@endif

<!-- Navigation -->
<x-welcome.nav />

<!-- Hero Section -->
<x-welcome.hero />

<!-- Features Section -->
<x-welcome.features />

<!-- How It Works Section -->
<x-welcome.how-it-works />

<!-- Use Cases Section -->
<x-welcome.use-cases />

<!-- Footer CTA -->
<x-welcome.footer-cta />

<!-- Smooth Scroll & Alpine.js Cloak -->
<style>
    html {
        scroll-behavior: smooth;
    }

    [x-cloak] {
        display: none !important;
    }
</style>
