@extends('layouts.app')

@section('content')
@php
    $totalStock = 0;
    if (isset($product->variants['items']) && is_array($product->variants['items'])) {
        foreach ($product->variants['items'] as $item) {
            if (isset($item['subs']) && count($item['subs']) > 0) {
                foreach ($item['subs'] as $sub) {
                    $totalStock += (int) ($sub['stock'] ?? 0);
                }
            } else {
                $totalStock += (int) ($item['stock'] ?? 0);
            }
        }
    } else {
        $totalStock = (int) ($product->stock_quantity ?? 0);
    }

    $firstPic = (!empty($product->pictures) && is_array($product->pictures))
        ? asset('storage/' . $product->pictures[0])
        : '';

    $seller = $seller
        ?? $product->user?->sellerProfile
        ?? $product->sellerProfile
        ?? ($product->user_id ? \App\Models\SellerProfile::where('user_id', $product->user_id)->first() : null);

    $sellerUserId = $seller?->user_id ?? $product->user_id ?? $product->user?->id;

    $storeStats = [
        'chat_response_rate' => $stats['chat_response_rate'] ?? ($seller->chat_response_rate ?? 98),
        'following'          => $stats['following'] ?? ($seller->following_count ?? 0),
        'followers'          => $stats['followers'] ?? ($seller->followers_count ?? 0),
        'ratings'            => $stats['ratings'] ?? ($seller->rating ?? '0.0'),
        'joined'             => $stats['joined'] ?? ($seller?->created_at ? $seller->created_at->diffForHumans() : ($product->user?->created_at ? $product->user->created_at->diffForHumans() : 'Recently')),
        'products_count'     => $stats['products_count'] ?? ($sellerUserId ? \App\Models\Product::where('user_id', $sellerUserId)->count() : 1),
    ];

    $shopUrl = '#';
    $candidateRoutes = [
        'seller.profile.show',
        'seller-profile.show',
        'buyer.seller-profile.show',
        'buyer.seller.show',
        'storefront.seller.show',
        'seller.storefront',
        'shop.show',
        'store.show',
        'seller.show',
    ];

    foreach ($candidateRoutes as $routeName) {
        if (\Illuminate\Support\Facades\Route::has($routeName)) {
            $routeObj = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
            $paramNames = $routeObj ? $routeObj->parameterNames() : [];
            $firstParam = $paramNames[0] ?? 'seller';
            $paramValue = in_array($firstParam, ['user', 'user_id', 'userId'])
                ? $sellerUserId
                : ($seller?->id ?? $sellerUserId);

            if ($paramValue) {
                $shopUrl = route($routeName, [$firstParam => $paramValue]);
                break;
            }
        }
    }

    if ($shopUrl === '#' && ($seller?->id || $sellerUserId)) {
        $shopUrl = url('/sellers/' . ($seller?->id ?? $sellerUserId));
    }

    $fromCategory = request()->query('from_category');
    $breadcrumbItems = [];

    if (!empty($fromCategory)) {
        $breadcrumbItems[] = [
            'label' => $fromCategory,
            'url'   => url('/category/' . \Illuminate\Support\Str::slug($fromCategory)),
        ];
    }

    $breadcrumbItems[] = [
        'label' => $product->name,
        'url'   => null,
    ];
@endphp

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
        return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
}" class="bg-surface-subtle font-sans antialiased text-text-main min-h-screen pb-16 relative overflow-x-hidden">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8">
        <x-ui.breadcrumbs :items="$breadcrumbItems" class="mb-6" />

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 overflow-hidden p-5 sm:p-8 lg:p-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">

                <div class="lg:col-span-5 flex flex-col">
                    <div class="aspect-square bg-surface rounded-2xl overflow-hidden border border-border-subtle/70 flex items-center justify-center group relative cursor-zoom-in shadow-2xs"
                         x-on:click="if(mainImage) imageModalOpen = true">
                        @if(!empty($product->pictures) && is_array($product->pictures))
                            <img :src="mainImage"
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            <div class="absolute bottom-3 right-3 bg-black/50 backdrop-blur-xs text-white text-xs font-medium px-3 py-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1.5 pointer-events-none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                                </svg>
                                <span>Click to zoom</span>
                            </div>
                        @else
                            <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        @endif
                    </div>

                    @if(!empty($product->pictures) && count($product->pictures) > 1)
                        <div class="flex gap-2.5 overflow-x-auto pt-4 pb-1 [&::-webkit-scrollbar]:hidden">
                            @foreach($product->pictures as $pic)
                                @php $picUrl = asset('storage/' . $pic); @endphp
                                <button type="button"
                                        x-on:click="setMainImage('{{ $picUrl }}')"
                                        class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden border-2 flex-shrink-0 transition-all cursor-pointer focus:outline-none"
                                        :class="mainImage === '{{ $picUrl }}' ? 'border-primary ring-2 ring-primary/20 opacity-100 scale-[1.02]' : 'border-border-subtle opacity-70 hover:opacity-100 hover:border-primary/50'">
                                    <img src="{{ $picUrl }}" alt="Thumbnail" class="w-full h-full object-cover bg-white">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-7 flex flex-col justify-between">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-text-main tracking-tight leading-snug mb-3">
                            {{ $product->name }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-4 pb-5 mb-5 border-b border-border-subtle/70 text-sm">
                            <div class="flex items-center gap-1.5">
                                <div class="flex text-amber-400">
                                    @for($i = 0; $i < 4; $i++)
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endfor
                                    <svg class="w-4 h-4 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </div>
                                <span class="text-text-muted font-semibold hover:text-primary cursor-pointer transition-colors">0 Ratings</span>
                            </div>
                            <span class="text-border-subtle">|</span>
                            <span class="text-text-muted font-semibold">0 Sold</span>
                        </div>

                        <div class="bg-gradient-to-r from-primary/5 via-surface-subtle to-surface rounded-2xl p-5 mb-6 border border-primary/10 flex flex-wrap items-baseline gap-3.5">
                            <span class="text-3xl sm:text-4xl font-extrabold text-primary tracking-tight" x-text="'₱' + formatMoney(discountedPrice)"></span>
                            <template x-if="discount > 0">
                                <span class="text-text-muted line-through text-base sm:text-lg font-medium" x-text="'₱' + formatMoney(activePrice)"></span>
                            </template>
                            <template x-if="discount > 0">
                                <span class="bg-danger/10 text-danger text-xs px-2.5 py-1 rounded-full font-extrabold uppercase tracking-wider" x-text="discount + '% OFF'"></span>
                            </template>
                        </div>

                        @if(is_array($product->variants) && isset($product->variants['items']) && is_array($product->variants['items']))
                            <div class="mb-6 space-y-5">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2.5">
                                        {{ $product->variants['title'] ?? 'Variants' }}
                                    </h3>
                                    <div class="flex flex-wrap gap-2.5">
                                        @foreach($product->variants['items'] as $vIndex => $item)
                                            @php
                                                $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                                $itemStock = $hasSubs ? collect($item['subs'])->sum('stock') : (int) ($item['stock'] ?? 0);
                                            @endphp
                                            <button type="button"
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
                                                :class="selectedMain === {{ $vIndex }}
                                                    ? 'border-primary text-primary bg-primary/10 ring-2 ring-primary/20 font-bold'
                                                    : 'border-border-subtle text-text-main hover:border-primary/50 hover:text-primary bg-surface font-medium'"
                                                class="px-4 py-2.5 border rounded-xl focus:outline-none transition-all text-sm cursor-pointer">
                                                {{ $item['name'] ?? 'Unnamed' }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                @foreach($product->variants['items'] as $vIndex => $item)
                                    @if(isset($item['subs']) && is_array($item['subs']) && count($item['subs']) > 0)
                                        @php
                                            $parentStock = collect($item['subs'])->sum('stock');
                                        @endphp
                                        <div x-show="selectedMain === {{ $vIndex }}" style="display: none;" x-cloak>
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2.5">
                                                {{ $product->variants['sub_title'] ?? 'Sub Variants' }}
                                            </h3>
                                            <div class="flex flex-wrap gap-2.5">
                                                @foreach($item['subs'] as $sIndex => $sub)
                                                    <button type="button"
                                                        x-on:click="
                                                            if (selectedSub === {{ $sIndex }}) {
                                                                selectedSub = null;
                                                                selectedSubName = '';
                                                                activePrice = {{ $item['price'] ?? $product->price }};
                                                                activeStock = {{ $parentStock }};
                                                                @if(isset($item['image']) && $item['image'])
                                                                    mainImage = '{{ asset('storage/' . $item['image']) }}';
                                                                @else
                                                                    mainImage = defaultImage;
                                                                @endif
                                                            } else {
                                                                selectedSub = {{ $sIndex }};
                                                                selectedSubName = '{{ addslashes($sub['name'] ?? '') }}';
                                                                activePrice = {{ $sub['price'] ?? ($item['price'] ?? $product->price) }};
                                                                activeStock = {{ (int) ($sub['stock'] ?? 0) }};
                                                                @if(isset($sub['image']) && $sub['image'])
                                                                    mainImage = '{{ asset('storage/' . $sub['image']) }}';
                                                                @endif
                                                            }
                                                            quantity = 1;
                                                        "
                                                        :class="selectedSub === {{ $sIndex }}
                                                            ? 'border-primary text-primary bg-primary/10 ring-2 ring-primary/20 font-bold'
                                                            : 'border-border-subtle text-text-main hover:border-primary/50 hover:text-primary bg-surface font-medium'"
                                                        class="px-4 py-2.5 border rounded-xl focus:outline-none transition-all text-sm cursor-pointer">
                                                        {{ $sub['name'] ?? 'Unnamed' }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-4 pt-2 mb-8">
                            <span class="text-xs font-bold uppercase tracking-wider text-text-muted w-20">Quantity</span>
                            <div class="flex items-center border border-border-subtle rounded-xl bg-surface h-11 overflow-hidden shadow-2xs">
                                <button type="button"
                                        x-on:click="if(quantity > 1) quantity--"
                                        class="w-10 h-full flex items-center justify-center text-text-main hover:text-primary hover:bg-surface-subtle transition-colors border-r border-border-subtle cursor-pointer focus:outline-none">-</button>
                                <input type="number"
                                       x-model="quantity"
                                       min="1"
                                       :max="activeStock"
                                       class="w-14 h-full text-center bg-transparent border-none focus:ring-0 text-text-main font-bold p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button"
                                        x-on:click="if(quantity < activeStock) quantity++"
                                        class="w-10 h-full flex items-center justify-center text-text-main hover:text-primary hover:bg-surface-subtle transition-colors border-l border-border-subtle cursor-pointer focus:outline-none">+</button>
                            </div>
                            <span class="text-xs sm:text-sm font-semibold px-3 py-1 rounded-full"
                                  :class="activeStock > 5 ? 'bg-surface-subtle text-text-muted' : 'bg-danger/10 text-danger'"
                                  x-text="activeStock + ' pieces available'"></span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3.5 w-full pt-2">
                        @auth
                            <form x-ref="addToCartForm" action="{{ route('cart.store') }}" method="POST" class="w-full sm:w-1/2 flex">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" x-bind:value="quantity">
                                <input type="hidden" name="variant" x-bind:value="selectedMainName">
                                <input type="hidden" name="sub_variant" x-bind:value="selectedSubName">

                                <button type="button"
                                        @click="validateAndSubmit(true)"
                                        class="w-full bg-primary/10 text-primary border border-primary/30 hover:bg-primary hover:text-white font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 flex items-center justify-center gap-2.5 cursor-pointer shadow-2xs">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <span>Add To Cart</span>
                                </button>
                            </form>
                            <button type="button"
                                    @click="validateAndSubmit(true)"
                                    class="w-full sm:w-1/2 bg-primary text-white hover:bg-primary-dark font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 shadow-md shadow-primary/20 hover:shadow-lg cursor-pointer">
                                Buy Now
                            </button>
                        @else
                            <button type="button"
                                    @click="validateAndSubmit(false)"
                                    class="w-full sm:w-1/2 bg-primary/10 text-primary border border-primary/30 hover:bg-primary hover:text-white font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 flex items-center justify-center gap-2.5 cursor-pointer shadow-2xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <span>Add To Cart</span>
                            </button>
                            <button type="button"
                                    @click="validateAndSubmit(false)"
                                    class="w-full sm:w-1/2 bg-primary text-white hover:bg-primary-dark font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 shadow-md shadow-primary/20 hover:shadow-lg cursor-pointer flex items-center justify-center">
                                Buy Now
                            </button>
                        @endauth
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 mt-6 p-6 sm:p-8">
            <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">

                <a href="{{ $shopUrl }}" class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full p-1 bg-gradient-to-br from-primary via-secondary to-primary/40 shadow-md flex-shrink-0 group">
                    <div class="w-full h-full bg-white rounded-full p-0.5 overflow-hidden flex items-center justify-center">
                        @if($seller && $seller->logo_path)
                            <img src="{{ Storage::url($seller->logo_path) }}"
                                 alt="{{ $seller->business_name }}"
                                 class="w-full h-full rounded-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full rounded-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                                <span class="text-2xl sm:text-3xl font-extrabold text-primary">
                                    {{ strtoupper(substr($seller->business_name ?? 'S', 0, 1)) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <span class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full bg-success ring-3 ring-white" title="Verified Active Store"></span>
                </a>

                <div class="flex-1 w-full text-center lg:text-left">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-5 border-b border-border-subtle/70">
                        <div>
                            <a href="{{ $shopUrl }}" class="text-xl sm:text-2xl font-extrabold text-text-main hover:text-primary transition-colors tracking-tight leading-tight inline-block">
                                {{ $seller->business_name ?? 'Official Store' }}
                            </a>
                            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2 mt-1 text-xs sm:text-sm text-text-muted">
                                @if(!empty($seller->line_of_business))
                                    <span class="font-medium">{{ $seller->line_of_business }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 sm:gap-3">
                            @auth
                                <a href="{{ route('user.messages.buyer-messages', ['contact' => $sellerUserId]) }}"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 transition-all duration-300 shadow-2xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Chat Now</span>
                                </a>
                            @else
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 transition-all duration-300 shadow-2xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Chat Now</span>
                                </a>
                            @endauth

                            <a href="{{ $shopUrl }}"
                               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18v4H3V3zm1 6h16v12H4V9zm5 4h6v6H9v-6z"></path>
                                </svg>
                                <span>View Store</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-y-4 gap-x-6 pt-5 text-left">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Chat Response</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ $storeStats['chat_response_rate'] }}%</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Following</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format((int) $storeStats['following']) }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Followers</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format((int) $storeStats['followers']) }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Ratings</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ $storeStats['ratings'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Joined</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $storeStats['joined'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Products</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ number_format((int) $storeStats['products_count']) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 mt-6 p-6 sm:p-8 lg:p-10 space-y-8">
            <div>
                <h2 class="bg-surface-subtle px-5 py-3.5 font-extrabold text-text-main rounded-2xl mb-5 uppercase text-xs tracking-wider border border-border-subtle/60">
                    Product Specifications
                </h2>
                <div class="divide-y divide-border-subtle/60 text-sm px-2">
                    <div class="flex items-center py-3.5">
                        <span class="w-44 text-text-muted font-medium">Weight</span>
                        <span class="font-bold text-text-main">{{ $product->weight ?? 'Not specified' }}</span>
                    </div>
                    <div class="flex items-center py-3.5">
                        <span class="w-44 text-text-muted font-medium">Stock</span>
                        <span class="font-bold text-text-main" x-text="activeStock"></span>
                    </div>
                    <div class="flex items-center py-3.5">
                        <span class="w-44 text-text-muted font-medium">Ships From</span>
                        <span class="font-bold text-text-main">
                            {{ $seller ? trim(($seller->municipality ?? '') . ', ' . ($seller->province ?? ''), ', ') ?: 'Local Warehouse' : 'Local Warehouse' }}
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="bg-surface-subtle px-5 py-3.5 font-extrabold text-text-main rounded-2xl mb-5 uppercase text-xs tracking-wider border border-border-subtle/60">
                    Product Description
                </h2>
                <div class="prose prose-sm max-w-none text-text-main/90 px-2 leading-relaxed">
                    {!! nl2br(e($product->description)) !!}
                    @if(!empty($product->additional_descriptions))
                        <div class="mt-6 pt-6 border-t border-border-subtle/70">
                            {!! nl2br(e($product->additional_descriptions)) !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div x-show="imageModalOpen"
         style="display: none;"
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 sm:p-8"
         x-on:keydown.escape.window="imageModalOpen = false">
        <div x-on:click.away="imageModalOpen = false" class="relative w-full h-full flex items-center justify-center cursor-default">
            <button type="button"
                    x-on:click="imageModalOpen = false"
                    class="absolute top-4 right-4 text-white hover:text-gray-200 z-[101] bg-black/50 hover:bg-black/70 rounded-full p-2.5 cursor-pointer transition-colors focus:outline-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img :src="mainImage" class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl" x-on:click.stop>
        </div>
    </div>

    <div x-show="showToast"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-1rem] scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-[-1rem] scale-95"
         class="fixed top-24 right-6 z-[100] bg-surface border border-border-subtle rounded-2xl shadow-2xl font-sans flex items-start gap-3 min-w-[300px] overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-danger"></div>
        <div class="bg-danger/10 text-danger p-2 rounded-full shrink-0 ml-3 mt-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <div class="py-3 pr-8">
            <h4 class="text-text-main text-sm font-bold mb-0.5">Selection Required</h4>
            <p class="text-text-muted text-xs font-medium leading-relaxed" x-text="toastMessage"></p>
        </div>
        <button type="button"
                @click="showToast = false"
                class="absolute top-3 right-3 text-text-muted hover:text-text-main transition-colors cursor-pointer focus:outline-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

</div>
@endsection