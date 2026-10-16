<div 
    x-show="hasMore" 
    x-cloak 
    class="w-full flex flex-col items-center justify-center pt-10 pb-6 space-y-3"
>
    <!-- Progress Label -->
    <span class="text-xs text-text-muted font-medium">
        Showing <span class="font-bold text-text-main" x-text="currentVisibleCount"></span> of <span class="font-bold text-text-main" x-text="totalFilteredCount"></span> products
    </span>

    <!-- Show More Action Button -->
    <button 
        type="button" 
        @click="showMore()"
        class="inline-flex items-center gap-2 px-8 py-3 bg-surface hover:bg-brand-light/30 border border-border-subtle hover:border-primary/50 text-text-main hover:text-primary font-bold text-sm rounded-full shadow-xs hover:shadow-sm transition-all duration-200 cursor-pointer active:scale-95"
    >
        <span>Show More</span>
        <svg class="w-4 h-4 text-text-muted group-hover:text-primary transition-transform duration-200 group-hover:translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
</div>