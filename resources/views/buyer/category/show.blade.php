@extends('layouts.app', ['title' => $categoryName])

@section('content')

    <!-- Main Category Experience Wrapper (Option A Alpine.js Filter Engine) -->
    <div 
        x-data="categoryPageManager({
            products: @js($productMetadata),
            subcategories: @js($subcategories),
            initialSubcategory: @js($selectedSubcategory),
            minPrice: @js($minPrice),
            maxPrice: @js($maxPrice)
        })"
        class="bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen"
    >
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <!-- Category Header & Filter Trigger Bar -->

            <!-- Breadcrumb Component -->
            <x-ui.breadcrumbs :items="[$categoryName]" class="mb-2" />

            <!-- Category Header: Title on Far Left, Filter Icon on Far Right -->
            <div class="flex items-center justify-between gap-4 py-4">
                <!-- Title -->
                <div class="flex items-baseline gap-2.5 sm:gap-3">
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-text-main tracking-tight leading-none">
                        {{ $categoryName }}
                    </h1>
                </div>

                <!-- Right Side Controls (Level with Title) -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Reset Badge when filters are active -->
                    <button 
                        type="button" 
                        x-show="hasActiveFilters" 
                        x-cloak 
                        @click="resetFilters()"
                        class="text-xs font-semibold text-primary hover:text-text-main hover:underline transition-colors cursor-pointer select-none"
                    >
                        Clear filters
                    </button>

                    <!-- Filter Modal Trigger Button (Pinned on far right) -->
                    <button 
                        type="button" 
                        @click="isFilterModalOpen = true"
                        class="relative p-1.5 text-text-main hover:text-primary transition-colors cursor-pointer flex items-center justify-center focus:outline-none"
                        aria-label="Filter products"
                        title="Filter"
                    >
                        <!-- Filter Icon -->
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>

                        <!-- Active Dot Indicator -->
                        <span 
                            x-show="hasActiveFilters" 
                            x-cloak 
                            class="absolute top-1 right-1 w-2 h-2 rounded-full bg-primary"
                        ></span>
                    </button>
                </div>
            </div>

            <!-- Active Subcategories Indicators (Quick removal chips) -->
            <div x-show="!selectedSubcategories.includes('All')" x-cloak class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-xs text-text-muted font-medium mr-1">Active Filters:</span>
                <template x-for="sub in selectedSubcategories" :key="sub">
                    <button 
                        type="button" 
                        @click="toggleSubcategory(sub)" 
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-surface text-text-main border border-text-main/30 hover:border-text-main hover:bg-surface-subtle transition-colors cursor-pointer select-none group"
                        title="Remove filter"
                    >
                        <span x-text="sub"></span>
                        <span class="text-text-muted group-hover:text-text-main transition-colors text-sm leading-none pointer-events-none">&times;</span>
                    </button>
                </template>
            </div>

            <!-- Products Grid Section (Reactivity handled via Option A) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-5 lg:gap-6">
                @forelse($products as $product)
                    <div 
                        x-show="isProductVisible({{ $product->id }})" 
                        x-cloak
                        class="transition-opacity duration-200"
                    >
                        <x-storefront.seller-product-card :product="$product" :from-category="$categoryName" />
                    </div>
                @empty
                    <div class="col-span-full py-16 text-center text-text-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-3 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="text-base font-semibold text-text-main">No products available in this category.</p>
                        <p class="text-xs mt-1">Please check back soon for incoming stock.</p>
                    </div>
                @endforelse
            </div>

            <!-- Empty State for Filter Misses -->
            <div 
                x-show="totalFilteredCount === 0 && products.length > 0" 
                x-cloak 
                class="py-16 text-center text-text-muted"
            >
                <div class="w-16 h-16 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-4 border border-border-subtle">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-text-main">No matching products found</h3>
                <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">Try widening your price range or selecting different subcategories.</p>
                <button 
                    type="button" 
                    @click="resetFilters()"
                    class="mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                >
                    Reset Filters
                </button>
            </div>

            <!-- Show More Button Component -->
            <x-storefront.show-more-button />

        </div>

        <!-- Filter Modal Component -->
        <x-storefront.category-filter-modal :subcategories="$subcategories" />

        <!-- Existing Global Product Quick-View Modal -->
        <x-storefront.product-modal />

    </div>

@endsection