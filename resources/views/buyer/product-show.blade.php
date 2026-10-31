@extends('layouts.app')

@section('content')
@php
    $totalStock = 0;
    if (isset($product->variants['items']) && is_array($product->variants['items'])) {
        foreach ($product->variants['items'] as $item) {
            if (!empty($item['is_archived']) || ($item['status'] ?? 'Approved') === 'Disapproved') {
                continue;
            }
            if (isset($item['subs']) && count($item['subs']) > 0) {
                foreach ($item['subs'] as $sub) {
                    if (!empty($sub['is_archived']) || ($sub['status'] ?? 'Approved') === 'Disapproved') {
                        continue;
                    }
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
    $isStoreOnVacation = (bool) ($seller?->is_on_vacation ?? false);
    $perBuyerLimit = (int) ($product->per_buyer_limit ?? 0);

    $storeStats = [
        'chat_response_rate' => $stats['chat_response_rate'] ?? 98,
        'following'          => $stats['following'] ?? 0,
        'followers'          => $stats['followers'] ?? 0,
        'ratings'            => $stats['ratings'] ?? '0.0',
        'joined'             => $stats['joined'] ?? ($seller?->created_at ? $seller->created_at->diffForHumans() : 'Recently'),
        'products_count'     => $stats['products_count'] ?? 1,
    ];

    $productReviews = $productReviews ?? collect();
    $productAvgRating = $productAvgRating ?? 0.0;
    $totalSoldUnits = $totalSoldUnits ?? 0;
    $isOwnStore = $isOwnStore ?? false;
    $isFollowingStore = $isFollowingStore ?? false;
    $isWishlisted = $isWishlisted ?? false;
    $storeVouchers = $storeVouchers ?? collect();
    $claimedVoucherIds = $claimedVoucherIds ?? [];
    $recommendedProducts = $recommendedProducts ?? collect();

    $productCardComponent = view()->exists('components.storefront.seller-product-card')
        ? 'storefront.seller-product-card'
        : (view()->exists('components.buyer.seller-product-card')
            ? 'buyer.seller-product-card'
            : 'seller-product-card');

    $shopUrl = $seller ? route('sellers.show', $seller->id) : '#';

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
    isStoreOnVacation: {{ $isStoreOnVacation ? 'true' : 'false' }},
    perBuyerLimit: {{ $perBuyerLimit }},
    isWishlisted: {{ $isWishlisted ? 'true' : 'false' }},
    isTogglingWishlist: false,
    isAddingToCart: false,
    toastMessage: '',
    toastType: 'danger',
    showToast: false,
    basePrice: {{ (float) ($product->price ?? 0) }},
    discount: {{ (float) ($product->discount ?? 0) }},
    totalStock: {{ (int) $totalStock }},
    activePrice: {{ (float) ($product->price ?? 0) }},
    activeStock: {{ (int) $totalStock }},
    mainImage: '{{ $firstPic }}',
    defaultImage: '{{ $firstPic }}',
    quantity: 1,
    imageModalOpen: false,

    get maxAllowedQuantity() {
        if (this.perBuyerLimit > 0) {
            return Math.min(this.activeStock, this.perBuyerLimit);
        }
        return this.activeStock;
    },
    get discountedPrice() {
        return this.discount > 0 ? this.activePrice - (this.activePrice * (this.discount / 100)) : this.activePrice;
    },
    formatMoney(value) {
        return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    setMainImage(img) {
        this.mainImage = img;
    },
    triggerToast(msg, type = 'danger') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => this.showToast = false, 4000);
    },
    async toggleWishlist(isAuth) {
        if (!isAuth) {
            window.location.href = '{{ route('login') }}';
            return;
        }
        if (this.isTogglingWishlist) return;
        this.isTogglingWishlist = true;
        try {
            const res = await fetch('/user/wishlist/toggle/{{ $product->id }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                this.isWishlisted = data.wishlisted;
                this.triggerToast(data.message, 'success');
            }
        } catch (e) {
            this.triggerToast('Unable to update wishlist right now.');
        } finally {
            this.isTogglingWishlist = false;
        }
    },
    validateAndSubmit(isAuth, isBuyNow = false) {
        if (this.isStoreOnVacation) {
            this.triggerToast('This store is currently on vacation and is temporarily not accepting orders.');
            return;
        }
        if (this.activeStock <= 0) {
            this.triggerToast('This product or selected variation is currently out of stock.');
            return;
        }
        if (this.hasVariants && !this.selectedMainName) {
            this.triggerToast('Please select a {{ addslashes($product->variants['title'] ?? 'variation') }} first.');
            return;
        }
        if (this.hasSubVariants && !this.selectedSubName) {
            this.triggerToast('Please select a {{ addslashes($product->variants['sub_title'] ?? 'sub-variation') }} too.');
            return;
        }
        const parsedQty = Number(this.quantity);
        if (!Number.isInteger(parsedQty) || parsedQty < 1) {
            this.triggerToast('Please enter a valid whole number quantity of at least 1.');
            return;
        }
        if (this.perBuyerLimit > 0 && parsedQty > this.perBuyerLimit) {
            this.triggerToast('Purchase limit reached: You can buy at most ' + this.perBuyerLimit + ' unit(s) of this item.');
            return;
        }
        if (parsedQty > this.activeStock) {
            this.triggerToast('Only ' + this.activeStock + ' piece(s) available in stock.');
            return;
        }
        if (!isAuth) {
            window.location.href = '{{ route('login') }}';
            return;
        }
        if (isBuyNow) {
            this.$refs.buyNowForm.submit();
        } else {
            this.addToCart();
        }
    },
    async addToCart() {
        if (this.isAddingToCart) return;
        this.isAddingToCart = true;
        const form = this.$refs.addToCartForm;
        const button = this.$refs.addToCartBtn;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.querySelector('input[name=_token]').value
                },
                body: new FormData(form),
                credentials: 'same-origin'
            });

            // Session expired -> Laravel redirects to the login page
            if (response.redirected && /\/login/.test(response.url)) {
                window.location.href = '{{ route('login') }}';
                return;
            }

            const isJson = (response.headers.get('content-type') || '').includes('application/json');
            const data = isJson ? await response.json().catch(() => ({})) : {};

            if (!response.ok || data.success === false) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                this.triggerToast(firstError || data.message || 'Unable to add this item to your cart right now.');
                return;
            }

            const count = Number.isFinite(Number(data.cart_count))
                ? Number(data.cart_count)
                : window.getCartBadgeCount() + 1;

            await window.flyToCart({ source: button, imageUrl: this.mainImage });
            window.setCartBadge(count);
        } catch (e) {
            this.triggerToast('Unable to add this item to your cart right now.');
        } finally {
            this.isAddingToCart = false;
        }
    }
}" class="bg-surface-subtle font-sans antialiased text-text-main min-h-screen pb-16 relative overflow-x-hidden">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 space-y-6">
        <x-ui.breadcrumbs :items="$breadcrumbItems" />

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
                @foreach($errors->all() as $err)
                    <p class="font-semibold">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        @if($isStoreOnVacation)
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 flex items-center justify-between gap-3 text-xs sm:text-sm font-bold">
                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-1 rounded-full bg-amber-200 text-amber-950 text-[10px] font-extrabold uppercase">On Vacation</span>
                    <span>{{ $seller->business_name ?? 'This store' }} is currently on vacation. Products remain viewable, but Add to Cart and Buy Now are temporarily paused.</span>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 overflow-hidden p-5 sm:p-8 lg:p-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">

                <div class="lg:col-span-5 flex flex-col">
                    <div class="aspect-square bg-surface rounded-2xl overflow-hidden border border-border-subtle/70 flex items-center justify-center group relative cursor-zoom-in shadow-2xs"
                         x-on:click="if(mainImage) imageModalOpen = true">
                        @if(!empty($product->pictures) && is_array($product->pictures))
                            <img :src="mainImage"
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        @else
                            <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        @endif

                        @if($totalStock <= 0)
                            <span class="absolute top-3 left-3 px-3 py-1 rounded-full bg-black/75 text-white text-xs font-extrabold uppercase">
                                Sold Out
                            </span>
                        @elseif($isStoreOnVacation)
                            <span class="absolute top-3 left-3 px-3 py-1 rounded-full bg-amber-500 text-white text-xs font-extrabold uppercase">
                                On Vacation
                            </span>
                        @endif

                        <button type="button"
                                @click.stop="toggleWishlist({{ auth()->check() ? 'true' : 'false' }})"
                                :disabled="isTogglingWishlist"
                                title="Save to Wishlist"
                                class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white/90 hover:bg-white shadow-md flex items-center justify-center transition-transform active:scale-95 cursor-pointer">
                            <svg class="w-5 h-5 transition-colors"
                                 :class="isWishlisted ? 'text-danger fill-danger' : 'text-gray-400 hover:text-danger'"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </button>
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
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-text-main tracking-tight leading-snug">
                                {{ $product->name }}
                            </h1>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 pb-5 mb-5 border-b border-border-subtle/70 text-sm">
                            <a href="#product-reviews-section" class="flex items-center gap-1.5">
                                <span class="font-extrabold text-amber-500">{{ number_format((float) $productAvgRating, 1) }}</span>
                                <div class="flex text-amber-400">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $i <= round($productAvgRating) ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-text-muted font-semibold hover:text-primary transition-colors">
                                    {{ number_format($productReviews->count()) }} {{ Str::plural('Rating', $productReviews->count()) }}
                                </span>
                            </a>
                            <span class="text-border-subtle">|</span>
                            <span class="text-text-muted font-semibold">{{ number_format($totalSoldUnits) }} Sold</span>
                            @if($perBuyerLimit > 0)
                                <span class="text-border-subtle">|</span>
                                <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-xs font-bold">
                                    Max {{ $perBuyerLimit }} per buyer
                                </span>
                            @endif
                        </div>

                        <div class="bg-gradient-to-r from-primary/5 via-surface-subtle to-surface rounded-2xl p-5 mb-5 border border-primary/10 flex flex-wrap items-baseline gap-3.5">
                            <span class="text-3xl sm:text-4xl font-extrabold text-primary tracking-tight" x-text="'₱' + formatMoney(discountedPrice)"></span>
                            <template x-if="discount > 0">
                                <span class="text-text-muted line-through text-base sm:text-lg font-medium" x-text="'₱' + formatMoney(activePrice)"></span>
                            </template>
                            <template x-if="discount > 0">
                                <span class="bg-danger/10 text-danger text-xs px-2.5 py-1 rounded-full font-extrabold uppercase tracking-wider" x-text="discount + '% OFF'"></span>
                            </template>
                        </div>

                        @if($storeVouchers->isNotEmpty())
                            <div class="mb-6 p-4 rounded-2xl bg-brand-light/25 border border-border-subtle space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">
                                        Store Vouchers ({{ $seller->business_name ?? 'Store' }})
                                    </span>
                                    <span class="text-[11px] text-text-muted">Claimable only on this store</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($storeVouchers as $sv)
                                        @php
                                            $alreadyClaimed = in_array($sv->id, $claimedVoucherIds);
                                        @endphp
                                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white border border-primary/30 shadow-2xs text-xs">
                                            <div>
                                                <span class="font-mono font-extrabold text-primary-dark">{{ $sv->code }}</span>
                                                <span class="font-bold text-primary ml-1">{{ $sv->badge_label }}</span>
                                                <div class="text-[10px] text-text-muted">
                                                    Min. ₱{{ number_format((float) $sv->min_spend, 0) }} &bull; Exp {{ $sv->ends_at?->format('M d, Y') }}
                                                </div>
                                            </div>
                                            @auth
                                                @if($alreadyClaimed)
                                                    <span class="px-2.5 py-1 rounded-lg bg-surface-subtle text-text-muted font-bold text-[10px]">Claimed</span>
                                                @else
                                                    <form action="{{ route('user.coupons.claim') }}" method="POST" class="m-0">
                                                        @csrf
                                                        <input type="hidden" name="code" value="{{ $sv->code }}">
                                                        <input type="hidden" name="seller_profile_id" value="{{ $seller->id }}">
                                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-[10px] cursor-pointer">
                                                            Claim
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <a href="{{ route('login') }}" class="px-2.5 py-1 rounded-lg bg-primary text-white font-bold text-[10px]">Claim</a>
                                            @endauth
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(is_array($product->variants) && isset($product->variants['items']) && is_array($product->variants['items']))
                            <div class="mb-6 space-y-5">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2.5">
                                        {{ $product->variants['title'] ?? 'Variants' }}
                                    </h3>
                                    <div class="flex flex-wrap gap-2.5">
                                        @foreach($product->variants['items'] as $vIndex => $item)
                                            @php
                                                if (!empty($item['is_archived']) || ($item['status'] ?? 'Approved') === 'Disapproved') {
                                                    continue;
                                                }
                                                $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                                $itemStock = $hasSubs
                                                    ? collect($item['subs'])->reject(fn($s) => !empty($s['is_archived']) || ($s['status'] ?? 'Approved') === 'Disapproved')->sum('stock')
                                                    : (int) ($item['stock'] ?? 0);
                                            @endphp
                                            <button type="button"
                                                @if($itemStock <= 0 && !$hasSubs) disabled @endif
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
                                                        activePrice = {{ (float) ($item['price'] ?? $product->price) }};
                                                        activeStock = {{ (int) $itemStock }};
                                                        @if(isset($item['image']) && $item['image'])
                                                            mainImage = '{{ asset('storage/' . $item['image']) }}';
                                                        @endif
                                                    }
                                                    quantity = 1;
                                                "
                                                :class="selectedMain === {{ $vIndex }}
                                                    ? 'border-primary text-primary bg-primary/10 ring-2 ring-primary/20 font-bold'
                                                    : '{{ $itemStock <= 0 && !$hasSubs ? 'border-border-subtle text-gray-300 bg-gray-50 line-through cursor-not-allowed' : 'border-border-subtle text-text-main hover:border-primary/50 hover:text-primary bg-surface font-medium cursor-pointer' }}'"
                                                class="px-4 py-2.5 border rounded-xl focus:outline-none transition-all text-sm">
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
                                                    @php
                                                        if (!empty($sub['is_archived']) || ($sub['status'] ?? 'Approved') === 'Disapproved') {
                                                            continue;
                                                        }
                                                        $subStock = (int) ($sub['stock'] ?? 0);
                                                    @endphp
                                                    <button type="button"
                                                        @if($subStock <= 0) disabled @endif
                                                        x-on:click="
                                                            if ({{ $subStock }} <= 0) return;
                                                            if (selectedSub === {{ $sIndex }}) {
                                                                selectedSub = null;
                                                                selectedSubName = '';
                                                                activePrice = {{ (float) ($item['price'] ?? $product->price) }};
                                                                activeStock = {{ (int) $parentStock }};
                                                            } else {
                                                                selectedSub = {{ $sIndex }};
                                                                selectedSubName = '{{ addslashes($sub['name'] ?? '') }}';
                                                                activePrice = {{ (float) ($sub['price'] ?? ($item['price'] ?? $product->price)) }};
                                                                activeStock = {{ $subStock }};
                                                            }
                                                            quantity = 1;
                                                        "
                                                        :class="selectedSub === {{ $sIndex }}
                                                            ? 'border-primary text-primary bg-primary/10 ring-2 ring-primary/20 font-bold'
                                                            : '{{ $subStock <= 0 ? 'border-border-subtle text-gray-300 bg-gray-50 line-through cursor-not-allowed' : 'border-border-subtle text-text-main hover:border-primary/50 hover:text-primary bg-surface font-medium cursor-pointer' }}'"
                                                        class="px-4 py-2.5 border rounded-xl focus:outline-none transition-all text-sm">
                                                        {{ $sub['name'] ?? 'Unnamed' }}
                                                        @if($subStock <= 0)
                                                            <span class="text-[10px] ml-1">(Out)</span>
                                                        @endif
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
                                       x-model.number="quantity"
                                       min="1"
                                       :max="maxAllowedQuantity"
                                       class="w-14 h-full text-center bg-transparent border-none focus:ring-0 text-text-main font-bold p-0">
                                <button type="button"
                                        x-on:click="if(quantity < maxAllowedQuantity) quantity++"
                                        class="w-10 h-full flex items-center justify-center text-text-main hover:text-primary hover:bg-surface-subtle transition-colors border-l border-border-subtle cursor-pointer focus:outline-none">+</button>
                            </div>
                            <span class="text-xs sm:text-sm font-semibold px-3 py-1 rounded-full"
                                  :class="activeStock > 5 ? 'bg-surface-subtle text-text-muted' : 'bg-danger/10 text-danger'"
                                  x-text="activeStock > 0 ? (activeStock + ' pieces available') : 'Sold out'"></span>
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
                                        x-ref="addToCartBtn"
                                        :disabled="isStoreOnVacation || activeStock <= 0 || isAddingToCart"
                                        @click="validateAndSubmit(true, false)"
                                        :class="(isStoreOnVacation || activeStock <= 0) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-primary/10 text-primary border border-primary/30 hover:bg-primary hover:text-white cursor-pointer'"
                                        class="w-full font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 flex items-center justify-center gap-2.5 shadow-2xs">
                                    <span x-text="isStoreOnVacation ? 'Store On Vacation' : (activeStock <= 0 ? 'Sold Out' : (isAddingToCart ? 'Adding...' : 'Add To Cart'))"></span>
                                </button>
                            </form>

                            <form x-ref="buyNowForm" action="{{ route('checkout.buy-now') }}" method="POST" class="w-full sm:w-1/2 flex">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" x-bind:value="quantity">
                                <input type="hidden" name="variant" x-bind:value="selectedMainName">
                                <input type="hidden" name="sub_variant" x-bind:value="selectedSubName">

                                <button type="button"
                                        :disabled="isStoreOnVacation || activeStock <= 0"
                                        @click="validateAndSubmit(true, true)"
                                        :class="(isStoreOnVacation || activeStock <= 0) ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-primary text-white hover:bg-primary-dark shadow-md shadow-primary/20 cursor-pointer'"
                                        class="w-full font-bold py-3.5 px-6 rounded-2xl transition-all duration-300">
                                    <span x-text="isStoreOnVacation ? 'On Vacation' : 'Buy Now'"></span>
                                </button>
                            </form>
                        @else
                            <button type="button"
                                    :disabled="isStoreOnVacation || activeStock <= 0"
                                    @click="validateAndSubmit(false, false)"
                                    class="w-full sm:w-1/2 bg-primary/10 text-primary border border-primary/30 hover:bg-primary hover:text-white font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 flex items-center justify-center gap-2.5 cursor-pointer shadow-2xs disabled:opacity-50">
                                <span>Add To Cart</span>
                            </button>
                            <button type="button"
                                    :disabled="isStoreOnVacation || activeStock <= 0"
                                    @click="validateAndSubmit(false, true)"
                                    class="w-full sm:w-1/2 bg-primary text-white hover:bg-primary-dark font-bold py-3.5 px-6 rounded-2xl transition-all duration-300 shadow-md shadow-primary/20 cursor-pointer flex items-center justify-center disabled:opacity-50">
                                <span>Buy Now</span>
                            </button>
                        @endauth
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-6 sm:p-8">
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
                    <span class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full {{ $isStoreOnVacation ? 'bg-amber-400' : 'bg-success' }} ring-3 ring-white"
                          title="{{ $isStoreOnVacation ? 'Store on Vacation' : 'Verified Active Store' }}"></span>
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

                        <div class="flex flex-wrap items-center justify-center gap-2.5 sm:gap-3">
                            @if($seller)
                                @auth
                                    @if($isOwnStore)
                                        <span class="px-4 py-2.5 rounded-2xl text-xs font-bold bg-surface-subtle text-text-muted border border-border-subtle">
                                            Your Store
                                        </span>
                                    @else
                                        <form action="{{ route('sellers.follow', $seller->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all cursor-pointer shadow-2xs {{ $isFollowingStore ? 'bg-surface-subtle text-text-main border border-border-subtle' : 'bg-primary text-white hover:bg-primary-dark' }}">
                                                <span>{{ $isFollowingStore ? '✓ Following' : '+ Follow Store (Get ₱30 Voucher)' }}</span>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Direct Product-Context Chat Draft Link -->
                                    <a href="{{ route('user.messages.buyer-messages', ['contact' => $sellerUserId, 'product_id' => $product->id]) }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 transition-all duration-300 shadow-2xs">
                                        <span>Chat Now (Ask About Product)</span>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-primary text-white hover:bg-primary-dark transition-all">
                                        + Follow Store
                                    </a>
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-primary/10 text-primary border border-primary/30">
                                        Chat Now
                                    </a>
                                @endauth
                            @endif

                            <a href="{{ $shopUrl }}"
                               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                <span>View Store</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-y-4 gap-x-6 pt-5 text-left">
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Chat Response</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ $storeStats['chat_response_rate'] }}%</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Following</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format((int) $storeStats['following']) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Followers</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format((int) $storeStats['followers']) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Store Avg Rating</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ $storeStats['ratings'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Joined</p>
                                <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $storeStats['joined'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] text-text-muted font-medium truncate">Products</p>
                                <p class="text-xs sm:text-sm font-bold text-primary">{{ number_format((int) $storeStats['products_count']) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-6 sm:p-8 lg:p-10 space-y-8">
            <div>
                <h2 class="bg-surface-subtle px-5 py-3.5 font-extrabold text-text-main rounded-2xl mb-5 uppercase text-xs tracking-wider border border-border-subtle/60">
                    Product Specifications
                </h2>
                <div class="divide-y divide-border-subtle/60 text-sm px-2">
                    @if($product->specifications && $product->specifications->count() > 0)
                        @foreach($product->specifications as $spec)
                            <div class="flex items-center py-3.5">
                                <span class="w-44 text-text-muted font-medium">{{ $spec->title }}</span>
                                <span class="font-bold text-text-main">{{ $spec->description }}</span>
                            </div>
                        @endforeach
                    @endif
                    <div class="flex items-center py-3.5">
                        <span class="w-44 text-text-muted font-medium">Weight</span>
                        <span class="font-bold text-text-main">{{ $product->weight ?? 'Not specified' }} kg</span>
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

            <div id="product-reviews-section">
                <h2 class="bg-surface-subtle px-5 py-3.5 font-extrabold text-text-main rounded-2xl mb-5 uppercase text-xs tracking-wider border border-border-subtle/60 flex items-center justify-between">
                    <span>Verified Buyer Product Ratings ({{ $productReviews->count() }})</span>
                    <span class="text-primary font-extrabold">{{ number_format((float) $productAvgRating, 1) }} / 5.0 ★</span>
                </h2>

                @if($productReviews->isEmpty())
                    <p class="text-xs text-text-muted px-2 py-4">No buyer ratings submitted for this product yet. Complete an order to leave the first review!</p>
                @else
                    <div class="divide-y divide-border-subtle/60 px-2">
                        @foreach($productReviews as $rev)
                            <div class="py-4 space-y-2.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <div class="font-bold text-text-main">
                                        {{ $rev->buyer?->first_name ? substr($rev->buyer->first_name, 0, 1) . '***' : 'Verified Buyer' }}
                                        <span class="text-amber-500 ml-2">{{ str_repeat('★', (int) $rev->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $rev->rating)) }}</span>
                                        @if($rev->edited_at)
                                            <span class="text-[10px] text-text-muted font-normal ml-1">(Edited)</span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-text-muted">{{ $rev->created_at?->format('M d, Y') }}</span>
                                </div>
                                @if($rev->review_text)
                                    <p class="text-text-main leading-relaxed">{{ $rev->review_text }}</p>
                                @endif
                                @php
                                    $photosList = is_array($rev->review_photos) && !empty($rev->review_photos)
                                        ? $rev->review_photos
                                        : ($rev->review_photo_path ? [$rev->review_photo_path] : []);
                                @endphp
                                @if(!empty($photosList))
                                    <div class="flex flex-wrap gap-2 pt-1">
                                        @foreach($photosList as $rPhoto)
                                            <img src="{{ asset('storage/' . $rPhoto) }}" alt="Review Photo" class="w-18 h-18 object-cover rounded-xl border border-border-subtle">
                                        @endforeach
                                    </div>
                                @endif
                                @if($rev->seller_reply)
                                    <div class="mt-2 p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                                        <span class="text-[10px] font-extrabold uppercase text-primary-dark block">
                                            Seller's Response ({{ $seller->business_name ?? 'Official Store' }})
                                        </span>
                                        <p class="text-text-main leading-relaxed">{{ $rev->seller_reply }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if($recommendedProducts->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-6 sm:p-8 space-y-5">
                <div class="flex items-center justify-between border-b border-border-subtle/70 pb-3">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">Tailored For You</span>
                        <h2 class="text-lg sm:text-xl font-bold font-serif text-text-main uppercase">Recommended Products You May Also Like</h2>
                    </div>
                    <a href="{{ route('home') }}" class="text-xs font-bold text-primary hover:underline">Explore All &rarr;</a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                    @foreach($recommendedProducts as $recProd)
                        <div class="bg-surface rounded-2xl p-2.5 border border-border-subtle/60 hover:border-primary/40 transition-all">
                            <x-dynamic-component :component="$productCardComponent" :product="$recProd" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div x-show="imageModalOpen"
         style="display: none;"
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 sm:p-8"
         x-on:keydown.escape.window="imageModalOpen = false">
        <div x-on:click.away="imageModalOpen = false" class="relative w-full h-full flex items-center justify-center cursor-default">
            <img :src="mainImage" class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl" x-on:click.stop>
        </div>
    </div>

    <div x-show="showToast"
         x-cloak
         class="fixed top-24 right-6 z-[100] bg-surface border border-border-subtle rounded-2xl shadow-2xl font-sans flex items-start gap-3 min-w-[300px] overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1.5" :class="toastType === 'success' ? 'bg-green-600' : 'bg-danger'"></div>
        <div class="py-3 pl-5 pr-8">
            <h4 class="text-text-main text-sm font-bold mb-0.5" x-text="toastType === 'success' ? 'Success' : 'Notice'"></h4>
            <p class="text-text-muted text-xs font-medium leading-relaxed" x-text="toastMessage"></p>
        </div>
    </div>

</div>
@endsection