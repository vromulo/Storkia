@props(['categories'])

<div 
    x-show="activeMenu"
    x-effect="if (activeMenu) $el.scrollTop = 0"
    class="hidden md:block absolute top-full left-0 right-0 w-full z-50 bg-transparent"
    x-cloak
    style="display: none;"
>
    <div class="mx-auto w-full max-w-7xl h-[450px] overflow-y-auto bg-surface relative border border-t-0 border-border-subtle rounded-b-2xl [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-surface-subtle [&::-webkit-scrollbar-thumb]:bg-border-subtle [&::-webkit-scrollbar-thumb]:rounded-full">
        <!-- Minimized Logo Background Overlay (opacity-5) -->
        <div class="absolute inset-0 pointer-events-none z-0 opacity-3 overflow-hidden" aria-hidden="true">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <pattern id="stork-pattern-dropdown" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                        <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#stork-pattern-dropdown)" />
            </svg>
        </div>

        <div class="p-8 lg:p-10 relative z-10">
            @foreach($categories as $categoryName => $subcategories)
                @if(count($subcategories) > 0)
                    <div 
                        x-show="activeMenu === '{{ addslashes($categoryName) }}'"
                        class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 md:gap-8"
                    >
                        
                        <!-- View All Link -->
                        <a href="{{ url('/category/' . Str::slug($categoryName)) }}" class="flex flex-col items-center group text-center">
                            <div class="w-16 h-16 md:w-24 md:h-24 rounded-full overflow-hidden mb-2 md:mb-4 border border-border-subtle group-hover:border-primary transition-colors duration-300 relative bg-[#1f2937] text-surface flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8 text-surface group-hover:scale-110 transition-all duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <rect x="4" y="4" width="6" height="6" rx="0.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <rect x="14" y="4" width="6" height="6" rx="0.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <rect x="4" y="14" width="6" height="6" rx="0.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="17" cy="17" r="3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="text-xs md:text-sm font-bold text-text-main group-hover:text-primary transition-colors leading-snug">
                                View All
                            </span>
                        </a>

                        <!-- Subcategories with Theme Icons -->
                        @foreach($subcategories as $sub)
                            <a href="{{ url('/category/' . Str::slug($categoryName) . '?subcategory=' . urlencode($sub['name'])) }}" class="flex flex-col items-center group text-center">
                                <div class="w-16 h-16 md:w-24 md:h-24 rounded-full overflow-hidden mb-2 md:mb-4 border-2 border-transparent group-hover:border-white/50 transition-colors duration-300 relative bg-[#623040] text-white flex items-center justify-center shrink-0 [&>svg]:w-6 [&>svg]:h-6 md:[&>svg]:w-8 md:[&>svg]:h-8 [&>svg]:!text-white [&>svg]:!stroke-white [&>svg_*]:!stroke-white [&>svg]:group-hover:scale-110 [&>svg]:transition-all [&>svg]:duration-300">
                                    @if(!empty($sub['icon']))
                                        {!! $sub['icon'] !!}
                                    @else
                                        <img 
                                            src="{{ $sub['image'] }}" 
                                            alt="{{ $sub['name'] }}" 
                                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                        >
                                    @endif
                                </div>
                                <span class="text-xs md:text-sm font-bold text-text-main group-hover:text-primary transition-colors leading-snug">
                                    {{ $sub['name'] }}
                                </span>
                            </a>
                        @endforeach
                        
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>