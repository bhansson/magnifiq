<section class="relative min-h-[120vh] overflow-hidden bg-[#0a0a0a]">
    <!-- Neural Network Background -->
    <div class="absolute inset-0 overflow-hidden neural-container pointer-events-none">
        <!-- Ambient brain glow -->
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[1000px] h-[800px]">
            <div class="absolute inset-0 bg-gradient-radial from-blue-500/10 via-sky-600/3 to-transparent blur-3xl"></div>
        </div>

        <!-- SVG Neural Network -->
        <div class="absolute inset-0">
        <svg class="w-full h-full" viewBox="0 0 1200 900" preserveAspectRatio="xMidYMid slice">
            <defs>
                <!-- Gradient for neural connections -->
                <linearGradient id="synapseGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:rgb(251,191,36);stop-opacity:0" />
                    <stop offset="50%" style="stop-color:rgb(251,191,36);stop-opacity:0.6" />
                    <stop offset="100%" style="stop-color:rgb(251,191,36);stop-opacity:0" />
                </linearGradient>

                <!-- Global soft blur for the entire neural network -->
                <filter id="networkBlur" x="-10%" y="-10%" width="120%" height="120%">
                    <feGaussianBlur stdDeviation="1" />
                </filter>

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

            <!-- Apply soft blur to entire network -->
            <g filter="url(#networkBlur)">

            <!-- Neural connections (axons/dendrites) - organic curved paths -->
            <g class="neural-connections" stroke-width="1" fill="none" opacity="0.25">
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
            <g class="neural-structure" stroke="rgba(148,197,253,0.08)" stroke-width="1" fill="none">
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
            <g class="neurons" opacity="0.25">
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

            </g><!-- End blur wrapper -->

        </svg>
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
        <div class="relative max-w-[90rem] mx-auto perspective-container"
             x-data="{
                 progress: 0,
                 // Transform values: [tilted, flat]
                 rotateX: [8, 0],
                 rotateY: [18, 0],
                 rotateZ: [-10, 0],
                 scale: [0.95, 1],
                 translateX: [8, 0],
                 fadeOpacity: [1, 0],

                 lerp(start, end, t) {
                     return start + (end - start) * Math.min(Math.max(t, 0), 1);
                 },

                 updateProgress() {
                     const rect = this.$el.getBoundingClientRect();
                     const windowHeight = window.innerHeight;

                     // Start animation when element enters viewport (bottom)
                     // Complete animation when element center reaches viewport center
                     const elementCenter = rect.top + rect.height / 2;
                     const viewportCenter = windowHeight / 2;

                     // Progress: 0 when element enters from bottom, 1 when center aligns
                     const startPoint = windowHeight;
                     const endPoint = viewportCenter;

                     this.progress = 1 - (elementCenter - endPoint) / (startPoint - endPoint);
                     this.progress = Math.min(Math.max(this.progress, 0), 1);
                 },

                 getTransform() {
                     const rx = this.lerp(this.rotateX[0], this.rotateX[1], this.progress);
                     const ry = this.lerp(this.rotateY[0], this.rotateY[1], this.progress);
                     const rz = this.lerp(this.rotateZ[0], this.rotateZ[1], this.progress);
                     const s = this.lerp(this.scale[0], this.scale[1], this.progress);
                     const tx = this.lerp(this.translateX[0], this.translateX[1], this.progress);

                     return `rotateX(${rx}deg) rotateY(${ry}deg) rotateZ(${rz}deg) scale(${s}) translateX(${tx}%)`;
                 },

                 getFadeOpacity() {
                     return this.lerp(this.fadeOpacity[0], this.fadeOpacity[1], this.progress);
                 }
             }"
             x-init="updateProgress(); window.addEventListener('scroll', () => updateProgress(), { passive: true })"
        >
            <!-- 3D Perspective Wrapper -->
            <div class="dashboard-3d group"
                 :style="'transform: ' + getTransform()"
            >
                <!-- Main dashboard mockup with glass effect -->
                <div class="relative rounded-2xl overflow-hidden bg-zinc-900/90 backdrop-blur-xl border border-zinc-800/80 shadow-2xl shadow-black/50">
                    <!-- Subtle top gradient shine -->
                    <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-zinc-600 to-transparent"></div>

                    <!-- Dashboard content - Marketing showcase layout -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[700px]">
                        <!-- Left panel - Product card showcase -->
                        <div class="lg:col-span-4 border-r border-zinc-800/80 p-6">
                            <!-- Product card -->
                            <div class="bg-zinc-800/40 rounded-xl border border-zinc-700/50 overflow-hidden">
                                <!-- Product image placeholder -->
                                <div class="aspect-square bg-gradient-to-br from-zinc-700/50 to-zinc-800/50 relative">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-32 h-32 rounded-2xl bg-gradient-to-br from-amber-400/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-amber-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <!-- AI generating badge -->
                                    <div class="absolute top-3 right-3 px-2.5 py-1 bg-amber-500/20 backdrop-blur-sm border border-amber-500/30 rounded-full">
                                        <span class="flex items-center gap-1.5 text-xs font-medium text-amber-400">
                                            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                                            AI Generating
                                        </span>
                                    </div>
                                </div>
                                <!-- Product info -->
                                <div class="p-4">
                                    <p class="text-xs text-zinc-500 mb-1">SKU: PRD-2847</p>
                                    <h3 class="text-white font-medium text-sm mb-3">Premium Wireless Headphones</h3>
                                    <div class="space-y-2">
                                        <div class="h-2 bg-zinc-700/50 rounded-full w-full"></div>
                                        <div class="h-2 bg-zinc-700/50 rounded-full w-4/5"></div>
                                        <div class="h-2 bg-zinc-700/50 rounded-full w-3/5"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats row -->
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="bg-zinc-800/40 rounded-xl p-4 border border-zinc-700/50">
                                    <p class="text-2xl font-bold text-white">2,847</p>
                                    <p class="text-xs text-zinc-500">Products</p>
                                </div>
                                <div class="bg-zinc-800/40 rounded-xl p-4 border border-zinc-700/50">
                                    <p class="text-2xl font-bold text-amber-400">94%</p>
                                    <p class="text-xs text-zinc-500">AI Optimized</p>
                                </div>
                            </div>
                        </div>

                        <!-- Center panel - AI Content Generation -->
                        <div class="lg:col-span-4 border-r border-zinc-800/80 p-6 overflow-hidden">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <span class="text-white font-semibold text-sm">AI Content Engine</span>
                                </div>
                                <span class="px-2 py-0.5 bg-green-500/20 text-green-400 text-xs font-medium rounded">Live</span>
                            </div>

                            <!-- Generated Description -->
                            <div class="bg-zinc-800/40 rounded-xl border border-amber-500/30 p-3 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-white text-xs font-medium">Description</h4>
                                    <svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <p class="text-zinc-400 text-xs leading-relaxed line-clamp-2">
                                    Experience premium audio with our wireless headphones featuring advanced noise cancellation and 40-hour battery...
                                </p>
                            </div>

                            <!-- USPs -->
                            <div class="bg-zinc-800/40 rounded-xl border border-zinc-700/50 p-3 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-white text-xs font-medium">Key Selling Points</h4>
                                    <svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1 h-1 bg-amber-400 rounded-full"></span>
                                        <span class="text-zinc-300 text-xs">40-hour battery life</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-1 h-1 bg-amber-400 rounded-full"></span>
                                        <span class="text-zinc-300 text-xs">Active noise cancellation</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-1 h-1 bg-amber-400 rounded-full"></span>
                                        <span class="text-zinc-300 text-xs">Premium memory foam</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQs -->
                            <div class="bg-zinc-800/40 rounded-xl border border-zinc-700/50 p-3 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-white text-xs font-medium">Product FAQs</h4>
                                    <svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="space-y-2">
                                    <div>
                                        <p class="text-zinc-300 text-xs font-medium">How long does the battery last?</p>
                                        <p class="text-zinc-500 text-xs">Up to 40 hours on a single charge...</p>
                                    </div>
                                    <div>
                                        <p class="text-zinc-300 text-xs font-medium">Is it compatible with all devices?</p>
                                        <p class="text-zinc-500 text-xs">Works with Bluetooth 5.0 and higher...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Meta Title & SEO -->
                            <div class="bg-zinc-800/40 rounded-xl border border-zinc-700/50 p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-white text-xs font-medium">SEO Meta</h4>
                                    <span class="flex items-center gap-1 text-amber-400">
                                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                                        <span class="text-xs">Generating</span>
                                    </span>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="h-2 bg-zinc-700/50 rounded w-full"></div>
                                    <div class="h-2 bg-zinc-700/50 rounded w-4/5"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Right panel - Photo Studio -->
                        <div class="lg:col-span-4 p-6 bg-zinc-900/30">
                            <div class="flex items-center gap-2 mb-5">
                                <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <span class="text-white font-semibold text-sm">Photo Studio</span>
                            </div>

                            <!-- Generated images grid -->
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="aspect-square bg-gradient-to-br from-amber-500/10 to-orange-500/10 rounded-xl border border-amber-500/20 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-amber-400/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="aspect-square bg-gradient-to-br from-zinc-700/30 to-zinc-800/30 rounded-xl border border-zinc-700/50 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="aspect-square bg-gradient-to-br from-zinc-700/30 to-zinc-800/30 rounded-xl border border-zinc-700/50 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="aspect-square bg-zinc-800/40 rounded-xl border border-dashed border-zinc-700 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                            </div>

                            <!-- Generation prompt -->
                            <div class="bg-zinc-800/40 rounded-xl border border-zinc-700/50 p-4">
                                <p class="text-xs text-zinc-500 mb-2">AI Prompt</p>
                                <p class="text-zinc-300 text-xs leading-relaxed italic">
                                    "Product photography, premium headphones on marble surface, soft studio lighting, minimalist aesthetic..."
                                </p>
                            </div>

                            <!-- Generate button -->
                            <button class="w-full mt-4 flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-black text-sm font-semibold rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Generate Images
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bottom fade gradient overlay -->
                <div class="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/80 to-transparent pointer-events-none rounded-b-2xl dashboard-fade"
                     :style="'opacity: ' + getFadeOpacity()"></div>
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

    /* Dashboard with 3D transform - scroll-driven animation handled by Alpine.js */
    .dashboard-3d {
        transform-style: preserve-3d;
        transform-origin: 30% 50%;
    }
</style>
