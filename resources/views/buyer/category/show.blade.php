@extends('layouts.app', ['title' => $categoryName])

@section('content')
    @php
        $fallbackMetadata = collect($products ?? [])->map(function ($prod) {
            $basePrice = (float) ($prod->price ?? 0);
            $discount = (float) ($prod->discount ?? 0);
            $effectivePrice = $discount > 0
                ? round($basePrice - ($basePrice * ($discount / 100)), 2)
                : round($basePrice, 2);

            return [
                'id'          => (int) $prod->id,
                'name'        => (string) ($prod->name ?? ''),
                'subcategory' => trim((string) ($prod->subcategory ?? 'Uncategorized')),
                'price'       => $effectivePrice,
                'base_price'  => $basePrice,
                'created_at'  => optional($prod->created_at)->timestamp ?? 0,
            ];
        })->values()->all();

        $resolvedMetadata = (!empty($productMetadata) && is_iterable($productMetadata) && count($productMetadata) > 0)
            ? collect($productMetadata)->map(function ($item) {
                $arr = is_array($item) ? $item : (array) $item;
                return [
                    'id'          => (int) ($arr['id'] ?? 0),
                    'name'        => (string) ($arr['name'] ?? ''),
                    'subcategory' => trim((string) ($arr['subcategory'] ?? 'Uncategorized')),
                    'price'       => (float) ($arr['price'] ?? ($arr['effective_price'] ?? 0)),
                    'base_price'  => (float) ($arr['base_price'] ?? ($arr['price'] ?? 0)),
                    'created_at'  => $arr['created_at'] ?? 0,
                ];
            })->values()->all()
            : $fallbackMetadata;

        $pricesCollection = collect($resolvedMetadata)->pluck('price')->filter(fn ($p) => $p !== null && $p >= 0);
        $resolvedMinPrice = isset($minPrice) && is_numeric($minPrice)
            ? (float) $minPrice
            : ($pricesCollection->isNotEmpty() ? (float) floor($pricesCollection->min()) : 0);
        $resolvedMaxPrice = isset($maxPrice) && is_numeric($maxPrice) && (float) $maxPrice > 0
            ? (float) $maxPrice
            : ($pricesCollection->isNotEmpty() ? (float) ceil($pricesCollection->max()) : 100000);

        if ($resolvedMaxPrice < $resolvedMinPrice) {
            $resolvedMaxPrice = $resolvedMinPrice;
        }
    @endphp

    <script>
        if (typeof window.categoryPageManager !== 'function') {
            window.categoryPageManager = function (config = {}) {
                const rawProducts = Array.isArray(config.products) ? config.products : [];
                const normalizedProducts = rawProducts.map(p => ({
                    id: Number(p.id),
                    name: String(p.name ?? ''),
                    subcategory: String(p.subcategory ?? '').trim(),
                    price: Number(p.price ?? 0),
                    created_at: p.created_at ?? 0
                }));

                const minBound = Number.isFinite(Number(config.minPrice)) ? Number(config.minPrice) : 0;
                const maxBound = Number.isFinite(Number(config.maxPrice)) && Number(config.maxPrice) >= minBound
                    ? Number(config.maxPrice)
                    : Math.max(minBound, ...normalizedProducts.map(p => p.price), 100000);

                const initSub = (config.initialSubcategory && String(config.initialSubcategory).trim() !== '' && String(config.initialSubcategory).trim() !== 'All')
                    ? [String(config.initialSubcategory).trim()]
                    : ['All'];

                return {
                    products: normalizedProducts,
                    subcategories: Array.isArray(config.subcategories) ? config.subcategories : [],
                    minPrice: minBound,
                    maxPrice: maxBound,
                    selectedSubcategories: [...initSub],
                    tempSubcategories: [...initSub],
                    selectedMinPrice: minBound,
                    selectedMaxPrice: maxBound,
                    tempMinPrice: minBound,
                    tempMaxPrice: maxBound,
                    sortBy: 'newest',
                    tempSortBy: 'newest',
                    pageSize: 24,
                    visibleLimit: 24,
                    isFilterModalOpen: false,

                    get hasActiveFilters() {
                        const subActive = !this.selectedSubcategories.includes('All') && this.selectedSubcategories.length > 0;
                        const minActive = Number(this.selectedMinPrice) > Number(this.minPrice);
                        const maxActive = Number(this.selectedMaxPrice) < Number(this.maxPrice);
                        return subActive || minActive || maxActive;
                    },

                    get filteredProducts() {
                        const activeSubs = this.selectedSubcategories
                            .map(s => String(s).trim().toLowerCase())
                            .filter(Boolean);
                        const showAllSubs = activeSubs.length === 0 || activeSubs.includes('all');
                        const minP = Number.isFinite(Number(this.selectedMinPrice)) ? Number(this.selectedMinPrice) : this.minPrice;
                        const maxP = Number.isFinite(Number(this.selectedMaxPrice)) ? Number(this.selectedMaxPrice) : this.maxPrice;

                        return this.products.filter(product => {
                            const prodSub = String(product.subcategory || '').trim().toLowerCase();
                            const matchesSub = showAllSubs || activeSubs.includes(prodSub);
                            const prodPrice = Number(product.price || 0);
                            const matchesPrice = prodPrice >= (minP - 0.01) && prodPrice <= (maxP + 0.01);
                            return matchesSub && matchesPrice;
                        });
                    },

                    get totalFilteredCount() {
                        return this.filteredProducts.length;
                    },

                    get visibleProductIds() {
                        return this.filteredProducts
                            .slice(0, this.visibleLimit)
                            .map(p => Number(p.id));
                    },

                    get hasMoreProducts() {
                        return this.visibleLimit < this.totalFilteredCount;
                    },

                    get remainingProductsCount() {
                        return Math.max(0, this.totalFilteredCount - this.visibleLimit);
                    },

                    isProductVisible(productId) {
                        return this.visibleProductIds.includes(Number(productId));
                    },

                    toggleSubcategory(sub) {
                        const normalized = String(sub || '').trim();
                        if (!normalized || normalized === 'All') {
                            this.selectedSubcategories = ['All'];
                        } else {
                            let current = this.selectedSubcategories.filter(s => s !== 'All');
                            if (current.includes(normalized)) {
                                current = current.filter(s => s !== normalized);
                            } else {
                                current.push(normalized);
                            }
                            this.selectedSubcategories = current.length > 0 ? current : ['All'];
                        }
                        this.tempSubcategories = [...this.selectedSubcategories];
                        this.visibleLimit = this.pageSize;
                    },

                    toggleTempSubcategory(sub) {
                        const normalized = String(sub || '').trim();
                        if (!normalized || normalized === 'All') {
                            this.tempSubcategories = ['All'];
                            return;
                        }
                        let current = this.tempSubcategories.filter(s => s !== 'All');
                        if (current.includes(normalized)) {
                            current = current.filter(s => s !== normalized);
                        } else {
                            current.push(normalized);
                        }
                        this.tempSubcategories = current.length > 0 ? current : ['All'];
                    },

                    applyFilters() {
                        this.selectedSubcategories = this.tempSubcategories.length > 0
                            ? [...this.tempSubcategories]
                            : ['All'];
                        this.selectedMinPrice = Number.isFinite(Number(this.tempMinPrice))
                            ? Number(this.tempMinPrice)
                            : this.minPrice;
                        this.selectedMaxPrice = Number.isFinite(Number(this.tempMaxPrice))
                            ? Number(this.tempMaxPrice)
                            : this.maxPrice;
                        this.sortBy = this.tempSortBy || 'newest';
                        this.visibleLimit = this.pageSize;
                        this.isFilterModalOpen = false;
                    },

                    resetFilters() {
                        this.selectedSubcategories = ['All'];
                        this.tempSubcategories = ['All'];
                        this.selectedMinPrice = this.minPrice;
                        this.selectedMaxPrice = this.maxPrice;
                        this.tempMinPrice = this.minPrice;
                        this.tempMaxPrice = this.maxPrice;
                        this.sortBy = 'newest';
                        this.tempSortBy = 'newest';
                        this.visibleLimit = this.pageSize;
                        this.isFilterModalOpen = false;
                    },

                    loadMore() {
                        this.visibleLimit += this.pageSize;
                    },

                    showMore() {
                        this.loadMore();
                    }
                };
            };
        }
    </script>

    <!-- Main Category Experience Wrapper (Option A Alpine.js Filter Engine) -->
    <div 
        x-data="categoryPageManager({
            products: @js($resolvedMetadata),
            subcategories: @js($subcategories ?? []),
            initialSubcategory: @js($selectedSubcategory ?? 'All'),
            minPrice: @js($resolvedMinPrice),
            maxPrice: @js($resolvedMaxPrice)
        })"
        x-init="initBatchScroll()"
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
                        x-show="isProductVisible({{ (int) $product->id }})" 
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

            <!-- Infinite-scroll sentinel (same pattern as home.blade.php) -->
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

        </div>

        <!-- Filter Modal Component -->
        <x-storefront.category-filter-modal :subcategories="$subcategories" />

        <!-- Existing Global Product Quick-View Modal -->
        <x-storefront.product-modal />

    </div>

@endsection