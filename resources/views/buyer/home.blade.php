@extends('layouts.app') 

@section('content')
    <!-- Alpine.js & Custom Styles -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>

    <!-- Main Container -->
    <div x-data="{ modalOpen: false, activeProduct: null }" class="relative bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">
        
        <!-- Main Content Wrapper -->
        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-6 lg:py-6 animate-fade-in-up">
            
            <!-- Hero Carousel Component -->
            <x-hero-carousel />

            <!-- Super Deals & Trends Component -->
            <x-deals-trends :products="$products" />

            <!-- Products Grid Section -->
             <div class="mb-6 border-b border-border-subtle/80 pb-3">
                <h2 class="text-center alingtext-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide">
                    Picked For You
                </h2>
            </div>

            <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5 2xl:grid-cols-5 gap-5 lg:gap-6">
                @forelse($products as $product)
                    <x-seller-product-card :product="$product" />
                @empty
                    <div class="col-span-full text-center py-12 text-text-muted font-medium text-sm">
                        No products available at the moment.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
@endsection