@extends('layouts.app') 

@section('content')
    <!-- Alpine.js & Custom Styles -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/buyer/home.css'])

    <!-- Main Container -->
    <div x-data="{ modalOpen: false, activeProduct: null }" class="relative bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">
        
        <!-- Main Content Wrapper -->
        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-6 lg:py-6 animate-fade-in-up">
            
            <!-- Hero Carousel Component -->
            <x-storefront.hero-carousel />

            <!-- Featured Sellers Section -->
            @php
                $sellers = $sellers ?? \App\Models\SellerProfile::take(6)->get();
            @endphp
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

            <!-- Super Deals & Trends Component -->
            <x-storefront.deals-trends :products="$products" />

            <!-- Products Grid Section -->
             <div class="mb-6 border-b border-border-subtle/80 pb-3">
                <h2 class="text-center alingtext-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide">
                    Picked For You
                </h2>
            </div>

            <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5 2xl:grid-cols-5 gap-5 lg:gap-6">
                @forelse($products as $product)
                    <x-storefront.seller-product-card :product="$product" />
                @empty
                    <div class="col-span-full text-center py-12 text-text-muted font-medium text-sm">
                        No products available at the moment.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
@endsection