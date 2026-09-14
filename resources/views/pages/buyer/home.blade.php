@extends('layouts.app')

@section('content')
    <!-- Alpine.js & Custom Styles -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>

    <!-- Main Container -->
    <div x-data="{ modalOpen: false, activeProduct: null }" class="relative bg-[#F5EBE0] font-sans antialiased text-text-main overflow-x-hidden min-h-screen">
        
        <!-- Logo Background Overlay (25% Opacity, Black, Original Reference Spacing) -->
        <div class="absolute inset-0 pointer-events-none z-0 opacity-10 overflow-hidden" aria-hidden="true">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <!-- Matched to the original 120x120 pattern tile & -15deg rotation -->
                    <pattern id="stork-pattern" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                        <!-- Logo sized to 48px and perfectly centered in the 120px tile -->
                        <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#stork-pattern)" />
            </svg>
        </div>

        <!-- Main Content Wrapper -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in-up">
            
            <!-- Hero Banner -->
            <div class="relative bg-cover bg-center rounded-3xl overflow-hidden mb-12 shadow-md border border-border-subtle h-[350px] flex items-center" style="background-image: url('{{ asset('assets/yeezy-preview.png') }}');">
                <!-- Overlay to ensure text pops like a promo ad -->
                <div class="absolute inset-0 bg-black/60"></div>
                
                <div class="absolute -right-20 -top-20 w-[500px] h-[500px] bg-secondary opacity-10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative z-10 p-10 md:p-16 w-full md:w-2/3">
                    <h1 class="text-4xl md:text-6xl font-serif text-white font-bold leading-tight mb-4 drop-shadow-lg">
                        Grab Up to 50% Off On <br class="hidden md:block">Selected Items
                    </h1>
                    <p class="text-gray-100 mb-8 text-lg drop-shadow-md">Fast delivery. Exclusive deals. Right to your doorstep.</p>
                    <a href="#" class="inline-flex px-8 py-3 bg-[#b30271] hover:bg-[#8a0257] text-white font-bold rounded-full transition-colors duration-300 shadow-lg relative z-30">
                        Shop Now
                    </a>
                </div>
            </div>

            <!-- Products Grid Component -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
                @forelse($products as $product)
                    <x-seller-product-card :product="$product" />
                @empty
                    <div class="col-span-full text-center py-12 text-gray-500 font-medium">
                        No products available at the moment.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
@endsection