<div x-data="{ mobileMenuOpen: false }" class="bg-surface/80 backdrop-blur-2xl border-b border-border-subtle/40 w-full transition-all duration-300 shadow-lg relative z-50">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12">
        <div class="flex justify-between items-center h-20">
            <div class="flex-shrink-0 flex items-center">
                <a href="{{ route('landing') }}" class="flex items-center text-primary hover:opacity-80 transition-opacity">
                    <img src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="Storkia" class="block md:hidden h-8 w-auto object-contain">
                    <img src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="hidden md:block h-9 w-auto object-contain">
                </a>
            </div>
            
            <div class="hidden md:flex items-center bg-surface-subtle/80 px-8 py-2.5 rounded-full border border-border-subtle/50 shadow-md backdrop-blur-md">
                <a href="#shops" class="text-[14px] font-bold text-text-muted hover:text-primary mx-5 transition-colors">Featured Shops</a>
                <a href="#products" class="text-[14px] font-bold text-text-muted hover:text-primary mx-5 transition-colors">Featured Products</a>
                <a href="#logistics" class="text-[14px] font-bold text-text-muted hover:text-primary mx-5 transition-colors">Logistics</a>
                <a href="#about" class="text-[14px] font-bold text-text-muted hover:text-primary mx-5 transition-colors">About Us</a>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('home') }}" class="hidden md:inline-flex text-[14px] font-bold bg-primary text-white px-6 py-2.5 rounded-full hover:bg-primary-dark transition-all duration-300 shadow-[0_4px_14px_0_rgba(207,65,115,0.39)] hover:shadow-[0_6px_20px_rgba(207,65,115,0.23)] hover:-translate-y-0.5">
                    Get Started
                </a>
                
                <!-- Enhanced Mobile Menu Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        type="button" 
                        class="md:hidden inline-flex items-center justify-center gap-2 px-4 py-2 rounded-full border border-border-subtle/60 text-text-main font-bold bg-surface hover:bg-surface-subtle hover:text-primary hover:border-primary/30 transition-all duration-300 shadow-sm z-50 relative" 
                        aria-controls="mobile-menu" 
                        aria-expanded="false">
                    <span class="text-sm uppercase tracking-wider">Menu</span>
                    <svg class="h-5 w-5 transition-transform duration-300" 
                         :class="mobileMenuOpen ? 'rotate-90 scale-0 opacity-0 absolute right-4' : 'rotate-0 scale-100 opacity-100 relative'" 
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg class="h-5 w-5 transition-transform duration-300" 
                         :class="mobileMenuOpen ? 'rotate-0 scale-100 opacity-100 relative' : '-rotate-90 scale-0 opacity-0 absolute right-4'" 
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Premium Mobile Menu Overlay -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300" 
         x-transition:enter-start="opacity-0 translate-y-[-10px]" 
         x-transition:enter-end="opacity-100 translate-y-0" 
         x-transition:leave="transition ease-in duration-200" 
         x-transition:leave-start="opacity-100 translate-y-0" 
         x-transition:leave-end="opacity-0 translate-y-[-10px]" 
         @click.away="mobileMenuOpen = false"
         class="md:hidden absolute top-[calc(100%+0.5rem)] left-0 w-full px-4 z-40" 
         id="mobile-menu"
         x-cloak>
        <div class="bg-surface/95 backdrop-blur-xl rounded-[2rem] border border-border-subtle/50 shadow-2xl overflow-hidden flex flex-col p-2">
            <div class="p-2 space-y-1">
                <a href="#shops" @click="mobileMenuOpen = false" class="group flex items-center justify-between p-4 rounded-2xl hover:bg-primary/10 transition-colors">
                    <span class="text-lg font-bold text-text-main group-hover:text-primary transition-colors">Featured Shops</span>
                    <svg class="w-5 h-5 text-text-muted group-hover:text-primary transition-colors transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <a href="#products" @click="mobileMenuOpen = false" class="group flex items-center justify-between p-4 rounded-2xl hover:bg-primary/10 transition-colors">
                    <span class="text-lg font-bold text-text-main group-hover:text-primary transition-colors">Featured Products</span>
                    <svg class="w-5 h-5 text-text-muted group-hover:text-primary transition-colors transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <a href="#logistics" @click="mobileMenuOpen = false" class="group flex items-center justify-between p-4 rounded-2xl hover:bg-primary/10 transition-colors">
                    <span class="text-lg font-bold text-text-main group-hover:text-primary transition-colors">Logistics</span>
                    <svg class="w-5 h-5 text-text-muted group-hover:text-primary transition-colors transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <a href="#about" @click="mobileMenuOpen = false" class="group flex items-center justify-between p-4 rounded-2xl hover:bg-primary/10 transition-colors">
                    <span class="text-lg font-bold text-text-main group-hover:text-primary transition-colors">About Us</span>
                    <svg class="w-5 h-5 text-text-muted group-hover:text-primary transition-colors transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            <div class="p-4 bg-surface-subtle/50 mt-2 rounded-b-[1.5rem]">
                <a href="{{ route('home') }}" class="block w-full text-center text-lg font-bold bg-primary text-white px-6 py-4 rounded-xl hover:bg-primary-dark transition-colors shadow-md">
                    Get Started Now
                </a>
            </div>
        </div>
    </div>
</div>