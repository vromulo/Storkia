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
@endphp

<div class="bg-surface font-sans antialiased text-text-main overflow-hidden selection:bg-primary selection:text-white relative">
    
    <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.02]" style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 32px 32px;"></div>

    <section class="relative z-10 w-full min-h-[calc(100vh-80px)] flex flex-col items-center justify-center text-center scroll-reveal overflow-hidden py-16 sm:py-20 px-4 sm:px-6">
        <div class="absolute inset-0 z-0 bg-cover bg-center opacity-80 pointer-events-none" style="background-image: url('{{ asset('assets/landing-page-section-2.avif') }}');"></div>
        <div class="absolute inset-0 z-0 bg-gradient-to-b from-surface/60 via-transparent to-surface pointer-events-none"></div>

        <div class="absolute top-10 left-4 sm:left-10 w-48 sm:w-80 h-48 sm:h-80 bg-brand-light/40 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob"></div>
        <div class="absolute top-40 right-4 sm:right-10 w-48 sm:w-80 h-48 sm:h-80 bg-secondary/30 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-10 left-1/2 w-48 sm:w-80 h-48 sm:h-80 bg-primary/20 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-70 animate-blob animation-delay-4000"></div>

        <div class="relative z-20 max-w-5xl mx-auto w-full">
            <h1 class="text-5xl sm:text-7xl lg:text-[6rem] font-extrabold tracking-tighter text-text-main mb-6 sm:mb-8 leading-[1.1] sm:leading-[1.05] drop-shadow-sm">
                Fast delivery. <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-secondary to-primary bg-[length:200%_auto] animate-gradient">Exclusive deals.</span>
            </h1>
            <p class="text-lg sm:text-xl lg:text-2xl text-text-muted mb-8 sm:mb-12 max-w-3xl mx-auto leading-relaxed font-medium px-2">
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

    <!-- Interactive Grid Cards Section -->
    <section class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-12 sm:py-16 scroll-reveal" x-data="{ activeCard: 1 }">
        <div class="flex flex-col md:flex-row w-full h-auto md:h-[500px] gap-4 md:gap-6 lg:gap-8">
            
            <!-- Card 1 -->
            <div @click="activeCard = 1"
                 :class="activeCard === 1 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                 class="relative flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                     style="background-image: url('{{ asset('assets/carousel-slide-3.webp') }}');"
                     :class="activeCard === 1 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                     :class="activeCard === 1 ? 'opacity-90' : 'opacity-100'"></div>

                <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex flex-col justify-end h-full z-10">
                    <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center mb-3 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                         :class="activeCard === 1 ? 'bg-primary border-primary shadow-[0_4px_14px_0_rgba(207,65,115,0.39)]' : ''">
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
            <div @click="activeCard = 2"
                 :class="activeCard === 2 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                 class="relative flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                     style="background-image: url('{{ asset('assets/carousel-slide-1.webp') }}');"
                     :class="activeCard === 2 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                     :class="activeCard === 2 ? 'opacity-90' : 'opacity-100'"></div>

                <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex flex-col justify-end h-full z-10">
                    <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center mb-3 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                         :class="activeCard === 2 ? 'bg-secondary border-secondary text-[#111] shadow-[0_4px_14px_0_rgba(244,185,184,0.5)]' : ''">
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
            <div @click="activeCard = 3"
                 :class="activeCard === 3 ? 'md:flex-[3] h-64 md:h-full' : 'md:flex-[1] h-24 md:h-full cursor-pointer opacity-90 md:opacity-80 hover:opacity-100'"
                 class="relative flex-1 w-full overflow-hidden rounded-[2rem] md:rounded-[2.5rem] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] group shadow-lg border border-white/20">
                <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700"
                     style="background-image: url('{{ asset('assets/carousel-slide-2.webp') }}');"
                     :class="activeCard === 3 ? 'scale-105' : 'scale-100 grayscale-[15%] md:grayscale-[30%]' "></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent transition-opacity duration-700" 
                     :class="activeCard === 3 ? 'opacity-90' : 'opacity-100'"></div>

                <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 lg:p-10 flex flex-col justify-end h-full z-10">
                    <div class="w-10 h-10 md:w-14 md:h-14 bg-white/10 backdrop-blur-md rounded-xl md:rounded-2xl flex items-center justify-center mb-3 md:mb-6 text-white shadow-sm border border-white/20 transition-all duration-500 group-hover:scale-110"
                         :class="activeCard === 3 ? 'bg-primary border-primary shadow-[0_4px_14px_0_rgba(207,65,115,0.39)]' : ''">
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

    <section id="shops" class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-16 sm:py-24 scroll-mt-20 scroll-reveal">
        <div class="flex justify-between items-end mb-8 sm:mb-12">
            <div>
                <span class="text-primary font-bold uppercase tracking-widest text-xs sm:text-sm mb-1 sm:mb-2 block">Curated Marketplace</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-text-main">Featured Shops</h2>
            </div>
            <a href="{{ route('home') }}" class="hidden sm:flex items-center gap-2 text-text-main font-bold hover:text-primary transition-colors">
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
                />
            @empty
                <div class="col-span-full w-full text-center py-12">
                    <p class="text-text-muted font-medium text-lg">No featured shops available at the moment.</p>
                </div>
            @endforelse
        </div>
        
        <!-- Mobile View All Button -->
        <div class="mt-6 sm:hidden text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-primary font-bold text-sm bg-primary/10 px-6 py-2.5 rounded-full">
                View All Shops
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
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

    <section id="logistics" class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-16 sm:py-24 scroll-mt-20 scroll-reveal">
        <div class="bg-[#111] rounded-[2rem] sm:rounded-[3rem] p-6 sm:p-12 lg:p-24 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute top-0 right-0 w-[400px] md:w-[800px] h-[400px] md:h-[800px] bg-primary-dark/30 rounded-full filter blur-[80px] md:blur-[120px] translate-x-1/3 -translate-y-1/3 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-[300px] md:w-[600px] h-[300px] md:h-[600px] bg-secondary/20 rounded-full filter blur-[60px] md:blur-[100px] -translate-x-1/3 translate-y-1/3 pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col lg:flex-row justify-between items-center gap-10 sm:gap-16">
                <div class="lg:w-1/2 w-full text-center lg:text-left">
                    <span class="text-primary font-bold uppercase tracking-widest text-xs sm:text-sm mb-3 sm:mb-4 block">Premium Fleet</span>
                    <h2 class="text-4xl sm:text-5xl lg:text-7xl font-serif font-bold tracking-tight mb-5 sm:mb-8 leading-[1.1] sm:leading-[1.05]">
                        Powered by<br class="hidden sm:block">Top Logistics
                    </h2>
                    <p class="text-base sm:text-xl text-white/70 mb-8 sm:mb-10 leading-relaxed font-medium">
                        Storkia partners with the most reliable logistics providers to ensure your package is handled with care and delivered with unprecedented speed.
                    </p>
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 bg-white text-text-main px-8 sm:px-10 py-3 sm:py-4 rounded-full font-bold text-sm sm:text-lg hover:bg-brand-light hover:scale-105 transition-all duration-300 shadow-xl">
                        Become a Partner
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
                
                <div class="lg:w-1/2 w-full flex flex-col gap-4 sm:gap-6">
                    <div class="bg-white/5 backdrop-blur-xl rounded-[1.5rem] sm:rounded-[2rem] p-6 sm:p-10 border border-white/10 hover:bg-white/10 transition-colors duration-300 cursor-default group shadow-lg">
                        <div class="flex items-center gap-4 sm:gap-5 mb-3 sm:mb-4">
                            <div class="w-12 h-12 sm:w-14 sm:h-14 flex-shrink-0 bg-gradient-to-br from-primary to-primary-dark rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h3 class="text-xl sm:text-2xl md:text-3xl font-extrabold tracking-tight break-words">SwiftExpress</h3>
                        </div>
                        <p class="text-white/60 text-sm sm:text-lg font-medium leading-relaxed">Same-day delivery specialists for urban zones. Real-time tracking included directly in your Storkia app.</p>
                    </div>
                    <div class="bg-white/5 backdrop-blur-xl rounded-[1.5rem] sm:rounded-[2rem] p-6 sm:p-10 border border-white/10 hover:bg-white/10 transition-colors duration-300 cursor-default group shadow-lg">
                        <div class="flex items-center gap-4 sm:gap-5 mb-3 sm:mb-4">
                            <div class="w-12 h-12 sm:w-14 sm:h-14 flex-shrink-0 bg-gradient-to-br from-secondary to-[#F9A8D4] rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-[#111]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <h3 class="text-xl sm:text-2xl md:text-3xl font-extrabold tracking-tight break-words">SecureFreight</h3>
                        </div>
                        <p class="text-white/60 text-sm sm:text-lg font-medium leading-relaxed">Heavy and fragile item secure transport with dedicated handling protocols. Your delicate items are safe.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kia Mascot Visual Novel Dialogue Section -->
    <section id="about" class="relative z-10 max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12 py-16 sm:py-24 pb-24 sm:pb-32 scroll-mt-20 scroll-reveal">
        <div class="text-center max-w-5xl mx-auto mb-10" x-data="kiaDialogue()">
            <span class="text-primary font-bold uppercase tracking-widest text-xs sm:text-sm mb-2 sm:mb-4 block">Our Story</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-text-main mb-12 sm:mb-16">About Storkia</h2>
            
            <div class="relative w-full max-w-4xl mx-auto flex flex-col md:flex-row items-center transition-all duration-700 justify-center min-h-[300px] sm:min-h-[350px]">
                
                <!-- Kia Character -->
                <div class="relative z-20 cursor-pointer transition-all duration-700 ease-in-out flex-shrink-0"
                     :class="isActive ? 'md:w-[40%] md:pr-8' : 'w-full flex justify-center'"
                     @click="interact()">
                    
                    <div class="relative inline-block transition-transform duration-700"
                         :class="isActive ? 'scale-90 sm:scale-100' : 'scale-100 sm:scale-110'">
                        <img :src="isActive ? images[step] : '{{ asset('kia-assets/kia-expressions-happy.svg') }}'" 
                             alt="Kia Mascot" 
                             class="h-48 sm:h-56 md:h-72 object-contain drop-shadow-xl hover:scale-105 transition-transform select-none origin-bottom"
                             :class="isBouncing ? 'animate-kia-jump' : ''">
                        
                        <!-- Helper Tooltip -->
                        <div x-show="!isActive" 
                             x-transition
                             class="absolute top-0 -right-4 sm:-right-8 bg-white/90 text-primary text-[10px] sm:text-xs font-bold px-3 py-1.5 sm:px-4 sm:py-2 rounded-full shadow-md animate-pulse whitespace-nowrap pointer-events-none border border-primary/20">
                            Tap me!
                        </div>
                    </div>
                </div>

                <!-- Dialogue Box -->
                <div class="w-full transition-all duration-700 ease-in-out transform md:origin-left origin-top flex-shrink-0"
                     :class="isActive ? 'opacity-100 md:w-[60%] scale-100 max-h-[500px]' : 'opacity-0 md:w-0 scale-95 max-h-0 md:max-h-[500px] overflow-hidden'">
                    
                    <div class="relative z-10 w-full bg-white/95 backdrop-blur-xl border-2 sm:border-4 border-primary/20 rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-8 md:p-10 shadow-2xl cursor-pointer hover:border-primary/40 transition-colors text-left mt-6 md:mt-0" 
                         @click="interact()">
                         
                        <!-- Name Badge -->
                        <div class="absolute -top-5 sm:-top-6 left-6 sm:left-8 md:left-10 bg-primary text-white font-extrabold text-sm sm:text-lg px-6 sm:px-8 py-1.5 sm:py-2 rounded-full shadow-[0_4px_14px_0_rgba(207,65,115,0.39)] border-2 border-white tracking-wide">
                            Kia
                        </div>
                        
                        <!-- Text Area -->
                        <div class="min-h-[6rem] md:min-h-[8rem] flex items-start pr-4 sm:pr-6">
                            <p class="text-base sm:text-xl md:text-2xl text-text-main font-medium leading-relaxed" x-text="displayedText"></p>
                        </div>
                        
                        <!-- Next Indicator -->
                        <div class="absolute bottom-4 sm:bottom-6 right-6 sm:right-8 text-primary/60 transition-opacity duration-300"
                             :class="isTyping ? 'opacity-0' : 'animate-bounce opacity-100'">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 lg:gap-10">
            <!-- Developer 1 -->
            <div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-10 text-center border border-border-subtle/50 shadow-lg hover:border-primary/20 hover:shadow-2xl transition-all duration-500 group relative overflow-hidden flex flex-col items-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-surface-subtle rounded-2xl mx-auto mb-6 sm:mb-8 flex items-center justify-center group-hover:bg-primary group-hover:text-white text-primary transition-all duration-500 shadow-sm flex-shrink-0">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                    </svg>
                </div>
                <h4 class="text-xl sm:text-2xl font-extrabold text-text-main mb-1 tracking-tight">Van Justin S. Romulo</h4>
                <div class="inline-block px-3 sm:px-4 py-1.5 rounded-full bg-surface-subtle text-text-main text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-4 sm:mb-5 border border-border-subtle/50">Full-stack Website Developer</div>
                <p class="text-text-muted font-medium leading-relaxed text-xs sm:text-sm">Architecting the core systems and database relations to ensure seamless multi-portal integration.</p>
            </div>
            
            <!-- Developer 2 -->
            <div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-10 text-center border border-border-subtle/50 shadow-lg hover:border-primary/20 hover:shadow-2xl transition-all duration-500 group relative overflow-hidden flex flex-col items-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-surface-subtle rounded-2xl mx-auto mb-6 sm:mb-8 flex items-center justify-center group-hover:bg-primary group-hover:text-white text-primary transition-all duration-500 shadow-sm flex-shrink-0">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>
                <h4 class="text-xl sm:text-2xl font-extrabold text-text-main mb-1 tracking-tight">Mark M. Trilles</h4>
                <div class="inline-block px-3 sm:px-4 py-1.5 rounded-full bg-surface-subtle text-text-main text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-4 sm:mb-5 border border-border-subtle/50">Full-stack Web & Mobile</div>
                <p class="text-text-muted font-medium leading-relaxed text-xs sm:text-sm">Bridging web and mobile experiences by developing scalable APIs and responsive interfaces across all platforms.</p>
            </div>
            
            <!-- Developer 3 -->
            <div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-10 text-center border border-border-subtle/50 shadow-lg hover:border-primary/20 hover:shadow-2xl transition-all duration-500 group relative overflow-hidden flex flex-col items-center sm:col-span-2 md:col-span-1 sm:mx-auto md:mx-0 sm:w-1/2 md:w-full">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-surface-subtle rounded-2xl mx-auto mb-6 sm:mb-8 flex items-center justify-center group-hover:bg-primary group-hover:text-white text-primary transition-all duration-500 shadow-sm flex-shrink-0">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
                    </svg>
                </div>
                <h4 class="text-xl sm:text-2xl font-extrabold text-text-main mb-1 tracking-tight">Princess Genea R. Umali</h4>
                <div class="inline-block px-3 sm:px-4 py-1.5 rounded-full bg-surface-subtle text-text-main text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-4 sm:mb-5 border border-border-subtle/50">UI/UX Designer/QA Tester</div>
                <p class="text-text-muted font-medium leading-relaxed text-xs sm:text-sm">Crafting intuitive, user-centric designs and conducting rigorous quality assurance to guarantee a flawless shopping experience.</p>
            </div>
        </div>
    </section>

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

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kiaDialogue', () => ({
            isActive: false,
            step: 0,
            isTyping: false,
            isBouncing: false,
            displayedText: '',
            typingInterval: null,
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
                this.isActive = false;
            },
            interact() {
                this.triggerBounce();

                if (!this.isActive) {
                    // Start dialogue layout
                    this.isActive = true;
                    this.step = 0;
                    
                    // Delay typing slightly to let the transition finish expanding
                    setTimeout(() => {
                        this.typeText();
                    }, 400); 
                } else {
                    if (this.isTyping) {
                        // Fast forward typing
                        clearInterval(this.typingInterval);
                        this.displayedText = this.dialogues[this.step];
                        this.isTyping = false;
                    } else {
                        // Go to the next dialogue step
                        if (this.step < this.dialogues.length - 1) {
                            this.step++;
                            this.typeText();
                        } else {
                            // Close the dialogue box and center the mascot
                            this.isActive = false;
                            this.displayedText = '';
                        }
                    }
                }
            },
            triggerBounce() {
                this.isBouncing = false;
                // Timeout needed to force DOM reflow to replay animation quickly
                setTimeout(() => { this.isBouncing = true; }, 10);
                setTimeout(() => { this.isBouncing = false; }, 410);
            },
            typeText() {
                clearInterval(this.typingInterval);
                this.displayedText = '';
                this.isTyping = true;
                let i = 0;
                let text = this.dialogues[this.step];
                
                this.typingInterval = setInterval(() => {
                    if (i < text.length) {
                        this.displayedText += text.charAt(i);
                        i++;
                    } else {
                        this.isTyping = false;
                        clearInterval(this.typingInterval);
                    }
                }, 15); // Fast typewriter speed
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