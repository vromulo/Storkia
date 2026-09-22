<div 
    x-data="{ showScrollTop: false }"
    @scroll.window="showScrollTop = window.scrollY > 400"
    class="fixed bottom-7 right-7 z-[60]"
>
    <button 
        type="button"
        x-show="showScrollTop" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="bg-primary text-surface p-3 rounded-full shadow-lg border border-primary-dark/20 hover:bg-primary-dark hover:scale-110 transition-all duration-300 focus:outline-none flex items-center justify-center cursor-pointer"
        aria-label="Scroll to top"
        x-cloak
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>
</div>