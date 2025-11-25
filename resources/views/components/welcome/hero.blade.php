<section class="relative min-h-[120vh] overflow-hidden bg-[#0a0a0a]">
    <!-- Neural Network Background -->
    <div class="absolute inset-0 overflow-hidden neural-container">
        <!-- Ambient brain glow -->
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[1000px] h-[800px]">
            <div class="absolute inset-0 bg-gradient-radial from-blue-500/15 via-sky-600/5 to-transparent blur-3xl"></div>
        </div>

        <!-- SVG Neural Network -->
        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 1200 900" preserveAspectRatio="xMidYMid slice">
            <defs>
                <!-- Gradient for neural connections -->
                <linearGradient id="synapseGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(251,191,36);stop-opacity:0" />
                    <stop offset="50%" style="stop-color:rgb(251,191,36);stop-opacity:0.6" />
                    <stop offset="100%" style="stop-color:rgb(251,191,36);stop-opacity:0" />
                </linearGradient>

                <!-- Glow filter for neurons -->
                <filter id="neuronGlow" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                    <feMerge>
                        <feMergeNode in="coloredBlur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>

                <!-- Stronger glow for firing neurons -->
                <filter id="firingGlow" x="-100%" y="-100%" width="300%" height="300%">
                    <feGaussianBlur stdDeviation="6" result="coloredBlur"/>
                    <feMerge>
                        <feMergeNode in="coloredBlur"/>
                        <feMergeNode in="coloredBlur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>

                <!-- Pulse animation for signals traveling along axons (slow, contemplative) -->
                <linearGradient id="pulseGradient1" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(255,255,255);stop-opacity:0">
                        <animate attributeName="offset" values="-0.3;1" dur="5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="10%" style="stop-color:rgb(255,255,255);stop-opacity:0.8">
                        <animate attributeName="offset" values="-0.2;1.1" dur="5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="20%" style="stop-color:rgb(255,255,255);stop-opacity:0">
                        <animate attributeName="offset" values="-0.1;1.2" dur="5s" repeatCount="indefinite"/>
                    </stop>
                </linearGradient>

                <linearGradient id="pulseGradient2" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(56,189,248);stop-opacity:0">
                        <animate attributeName="offset" values="-0.3;1" dur="6s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="10%" style="stop-color:rgb(125,211,252);stop-opacity:0.7">
                        <animate attributeName="offset" values="-0.2;1.1" dur="6s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="20%" style="stop-color:rgb(56,189,248);stop-opacity:0">
                        <animate attributeName="offset" values="-0.1;1.2" dur="6s" repeatCount="indefinite"/>
                    </stop>
                </linearGradient>

                <linearGradient id="pulseGradient3" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(59,130,246);stop-opacity:0">
                        <animate attributeName="offset" values="-0.3;1" dur="4.5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="15%" style="stop-color:rgb(147,197,253);stop-opacity:0.9">
                        <animate attributeName="offset" values="-0.15;1.15" dur="4.5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="30%" style="stop-color:rgb(59,130,246);stop-opacity:0">
                        <animate attributeName="offset" values="0;1.3" dur="4.5s" repeatCount="indefinite"/>
                    </stop>
                </linearGradient>

                <linearGradient id="pulseGradient4" x1="100%" y1="0%" x2="0%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(186,230,253);stop-opacity:0">
                        <animate attributeName="offset" values="-0.3;1" dur="7s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="12%" style="stop-color:rgb(240,249,255);stop-opacity:0.6">
                        <animate attributeName="offset" values="-0.18;1.12" dur="7s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="24%" style="stop-color:rgb(186,230,253);stop-opacity:0">
                        <animate attributeName="offset" values="-0.06;1.24" dur="7s" repeatCount="indefinite"/>
                    </stop>
                </linearGradient>

                <linearGradient id="pulseGradient5" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(14,165,233);stop-opacity:0">
                        <animate attributeName="offset" values="-0.3;1" dur="5.5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="8%" style="stop-color:rgb(56,189,248);stop-opacity:0.75">
                        <animate attributeName="offset" values="-0.22;1.08" dur="5.5s" repeatCount="indefinite"/>
                    </stop>
                    <stop offset="16%" style="stop-color:rgb(14,165,233);stop-opacity:0">
                        <animate attributeName="offset" values="-0.14;1.16" dur="5.5s" repeatCount="indefinite"/>
                    </stop>
                </linearGradient>
            </defs>

            <!-- Neural connections (axons/dendrites) - organic curved paths -->
            <g class="neural-connections" stroke-width="1.5" fill="none" opacity="0.4">
                <!-- Primary network connections -->
                <path d="M200,300 Q350,250 450,350" stroke="url(#pulseGradient1)" stroke-width="2"/>
                <path d="M450,350 Q550,400 650,320" stroke="url(#pulseGradient2)" stroke-width="1.5"/>
                <path d="M650,320 Q750,280 850,350" stroke="url(#pulseGradient3)" stroke-width="2"/>
                <path d="M850,350 Q950,420 1000,380" stroke="url(#pulseGradient1)" stroke-width="1.5"/>

                <!-- Secondary branches -->
                <path d="M450,350 Q480,450 550,500" stroke="url(#pulseGradient4)" stroke-width="1.5"/>
                <path d="M550,500 Q620,550 700,520" stroke="url(#pulseGradient2)" stroke-width="2"/>
                <path d="M700,520 Q800,490 850,350" stroke="url(#pulseGradient5)" stroke-width="1.5"/>

                <!-- Upper network -->
                <path d="M300,150 Q400,180 500,200" stroke="url(#pulseGradient3)" stroke-width="1.5"/>
                <path d="M500,200 Q580,220 650,320" stroke="url(#pulseGradient1)" stroke-width="2"/>
                <path d="M650,320 Q700,250 800,200" stroke="url(#pulseGradient4)" stroke-width="1.5"/>
                <path d="M800,200 Q900,180 950,220" stroke="url(#pulseGradient2)" stroke-width="1.5"/>

                <!-- Lower network -->
                <path d="M250,550 Q350,580 450,550" stroke="url(#pulseGradient5)" stroke-width="1.5"/>
                <path d="M450,550 Q500,520 550,500" stroke="url(#pulseGradient1)" stroke-width="2"/>
                <path d="M700,520 Q780,560 850,600" stroke="url(#pulseGradient3)" stroke-width="1.5"/>
                <path d="M850,600 Q920,620 1000,580" stroke="url(#pulseGradient2)" stroke-width="1.5"/>

                <!-- Cross connections -->
                <path d="M500,200 Q520,280 450,350" stroke="url(#pulseGradient4)" stroke-width="1"/>
                <path d="M800,200 Q820,270 850,350" stroke="url(#pulseGradient5)" stroke-width="1"/>
                <path d="M450,550 Q400,450 450,350" stroke="url(#pulseGradient1)" stroke-width="1"/>
                <path d="M850,600 Q880,500 850,350" stroke="url(#pulseGradient2)" stroke-width="1"/>

                <!-- Distant fine connections -->
                <path d="M150,400 Q200,350 200,300" stroke="url(#pulseGradient3)" stroke-width="1" opacity="0.5"/>
                <path d="M100,500 Q180,520 250,550" stroke="url(#pulseGradient4)" stroke-width="1" opacity="0.5"/>
                <path d="M1050,300 Q1020,340 1000,380" stroke="url(#pulseGradient1)" stroke-width="1" opacity="0.5"/>
                <path d="M1100,500 Q1050,540 1000,580" stroke="url(#pulseGradient5)" stroke-width="1" opacity="0.5"/>
            </g>

            <!-- Static connection lines (dimmer, background structure) - with IDs for motion paths -->
            <g class="neural-structure" stroke="rgba(148,197,253,0.15)" stroke-width="1" fill="none">
                <path id="path-1" d="M200,300 Q350,250 450,350"/>
                <path id="path-2" d="M450,350 Q550,400 650,320"/>
                <path id="path-3" d="M650,320 Q750,280 850,350"/>
                <path id="path-4" d="M850,350 Q950,420 1000,380"/>
                <path id="path-5" d="M450,350 Q480,450 550,500"/>
                <path id="path-6" d="M550,500 Q620,550 700,520"/>
                <path id="path-7" d="M700,520 Q800,490 850,350"/>
                <path id="path-8" d="M300,150 Q400,180 500,200"/>
                <path id="path-9" d="M500,200 Q580,220 650,320"/>
                <path id="path-10" d="M650,320 Q700,250 800,200"/>
                <path id="path-11" d="M800,200 Q900,180 950,220"/>
                <path id="path-12" d="M250,550 Q350,580 450,550"/>
                <path id="path-13" d="M450,550 Q500,520 550,500"/>
                <path id="path-14" d="M700,520 Q780,560 850,600"/>
                <path id="path-15" d="M850,600 Q920,620 1000,580"/>
                <path id="path-16" d="M500,200 Q520,280 450,350"/>
                <path id="path-17" d="M800,200 Q820,270 850,350"/>
                <path id="path-18" d="M450,550 Q400,450 450,350"/>
                <path id="path-19" d="M850,600 Q880,500 850,350"/>
            </g>

            <!-- Neuron cell bodies (subtle static nodes) -->
            <g class="neurons" opacity="0.4">
                <!-- Central hub neurons -->
                <circle cx="450" cy="350" r="4" fill="rgb(255,255,255)" filter="url(#neuronGlow)"/>
                <circle cx="650" cy="320" r="4.5" fill="rgb(186,230,253)" filter="url(#neuronGlow)"/>
                <circle cx="850" cy="350" r="3.5" fill="rgb(125,211,252)" filter="url(#neuronGlow)"/>
                <circle cx="550" cy="500" r="3.5" fill="rgb(56,189,248)" filter="url(#neuronGlow)"/>
                <circle cx="700" cy="520" r="3" fill="rgb(186,230,253)" filter="url(#neuronGlow)"/>

                <!-- Secondary neurons -->
                <circle cx="200" cy="300" r="2.5" fill="rgb(147,197,253)"/>
                <circle cx="500" cy="200" r="2.5" fill="rgb(186,230,253)"/>
                <circle cx="800" cy="200" r="2.5" fill="rgb(255,255,255)"/>
                <circle cx="1000" cy="380" r="2.5" fill="rgb(125,211,252)"/>
                <circle cx="450" cy="550" r="2.5" fill="rgb(56,189,248)"/>
                <circle cx="850" cy="600" r="2.5" fill="rgb(59,130,246)"/>

                <!-- Peripheral neurons -->
                <circle cx="300" cy="150" r="2" fill="rgb(186,230,253)" opacity="0.6"/>
                <circle cx="950" cy="220" r="2" fill="rgb(147,197,253)" opacity="0.6"/>
                <circle cx="250" cy="550" r="2" fill="rgb(125,211,252)" opacity="0.6"/>
                <circle cx="1000" cy="580" r="2" fill="rgb(56,189,248)" opacity="0.6"/>

                <!-- Edge neurons -->
                <circle cx="150" cy="400" r="1.5" fill="rgb(186,230,253)" opacity="0.4"/>
                <circle cx="100" cy="500" r="1.5" fill="rgb(147,197,253)" opacity="0.3"/>
                <circle cx="1050" cy="300" r="1.5" fill="rgb(125,211,252)" opacity="0.4"/>
                <circle cx="1100" cy="500" r="1.5" fill="rgb(59,130,246)" opacity="0.3"/>
            </g>

        </svg>

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
    /* Radial gradient utility (Tailwind doesn't have this by default) */
    .bg-gradient-radial {
        background: radial-gradient(circle, var(--tw-gradient-stops));
    }


    /* 3D Perspective Container */
    .perspective-container {
        perspective: 1500px;
        perspective-origin: 40% 50%;
    }

    /* Dashboard with 3D transform - tilted and skewed by default */
    .dashboard-3d {
        transform-style: preserve-3d;
        transform: rotateX(8deg) rotateY(18deg) rotateZ(-10deg) scale(0.95) translateX(8%);
        transition: transform 1.2s cubic-bezier(0.23, 1, 0.32, 1);
        transform-origin: 30% 50%;
    }

    /* On hover, flatten to front view */
    .dashboard-3d:hover {
        transform: rotateX(0deg) rotateY(0deg) scale(1) translateX(0);
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
