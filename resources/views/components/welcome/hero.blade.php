<section class="relative min-h-screen overflow-hidden bg-[#0a0a0a]">
    <!-- Animated Background Glow -->
    <div class="absolute inset-0 overflow-hidden">
        <!-- Top edge red glow lines -->
        <div class="absolute top-0 left-1/4 w-px h-48 bg-gradient-to-b from-red-500/60 via-red-500/20 to-transparent"></div>
        <div class="absolute top-0 right-1/4 w-px h-64 bg-gradient-to-b from-red-500/40 via-red-500/10 to-transparent"></div>
        <div class="absolute top-0 left-1/3 w-px h-32 bg-gradient-to-b from-orange-500/50 via-orange-500/10 to-transparent"></div>
        <div class="absolute top-0 right-1/3 w-px h-40 bg-gradient-to-b from-orange-500/30 via-orange-500/5 to-transparent"></div>

        <!-- Central orb glow -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px]">
            <div class="absolute inset-0 bg-gradient-to-b from-red-600/30 via-orange-500/10 to-transparent blur-3xl"></div>
        </div>

        <!-- The glowing orb/eclipse effect -->
        <div class="absolute top-20 left-1/2 -translate-x-1/2 orb-container">
            <div class="relative w-64 h-64 md:w-80 md:h-80">
                <!-- Outer glow ring -->
                <div class="absolute inset-0 rounded-full bg-gradient-to-b from-amber-500/40 via-orange-600/20 to-red-700/10 blur-2xl animate-pulse-slow"></div>
                <!-- Eclipse core -->
                <div class="absolute inset-4 rounded-full bg-black shadow-[0_0_100px_40px_rgba(251,146,60,0.3)]"></div>
                <!-- Top corona glow -->
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 w-48 h-24 bg-gradient-to-b from-amber-400/60 via-orange-500/30 to-transparent blur-xl rounded-full"></div>
                <!-- Bottom subtle glow -->
                <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 w-64 h-16 bg-gradient-to-t from-red-900/20 to-transparent blur-xl"></div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-8 pt-32 pb-16">
        <!-- Hero Text -->
        <div class="text-center max-w-6xl mx-auto mb-10">
            <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight leading-tight mb-6">
                <span class="text-white whitespace-nowrap">The fastest way to scale high-</span>
                <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-b from-white via-white to-zinc-600">performing product content</span>
            </h1>
            <p class="text-xl sm:text-2xl text-zinc-400 max-w-3xl mx-auto leading-relaxed">
                The AI engine for e-commerce teams who demand results. Optimize product pages with the power of AI and create premium product images at speed.
            </p>
        </div>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pb-32 md:pb-48">
            <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 px-8 py-4 text-base font-medium text-black bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-300 rounded-full hover:shadow-xl hover:shadow-amber-500/25 transition-all duration-300">
                Start building
                <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                </svg>
            </a>
            <a href="#how-it-works" class="inline-flex items-center gap-2 px-8 py-4 text-base font-medium text-zinc-300 bg-zinc-800/80 backdrop-blur-sm rounded-full border border-zinc-700/50 hover:bg-zinc-700/80 hover:border-zinc-600 transition-all duration-300">
                Watch demo
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>

        <!-- Product Showcase Panel with 3D Perspective -->
        <div class="relative max-w-[90rem] mx-auto perspective-container">
            <!-- 3D Perspective Wrapper -->
            <div class="dashboard-3d group">
                <!-- Main dashboard mockup with glass effect -->
                <div class="relative rounded-2xl overflow-hidden bg-zinc-900/90 backdrop-blur-xl border border-zinc-800/80 shadow-2xl shadow-black/50">
                    <!-- Subtle top gradient shine -->
                    <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-zinc-600 to-transparent"></div>

                    <!-- Dashboard content grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[700px]">
                        <!-- Left sidebar -->
                        <div class="lg:col-span-3 border-r border-zinc-800/80 p-8">
                            <!-- Logo area -->
                            <div class="flex items-center gap-4 mb-10">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <span class="text-white font-semibold text-base">Magnifiq</span>
                            </div>

                            <!-- New action button -->
                            <button class="w-full flex items-center gap-4 px-5 py-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-amber-400 text-base font-medium mb-8 hover:bg-amber-500/20 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                New Generation
                                <span class="ml-auto text-sm text-zinc-500 bg-zinc-800 px-2.5 py-1 rounded">C</span>
                            </button>

                            <!-- Nav items -->
                            <nav class="space-y-2">
                                <a href="#" class="flex items-center gap-4 px-5 py-3.5 text-zinc-400 hover:text-white hover:bg-zinc-800/50 rounded-lg transition-colors text-base">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    Products
                                    <span class="ml-auto text-sm text-zinc-600">24</span>
                                </a>
                                <a href="#" class="flex items-center gap-4 px-5 py-3.5 text-zinc-400 hover:text-white hover:bg-zinc-800/50 rounded-lg transition-colors text-base">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Photo Studio
                                </a>
                                <a href="#" class="flex items-center gap-4 px-5 py-3.5 text-zinc-400 hover:text-white hover:bg-zinc-800/50 rounded-lg transition-colors text-base">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Templates
                                </a>
                            </nav>

                            <!-- Team section -->
                            <div class="mt-10 pt-8 border-t border-zinc-800/80">
                                <p class="px-5 text-sm font-medium text-zinc-600 uppercase tracking-wider mb-4">Your Teams</p>
                                <div class="space-y-2">
                                    <a href="#" class="flex items-center gap-4 px-5 py-3.5 bg-zinc-800/50 text-white rounded-lg text-base">
                                        <span class="w-8 h-8 rounded bg-amber-500/20 flex items-center justify-center text-amber-400 text-sm font-bold">M</span>
                                        Marketing Team
                                    </a>
                                    <a href="#" class="flex items-center gap-4 px-5 py-3.5 text-zinc-400 hover:text-white hover:bg-zinc-800/50 rounded-lg transition-colors text-base">
                                        <span class="w-8 h-8 rounded bg-blue-500/20 flex items-center justify-center text-blue-400 text-sm font-bold">E</span>
                                        E-commerce
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Main content area -->
                        <div class="lg:col-span-5 border-r border-zinc-800/80 p-8">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-8">
                                <h2 class="text-white font-semibold text-lg">Recent Generations</h2>
                                <div class="flex items-center gap-3">
                                    <button class="p-2.5 text-zinc-500 hover:text-white hover:bg-zinc-800 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                        </svg>
                                    </button>
                                    <button class="p-2.5 text-zinc-500 hover:text-white hover:bg-zinc-800 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Task list -->
                            <div class="space-y-3">
                                <!-- Active task -->
                                <div class="p-5 bg-zinc-800/50 rounded-xl border border-amber-500/30 cursor-pointer">
                                    <div class="flex items-start gap-4">
                                        <span class="shrink-0 px-3 py-1.5 bg-amber-500/20 text-amber-400 text-sm font-medium rounded">AI-2491</span>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-sm text-amber-400 font-medium">High Priority</span>
                                            <p class="text-white text-base font-medium mt-1.5 truncate">Generate product descriptions for Spring Collection</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <span class="inline-flex items-center gap-2 text-sm text-amber-400">
                                                    <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
                                                    In Progress
                                                </span>
                                            </div>
                                        </div>
                                        <div class="w-6 h-6 rounded-full border-2 border-amber-500/50"></div>
                                    </div>
                                </div>

                                <!-- Completed task -->
                                <div class="p-5 bg-zinc-800/30 rounded-xl border border-zinc-700/50 cursor-pointer hover:bg-zinc-800/50 transition-colors">
                                    <div class="flex items-start gap-4">
                                        <span class="shrink-0 px-3 py-1.5 bg-zinc-700 text-zinc-400 text-sm font-medium rounded">AI-2490</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-zinc-300 text-base font-medium truncate">Photo studio renders for Homepage Banner</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <span class="inline-flex items-center gap-2 text-sm text-green-400">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Done
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Another task -->
                                <div class="p-5 bg-zinc-800/30 rounded-xl border border-zinc-700/50 cursor-pointer hover:bg-zinc-800/50 transition-colors">
                                    <div class="flex items-start gap-4">
                                        <span class="shrink-0 px-3 py-1.5 bg-zinc-700 text-zinc-400 text-sm font-medium rounded">AI-2489</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-zinc-300 text-base font-medium truncate">Bulk SEO optimization for Electronics category</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <span class="inline-flex items-center gap-2 text-sm text-zinc-500">
                                                    <span class="w-2 h-2 bg-zinc-500 rounded-full"></span>
                                                    Backlog
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Urgent task -->
                                <div class="p-5 bg-zinc-800/30 rounded-xl border border-zinc-700/50 cursor-pointer hover:bg-zinc-800/50 transition-colors">
                                    <div class="flex items-start gap-4">
                                        <span class="shrink-0 px-3 py-1.5 bg-red-500/20 text-red-400 text-sm font-medium rounded">AI-2488</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-zinc-300 text-base font-medium truncate">Regenerate broken image assets</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <span class="inline-flex items-center gap-2 text-sm text-red-400">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Urgent
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right detail panel -->
                        <div class="lg:col-span-4 p-8 bg-zinc-900/50">
                            <!-- Breadcrumb -->
                            <div class="flex items-center gap-2 text-sm text-zinc-500 mb-5">
                                <span>Marketing Team</span>
                                <span>/</span>
                                <span class="text-zinc-400">AI-2491</span>
                            </div>

                            <!-- Task detail -->
                            <h3 class="text-xl font-semibold text-white mb-5">Generate product descriptions for Spring Collection</h3>

                            <div class="flex items-center gap-5 mb-8 text-base">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500"></div>
                                    <span class="text-zinc-400">Alex Chen</span>
                                </div>
                                <div class="flex items-center gap-2 text-amber-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Due in 2 days</span>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="prose prose-sm prose-invert mb-8">
                                <p class="text-zinc-400 text-base leading-relaxed">
                                    Generate compelling product descriptions for the Spring 2025 collection. The AI should analyze product images and existing metadata to create SEO-optimized copy that matches our brand voice.
                                </p>
                            </div>

                            <!-- Code preview snippet -->
                            <div class="bg-black/50 rounded-xl p-5 border border-zinc-800 font-mono text-sm overflow-hidden">
                                <div class="flex items-center gap-3 text-zinc-500 mb-4">
                                    <span>template-config.ts</span>
                                    <span class="ml-auto px-3 py-1 bg-zinc-800 rounded text-zinc-600">TypeScript</span>
                                </div>
                                <pre class="text-zinc-400 overflow-x-auto"><code><span class="text-zinc-600">// Initialize AI template</span>
<span class="text-purple-400">const</span> <span class="text-blue-400">template</span> = <span class="text-amber-400">createTemplate</span>({
  <span class="text-green-400">model</span>: <span class="text-amber-300">'gpt-4-vision'</span>,
  <span class="text-green-400">tone</span>: <span class="text-amber-300">'professional'</span>,
});

<span class="text-purple-400">await</span> <span class="text-blue-400">template</span>.<span class="text-amber-400">generate</span>({
  <span class="text-green-400">products</span>: <span class="text-blue-400">collection</span>,
});</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom fade gradient overlay -->
                <div class="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/80 to-transparent pointer-events-none rounded-b-2xl dashboard-fade"></div>
            </div>

            <!-- Floating glow effect under panel -->
            <div class="absolute -bottom-20 left-1/2 -translate-x-1/2 w-3/4 h-40 bg-gradient-to-t from-transparent via-amber-500/5 to-transparent blur-3xl pointer-events-none"></div>
        </div>
    </div>
</section>

<style>
    @keyframes pulse-slow {
        0%, 100% { opacity: 0.4; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    .animate-pulse-slow {
        animation: pulse-slow 4s ease-in-out infinite;
    }

    /* 3D Perspective Container */
    .perspective-container {
        perspective: 1500px;
        perspective-origin: 40% 50%;
    }

    /* Dashboard with 3D transform - tilted and skewed by default */
    .dashboard-3d {
        transform-style: preserve-3d;
        transform: rotateX(8deg) rotateY(18deg) rotateZ(-10deg) scale(0.95);
        transition: transform 1.2s cubic-bezier(0.23, 1, 0.32, 1);
        transform-origin: 30% 50%;
    }

    /* On hover, flatten to front view */
    .dashboard-3d:hover {
        transform: rotateX(0deg) rotateY(0deg) scale(1);
    }

    /* Fade effect adjusts on hover */
    .dashboard-fade {
        opacity: 1;
        transition: opacity 1.2s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .dashboard-3d:hover .dashboard-fade {
        opacity: 0;
    }
</style>
