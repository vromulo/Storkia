@props(['subcategories' => []])

<div 
    x-show="isFilterModalOpen" 
    x-cloak 
    @keydown.escape.window="isFilterModalOpen = false"
    class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
>
    <!-- Backdrop Blur Overlay -->
    <div 
        x-show="isFilterModalOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="isFilterModalOpen = false"
        class="fixed inset-0 bg-black/60"
    ></div>

    <!-- Modal Dialog Window -->
    <div 
        x-show="isFilterModalOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-95 translate-y-3"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-3"
        @click.away="isFilterModalOpen = false"
        class="relative w-full max-w-2xl bg-surface rounded-3xl shadow-2xl border border-border-subtle overflow-hidden flex flex-col max-h-[90vh] z-10 font-sans"
    >
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-border-subtle flex items-center justify-between bg-surface sticky top-0 z-20">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-surface-subtle text-text-main flex items-center justify-center border border-border-subtle">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-text-main font-sans tracking-tight">
                        Filter Products
                    </h2>
                </div>
            </div>

            <button 
                type="button" 
                @click="isFilterModalOpen = false" 
                class="p-2 text-text-muted hover:text-text-main hover:bg-surface-subtle rounded-full transition-colors cursor-pointer"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="p-6 overflow-y-auto space-y-8 custom-scrollbar">

            <!-- Section 1: Subcategories Filter Chips -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-text-muted uppercase tracking-wider">
                        Subcategory
                    </label>
                    <span class="text-[11px] text-text-muted" x-text="selectedSubcategories.includes('All') ? 'All subcategories' : selectedSubcategories.length + ' selected'"></span>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <!-- 'All' Chip -->
                    <button 
                        type="button"
                        @click="toggleSubcategory('All')"
                        :class="isSubcategoryActive('All') 
                            ? 'bg-primary text-white border-primary shadow-xs ring-2 ring-primary/20 font-bold' 
                            : 'bg-surface-subtle text-text-main border-border-subtle hover:border-primary/50 hover:bg-brand-light/20 font-medium'"
                        class="px-4 py-2 rounded-full border text-xs sm:text-sm transition-all duration-200 cursor-pointer flex items-center gap-1.5 select-none"
                    >
                        <span>All</span>
                        <svg x-show="isSubcategoryActive('All')" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>

                    <!-- Individual Subcategory Chips -->
                    @foreach($subcategories as $sub)
                        @php $name = $sub['name']; @endphp
                        <button 
                            type="button"
                            @click="toggleSubcategory('{{ addslashes($name) }}')"
                            :class="isSubcategoryActive('{{ addslashes($name) }}') 
                                ? 'bg-primary text-white border-primary shadow-xs ring-2 ring-primary/20 font-bold' 
                                : 'bg-surface-subtle text-text-main border-border-subtle hover:border-primary/50 hover:bg-brand-light/20 font-medium'"
                            class="px-4 py-2 rounded-full border text-xs sm:text-sm transition-all duration-200 cursor-pointer flex items-center gap-1.5 select-none"
                        >
                            <span>{{ $name }}</span>
                            <svg x-show="isSubcategoryActive('{{ addslashes($name) }}')" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Section 2: Synchronized Price Range (Slider + Numerical Inputs) -->
            <div class="pt-6 space-y-5">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-text-muted uppercase tracking-wider">
                        Price Range (₱)
                    </label>
                    <span class="text-xs font-bold text-text-main">
                        ₱<span x-text="Number(minPrice).toLocaleString()"></span> — ₱<span x-text="Number(maxPrice).toLocaleString()"></span>
                    </span>
                </div>

                <!-- Dual Range Slider Graphic -->
                <div class="relative w-full py-4 select-none">
                    <!-- Base Track -->
                    <div class="relative w-full h-2 rounded-full bg-surface-subtle border border-border-subtle overflow-hidden">
                        <!-- Active Range Highlight -->
                        <div 
                            class="absolute top-0 bottom-0 bg-primary rounded-full transition-all duration-75"
                            :style="`left: ${minPercent}%; width: ${Math.max(0, maxPercent - minPercent)}%;`"
                        ></div>
                    </div>

                    <!-- Left Range Slider -->
                    <input 
                        type="range" 
                        :min="minAllowed" 
                        :max="maxAllowed" 
                        step="10"
                        x-model.number="minPrice"
                        @input="syncMinSlider()"
                        class="absolute inset-0 w-full h-2 appearance-none bg-transparent pointer-events-none z-20 cursor-pointer [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:shadow-md [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white"
                    >

                    <!-- Right Range Slider -->
                    <input 
                        type="range" 
                        :min="minAllowed" 
                        :max="maxAllowed" 
                        step="10"
                        x-model.number="maxPrice"
                        @input="syncMaxSlider()"
                        class="absolute inset-0 w-full h-2 appearance-none bg-transparent pointer-events-none z-20 cursor-pointer [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:shadow-md [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white"
                    >
                </div>

                <!-- Numerical Input Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="block text-[11px] font-semibold text-text-muted mb-1">Min Price</span>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-text-muted text-xs font-bold pointer-events-none">₱</span>
                            <input 
                                type="number" 
                                x-model.number="minPrice"
                                @change="syncMinInput()"
                                :min="minAllowed" 
                                :max="maxPrice"
                                class="w-full pl-7 pr-3 py-2 text-sm bg-surface-subtle border border-border-subtle rounded-xl text-text-main font-semibold outline-none focus:border-primary focus:bg-surface transition-all no-spinners"
                            >
                        </div>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-text-muted mb-1">Max Price</span>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-text-muted text-xs font-bold pointer-events-none">₱</span>
                            <input 
                                type="number" 
                                x-model.number="maxPrice"
                                @change="syncMaxInput()"
                                :min="minPrice" 
                                :max="maxAllowed"
                                class="w-full pl-7 pr-3 py-2 text-sm bg-surface-subtle border border-border-subtle rounded-xl text-text-main font-semibold outline-none focus:border-primary focus:bg-surface transition-all no-spinners"
                            >
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="px-6 py-4 border-t border-border-subtle bg-surface-subtle/60 flex items-center justify-between sticky bottom-0 z-20">
            <button 
                type="button" 
                @click="resetFilters()"
                class="text-xs font-bold text-text-muted hover:text-danger transition-colors cursor-pointer"
            >
                Reset All Filters
            </button>

            <button 
                type="button" 
                @click="isFilterModalOpen = false"
                class="px-6 py-2.5 bg-text-main hover:bg-dark-grey text-surface text-xs sm:text-sm font-semibold rounded-xl transition-colors cursor-pointer active:scale-95 select-none"            
            >
                View Results
            </button>
        </div>
    </div>
</div>