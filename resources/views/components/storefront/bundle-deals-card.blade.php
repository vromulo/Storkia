{{-- Bundle Deals callout card shown on the buyer home page, between Super Deals/Trends and You May Also Like. --}}
<div class="px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8 rounded-2xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4 md:gap-6 shadow-xs">
    <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-4 min-w-0">
        <span class="self-start sm:self-auto shrink-0 px-2.5 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-wider whitespace-nowrap">Bundle &amp; Save</span>
        <p class="text-xs sm:text-sm lg:text-base font-bold leading-relaxed">
            Explore Multi-Item Store Bundle Deals — Pick your variants &amp; sizes to unlock Buy N Get % Off or Free Gift (₱0.00) rewards!
        </p>
    </div>
    <a href="{{ route('bundles.index') }}" class="w-full md:w-auto shrink-0 inline-flex items-center justify-center px-5 py-2.5 sm:py-3 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs sm:text-sm font-extrabold shadow-2xs transition-colors whitespace-nowrap">
        Browse Bundle Deals &rarr;
    </a>
</div>