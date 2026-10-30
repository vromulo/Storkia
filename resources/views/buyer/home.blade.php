@extends('layouts.app')

@section('content')
    @vite(['resources/css/buyer/home.css'])

    @php
        $sellers = $sellers ?? \App\Models\SellerProfile::take(6)->get();
        $youMayAlsoLike = $youMayAlsoLike ?? $products->take(12);
        $totalProductsCount = $products->count();
    @endphp

    <div x-data="{
            modalOpen: false,
            activeProduct: null,
            batchSize: 10,
            visibleCount: 10,
            totalProducts: {{ (int) $totalProductsCount }},
            isLoadingMore: false,
            initBatchScroll() {
                this.$nextTick(() => {
                    if (!this.$refs.homeScrollSentinel || typeof IntersectionObserver === 'undefined') return;
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting && this.visibleCount < this.totalProducts && !this.isLoadingMore) {
                                this.isLoadingMore = true;
                                setTimeout(() => {
                                    this.visibleCount = Math.min(this.totalProducts, this.visibleCount + this.batchSize);
                                    this.isLoadingMore = false;
                                }, 200);
                            }
                        });
                    }, { rootMargin: '180px' });
                    observer.observe(this.$refs.homeScrollSentinel);
                });
            }
         }"
         x-init="initBatchScroll()"
         class="relative bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">

        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-10 lg:py-6 animate-fade-in-up space-y-10">

            <x-storefront.hero-carousel />

            <div class="my-6 sm:my-8">
                <div class="flex justify-between items-center mb-4 border-b border-border-subtle/80 pb-3">
                    <h2 class="text-base sm:text-xl font-bold font-serif text-main uppercase tracking-wide">
                        Our Sellers
                    </h2>
                    <a href="{{ route('sellers.index') }}" class="inline-flex items-center gap-1 text-xs sm:text-sm font-bold text-primary hover:text-primary-dark transition-colors">
                        See More
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
                    @forelse($sellers as $seller)
                        <x-storefront.seller-card :seller="$seller" :is-home="true" />
                    @empty
                        <div class="col-span-full text-center py-6 text-text-muted font-medium text-xs sm:text-sm">
                            No sellers available at the moment.
                        </div>
                    @endforelse
                </div>
            </div>

            <x-storefront.deals-trends :products="$products" />

            @if($youMayAlsoLike->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2 border-b border-border-subtle/80 pb-3">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                                Personalized Recommendations
                            </span>
                            <h2 class="text-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide mt-1">
                                You May Also Like
                            </h2>
                        </div>
                        <p class="text-xs text-text-muted">
                            @auth
                                Curated from your recent orders, cart preferences, and followed stores
                            @else
                                Trending picks across top verified stores
                            @endauth
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-5">
                        @foreach($youMayAlsoLike->take(6) as $recProduct)
                            <x-storefront.seller-product-card :product="$recProduct" />
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="space-y-5">
                <div class="flex items-center justify-between border-b border-border-subtle/80 pb-3">
                    <h2 class="text-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide">
                        Picked For You (All Products)
                    </h2>
                    <span class="text-xs font-bold text-text-muted" x-text="'Showing ' + Math.min(visibleCount, totalProducts) + ' of ' + totalProducts + ' products'"></span>
                </div>

                <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5 2xl:grid-cols-5 gap-5 lg:gap-6">
                    @forelse($products as $idx => $product)
                        <div x-show="{{ $idx }} < visibleCount"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <x-storefront.seller-product-card :product="$product" />
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-text-muted font-medium text-sm">
                            No products available at the moment.
                        </div>
                    @endforelse
                </div>

                <div x-ref="homeScrollSentinel"
                     x-show="visibleCount < totalProducts"
                     class="py-8 flex flex-col items-center justify-center gap-2 text-xs font-bold text-text-muted">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
                    </div>
                    <span x-text="'Loaded ' + Math.min(visibleCount, totalProducts) + ' of ' + totalProducts + ' products — Scroll down for next batch'"></span>
                </div>
            </section>

        </div>
    </div>
@endsection