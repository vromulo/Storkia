@extends('layouts.app')

@section('content')
@php
    $featuredSellers = \App\Models\SellerProfile::take(4)->get();
    $delays = ['0s', '-0.8s', '-1.6s', '-2.4s'];
    $colors = ['primary', 'secondary', 'primary', 'secondary'];

    // Retrieve approved products with their associated seller profiles
    $featuredProducts = \App\Models\Product::whereHas('approval', function($q) {
            $q->where('status', 'Approved');
        })
        ->with('sellerProfile')
        ->inRandomOrder()
        ->take(4)
        ->get();

    // Registered logistics partners. A row in logistics_profiles means the account is approved.
    // Capped at 12 so the scrolling list stays light.
    $logisticsPartners = \App\Models\LogisticsProfile::query()
        ->withCount(['hubs as active_hubs_count' => fn ($q) => $q->where('status', 'active')])
        ->orderBy('business_name')
        ->take(12)
        ->get();
@endphp

<div class="bg-surface font-sans antialiased text-text-main overflow-x-clip selection:bg-primary selection:text-white relative">

    <!-- Sticky Hero Background Layer (Stays fixed on desktop & mobile while content scrolls over it) -->
    <div class="fixed inset-0 w-full h-svh z-0 pointer-events-none overflow-hidden">
        
        <!-- Responsive Background Picture -->
        <picture class="absolute inset-0 w-full h-full block">
            <!-- Mobile Photo (< 768px / md) -->
            <source media="(max-width: 767px)" srcset="{{ asset('assets/shopping-banner-mobile.avif') }}">
            <!-- Desktop / Default Photo (>= 768px / md) -->
            <img src="{{ asset('assets/shopping-banner.avif') }}" 
                 alt="Storkia Shopping Banner" 
                 fetchpriority="high"
                 loading="eager"
                 class="w-full h-full object-cover object-center">
        </picture>

        <div class="absolute top-10 left-4 sm:left-10 w-48 sm:w-80 h-48 sm:h-80 bg-brand-light/40 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob"></div>
        <div class="absolute top-40 right-4 sm:right-10 w-48 sm:w-80 h-48 sm:h-80 bg-secondary/30 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-10 left-1/2 w-48 sm:w-80 h-48 sm:h-80 bg-primary/20 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Hero Content (Scrolls up naturally with the page) -->
    <section class="relative z-10 w-full min-h-svh flex flex-col items-center justify-center text-center pt-24 pb-16 sm:pb-20 px-4 sm:px-6">
        <div class="relative z-20 max-w-5xl mx-auto w-full">
            <h1 class="text-[clamp(2.75rem,9vw,6rem)] font-extrabold tracking-[-0.03em] text-text-main mb-6 sm:mb-8 leading-[1.08] text-balance drop-shadow-[0_2px_20px_rgba(255,255,255,0.55)]">
                Fast delivery. <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-primary-dark to-primary bg-[length:200%_auto] animate-gradient">Exclusive deals.</span>
            </h1>
            <p class="text-lg sm:text-xl lg:text-[1.375rem] text-dark-grey mb-8 sm:mb-12 max-w-2xl mx-auto leading-[1.65] font-medium px-2 text-pretty [text-shadow:0_1px_14px_rgba(255,255,255,0.85)]">
                Experience the fastest routing and secure handling in the city. Bridging buyers, curated shops, and premium logistics in one seamless platform.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 sm:gap-5 px-4 sm:px-0">
                <a href="{{ route('home') }}" class="group relative flex justify-center items-center bg-text-main text-white px-8 sm:px-10 py-3.5 sm:py-4 rounded-full font-bold text-base sm:text-lg hover:bg-black transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-text-main/20 overflow-hidden shadow-lg w-full sm:w-auto">
                    <span class="relative z-10">Shop Now</span>
                    <div class="absolute inset-0 h-full w-full bg-white/20 scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-300"></div>
                </a>
                <a href="#shops" class="flex justify-center items-center bg-white text-text-main px-8 sm:px-10 py-3.5 sm:py-4 rounded-full font-bold text-base sm:text-lg border border-border-subtle/50 shadow-md hover:border-text-main/20 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 w-full sm:w-auto">
                    Explore Shops
                </a>
            </div>
        </div>
    </section>

    <!-- Scrolling Content Wrapper (Slides up and overlays the fixed hero background) -->
    <div class="relative z-20 bg-surface">
        <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.02]" style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 32px 32px;"></div>

        <!-- Interactive Grid Cards Section -->
        <section class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-12 sm:py-16 scroll-reveal" x-data="{ activeCard: 1 }">
            <div class="flex flex-col md:flex-row w-full h-auto md:h-[500px] gap-4 md:gap-6 lg:gap-8">
                
                <!-- Card 1 -->
                <div @mouseenter="activeCard = 1"
                     @click="activeCard = 1"
                     :class="activeCard === 1 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                     class="relative flex-none md:flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                    <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                         style="background-image: url('{{ asset('assets/carousel-slide-3.webp') }}');"
                         :class="activeCard === 1 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                         :class="activeCard === 1 ? 'opacity-90' : 'opacity-100'"></div>
 
                    <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex md:flex-col md:justify-end h-full z-10 transition-all duration-500"
                         :class="activeCard === 1 ? 'flex-col justify-end' : 'flex-row items-center gap-4 md:items-stretch md:gap-0'">
                        <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center shrink-0 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                             :class="activeCard === 1 ? 'mb-3 bg-primary border-primary shadow-[0_4px_14px_0_rgba(207,65,115,0.39)]' : 'mb-0'">
                            <svg class="w-5 h-5 md:w-7 md:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg md:text-2xl lg:text-3xl font-bold tracking-tight text-white transition-all duration-500"
                            :class="activeCard === 1 ? 'mb-1 md:mb-3' : 'mb-0'">
                            Lightning Fast Delivery
                        </h3>
                        <div class="overflow-hidden transition-all duration-700"
                             :class="activeCard === 1 ? 'max-h-40 opacity-100 md:mt-2' : 'max-h-0 opacity-0'">
                            <p class="text-white/80 leading-relaxed font-medium text-xs md:text-base">Experience the fastest routing in the city. Your package arrives before you know it.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Card 2 -->
                <div @mouseenter="activeCard = 2"
                     @click="activeCard = 2"
                     :class="activeCard === 2 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                     class="relative flex-none md:flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                    <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                         style="background-image: url('{{ asset('assets/carousel-slide-1.webp') }}');"
                         :class="activeCard === 2 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                         :class="activeCard === 2 ? 'opacity-90' : 'opacity-100'"></div>
 
                    <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex md:flex-col md:justify-end h-full z-10 transition-all duration-500"
                         :class="activeCard === 2 ? 'flex-col justify-end' : 'flex-row items-center gap-4 md:items-stretch md:gap-0'">
                        <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center shrink-0 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                             :class="activeCard === 2 ? 'mb-3 bg-secondary border-secondary text-[#111] shadow-[0_4px_14px_0_rgba(244,185,184,0.5)]' : 'mb-0'">
                            <svg class="w-5 h-5 md:w-7 md:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg md:text-2xl lg:text-3xl font-bold tracking-tight text-white transition-all duration-500"
                            :class="activeCard === 2 ? 'mb-1 md:mb-3' : 'mb-0'">
                            Secure Handling
                        </h3>
                        <div class="overflow-hidden transition-all duration-700"
                             :class="activeCard === 2 ? 'max-h-40 opacity-100 md:mt-2' : 'max-h-0 opacity-0'">
                            <p class="text-white/80 leading-relaxed font-medium text-xs md:text-base">Every item is tracked and handled with the utmost care by our verified couriers.</p>
                        </div>
                    </div>
                </div>
 
                <!-- Card 3 -->
                <div @mouseenter="activeCard = 3"
                     @click="activeCard = 3"
                     :class="activeCard === 3 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                     class="relative flex-none md:flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                    <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                         style="background-image: url('{{ asset('assets/carousel-slide-2.webp') }}');"
                         :class="activeCard === 3 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                         :class="activeCard === 3 ? 'opacity-90' : 'opacity-100'"></div>
 
                    <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex md:flex-col md:justify-end h-full z-10 transition-all duration-500"
                         :class="activeCard === 3 ? 'flex-col justify-end' : 'flex-row items-center gap-4 md:items-stretch md:gap-0'">
                        <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center shrink-0 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                             :class="activeCard === 3 ? 'mb-3 bg-primary border-primary shadow-[0_4px_14px_0_rgba(207,65,115,0.39)]' : 'mb-0'">
                            <svg class="w-5 h-5 md:w-7 md:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg md:text-2xl lg:text-3xl font-bold tracking-tight text-white transition-all duration-500"
                            :class="activeCard === 3 ? 'mb-1 md:mb-3' : 'mb-0'">
                            Real-time Tracking
                        </h3>
                        <div class="overflow-hidden transition-all duration-700"
                             :class="activeCard === 3 ? 'max-h-40 opacity-100 md:mt-2' : 'max-h-0 opacity-0'">
                            <p class="text-white/80 leading-relaxed font-medium text-xs md:text-base">Watch your delivery move across the map in real-time, right to your doorstep.</p>
                        </div>
                    </div>
                </div>
 
            </div>
        </section>

        <!-- SECTION 2: Featured Stores (Seamless Full-Width Dark Background with Frosted Glass Cards) -->
        <section id="shops" class="relative z-10 w-full bg-[#111] text-white py-16 sm:py-24 overflow-hidden shadow-2xl scroll-mt-20 scroll-reveal">
            <!-- Ambient Radial Glow Effects -->
            <div class="absolute top-0 right-0 w-[400px] md:w-[800px] h-[400px] md:h-[800px] [--gw:400px] md:[--gw:800px] bg-primary/25 rounded-full filter blur-[80px] md:blur-[120px] translate-x-1/3 -translate-y-1/3 pointer-events-none animate-glow-slide-a"></div>
            <div class="absolute bottom-0 left-0 w-[300px] md:w-[600px] h-[300px] md:h-[600px] [--gw:300px] md:[--gw:600px] bg-secondary/15 rounded-full filter blur-[60px] md:blur-[100px] -translate-x-1/3 translate-y-1/3 pointer-events-none animate-glow-slide-b"></div>

            <div class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12">
                <div class="flex justify-between items-end mb-8 sm:mb-12">
                    <div>
                        <span class="text-brand-light font-bold uppercase tracking-widest text-xs sm:text-sm mb-1 sm:mb-2 block">Curated Marketplace</span>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white">Featured Stores</h2>
                    </div>
                    <a href="{{ route('sellers.index') }}" class="hidden sm:flex items-center gap-2 text-white font-bold hover:text-brand-light transition-colors">
                        View All 
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 lg:gap-8">
                    @forelse($featuredSellers as $index => $seller)
                        <x-storefront.seller-card 
                            :seller="$seller" 
                            :delay="$delays[$index % 4]" 
                            :color="$colors[$index % 4]" 
                            :glass="true"
                        />
                    @empty
                        <div class="col-span-full w-full text-center py-12">
                            <p class="text-white/70 font-medium text-lg">No featured shops available at the moment.</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- Mobile View All Button -->
                <div class="mt-6 sm:hidden text-center">
                    <a href="{{ route('sellers.index') }}" class="inline-flex items-center gap-2 text-white font-bold text-sm bg-white/10 hover:bg-white/20 px-6 py-2.5 rounded-full border border-white/10 transition-colors">
                        View All Shops
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                </div>
            </div>
        </section>

        <section id="products" class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-16 sm:py-24 scroll-mt-20 scroll-reveal">
            <div class="flex justify-between items-end mb-8 sm:mb-12">
                <div>
                    <span class="text-primary font-bold uppercase tracking-widest text-xs sm:text-sm mb-1 sm:mb-2 block">Handpicked For You</span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-text-main">Featured Products</h2>
                </div>
                <a href="{{ route('home') }}" class="hidden sm:flex items-center gap-2 text-text-main font-bold hover:text-primary transition-colors">
                    Explore Catalog 
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 lg:gap-8">
                @forelse($featuredProducts as $product)
                    <div class="w-full h-full">
                        <x-storefront.seller-product-card :product="$product" />
                    </div>
                @empty
                    <div class="col-span-full w-full text-center py-12">
                        <p class="text-text-muted font-medium text-lg">No featured products available at the moment.</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Mobile Explore Button -->
            <div class="mt-6 sm:hidden text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-primary font-bold text-sm bg-primary/10 px-6 py-2.5 rounded-full">
                    Explore Catalog
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </a>
            </div>
        </section>

        <!-- SECTION 4: Powered by Top Logistics (Seamless Full-Width Dark Card Background) -->
        <section id="logistics" class="relative z-10 w-full bg-[#111] text-white py-16 sm:py-24 lg:py-28 overflow-hidden shadow-2xl scroll-mt-20 scroll-reveal">
            <!-- Ambient Radial Glow Effects -->
            <div class="absolute top-0 right-0 w-[400px] md:w-[800px] h-[400px] md:h-[800px] [--gw:400px] md:[--gw:800px] bg-primary/25 rounded-full filter blur-[80px] md:blur-[120px] translate-x-1/3 -translate-y-1/3 pointer-events-none animate-glow-slide-a"></div>
            <div class="absolute bottom-0 left-0 w-[300px] md:w-[600px] h-[300px] md:h-[600px] [--gw:300px] md:[--gw:600px] bg-secondary/15 rounded-full filter blur-[60px] md:blur-[100px] -translate-x-1/3 translate-y-1/3 pointer-events-none animate-glow-slide-b"></div>

            <!-- Centered Content Grid -->
            <div class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 flex flex-col lg:flex-row justify-between items-center gap-10 sm:gap-16">
                
                <!-- Left: Copy & Call-to-Action -->
                <div class="lg:w-1/2 w-full text-center lg:text-left">
                    <span class="text-brand-light font-bold uppercase tracking-widest text-xs sm:text-sm mb-3 sm:mb-4 block">Premium Fleet</span>
                    <h2 class="text-4xl sm:text-5xl lg:text-7xl font-serif font-bold tracking-tight mb-5 sm:mb-8 leading-[1.1] sm:leading-[1.05]">
                        Powered by<br class="hidden sm:block">Top Logistics
                    </h2>
                    <p class="text-base sm:text-xl text-white/75 mb-8 sm:mb-10 leading-relaxed font-medium">
                        Storkia partners with the most reliable logistics providers to ensure your package is handled with care and delivered with unprecedented speed.
                    </p>
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 bg-surface text-primary-dark px-8 sm:px-10 py-3 sm:py-4 rounded-full font-bold text-sm sm:text-lg hover:bg-brand-light hover:scale-105 transition-all duration-300 shadow-xl">
                        Become a Partner
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
                
                <!-- Right: Registered logistics partners (from the database) -->
                @php
                    $logiCount = $logisticsPartners->count();
                    $logiScrolls = $logiCount > 2;
                    $logiThemes = [
                        [
                            'gradient'  => 'from-primary to-primary-dark',
                            'iconColor' => 'text-white',
                            'spot'      => 'rgba(207,65,115,0.24)',
                            'hover'     => 'hover:border-primary/40 hover:shadow-[0_12px_40px_-12px_rgba(207,65,115,0.5)]',
                        ],
                        [
                            'gradient'  => 'from-secondary to-[#F9A8D4]',
                            'iconColor' => 'text-[#111]',
                            'spot'      => 'rgba(243,147,153,0.2)',
                            'hover'     => 'hover:border-secondary/40 hover:shadow-[0_12px_40px_-12px_rgba(243,147,153,0.4)]',
                        ],
                    ];
                @endphp

                <div class="lg:w-1/2 w-full">
                    @if ($logiCount === 0)
                        <!-- No logistics registered yet -->
                        <article class="relative overflow-hidden rounded-[1.5rem] sm:rounded-[2rem] border border-white/10 bg-white/5 p-6 sm:p-10 shadow-lg backdrop-blur-xl">
                            <span aria-hidden="true" class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent"></span>
                            <div class="relative mb-3 sm:mb-4 flex items-center gap-4 sm:gap-5">
                                <div class="flex h-12 w-12 sm:h-14 sm:w-14 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dark shadow-lg ring-1 ring-white/20">
                                    <svg class="h-6 w-6 sm:h-7 sm:w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <h3 class="text-xl sm:text-2xl md:text-3xl font-extrabold tracking-tight">Partners coming soon</h3>
                            </div>
                            <p class="relative text-sm sm:text-lg font-medium leading-relaxed text-white/70 text-pretty">Our logistics partners will appear here once they are registered.</p>
                        </article>
                    @else
                        <div class="{{ $logiScrolls ? 'logi-viewport' : '' }}"
                             @if ($logiScrolls) role="region" aria-label="Registered logistics partners" @endif>
                            <div class="{{ $logiScrolls ? 'logi-track' : '' }}"
                                 @if ($logiScrolls) style="--logi-dur: {{ max(16, $logiCount * 6) }}s" @endif>

                                {{-- When there are more than 2 partners the list is rendered twice for a seamless loop --}}
                                @foreach (range(0, $logiScrolls ? 1 : 0) as $copy)
                                    <div class="flex flex-col gap-4 sm:gap-6 {{ $logiScrolls ? 'px-2 pb-4 sm:pb-6' : '' }}"
                                         @if ($copy === 1) aria-hidden="true" @endif>
                                        @foreach ($logisticsPartners as $partner)
                                            @php $theme = $logiThemes[$loop->index % 2]; @endphp
                                            <article data-spotlight
                                                     style="--spot: {{ $theme['spot'] }}"
                                                     class="group relative overflow-hidden rounded-[1.5rem] sm:rounded-[2rem] border border-white/10 bg-white/5 p-6 sm:p-10 shadow-lg backdrop-blur-xl cursor-default transition-all duration-500 ease-out hover:-translate-y-1 hover:bg-white/[0.08] motion-reduce:transition-none motion-reduce:hover:translate-y-0 {{ $theme['hover'] }}">

                                                <!-- Cursor-following spotlight (appears on hover) -->
                                                <span aria-hidden="true"
                                                      class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                                                      style="background: radial-gradient(420px circle at var(--x, 50%) var(--y, 0%), var(--spot), transparent 65%);"></span>

                                                <!-- Glass edge highlight -->
                                                <span aria-hidden="true" class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent"></span>

                                                <div class="relative flex items-center gap-4 sm:gap-5">
                                                    <!-- Logo (or initial) with soft colored glow -->
                                                    <div class="relative flex-shrink-0">
                                                        <div aria-hidden="true" class="absolute inset-0 rounded-full bg-gradient-to-br {{ $theme['gradient'] }} opacity-40 blur-lg transition-opacity duration-500 group-hover:opacity-90"></div>
                                                        <div class="relative flex h-12 w-12 sm:h-14 sm:w-14 items-center justify-center overflow-hidden rounded-full {{ $partner->logo_path ? 'bg-white' : 'bg-gradient-to-br ' . $theme['gradient'] }} shadow-lg ring-1 ring-white/20 transition-transform duration-500 group-hover:-rotate-6 group-hover:scale-110">
                                                            @if ($partner->logo_path)
                                                                <img src="{{ Storage::url($partner->logo_path) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                            @else
                                                                <span class="text-lg sm:text-xl font-extrabold {{ $theme['iconColor'] }}">{{ strtoupper(mb_substr($partner->business_name, 0, 1)) }}</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <h3 class="min-w-0 break-words text-xl sm:text-2xl md:text-3xl font-extrabold tracking-tight line-clamp-2">{{ $partner->business_name }}</h3>
                                                </div>

                                                <div class="relative mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                                                    <p class="flex min-w-0 items-center gap-2 text-sm sm:text-base font-medium text-white/70">
                                                        <svg class="h-4 w-4 flex-shrink-0 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                        <span class="line-clamp-1">{{ $partner->municipality }}, {{ $partner->province }}</span>
                                                    </p>
                                                    @if ($partner->active_hubs_count > 0)
                                                        <span class="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-bold text-white/80">
                                                            {{ $partner->active_hubs_count }} {{ \Illuminate\Support\Str::plural('hub', $partner->active_hubs_count) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <script>
                    // Moves the spotlight with the mouse (desktop only; touch devices skip it)
                    document.addEventListener('DOMContentLoaded', () => {
                        document.querySelectorAll('[data-spotlight]').forEach((card) => {
                            card.addEventListener('pointermove', (e) => {
                                if (e.pointerType !== 'mouse') return;
                                const r = card.getBoundingClientRect();
                                card.style.setProperty('--x', (e.clientX - r.left) + 'px');
                                card.style.setProperty('--y', (e.clientY - r.top) + 'px');
                            });
                        });
                    });
                </script>

            </div>
        </section>

        <!-- Kia Mascot Visual Novel Dialogue Section -->
        <section id="about" class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-16 sm:py-24 pb-24 sm:pb-32 scroll-mt-20 scroll-reveal">
            <div class="text-center max-w-5xl mx-auto mb-10"
                x-data="kiaDialogue()"
                @keydown.escape="close()">
            
                <span class="text-primary font-bold uppercase tracking-widest text-xs sm:text-sm mb-2 sm:mb-4 block">Our Story</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-text-main mb-8 sm:mb-10">About Storkia</h2>
            
                <!-- Screen readers get the line once per step, instead of every typed letter -->
                <p class="sr-only" aria-live="polite" x-text="isActive ? dialogues[step] : ''"></p>
            
                <!-- Stage -->
                <div class="relative w-full max-w-4xl mx-auto flex flex-col md:flex-row items-center justify-center transition-all duration-700"
                    :class="isActive ? 'md:items-start' : ''">
            
                    <!-- Soft spotlight that follows Kia when the dialogue opens -->
                    <div aria-hidden="true" class="pointer-events-none absolute top-1/2 h-72 w-72 sm:h-96 sm:w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/10 blur-3xl transition-all duration-700"
                        :class="isActive ? 'left-1/2 md:left-[20%]' : 'left-1/2'"></div>
            
                    <!-- Kia (a real button, so keyboard and screen-reader users can talk to her too) -->
                    <button type="button"
                            @click="interact()"
                            :aria-label="isActive ? 'Continue the conversation with Kia' : 'Talk to Kia'"
                            class="group relative z-20 flex-shrink-0 cursor-pointer rounded-[2rem] outline-none transition-all duration-700 ease-in-out focus-visible:ring-4 focus-visible:ring-primary/40"
                            :class="isActive ? 'md:w-[40%] md:pr-8 md:pt-6' : 'w-full flex justify-center'">
            
                        <span class="relative inline-block transition-transform duration-700"
                            :class="isActive ? 'scale-90 sm:scale-100' : 'scale-100 sm:scale-110 translate-y-4 sm:translate-y-6'">
            
                            <!-- Gentle idle float until the conversation starts -->
                            <span class="block" :class="isActive ? '' : 'animate-kia-float'">
                                <!-- Expressions are stacked and cross-fade instead of swapping abruptly -->
                                <span class="grid origin-bottom transition-transform duration-300 group-hover:scale-105"
                                    :class="isBouncing ? 'animate-kia-jump' : ''">
                                    <template x-for="(src, i) in images" :key="i">
                                        <img :src="src" alt=""
                                            class="col-start-1 row-start-1 h-48 sm:h-56 md:h-72 w-auto object-contain drop-shadow-xl select-none transition-opacity duration-300"
                                            :class="(isActive ? step : 0) === i ? 'opacity-100' : 'opacity-0'">
                                    </template>
                                </span>
                            </span>
            
                            <!-- Ground shadow keeps her anchored while she floats -->
                            <span aria-hidden="true" class="mx-auto mt-1 block h-3 w-24 sm:w-32 rounded-full bg-black/10 blur-md"></span>
            
                            <!-- Helper tooltip -->
                            <span x-show="!isActive"
                                x-transition
                                aria-hidden="true"
                                class="pointer-events-none absolute top-0 -right-4 sm:-right-8 whitespace-nowrap rounded-full border border-primary/20 bg-white/90 px-3 py-1.5 sm:px-4 sm:py-2 text-[10px] sm:text-xs font-bold text-primary shadow-md animate-pulse">
                                Tap me!
                            </span>
                        </span>
                    </button>
            
                    <!-- Dialogue box -->
                    <div class="w-full flex-shrink-0 transform transition-all duration-700 ease-in-out origin-top md:origin-left"
                        :class="isActive ? 'opacity-100 md:w-[60%] scale-100 max-h-[700px]' : 'opacity-0 md:w-0 scale-95 max-h-0 overflow-hidden'">
            
                        <div @click="interact()"
                            class="relative z-10 mt-8 md:mt-0 w-full cursor-pointer rounded-[2rem] sm:rounded-[2.5rem] border border-primary/20 bg-white p-6 sm:p-8 md:p-10 text-left shadow-2xl ring-4 ring-white/60 transition-colors hover:border-primary/40">
            
                            <!-- Speech-bubble tail pointing at Kia (up on mobile, left on desktop) -->
                            <span aria-hidden="true" class="absolute -top-2 left-1/2 h-4 w-4 -translate-x-1/2 rotate-45 border-l border-t border-primary/20 bg-white md:top-20 md:left-0 md:-translate-y-1/2 md:border-t-0 md:border-b"></span>
            
                            <!-- Name badge -->
                            <div class="absolute -top-5 sm:-top-6 left-6 sm:left-8 md:left-10 rounded-full border-2 border-white bg-primary px-6 sm:px-8 py-1.5 sm:py-2 text-sm sm:text-lg font-extrabold tracking-wide text-white shadow-[0_4px_14px_0_rgba(207,65,115,0.39)]">
                                Kia
                            </div>
            
                            <!-- Text area: only the typed text is rendered, so the box grows line by line as she types -->
                            <div class="min-h-[3rem] pb-8 pr-4 sm:pr-6" aria-hidden="true">
                                <p class="text-base sm:text-lg md:text-xl lg:text-[1.375rem] font-medium leading-relaxed text-text-main text-pretty">
                                    <span class="kia-typed" :class="isTyping ? 'is-typing' : ''" x-text="typed"></span>
                                </p>
                            </div>
            
                            <!-- Progress dots -->
                            <div class="absolute bottom-5 sm:bottom-7 left-6 sm:left-8 md:left-10 flex items-center gap-1.5" aria-hidden="true">
                                <template x-for="(d, i) in dialogues" :key="'dot' + i">
                                    <span class="h-1.5 rounded-full transition-all duration-500"
                                        :class="i === step ? 'w-6 bg-primary' : (i < step ? 'w-1.5 bg-primary/50' : 'w-1.5 bg-border-subtle')"></span>
                                </template>
                            </div>
            
                            <!-- Next / done indicator -->
                            <div class="absolute bottom-4 sm:bottom-6 right-6 sm:right-8 text-primary/70 transition-opacity duration-300"
                                :class="isTyping ? 'opacity-0' : 'opacity-100'" aria-hidden="true">
                                <svg x-show="step < dialogues.length - 1" class="h-5 w-5 sm:h-6 sm:w-6 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                                <svg x-show="step === dialogues.length - 1" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-2 sm:gap-4 mb-8 sm:mb-12 mt-16 sm:mt-20 px-4">
                <div class="h-px bg-border-subtle flex-1 max-w-[50px] sm:max-w-[100px]"></div>
                <h3 class="text-xl sm:text-2xl font-extrabold tracking-tight text-center text-text-main uppercase whitespace-nowrap">Meet the Developers</h3>
                <div class="h-px bg-border-subtle flex-1 max-w-[50px] sm:max-w-[100px]"></div>
            </div>

            @php
                $developers = [
                    [
                        'name' => 'Van Romulo',
                        'role' => 'Full-stack Website Developer',
                        'bio'  => 'Architecting the core systems and database relations to ensure seamless multi-portal integration.',
                        'icon' => 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
                    ],
                    [
                        'name' => 'Mark Trilles',
                        'role' => 'Full-stack Web & Mobile',
                        'bio'  => 'Bridging web and mobile experiences by developing scalable APIs and responsive interfaces across all platforms.',
                        'icon' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
                    ],
                    [
                        'name' => 'Genea Umali',
                        'role' => 'UI/UX Designer/QA Tester',
                        'bio'  => 'Crafting intuitive, user-centric designs and conducting rigorous quality assurance to guarantee a flawless shopping experience.',
                        'icon' => 'M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z',
                    ],
                ];
            @endphp
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 lg:gap-10">
                @foreach ($developers as $dev)
                    <article data-dev-card class="group relative flex h-full flex-col items-center overflow-hidden rounded-[2rem] sm:rounded-[2.5rem] border border-border-subtle/60 bg-white p-6 sm:p-10 text-center shadow-lg transition-all duration-500 ease-out hover:-translate-y-1.5 hover:border-primary/30 hover:shadow-2xl hover:shadow-primary/10 motion-reduce:transition-none motion-reduce:hover:translate-y-0 {{ $loop->last ? 'sm:col-span-2 md:col-span-1 sm:mx-auto md:mx-0 sm:w-1/2 md:w-full' : '' }}">
            
                        <!-- Accent line that draws across the top on hover -->
                        <span aria-hidden="true" class="absolute inset-x-0 top-0 h-1 origin-left scale-x-0 bg-gradient-to-r from-primary via-secondary to-primary transition-transform duration-500 ease-out group-hover:scale-x-100"></span>
            
                        <!-- Soft glow behind the icon -->
                        <span aria-hidden="true" class="pointer-events-none absolute -top-14 left-1/2 h-44 w-44 -translate-x-1/2 rounded-full bg-primary/10 opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"></span>
            
                        <!-- Icon tile -->
                        <div class="relative mb-6 sm:mb-8 flex-shrink-0">
                            <div aria-hidden="true" class="absolute inset-0 rounded-2xl bg-primary/30 opacity-0 blur-xl transition-opacity duration-500 group-hover:opacity-100"></div>
                            <div class="relative flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-surface-subtle text-primary shadow-sm ring-1 ring-border-subtle/70 transition-all duration-500 group-hover:-rotate-3 group-hover:scale-105 group-hover:bg-gradient-to-br group-hover:from-primary group-hover:to-primary-dark group-hover:text-white group-hover:ring-transparent">
                                <svg class="h-8 w-8 sm:h-10 sm:w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $dev['icon'] }}" />
                                </svg>
                            </div>
                        </div>
            
                        <h4 data-typer="45" class="relative mb-3 text-xl sm:text-2xl font-extrabold tracking-tight text-text-main text-balance">{{ $dev['name'] }}</h4>
            
                        <div data-typer="35" class="relative mb-5 inline-block rounded-full border border-border-subtle/50 bg-surface-subtle px-3 sm:px-4 py-1.5 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-text-main transition-colors duration-500 group-hover:border-primary/20 group-hover:bg-primary/10 group-hover:text-primary">{{ $dev['role'] }}</div>
            
                        <!-- Divider that lengthens on hover -->
                        <span aria-hidden="true" class="mb-5 block h-px w-10 bg-border-subtle transition-all duration-500 group-hover:w-20 group-hover:bg-primary/40"></span>
            
                        <p data-typer="14" class="relative max-w-[34ch] text-xs sm:text-sm font-medium leading-relaxed text-text-muted text-pretty">{{ $dev['bio'] }}</p>
                    </article>
                @endforeach
            </div>
            
            <style>
                /* Blinking caret on the line currently being typed. Zero layout width, so text never shifts. */
                [data-typer].is-typing [data-done]::after {
                    content: '';
                    display: inline-block;
                    width: 0;
                    height: 1.05em;
                    position: relative;
                    left: 2px;
                    vertical-align: text-bottom;
                    box-shadow: 0 0 0 1px currentColor;
                    animation: type-caret 0.8s steps(1) infinite;
                }
                @keyframes type-caret {
                    50% { opacity: 0; }
                }
            </style>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const cards = document.querySelectorAll('[data-dev-card]');
                    if (!cards.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            
                    // Split each text into "typed" + "not yet typed (transparent)" so the full text
                    // always occupies its final space and nothing reflows while typing.
                    cards.forEach((card) => {
                        card._token = 0;        // changes whenever the card is reset, which cancels any typing in progress
                        card._playing = false;
                        card._startTimer = null;
            
                        card.querySelectorAll('[data-typer]').forEach((el) => {
                            const text = el.textContent.replace(/\s+/g, ' ').trim();
                            el.dataset.full = text;
                            el.textContent = '';
                            const done = document.createElement('span');
                            done.setAttribute('data-done', '');
                            const rest = document.createElement('span');
                            rest.setAttribute('data-rest', '');
                            rest.style.opacity = '0';
                            rest.textContent = text;
                            el.append(done, rest);
                        });
                    });
            
                    // Back to the blank "not typed yet" state
                    const resetText = (card) => {
                        card.querySelectorAll('[data-typer]').forEach((el) => {
                            el.classList.remove('is-typing');
                            el.querySelector('[data-done]').textContent = '';
                            el.querySelector('[data-rest]').textContent = el.dataset.full;
                        });
                    };
            
                    // Resolves true when the line finished, false if the card was reset while typing
                    const typeOne = (el, card, token) => new Promise((resolve) => {
                        const text = el.dataset.full;
                        const speed = Number(el.dataset.typer) || 30;
                        const done = el.querySelector('[data-done]');
                        const rest = el.querySelector('[data-rest]');
                        if (!text) return resolve(true);
                        let i = 0;
                        el.classList.add('is-typing');
                        const tick = () => {
                            if (card._token !== token) return resolve(false);
                            i++;
                            done.textContent = text.slice(0, i);
                            rest.textContent = text.slice(i);
                            if (i < text.length) {
                                setTimeout(tick, speed);
                            } else {
                                el.classList.remove('is-typing');
                                setTimeout(() => resolve(card._token === token), 180); // short pause before the next line
                            }
                        };
                        tick();
                    });
            
                    // Name -> role -> description, one after another
                    const startCard = (card, delay) => {
                        if (card._playing) return;
                        card._playing = true;
                        const token = ++card._token;
                        card._startTimer = setTimeout(async () => {
                            for (const el of card.querySelectorAll('[data-typer]')) {
                                if (!(await typeOne(el, card, token))) return;
                            }
                        }, delay);
                    };
            
                    // Cancel anything in progress and blank the card so it can type again next time
                    const resetCard = (card) => {
                        clearTimeout(card._startTimer);
                        card._token++;
                        card._playing = false;
                        resetText(card);
                    };
            
                    // Start when a card is mostly in view; reset once it has fully left the screen.
                    // Cards that appear together are staggered.
                    const io = new IntersectionObserver((entries) => {
                        let n = 0;
                        entries.forEach((entry) => {
                            const card = entry.target;
                            if (entry.intersectionRatio === 0) {
                                resetCard(card);
                            } else if (entry.intersectionRatio >= 0.3 && !card._playing) {
                                startCard(card, n++ * 350);
                            }
                        });
                    }, { threshold: [0, 0.35] });
            
                    cards.forEach((card) => io.observe(card));
                });
            </script>
        </section>
    </div>

</div>

<style>
    .animate-blob {
        animation: blob 8s infinite cubic-bezier(0.4, 0, 0.2, 1);
    }
    .animation-delay-2000 {
        animation-delay: 2s;
    }
    .animation-delay-4000 {
        animation-delay: 4s;
    }
    .animate-gradient {
        animation: gradient 8s linear infinite;
    }

    .animate-glow-slide-a {
        animation:
            glow-slide-a 20s cubic-bezier(0.45, 0, 0.55, 1) infinite alternate,
            glow-pulse-a 9s ease-in-out infinite alternate;
        will-change: transform, scale;
    }
    .animate-glow-slide-b {
        animation:
            glow-slide-b 20s cubic-bezier(0.45, 0, 0.55, 1) infinite alternate,
            glow-pulse-b 9s ease-in-out infinite alternate;
        will-change: transform, scale;
    }
    
    @keyframes blob {
        0% { transform: translate(0px, 0px) scale(1); }
        33% { transform: translate(40px, -60px) scale(1.1); }
        66% { transform: translate(-30px, 30px) scale(0.9); }
        100% { transform: translate(0px, 0px) scale(1); }
    }
    @keyframes gradient {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Opposite phases: when A grows, B shrinks */
    @keyframes glow-pulse-a {
        from { scale: 0.8; }
        to   { scale: 1.3; }
    }
    @keyframes glow-pulse-b {
        from { scale: 1.3; }
        to   { scale: 0.8; }
    }
    /* A: top-right -> top-left. B: bottom-left -> bottom-right */
    @keyframes glow-slide-a {
        from { transform: translateX(0); }
        to   { transform: translateX(calc(-100vw + var(--gw) / 3)); }
    }
    @keyframes glow-slide-b {
        from { transform: translateX(0); }
        to   { transform: translateX(calc(100vw - var(--gw) / 3)); }
    }

    @media (prefers-reduced-motion: reduce) {
        .animate-glow-slide-a, .animate-glow-slide-b { animation: none; }
    }

    /* Kia Bounce Animation */
    @keyframes kia-jump {
        0% { transform: translateY(0) scale(1, 1); }
        25% { transform: translateY(-10px) scale(0.95, 1.05); }
        50% { transform: translateY(-35px) scale(1, 1); }
        75% { transform: translateY(-10px) scale(1.02, 0.98); }
        100% { transform: translateY(0) scale(1, 1); }
    }
    .animate-kia-jump {
        animation: kia-jump 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    /* Logistics partners: vertical infinite scroll (only used when there are more than 2) */
    .logi-viewport {
        height: 22rem;
        overflow: hidden;
        -webkit-mask-image: linear-gradient(to bottom, transparent, #000 10%, #000 90%, transparent);
                mask-image: linear-gradient(to bottom, transparent, #000 10%, #000 90%, transparent);
    }
    @media (min-width: 640px)  { .logi-viewport { height: 26rem; } }
    @media (min-width: 1024px) { .logi-viewport { height: 28rem; } }

    .logi-track {
        animation: logi-scroll var(--logi-dur, 30s) linear infinite;
        will-change: transform;
    }
    /* The list is rendered twice, so moving by 50% lands exactly where it started */
    @keyframes logi-scroll {
        from { transform: translateY(0); }
        to   { transform: translateY(-50%); }
    }
    .logi-viewport:hover .logi-track,
    .logi-viewport:focus-within .logi-track {
        animation-play-state: paused;
    }
    @media (prefers-reduced-motion: reduce) {
        .logi-track { animation: none; }
        .logi-track > [aria-hidden="true"] { display: none; }
        .logi-viewport { overflow-y: auto; -webkit-mask-image: none; mask-image: none; }
    }

    .scroll-reveal {
        opacity: 0;
        transform: translateY(50px);
        transition: opacity 0.8s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        will-change: opacity, transform;
    }
    
    .scroll-reveal.active {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<style>
    /* Idle float for Kia */
    @keyframes kia-float {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }
    .animate-kia-float { animation: kia-float 3.4s ease-in-out infinite; }
 
    /* Blinking caret at the end of the typed text. Zero layout width, so words never re-wrap. */
    .kia-typed.is-typing::after {
        content: '';
        display: inline-block;
        width: 0;
        height: 1.05em;
        position: relative;
        left: 2px;
        vertical-align: text-bottom;
        box-shadow: 0 0 0 1px currentColor;
        animation: kia-caret 0.8s steps(1) infinite;
    }
    @keyframes kia-caret { 50% { opacity: 0; } }
 
    @media (prefers-reduced-motion: reduce) {
        .animate-kia-float { animation: none; }
    }
</style>

<script>
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    
    document.addEventListener('alpine:init', () => {
        Alpine.data('kiaDialogue', () => ({
            isActive: false,
            step: 0,
            isTyping: false,
            isBouncing: false,
            typed: '',
            timer: null,
            reduceMotion: false,
            dialogues: [
                "Hi there! I'm Kia, the official stork mascot of Storkia! It is absolutely wonderful to finally meet you. Let me tell you a little bit about what we do around here!",
                "Storkia was born out of a vision to revolutionize localized e-commerce. We are more than just a marketplace; we are a community-driven ecosystem that bridges the gap between passionate local sellers, eager buyers, and top-tier logistics providers. Our mission is to make fast, reliable, and exclusive shopping accessible to everyone in the city.",
                "That's all from me for now! Now you know the heart behind our story. Thank you so much for stopping by, explore around, and take care out there!"
            ],
            images: [
                "{{ asset('kia-assets/kia-expressions-happy.svg') }}",
                "{{ asset('kia-assets/kia-expressions-chirpy.svg') }}",
                "{{ asset('kia-assets/kia-expressions-happy.svg') }}"
            ],
 
            init() {
                this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            },
 
            interact() {
                this.triggerBounce();
 
                if (!this.isActive) {
                    this.isActive = true;
                    this.step = 0;
                    this.typed = '';
                    // let the box finish opening before typing starts
                    setTimeout(() => { if (this.isActive) this.typeText(); }, 400);
                    return;
                }
 
                if (this.isTyping) {
                    // fast-forward the current line
                    clearTimeout(this.timer);
                    this.typed = this.dialogues[this.step];
                    this.isTyping = false;
                    return;
                }
 
                if (this.step < this.dialogues.length - 1) {
                    this.step++;
                    this.typeText();
                } else {
                    this.close();
                }
            },
 
            close() {
                if (!this.isActive) return;
                clearTimeout(this.timer);
                this.isTyping = false;
                this.isActive = false;
                // reset after the box has faded out, so the text doesn't vanish mid-fade
                setTimeout(() => {
                    if (!this.isActive) { this.typed = ''; this.step = 0; }
                }, 700);
            },
 
            triggerBounce() {
                this.isBouncing = false;
                // timeout forces a reflow so the animation can replay quickly
                setTimeout(() => { this.isBouncing = true; }, 10);
                setTimeout(() => { this.isBouncing = false; }, 410);
            },
 
            typeText() {
                clearTimeout(this.timer);
                const text = this.dialogues[this.step];
                this.typed = '';
 
                if (this.reduceMotion) {
                    this.typed = text;
                    this.isTyping = false;
                    return;
                }
 
                this.isTyping = true;
                let i = 0;
                const tick = () => {
                    i++;
                    this.typed = text.slice(0, i);
                    if (i >= text.length) {
                        this.isTyping = false;
                        return;
                    }
                    // natural rhythm: short pause on commas, longer on sentence ends
                    const ch = text[i - 1];
                    const delay = '.!?'.includes(ch) ? 260 : (ch === ',' || ch === ';') ? 120 : 15;
                    this.timer = setTimeout(tick, delay);
                };
                this.timer = setTimeout(tick, 15);
            }
        }));
    });

    document.addEventListener('DOMContentLoaded', () => {
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -50px 0px', 
            threshold: 0.1 
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                } else {
                    if(entry.boundingClientRect.top > 0) {
                        entry.target.classList.remove('active');
                    }
                }
            });
        }, observerOptions);

        const revealElements = document.querySelectorAll('.scroll-reveal');
        
        revealElements.forEach((el) => {
            observer.observe(el);
        });
    });
</script>
@endsection