@extends('layouts.app')

@section('content')

@php
    $totalStock = 0;
    if (isset($product->variants['items']) && is_array($product->variants['items'])) {
        foreach ($product->variants['items'] as $item) {
            if (isset($item['subs']) && count($item['subs']) > 0) {
                foreach ($item['subs'] as $sub) {
                    $totalStock += (int)($sub['stock'] ?? 0);
                }
            } else {
                $totalStock += (int)($item['stock'] ?? 0);
            }
        }
    } else {
        $totalStock = (int)($product->stock_quantity ?? 0);
    }
    $firstPic = (!empty($product->pictures) && is_array($product->pictures)) ? asset('storage/' . $product->pictures[0]) : '';
@endphp

<!-- Alpine Data Wrapper for interactivity -->
<div x-data="{
    selectedMain: null,
    selectedSub: null,
    selectedMainName: '',
    selectedSubName: '',
    hasVariants: {{ isset($product->variants['items']) && count($product->variants['items']) > 0 ? 'true' : 'false' }},
    hasSubVariants: false,
    toastMessage: '',
    showToast: false,
    
    basePrice: {{ $product->price ?? 0 }},
    discount: {{ $product->discount ?? 0 }},
    totalStock: {{ $totalStock }},
    activePrice: {{ $product->price ?? 0 }},
    activeStock: {{ $totalStock }},
    mainImage: '{{ $firstPic }}',
    defaultImage: '{{ $firstPic }}',
    quantity: 1,
    imageModalOpen: false,

    get discountedPrice() {
        return this.discount > 0 ? this.activePrice - (this.activePrice * (this.discount / 100)) : this.activePrice;
    },
    formatMoney(value) {
        return Number(value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    },
    setMainImage(img) {
        this.mainImage = img;
    },
    validateAndSubmit(isAuth) {
        if (this.hasVariants && !this.selectedMainName) {
            this.toastMessage = 'Please pick a {{ addslashes($product->variants['title'] ?? 'variant') }} first.';
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);
            return;
        }
        if (this.hasSubVariants && !this.selectedSubName) {
            this.toastMessage = 'Please pick a {{ addslashes($product->variants['sub_title'] ?? 'sub-variant') }} too.';
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);
            return;
        }
        
        if (!isAuth) {
            window.location.href = '{{ route('login') }}';
            return;
        }
        
        this.$refs.addToCartForm.submit();
    }
}" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 bg-gray-50 min-h-screen relative overflow-hidden">
    
    <!-- Breadcrumbs -->
    <div class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-primary cursor-pointer transition-colors">Home</a> &gt; 
        <span class="text-gray-700 font-medium">{{ $product->name }}</span>
    </div>

    <!-- Product Overview -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row p-6 md:p-8 gap-8 relative">
        
        <!-- Left: Images -->
        <div class="w-full md:w-5/12">
            <div class="aspect-square bg-gray-50 rounded-xl overflow-hidden mb-4 border border-gray-100 flex items-center justify-center group relative cursor-pointer" x-on:click="if(mainImage) imageModalOpen = true">
                @if(!empty($product->pictures) && is_array($product->pictures))
                    <img :src="mainImage" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                @else
                    <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                @endif
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-all flex items-center justify-center pointer-events-none"></div>
            </div>
            @if(!empty($product->pictures) && count($product->pictures) > 1)
            <div class="flex gap-2 overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden">
                @foreach($product->pictures as $pic)
                <img src="{{ asset('storage/' . $pic) }}" 
                     alt="Thumbnail" 
                     class="w-16 h-16 sm:w-20 sm:h-20 object-cover bg-white rounded-lg border hover:border-primary cursor-pointer transition-all" 
                     x-on:click="setMainImage('{{ asset('storage/' . $pic) }}')" 
                     :class="mainImage === '{{ asset('storage/' . $pic) }}' ? 'border-primary ring-2 ring-primary/20 opacity-100' : 'border-gray-200 opacity-70 hover:opacity-100'">
                @endforeach
            </div>
            @endif
        </div>

        <!-- Right: Details & Actions -->
        <div class="w-full md:w-7/12">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2 leading-tight">{{ $product->name }}</h1>
            
            <div class="flex items-center space-x-4 mb-5 text-sm">
                <div class="flex text-yellow-400">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <span class="text-gray-500 underline cursor-pointer hover:text-primary">0 Ratings</span>
                <span class="text-gray-300">|</span>
                <span class="text-gray-500">0 Sold</span>
            </div>

            <!-- Price Block -->
            <div class="bg-gray-50 px-6 py-4 rounded-xl mb-6 flex items-baseline gap-4 border border-gray-100">
                <template x-if="discount > 0">
                    <span class="text-gray-400 line-through text-lg" x-text="'₱' + formatMoney(activePrice)"></span>
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
                                @php
                                    $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                    $itemStock = $hasSubs ? collect($item['subs'])->sum('stock') : (int)($item['stock'] ?? 0);
                                @endphp
                                <button 
                                    x-on:click="
                                        if (selectedMain === {{ $vIndex }}) {
                                            selectedMain = null;
                                            selectedSub = null;
                                            selectedMainName = '';
                                            selectedSubName = '';
                                            hasSubVariants = false;
                                            activePrice = basePrice;
                                            activeStock = totalStock;
                                            mainImage = defaultImage;
                                        } else {
                                            selectedMain = {{ $vIndex }};
                                            selectedSub = null;
                                            selectedMainName = '{{ addslashes($item['name'] ?? '') }}';
                                            selectedSubName = '';
                                            hasSubVariants = {{ $hasSubs ? 'true' : 'false' }};
                                            activePrice = {{ $item['price'] ?? $product->price }};
                                            activeStock = {{ $itemStock }};
                                            @if(isset($item['image']) && $item['image'])
                                                mainImage = '{{ asset('storage/' . $item['image']) }}';
                                            @endif
                                        }
                                        quantity = 1;
                                    "
                                    :class="selectedMain === {{ $vIndex }} ? 'border-primary text-primary ring-1 ring-primary bg-primary-light/10' : 'border-gray-300 text-gray-700 hover:border-primary hover:text-primary'"
                                    class="px-4 py-2 border rounded-lg focus:outline-none transition-all bg-white text-sm cursor-pointer font-medium">
                                    {{ $item['name'] ?? 'Unnamed' }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sub-Variants -->
                    @foreach($product->variants['items'] as $vIndex => $item)
                        @if(isset($item['subs']) && is_array($item['subs']) && count($item['subs']) > 0)
                            <div x-show="selectedMain === {{ $vIndex }}" style="display: none;" x-cloak>
                                <h3 class="text-gray-700 font-medium mb-3 capitalize">{{ $product->variants['sub_title'] ?? 'Sub Variants' }}</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($item['subs'] as $sIndex => $sub)
                                        <button 
                                            x-on:click="
                                                if (selectedSub === {{ $sIndex }}) {
                                                    selectedSub = null;
                                                    selectedSubName = '';
                                                    activePrice = {{ $item['price'] ?? $product->price }};
                                                    activeStock = {{ $itemStock }};
                                                    @if(isset($item['image']) && $item['image'])
                                                        mainImage = '{{ asset('storage/' . $item['image']) }}';
                                                    @else
                                                        mainImage = defaultImage;
                                                    @endif
                                                } else {
                                                    selectedSub = {{ $sIndex }};
                                                    selectedSubName = '{{ addslashes($sub['name'] ?? '') }}';
                                                    activePrice = {{ $sub['price'] ?? ($item['price'] ?? $product->price) }};
                                                    activeStock = {{ (int)($sub['stock'] ?? 0) }};
                                                    @if(isset($sub['image']) && $sub['image'])
                                                        mainImage = '{{ asset('storage/' . $sub['image']) }}';
                                                    @endif
                                                }
                                                quantity = 1;
                                            "
                                            :class="selectedSub === {{ $sIndex }} ? 'border-primary text-primary ring-1 ring-primary bg-primary-light/10' : 'border-gray-300 text-gray-700 hover:border-primary hover:text-primary'"
                                            class="px-4 py-2 border rounded-lg focus:outline-none transition-all bg-white text-sm cursor-pointer font-medium">
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
                <div class="flex items-center border border-gray-300 rounded-lg bg-white h-10 overflow-hidden">
                    <button x-on:click="if(quantity > 1) quantity--" class="px-4 text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors border-r border-gray-300 cursor-pointer h-full outline-none focus:outline-none">-</button>
                    <input type="number" x-model="quantity" min="1" :max="activeStock" class="w-16 h-full text-center border-none focus:ring-0 text-gray-700 font-bold p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    <button x-on:click="if(quantity < activeStock) quantity++" class="px-4 text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors border-l border-gray-300 cursor-pointer h-full outline-none focus:outline-none">+</button>
                </div>
                <span class="text-sm font-medium" :class="activeStock > 5 ? 'text-gray-500' : 'text-danger'" x-text="activeStock + ' pieces available'"></span>
            </div>

            <!-- Action Buttons connected to Variant Logic -->
            <div class="flex gap-4 w-full">
                @auth
                    <!-- Auth: Validates variant requirements and submits -->
                    <form x-ref="addToCartForm" action="{{ route('cart.store') }}" method="POST" class="w-1/2 flex">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="quantity" x-bind:value="quantity">
                        <input type="hidden" name="variant" x-bind:value="selectedMainName">
                        <input type="hidden" name="sub_variant" x-bind:value="selectedSubName">
                        
                        <button type="button" @click="validateAndSubmit(true)" class="w-full bg-primary-light text-primary border border-primary hover:bg-primary hover:text-white font-bold py-3.5 px-6 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add To Cart
                        </button>
                    </form>
                    <button type="button" @click="validateAndSubmit(true)" class="w-1/2 bg-primary text-white hover:bg-primary-dark font-bold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg cursor-pointer">
                        Buy Now
                    </button>
                @else
                    <!-- Guest: Validates variant logic first, then routes to login -->
                    <button type="button" @click="validateAndSubmit(false)" class="w-1/2 bg-primary-light text-primary border border-primary hover:bg-primary hover:text-white font-bold py-3.5 px-6 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer text-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Add To Cart
                    </button>
                    <button type="button" @click="validateAndSubmit(false)" class="w-1/2 bg-primary text-white hover:bg-primary-dark font-bold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg cursor-pointer text-center flex items-center justify-center">
                        Buy Now
                    </button>
                @endguest
            </div>
        </div>
    </div>

    <!-- Shop Profile Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center border border-gray-200 cursor-pointer hover:bg-gray-200 transition-colors">
                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg cursor-pointer hover:text-primary transition-colors">
                    {{ $product->user->sellerProfile->business_name ?? ($product->sellerProfile->business_name ?? 'Official Store') }}
                </h3>
                <p class="text-sm text-gray-500">Active just now</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button class="px-5 py-2.5 border border-primary text-primary rounded-lg hover:bg-primary-light transition-colors text-sm font-bold flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                Chat Now
            </button>
            <button class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-bold cursor-pointer">View Shop</button>
        </div>
    </div>

    <!-- Product Specifications & Description -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 p-6 md:p-8">
        <h2 class="bg-gray-50 px-4 py-3 font-bold text-gray-900 rounded-lg mb-6 uppercase text-sm tracking-wider">Product Specifications</h2>
        <div class="space-y-4 text-sm text-gray-600 px-4 mb-10">
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400 font-medium">Weight</span><span class="font-medium text-gray-800">{{ $product->weight ?? 'Not specified' }}</span></div>
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400 font-medium">Stock</span><span class="font-medium text-gray-800" x-text="activeStock"></span></div>
            <div class="flex border-b border-gray-50 pb-2"><span class="w-40 text-gray-400 font-medium">Ships From</span><span class="font-medium text-gray-800">Local Warehouse</span></div>
        </div>
        
        <h2 class="bg-gray-50 px-4 py-3 font-bold text-gray-900 rounded-lg mb-6 uppercase text-sm tracking-wider">Product Description</h2>
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
    <div x-show="imageModalOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-md p-4 sm:p-8" x-on:keydown.escape.window="imageModalOpen = false">
        <div x-on:click.away="imageModalOpen = false" class="relative w-full h-full flex items-center justify-center cursor-default">
            <button x-on:click="imageModalOpen = false" class="absolute top-4 right-4 text-white hover:text-gray-300 z-[101] bg-black/50 hover:bg-black/70 rounded-full p-2 cursor-pointer transition-colors outline-none focus:outline-none">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <img :src="mainImage" class="max-w-full max-h-full object-contain rounded-xl shadow-2xl" x-on:click.stop>
        </div>
    </div>

    <!-- Refined Upper-Right Toast Notification -->
    <div x-show="showToast" 
         x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-1rem] scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-[-1rem] scale-95"
         class="fixed top-24 right-6 z-[100] bg-surface border border-border-subtle rounded-xl shadow-2xl font-sans flex items-start gap-3 min-w-[300px] overflow-hidden">
        
        <!-- Left accent line -->
        <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-danger"></div>
        
        <!-- Icon -->
        <div class="bg-danger/10 text-danger p-2 rounded-full shrink-0 ml-3 mt-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        
        <div class="py-3 pr-8">
            <h4 class="text-text-main text-sm font-bold mb-0.5">Selection Required</h4>
            <p class="text-text-muted text-xs font-medium leading-relaxed" x-text="toastMessage"></p>
        </div>

        <!-- Close button -->
        <button type="button" @click="showToast = false" class="absolute top-3 right-3 text-text-muted hover:text-text-main transition-colors cursor-pointer outline-none focus:outline-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

</div>
@endsection