@extends('layouts.app', ['title' => $query ? 'Search: ' . $query : 'Search'])

@section('content')
    {{-- Same Alpine manager (resources/js/buyer/category-manager.js) that powers the
         category page: price range filter + batch-reveal infinite scroll. --}}
    <div
        x-data="categoryPageManager({
            products: @js($productMetadata),
            subcategories: [],
            initialSubcategory: 'All',
            minPrice: @js($minPrice),
            maxPrice: @js($maxPrice)
        })"
        x-init="initBatchScroll()"
        class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 font-sans"
    >

        <!-- Search Header & Filters -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-border-subtle pb-6">
            <div>
                <h1 class="text-2xl font-bold text-text-main">
                    Search Results for <span class="text-primary">"{{ $correctedQuery ?? $query }}"</span>
                </h1>
                <p class="text-sm text-text-muted mt-1">
                    <span x-text="totalFilteredCount">{{ $products->count() }}</span> items found
                </p>
            </div>

            <!-- Modern Dropdown Filters -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('search.search') }}" class="flex items-center gap-2" id="filterForm">
                    @if($query)
                        <input type="hidden" name="q" value="{{ $query }}">
                    @endif
                    @if(request()->boolean('exact'))
                        <input type="hidden" name="exact" value="1">
                    @endif

                    <!-- Category Dropdown (Allows clearing/changing category even if set via search suggestion) -->
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.away="open = false" class="flex items-center justify-between min-w-[160px] px-4 py-2 text-sm bg-surface border border-border-subtle rounded-lg text-text-main hover:border-primary transition-colors focus:outline-none">
                            <span class="truncate">{{ $category ?: 'All Categories' }}</span>
                            <svg class="w-4 h-4 ml-2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 z-10 w-full mt-1 bg-surface border border-border-subtle rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <!-- Option to clear the category and revert to All -->
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors">All Categories</a>

                            <!-- Available Categories list to change search scope dynamically -->
                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat, 'subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors {{ $category == $cat ? 'bg-surface-subtle text-primary font-medium' : '' }}">
                                    {{ $cat }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Subcategory Dropdown -->
                    @if(count($subcategories) > 0 && $category)
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.away="open = false" class="flex items-center justify-between min-w-[160px] px-4 py-2 text-sm bg-surface border border-border-subtle rounded-lg text-text-main hover:border-primary transition-colors focus:outline-none">
                            <span class="truncate">{{ $subcategory ?: 'All Subcategories' }}</span>
                            <svg class="w-4 h-4 ml-2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 z-10 w-full mt-1 bg-surface border border-border-subtle rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <a href="{{ request()->fullUrlWithQuery(['subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors">All Subcategories</a>
                            @foreach($subcategories as $sub)
                                <a href="{{ request()->fullUrlWithQuery(['subcategory' => $sub]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors {{ $subcategory == $sub ? 'bg-surface-subtle text-primary font-medium' : '' }}">
                                    {{ $sub }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </form>

                @if($products->isNotEmpty())
                    <!-- Price filter trigger (same control as the category page) -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            x-show="hasActiveFilters"
                            x-cloak
                            @click="resetFilters()"
                            class="text-xs font-semibold text-primary hover:text-text-main hover:underline transition-colors cursor-pointer select-none"
                        >
                            Clear filters
                        </button>

                        <button
                            type="button"
                            @click="isFilterModalOpen = true"
                            class="relative p-1.5 text-text-main hover:text-primary transition-colors cursor-pointer flex items-center justify-center focus:outline-none"
                            aria-label="Filter products"
                            title="Filter"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span
                                x-show="hasActiveFilters"
                                x-cloak
                                class="absolute top-1 right-1 w-2 h-2 rounded-full bg-primary"
                            ></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if($correctedQuery)
            <!-- Typo tolerance notice (BUY-SRCH-004) -->
            <div class="mb-6 px-4 py-3 rounded-xl bg-surface-subtle border border-border-subtle text-sm text-text-main">
                No exact matches for <span class="font-semibold">"{{ $query }}"</span>.
                Showing results for <span class="font-semibold text-primary">"{{ $correctedQuery }}"</span> instead.
                <a href="{{ request()->fullUrlWithQuery(['exact' => 1]) }}" class="ml-1 font-semibold text-primary hover:underline">
                    Search for "{{ $query }}" only
                </a>
            </div>
        @endif

        <!-- Product Grid -->
        @if($products->isNotEmpty())
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                @foreach($products as $product)
                    <div
                        x-cloak
                        x-show="isProductVisible({{ (int) $product->id }})"
                        class="transition-opacity duration-200"
                    >
                        <!-- Component Call mapping to seller-product-card.blade.php -->
                        <x-storefront.seller-product-card
                            :product="$product"
                            :is-wishlisted="in_array((int) $product->id, $wishlistedProductIds, true)"
                        />
                    </div>
                @endforeach
            </div>

            <!-- Empty State for price-filter misses -->
            <div
                x-show="totalFilteredCount === 0"
                x-cloak
                class="py-16 text-center text-text-muted"
            >
                <h3 class="text-base font-bold text-text-main">No matching products found</h3>
                <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">Try widening your price range.</p>
                <button
                    type="button"
                    @click="resetFilters()"
                    class="mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                >
                    Reset Filters
                </button>
            </div>

            <!-- Infinite-scroll sentinel (same pattern as category + home pages) -->
            <div x-ref="homeScrollSentinel"
                 x-show="hasMore"
                 x-cloak
                 class="py-8 flex flex-col items-center justify-center gap-2 text-xs font-bold text-text-muted">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
                </div>
                <span x-text="'Loaded ' + currentVisibleCount + ' of ' + totalFilteredCount + ' products — Scroll down for next batch'"></span>
            </div>

            <!-- Price range modal (subcategory chips hidden: this page already has its own category dropdowns) -->
            <x-storefront.category-filter-modal :subcategories="[]" :show-subcategories="false" />
        @else
            <div class="text-center py-16 bg-surface border border-border-subtle rounded-xl">
                <svg class="mx-auto h-12 w-12 text-text-muted mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-medium text-text-main">No products found</h3>
                <p class="text-sm text-text-muted mt-1">We couldn't find anything matching "{{ $query }}"{{ $category ? ' in ' . $category : '' }}. Try adjusting your search or filters.</p>
                <a href="{{ route('home') }}" class="mt-6 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-surface bg-primary hover:bg-primary-dark transition-colors">
                    Back to Home
                </a>
            </div>
        @endif
    </div>
@endsection