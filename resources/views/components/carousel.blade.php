<div x-data="{ 
        activeSlide: parseInt(sessionStorage.getItem('storkiaCarouselSlide')) || 1, 
        timer: null,
        startTimer() {
            this.timer = setInterval(() => { 
                this.activeSlide = this.activeSlide === 3 ? 1 : this.activeSlide + 1; 
            }, 5000);
        },
        resetTimer(slide) {
            clearInterval(this.timer);
            this.activeSlide = slide;
            this.startTimer();
        }
     }"
     x-init="
        $watch('activeSlide', value => sessionStorage.setItem('storkiaCarouselSlide', value));
        startTimer();
     "
     class="relative w-full h-full bg-primary-dark overflow-hidden shrink-0 group">
    
    <!-- Home Icon Button -->
    <a href="/" wire:navigate class="absolute top-8 left-8 z-50 transition-transform duration-300 hover:scale-105 cursor-pointer">
        <img src="/assets/storkia-minimized.png" alt="Home" class="w-12 h-12 object-contain drop-shadow-[0_4px_10px_rgba(0,0,0,0.6)]">
    </a>

    <!-- Slides Container -->
    <div class="relative w-full h-full">
        
        <!-- Slide 1: Lightning Fast Delivery -->
        <div x-show="activeSlide === 1" 
             x-transition:enter="transition ease-in-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in-out duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 w-full h-full">
            
            <img src="/assets/carousel-slide-3.png" alt="Lightning Fast Delivery" class="absolute inset-0 w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
            
            <div class="absolute bottom-24 left-10 md:left-16 max-w-lg text-left z-10">
                <div class="w-16 h-16 mb-4 text-brand-light flex items-center justify-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h2 class="text-4xl font-serif mb-3 text-white drop-shadow-md">Lightning Fast Delivery</h2>
                <p class="text-white/90 font-sans text-lg leading-relaxed drop-shadow-md">Experience the fastest routing in the city. Your package arrives before you know it.</p>
            </div>
        </div>

        <!-- Slide 2: Secure Handling -->
        <div x-show="activeSlide === 2" 
             x-transition:enter="transition ease-in-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in-out duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 w-full h-full" style="display: none;">
            
            <img src="/assets/carousel-slide-1.png" alt="Secure Handling" class="absolute inset-0 w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
            
            <div class="absolute bottom-24 left-10 md:left-16 max-w-lg text-left z-10">
                <div class="w-16 h-16 mb-4 text-brand-light flex items-center justify-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h2 class="text-4xl font-serif mb-3 text-white drop-shadow-md">Secure Handling</h2>
                <p class="text-white/90 font-sans text-lg leading-relaxed drop-shadow-md">Every item is tracked and handled with the utmost care by our verified couriers.</p>
            </div>
        </div>

        <!-- Slide 3: Real-time Tracking -->
        <div x-show="activeSlide === 3" 
             x-transition:enter="transition ease-in-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in-out duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 w-full h-full" style="display: none;">
            
            <img src="/assets/carousel-slide-2.png" alt="Real-time Tracking" class="absolute inset-0 w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
            
            <div class="absolute bottom-24 left-10 md:left-16 max-w-lg text-left z-10">
                <div class="w-16 h-16 mb-4 text-brand-light flex items-center justify-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h2 class="text-4xl font-serif mb-3 text-white drop-shadow-md">Real-time Tracking</h2>
                <p class="text-white/90 font-sans text-lg leading-relaxed drop-shadow-md">Watch your delivery move across the map in real-time, right to your doorstep.</p>
            </div>
        </div>
    </div>

    <!-- Navigation Indicators -->
    <div class="absolute bottom-10 left-10 md:left-16 flex space-x-4 z-20">
        <button @click="resetTimer(1)" :class="{'bg-brand-light w-10': activeSlide === 1, 'bg-white opacity-50 w-3 hover:opacity-100': activeSlide !== 1}" class="h-3 rounded-full transition-all duration-300 cursor-pointer shadow-md"></button>
        <button @click="resetTimer(2)" :class="{'bg-brand-light w-10': activeSlide === 2, 'bg-white opacity-50 w-3 hover:opacity-100': activeSlide !== 2}" class="h-3 rounded-full transition-all duration-300 cursor-pointer shadow-md"></button>
        <button @click="resetTimer(3)" :class="{'bg-brand-light w-10': activeSlide === 3, 'bg-white opacity-50 w-3 hover:opacity-100': activeSlide !== 3}" class="h-3 rounded-full transition-all duration-300 cursor-pointer shadow-md"></button>
    </div>
</div>