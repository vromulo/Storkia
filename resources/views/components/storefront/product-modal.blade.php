<div x-show="modalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Background Overlay -->
        <div x-show="modalOpen" @click="modalOpen = false" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" aria-hidden="true"></div>

        <!-- Modal Panel -->
        <div x-show="modalOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="relative inline-block w-full max-w-xl p-8 overflow-hidden text-left align-middle transition-all transform bg-surface shadow-2xl rounded-2xl">
            
            <button @click="modalOpen = false" class="absolute top-4 right-4 text-text-muted hover:text-primary transition-colors focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="mt-4" x-if="activeProduct">
                <h3 class="text-2xl font-bold font-serif text-primary-dark mb-2" x-text="activeProduct.name"></h3>
                <div class="inline-block px-3 py-1 mb-4 text-xs font-medium bg-surface-subtle border border-border-subtle rounded-full text-text-muted" x-text="activeProduct.category"></div>
                
                <p class="text-text-main mb-6 leading-relaxed" x-text="activeProduct.desc"></p>
                
                <div class="flex items-center justify-between mt-8 pt-6 border-t border-border-subtle">
                    <span class="text-3xl font-bold text-text-main">$<span x-text="activeProduct.price"></span></span>
                    <button class="px-6 py-3 text-sm font-bold text-surface bg-primary rounded-full hover:bg-primary-dark transition-colors shadow-md">
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>