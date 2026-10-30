@php
    $customBanners = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('hero_banners')) {
        $customBanners = \Illuminate\Support\Facades\DB::table('hero_banners')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }
    $totalSlidesCount = $customBanners->isNotEmpty() ? $customBanners->count() : 4;
@endphp

<div 
    x-data="{
        activeSlide: 1,
        totalSlides: {{ $totalSlidesCount }},
        timer: null,
        isPaused: false,
        imageLoaded: false,
        init() {
            this.startTimer();
            const img = new Image();
            img.src = '{{ asset('assets/yeezy-preview.webp') }}';
            img.onload = () => { this.imageLoaded = true; };
        },
        startTimer() {
            this.timer = setInterval(() => {
                if (!this.isPaused) {
                    this.next();
                }
            }, 5500);
        },
        stopTimer() {
            if (this.timer) clearInterval(this.timer);
        },
        resetTimer() {
            this.stopTimer();
            this.startTimer();
        },
        togglePause() {
            this.isPaused = !this.isPaused;
            if (!this.isPaused) {
                this.resetTimer();
            }
        },
        goTo(slide) {
            this.activeSlide = slide;
            this.resetTimer();
        },
        next() {
            this.activeSlide = this.activeSlide === this.totalSlides ? 1 : this.activeSlide + 1;
        },
        prev() {
            this.activeSlide = this.activeSlide === 1 ? this.totalSlides : this.activeSlide - 1;
        }
    }"
    class="relative -mx-4 sm:-mx-6 lg:mx-0 w-auto lg:w-full rounded-none lg:rounded-2xl overflow-hidden mb-8 shadow-sm border-y lg:border border-border-subtle h-[340px] sm:h-[400px] bg-primary-dark select-none"
    @mouseenter="if (!isPaused) stopTimer()"
    @mouseleave="if (!isPaused) startTimer()"
>
    <div class="relative w-full h-full">

        @if($customBanners->isNotEmpty())
            @foreach($customBanners as $idx => $banner)
                @php
                    $slideNum = $idx + 1;
                    $bgUrl = $banner->image_path ? asset('storage/' . $banner->image_path) : asset('assets/yeezy-preview.webp');
                @endphp
                <div 
                    x-show="activeSlide === {{ $slideNum }}"
                    x-transition:enter="transition ease-out duration-700"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-500"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 w-full h-full bg-cover bg-center flex items-center"
                    style="{{ $slideNum > 1 ? 'display: none;' : '' }} background-image: url('{{ $bgUrl }}');"
                >
                    <div class="absolute inset-0 bg-black/55"></div>
                    <div class="relative z-10 p-8 sm:p-12 lg:p-16 w-full md:w-2/3">
                        @if($banner->badge_text)
                            <span class="inline-block px-3 py-1 bg-primary text-surface text-xs font-bold uppercase rounded-full mb-3 tracking-wider">
                                {{ $banner->badge_text }}
                            </span>
                        @endif
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif text-white font-bold leading-tight mb-3 drop-shadow-md">
                            {{ $banner->title }}
                        </h2>
                        @if($banner->subtitle)
                            <p class="text-gray-100 mb-6 text-sm sm:text-base drop-shadow-md max-w-lg">
                                {{ $banner->subtitle }}
                            </p>
                        @endif
                        <a href="{{ $banner->cta_url ?: '#products-grid' }}" class="inline-flex px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                            {{ $banner->cta_text ?: 'Shop Now' }}
                        </a>
                    </div>
                </div>
            @endforeach
        @else
            <!-- Default Slide 1 -->
            <div 
                x-show="activeSlide === 1"
                x-transition:enter="transition ease-out duration-700"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-500"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 w-full h-full"
            >
                <div 
                    :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                    class="absolute inset-0 w-full h-full bg-cover bg-center flex items-center transition-opacity duration-700"
                    style="background-image: url('{{ asset('assets/yeezy-preview.webp') }}');"
                >
                    <div class="absolute inset-0 bg-black/60"></div>
                    <div class="relative z-10 p-8 sm:p-12 lg:p-16 w-full md:w-2/3">
                        <span class="inline-block px-3 py-1 bg-primary text-surface text-xs font-bold uppercase rounded-full mb-3 tracking-wider">
                            Limited Offer
                        </span>
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif text-white font-bold leading-tight mb-3 drop-shadow-md">
                            Grab Up to 50% Off On <br class="hidden md:block">Selected Items
                        </h2>
                        <p class="text-gray-100 mb-6 text-sm sm:text-base drop-shadow-md max-w-lg">
                            Fast delivery. Exclusive Super Deals. Verified brands right to your doorstep.
                        </p>
                        <a href="#products-grid" class="inline-flex px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                            Shop Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- Default Slide 2 -->
            <div 
                x-show="activeSlide === 2"
                class="absolute inset-0 w-full h-full bg-gradient-to-r from-[#441f2e] via-[#5D3140] to-[#29131c] flex items-center"
                style="display: none;"
            >
                <div class="absolute inset-0 bg-black/30"></div>
                <div class="relative z-10 p-8 sm:p-12 lg:p-16 w-full md:w-2/3">
                    <span class="inline-block px-3 py-1 bg-brand-light text-primary-dark text-xs font-bold uppercase rounded-full mb-3 tracking-wider">
                        Member Access
                    </span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif text-white font-bold leading-tight mb-3 drop-shadow-md">
                        Welcome to Storkia
                    </h2>
                    <p class="text-gray-100 mb-6 text-sm sm:text-base drop-shadow-md max-w-lg">
                        Sign in to check out faster, track your active shipments, or access the seller management dashboard.
                    </p>
                    <div class="flex flex-wrap items-center gap-3.5">
                        <a href="{{ route('login') }}" class="inline-flex items-center px-7 py-3 bg-primary hover:bg-primary-dark text-white font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                            Sign In
                        </a>
                        <a href="{{ route('seller.login') }}" class="inline-flex items-center px-7 py-3 bg-surface text-primary-dark hover:bg-brand-light font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                            Seller Sign In
                        </a>
                    </div>
                </div>
            </div>

            <!-- Default Slide 3 -->
            <div 
                x-show="activeSlide === 3"
                class="absolute inset-0 w-full h-full bg-gradient-to-r from-[#1c1a24] via-[#2f2738] to-[#5D3140] flex items-center"
                style="display: none;"
            >
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative z-10 p-8 sm:p-12 lg:p-16 w-full md:w-2/3">
                    <span class="inline-block px-3 py-1 bg-brand-light text-primary-dark text-xs font-bold uppercase rounded-full mb-3 tracking-wider">
                        Express Fulfillment
                    </span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif text-white font-bold leading-tight mb-3 drop-shadow-md">
                        Real-Time Routing &amp; <br class="hidden md:block">Safe Delivery
                    </h2>
                    <p class="text-gray-100 mb-6 text-sm sm:text-base drop-shadow-md max-w-lg">
                        Experience guaranteed handling from our certified couriers with doorstep status updates.
                    </p>
                    <a href="#products-grid" class="inline-flex px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                        Explore Storefront
                    </a>
                </div>
            </div>

            <!-- Default Slide 4 -->
            <div 
                x-show="activeSlide === 4"
                class="absolute inset-0 w-full h-full bg-gradient-to-r from-[#2a1720] via-[#5D3140] to-[#80354f] flex items-center"
                style="display: none;"
            >
                <div class="absolute inset-0 bg-black/35"></div>
                <div class="relative z-10 p-8 sm:p-12 lg:p-16 w-full md:w-2/3">
                    <span class="inline-block px-3 py-1 bg-primary text-surface text-xs font-bold uppercase rounded-full mb-3 tracking-wider">
                        Trending Catalog
                    </span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif text-white font-bold leading-tight mb-3 drop-shadow-md">
                        Discover What’s New
                    </h2>
                    <p class="text-gray-100 mb-6 text-sm sm:text-base drop-shadow-md max-w-lg">
                        From lifestyle essentials to electronics, find verified merchandise added every day.
                    </p>
                    <a href="#products-grid" class="inline-flex px-8 py-3 bg-surface text-primary-dark hover:bg-brand-light font-bold text-sm rounded-full transition-colors duration-300 shadow-md">
                        Browse Catalog
                    </a>
                </div>
            </div>
        @endif

    </div>

    <div class="absolute bottom-4 right-4 sm:bottom-6 sm:right-6 z-30 flex items-center gap-2">
        <button type="button" @click="prev(); resetTimer();" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-sm border border-white/25 text-white flex items-center justify-center cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <button type="button" @click="next(); resetTimer();" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-sm border border-white/25 text-white flex items-center justify-center cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
        </button>
        <button type="button" @click="togglePause()" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-sm border border-white/25 text-white flex items-center justify-center cursor-pointer">
            <svg x-show="!isPaused" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            <svg x-show="isPaused" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
    </div>

    <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 flex items-center space-x-2">
        <template x-for="slide in totalSlides" :key="slide">
            <button type="button" @click="goTo(slide)" :class="activeSlide === slide ? 'w-8 bg-brand-light' : 'w-2.5 bg-white/50 hover:bg-white/80'" class="h-2 rounded-full transition-all duration-300 cursor-pointer"></button>
        </template>
    </div>
</div>