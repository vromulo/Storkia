@extends('layouts.app')

@section('content')

<!-- Cross-browser CSS to hide the up/down number arrows -->
<style>
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] {
        -moz-appearance: textfield; /* Firefox */
    }
</style>

<!-- Alpine Data Wrapper for interactivity -->
<div x-data="productApp()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 bg-gray-50 min-h-screen relative">
    
    <!-- Breadcrumbs -->
    <div class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-primary cursor-pointer">Home</a> &gt; 
        <span class="text-gray-700 font-medium">{{ $product->name }}</span>
    </div>

    <!-- Product Overview -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row p-6 md:p-8 gap-8">
        
        <!-- Left: Images -->
        <div class="w-full md:w-5/12">
            <!-- Main Image Wrapper -->
            <div class="aspect-square bg-gray-50 rounded-lg overflow-hidden mb-4 border border-gray-100 flex items-center justify-center group relative cursor-pointer" x-on:click="if(mainImage) imageModalOpen = true">
                
                @if(!empty($product->pictures) && is_array($product->pictures))
                    <img :src="mainImage" 
                         alt="{{ $product->name }}" 
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                @else
                    <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                @endif
                
                <!-- Hover overlay hint -->
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all flex items-center justify-center pointer-events-none">
                    <svg class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                </div>
            </div>
            
            <!-- Thumbnails -->
            @if(!empty($product->pictures) && count($product->pictures) > 1)
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach($product->pictures as $pic)
                <img src="{{ asset('storage/' . $pic) }}" 
                     alt="Thumbnail" 
                     class="w-20 h-20 object-cover bg-white rounded border hover:border-primary cursor-pointer transition-colors" 
                     x-on:click="setMainImage('{{ asset('storage/' . $pic) }}')" 
                     :class="mainImage === '{{ asset('storage/' . $pic) }}' ? 'border-primary ring-1 ring-primary' : 'border-gray-200'">
                @endforeach
            </div>
            @endif
        </div>

        <!-- Right: Details & Actions -->
        <div class="w-full md:w-7/12">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $product->name }}</h1>
            
            <div class="flex items-center space-x-4 mb-4 text-sm">
                <div class="flex text-yellow-400">★★★★★</div>
                <span class="text-gray-500 underline cursor-pointer hover:text-primary">0 Ratings</span>
                <span class="text-gray-300">|</span>
                <span class="text-gray-500">0 Sold</span>
            </div>

            <!-- Price Block -->
            <div class="bg-gray-50 px-6 py-4 rounded-lg mb-6 flex items-baseline gap-4 border border-gray-100">
                <template x-if="discount > 0">
                    <span class="text-gray-400 line-through text-lg" x-text="'₱' + formatMoney(currentPrice)"></span>
                </template>
                
                <span class="text-3xl font-bold text-primary" x-text="'₱' + formatMoney(discountedPrice)"></span>
                
                <template x-if="discount > 0">
                    <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded font-bold uppercase tracking-wide" x-text="discount + '% Off'"></span>
                </template>
            </div>

            <!-- Dynamic Variations -->
            @if(is_array($product->variants) && isset($product->variants['items']) && is_array($product->variants['items']))
                <div class="mb-6 space-y-5">
                    
                    <!-- Main Variants -->
                    <div>
                        <h3 class="text-gray-700 font-medium mb-3 capitalize">{{ $product->variants['title'] ?? 'Variants' }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($product->variants['items'] as $vIndex => $item)
                                <button 
                                    x-on:click="
                                        if (selectedMain === {{ $vIndex }}) {
                                            selectedMain = null;
                                            selectedSub = null;
                                            currentPrice = basePrice;
                                            mainImage = defaultImage;
                                        } else {
                                            selectedMain = {{ $vIndex }};
                                            selectedSub = null;
                                            currentPrice = {{ $item['price'] ?? $product->price }};
                                            @if(isset($item['image']) && $item['image'])
                                                mainImage = '{{ asset('storage/' . $item['image']) }}';
                                            @endif
                                        }
                                    "
                                    :class="selectedMain === {{ $vIndex }} ? 'border-primary text-primary ring-1 ring-primary bg-primary-light/10' : 'border-gray-300 text-gray-700 hover:border-primary hover:text-primary'"
                                    class="px-4 py-2 border rounded focus:outline-none transition-all bg-white text-sm cursor-pointer">
                                    {{ $item['name'] ?? 'Unnamed' }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sub-Variants -->
                    @foreach($product->variants['items'] as $vIndex => $item)
                        @if(isset($item['subs']) && is_array($item['subs']) && count($item['subs']) > 0)
                            <div x-show="selectedMain === {{ $vIndex }}" style="display: none;">
                                <h3 class="text-gray-700 font-medium mb-3 capitalize">{{ $product->variants['sub_title'] ?? 'Sub Variants' }}</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($item['subs'] as $sIndex => $sub)
                                        <button 
                                            x-on:click="
                                                if (selectedSub === {{ $sIndex }}) {
                                                    selectedSub = null;
                                                    currentPrice = {{ $item['price'] ?? $product->price }};
                                                    @if(isset($item['image']) && $item['image'])
                                                        mainImage = '{{ asset('storage/' . $item['image']) }}';
                                                    @else
                                                        mainImage = defaultImage;
                                                    @endif
                                                } else {
                                                    selectedSub = {{ $sIndex }};
                                                    currentPrice = {{ $sub['price'] ?? ($item['price'] ?? $product->price) }};
                                                    @if(isset($sub['image']) && $sub['image'])
                                                        mainImage = '{{ asset('storage/' . $sub['image']) }}';
                                                    @endif
                                                }
                                            "
                                            :class="selectedSub === {{ $sIndex }} ? 'border-primary text-primary ring-1 ring-primary bg-primary-light/10' : 'border-gray-300 text-gray-700 hover:border-primary hover:text-primary'"
                                            class="px-4 py-2 border rounded focus:outline-none transition-all bg-white text-sm cursor-pointer">
                                            {{ $sub['name'] ?? 'Unnamed' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                </div>
            @endif

            <!-- Quantity & Actions -->
            <div class="flex items-center gap-4 mt-8 mb-6">
                <span class="text-gray-700 font-medium w-20">Quantity</span>
                <div class="flex items-center border border-gray-300 rounded bg-white">
                    <button x-on:click="if(quantity > 1) quantity--" class="px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors border-r border-gray-300 cursor-pointer">-</button>
                    <!-- Pure number input (spinners hidden by CSS in header) -->
                    <input type="number" x-model="quantity" min="1" max="{{ $product->stock_quantity }}" class="w-16 text-center border-none focus:ring-0 text-gray-700 py-2">
                    <button x-on:click="if(quantity < maxStock) quantity++" class="px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors border-l border-gray-300 cursor-pointer">+</button>
                </div>
                <span class="text-sm text-gray-500" x-text="maxStock + ' pieces available'"></span>
            </div>

            <div class="flex gap-4">
                @guest
                    <!-- If Guest, act as an Anchor routing to login -->
                    <a href="{{ route('login') }}" class="w-1/2 bg-primary-light text-primary border border-primary hover:bg-primary hover:text-white font-medium py-3 px-6 rounded transition-colors flex items-center justify-center gap-2 cursor-pointer text-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Add To Cart
                    </a>
                    <a href="{{ route('login') }}" class="w-1/2 bg-primary text-white hover:bg-primary-dark font-medium py-3 px-6 rounded transition-colors shadow-sm cursor-pointer text-center flex items-center justify-center">
                        Buy Now
                    </a>
                @else
                    <!-- If Auth, act as a Button (Disabled for now) -->
                    <button type="button" class="w-1/2 bg-primary-light text-primary border border-primary hover:bg-primary hover:text-white font-medium py-3 px-6 rounded transition-colors flex items-center justify-center gap-2 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Add To Cart
                    </button>
                    <button type="button" class="w-1/2 bg-primary text-white hover:bg-primary-dark font-medium py-3 px-6 rounded transition-colors shadow-sm cursor-pointer">
                        Buy Now
                    </button>
                @endguest
            </div>
        </div>
    </div>

    <!-- Shop Profile Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6 p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center border border-gray-200 cursor-pointer hover:bg-gray-200 transition-colors">
                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg cursor-pointer hover:text-primary transition-colors">Official Store</h3>
                <p class="text-sm text-gray-500">Active just now</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button class="px-5 py-2 border border-primary text-primary rounded hover:bg-primary-light transition-colors text-sm font-medium flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                Chat Now
            </button>
            <button class="px-5 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors text-sm font-medium cursor-pointer">View Shop</button>
        </div>
    </div>

    <!-- Product Specifications & Description -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6 p-6 md:p-8">
        <h2 class="bg-gray-50 px-4 py-3 font-medium text-gray-900 rounded mb-6 uppercase text-sm tracking-wider">Product Specifications</h2>
        
        <div class="space-y-4 text-sm text-gray-600 px-4 mb-10">
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400">Weight</span><span>{{ $product->weight ?? 'Not specified' }}</span></div>
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400">Stock</span><span x-text="maxStock"></span></div>
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400">Ships From</span><span>Local Warehouse</span></div>
        </div>
        
        <h2 class="bg-gray-50 px-4 py-3 font-medium text-gray-900 rounded mb-6 uppercase text-sm tracking-wider">Product Description</h2>
        
        <div class="prose prose-sm max-w-none text-gray-700 px-4 leading-relaxed">
            {!! nl2br(e($product->description)) !!}
            
            @if(!empty($product->additional_descriptions))
                <div class="mt-6 pt-6 border-t border-gray-100">
                    {!! nl2br(e($product->additional_descriptions)) !!}
                </div>
            @endif
        </div>
    </div>

    <!-- Fullscreen Image Modal -->
    <div x-show="imageModalOpen" 
         style="display: none;" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/20 backdrop-blur-xl p-4 sm:p-8 transition-opacity"
         x-on:keydown.escape.window="imageModalOpen = false"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
         
        <div x-on:click.away="imageModalOpen = false" class="relative w-full h-full flex items-center justify-center cursor-default">
            <button x-on:click="imageModalOpen = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-50 bg-black/50 rounded-full p-2 cursor-pointer">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            
            <img :src="mainImage" class="max-w-full max-h-full object-contain rounded shadow-2xl" x-on:click.stop>
        </div>
    </div>

</div>

<!-- Alpine Script for Interactivity -->
<script>
    function productApp() {
        return {
            defaultImage: '{{ (!empty($product->pictures) && is_array($product->pictures)) ? asset("storage/" . $product->pictures[0]) : "" }}',
            mainImage: '{{ (!empty($product->pictures) && is_array($product->pictures)) ? asset("storage/" . $product->pictures[0]) : "" }}',
            imageModalOpen: false,

            basePrice: {{ $product->price ?? 0 }},
            currentPrice: {{ $product->price ?? 0 }},
            discount: {{ $product->discount ?? 0 }},
            maxStock: {{ $product->stock_quantity ?? 0 }},
            quantity: 1,
            
            selectedMain: null,
            selectedSub: null,

            setMainImage(url) {
                if(url) this.mainImage = url;
            },

            get discountedPrice() {
                if (this.discount > 0) {
                    return this.currentPrice - (this.currentPrice * (this.discount / 100));
                }
                return this.currentPrice;
            },

            formatMoney(amount) {
                return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
    }
</script>
@endsection