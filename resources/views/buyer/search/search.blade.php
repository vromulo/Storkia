@extends('layouts.app', ['title' => $query ? 'Search: ' . $query : 'Search'])

@section('content')
    <div
        x-data="categoryPageManager({
            products: @js($productMetadata),
            subcategories: [],
            initialSubcategory: 'All',
            minPrice: @js($minPrice),
            maxPrice: @js($maxPrice)
        })"
        x-init="initBatchScroll()"
        class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 font-sans select-none"
    >
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-border-subtle pb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-primary-dark">
                    Search Results for <span class="text-primary">"{{ $correctedQuery ?? $query }}"</span>
                </h1>
                <p class="text-xs sm:text-sm text-text-muted mt-1 font-medium">
                    <strong class="text-primary-dark" x-text="totalFilteredCount">{{ $products->count() }}</strong> items found
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('search.search') }}" class="flex flex-wrap items-center gap-2" id="filterForm">
                    @if($query)
                        <input type="hidden" name="q" value="{{ $query }}">
                    @endif
                    @if(request()->boolean('exact'))
                        <input type="hidden" name="exact" value="1">
                    @endif

                    <div x-data="{ open: false }" class="relative">
                        <button type="button"
                                @click="open = !open"
                                @click.away="open = false"
                                class="flex items-center justify-between min-w-[175px] px-4 py-2.5 text-xs sm:text-sm font-bold bg-surface border border-border-subtle rounded-2xl text-text-main hover:border-primary transition-colors focus:outline-none cursor-pointer shadow-2xs">
                            <span class="truncate">{{ $category ?: 'All Categories' }}</span>
                            <svg class="w-4 h-4 ml-2 text-primary transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 w-56 mt-1.5 bg-surface border border-border-subtle rounded-2xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar py-1.5 divide-y divide-border-subtle/40">
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'subcategory' => null]) }}"
                               class="block px-4 py-2.5 text-xs sm:text-sm text-text-main hover:bg-brand-light/30 hover:text-primary font-semibold transition-colors cursor-pointer">
                                All Categories
                            </a>

                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat, 'subcategory' => null]) }}"
                                   class="flex items-center justify-between px-4 py-2.5 text-xs sm:text-sm text-text-main hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer {{ $category == $cat ? 'bg-primary/10 text-primary font-bold' : 'font-medium' }}">
                                    <span class="truncate">{{ $cat }}</span>
                                    @if($category == $cat)
                                        <span class="text-primary font-extrabold">&#10003;</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @if(count($subcategories) > 0 && $category)
                        <div x-data="{ open: false }" class="relative">
                            <button type="button"
                                    @click="open = !open"
                                    @click.away="open = false"
                                    class="flex items-center justify-between min-w-[175px] px-4 py-2.5 text-xs sm:text-sm font-bold bg-surface border border-border-subtle rounded-2xl text-text-main hover:border-primary transition-colors focus:outline-none cursor-pointer shadow-2xs">
                                <span class="truncate">{{ $subcategory ?: 'All Subcategories' }}</span>
                                <svg class="w-4 h-4 ml-2 text-primary transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 w-56 mt-1.5 bg-surface border border-border-subtle rounded-2xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar py-1.5 divide-y divide-border-subtle/40">
                                <a href="{{ request()->fullUrlWithQuery(['subcategory' => null]) }}"
                                   class="block px-4 py-2.5 text-xs sm:text-sm text-text-main hover:bg-brand-light/30 hover:text-primary font-semibold transition-colors cursor-pointer">
                                    All Subcategories
                                </a>
                                @foreach($subcategories as $sub)
                                    <a href="{{ request()->fullUrlWithQuery(['subcategory' => $sub]) }}"
                                       class="flex items-center justify-between px-4 py-2.5 text-xs sm:text-sm text-text-main hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer {{ $subcategory == $sub ? 'bg-primary/10 text-primary font-bold' : 'font-medium' }}">
                                        <span class="truncate">{{ $sub }}</span>
                                        @if($subcategory == $sub)
                                            <span class="text-primary font-extrabold">&#10003;</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </form>

                @if($products->isNotEmpty())
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            x-show="hasActiveFilters"
                            x-cloak
                            @click="resetFilters()"
                            class="px-3.5 py-2 rounded-xl bg-brand-light/40 hover:bg-brand-light text-xs font-bold text-primary-dark transition-colors cursor-pointer"
                        >
                            Clear filters
                        </button>

                        <button
                            type="button"
                            @click="isFilterModalOpen = true"
                            class="relative px-3.5 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-primary-dark hover:text-primary transition-colors cursor-pointer flex items-center gap-2 focus:outline-none shadow-2xs text-xs font-bold"
                            aria-label="Filter products"
                            title="Filter"
                        >
                            <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Price Filter</span>
                            <span
                                x-show="hasActiveFilters"
                                x-cloak
                                class="w-2 h-2 rounded-full bg-primary"
                            ></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if($correctedQuery)
            <div class="mb-6 px-5 py-3.5 rounded-2xl bg-brand-light/30 border border-primary/25 text-xs sm:text-sm text-text-main flex flex-wrap items-center justify-between gap-2">
                <div>
                    No exact matches for <span class="font-bold text-primary-dark">"{{ $query }}"</span>.
                    Showing results for <span class="font-bold text-primary">"{{ $correctedQuery }}"</span> instead.
                </div>
                <a href="{{ request()->fullUrlWithQuery(['exact' => 1]) }}" class="font-extrabold text-primary hover:text-primary-dark underline cursor-pointer">
                    Search for "{{ $query }}" only &rarr;
                </a>
            </div>
        @endif

        @if($products->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-5 lg:gap-6">
                @foreach($products as $product)
                    <div
                        x-cloak
                        x-show="isProductVisible({{ (int) $product->id }})"
                        class="transition-opacity duration-200"
                    >
                        <x-storefront.seller-product-card
                            :product="$product"
                            :is-wishlisted="in_array((int) $product->id, $wishlistedProductIds, true)"
                        />
                    </div>
                @endforeach
            </div>

            <div
                x-show="totalFilteredCount === 0"
                x-cloak
                class="py-16 text-center text-text-muted bg-surface rounded-3xl border border-border-subtle mt-4"
            >
                <h3 class="text-base font-bold text-primary-dark">No matching products found</h3>
                <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">Try widening your price range.</p>
                <button
                    type="button"
                    @click="resetFilters()"
                    class="mt-4 px-5 py-2.5 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                >
                    Reset Filters
                </button>
            </div>

            <div x-ref="homeScrollSentinel"
                 x-show="hasMore"
                 x-cloak
                 class="py-8 flex flex-col items-center justify-center gap-3 text-xs font-bold text-text-muted">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
                </div>
                <span x-text="'Loaded ' + currentVisibleCount + ' of ' + totalFilteredCount + ' products — Scroll down or click below'"></span>
                <button type="button"
                        @click="if(typeof loadMore === 'function') loadMore()"
                        class="px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                    See More Results
                </button>
            </div>

            <x-storefront.category-filter-modal :subcategories="[]" :show-subcategories="false" />
        @else
            <div class="text-center py-16 bg-surface border border-border-subtle rounded-3xl shadow-xs">
                <svg class="mx-auto h-12 w-12 text-primary/50 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-bold text-primary-dark">No products found</h3>
                <p class="text-xs sm:text-sm text-text-muted mt-1 max-w-md mx-auto">We couldn't find anything matching "{{ $query }}"{{ $category ? ' in ' . $category : '' }}. Try adjusting your search or filters.</p>
                <a href="{{ route('home') }}" class="mt-6 inline-flex items-center px-6 py-2.5 text-xs sm:text-sm font-bold rounded-full text-surface bg-primary hover:bg-primary-dark transition-colors shadow-xs cursor-pointer">
                    Back to Home
                </a>
            </div>
        @endif
    </div>
@endsection