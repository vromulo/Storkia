@extends('layouts.app')

@section('content')
    @php
        $productCardComponent = view()->exists('components.storefront.seller-product-card')
            ? 'storefront.seller-product-card'
            : (view()->exists('components.buyer.seller-product-card')
                ? 'buyer.seller-product-card'
                : 'seller-product-card');

        $productsJson = $products->map(function ($product) use ($seller) {
            $effectivePrice = $product->discount > 0
                ? (float) $product->price - ((float) $product->price * ((float) $product->discount / 100))
                : (float) $product->price;

            return [
                'id'            => $product->id,
                'name'          => strtolower($product->name ?? ''),
                'main_category' => $product->resolved_main_category ?? ($product->category ?? ($seller->line_of_business ?? '')),
                'category'      => $product->resolved_sub_category ?? '',
                'price'         => round($effectivePrice, 2),
                'timestamp'     => $product->created_at ? $product->created_at->timestamp : 0,
            ];
        })->values();

        $storeMainCategories = $storeMainCategories ?? [$seller->line_of_business];
        $storeCategoryTree = $storeCategoryTree ?? [$seller->line_of_business => $subCategories->all()];
        $isOwnStore = $isOwnStore ?? false;
        $isFollowing = $isFollowing ?? false;
        $storeVouchers = $storeVouchers ?? collect();
        $claimedVoucherIds = $claimedVoucherIds ?? [];
    @endphp

    <div class="relative bg-surface-subtle font-sans antialiased text-text-main overflow-x-hidden min-h-screen pb-20"
         x-data="sellerStorefront({
             products: {{ Js::from($productsJson) }},
             categoryTree: {{ Js::from($storeCategoryTree) }},
             allSubCategories: {{ Js::from($subCategories->values()) }},
             initialFollowers: {{ (int) $stats['followers'] }},
             initialFollowingState: {{ $isFollowing ? 'true' : 'false' }},
             followUrl: '{{ route('sellers.follow', $seller->id) }}',
             isAuthenticated: {{ auth()->check() ? 'true' : 'false' }},
             loginUrl: '{{ route('login') }}'
         })"
         x-init="initScrollObserver()">

        <div class="bg-white border-b border-border-subtle/80 shadow-2xs">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-7 sm:py-10 space-y-6">

                @if(session('success'))
                    <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
                        {{ session('success') }}
                    </div>
                @endif

                <template x-if="followBannerMessage">
                    <div class="p-4 rounded-2xl border text-xs sm:text-sm font-bold"
                         :class="followBannerError ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'"
                         x-text="followBannerMessage"></div>
                </template>

                <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">
                    <div class="relative w-28 h-28 sm:w-36 sm:h-36 rounded-full p-1 bg-gradient-to-br from-primary via-secondary to-primary/40 shadow-md flex-shrink-0">
                        <div class="w-full h-full bg-white rounded-full p-1 overflow-hidden flex items-center justify-center">
                            @if($seller->logo_path)
                                <img src="{{ Storage::url($seller->logo_path) }}" alt="{{ $seller->business_name }}" class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                                    <span class="text-4xl sm:text-5xl font-extrabold text-primary">
                                        {{ strtoupper(substr($seller->business_name, 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <span class="absolute bottom-1.5 right-1.5 w-4 h-4 rounded-full bg-success ring-4 ring-white" title="Verified Active Store"></span>
                    </div>

                    <div class="flex-1 w-full text-center lg:text-left">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-5 border-b border-border-subtle/70">
                            <div>
                                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-text-main tracking-tight leading-tight">
                                    {{ $seller->business_name }}
                                </h1>
                                <p class="text-sm sm:text-base font-normal text-text-muted mt-1">
                                    {{ implode(' • ', $storeMainCategories) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2.5 sm:gap-3">
                                @if($isOwnStore)
                                    <span class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface-subtle text-text-muted border border-border-subtle">
                                        Your Store (Cannot Follow Yourself)
                                    </span>
                                @else
                                    <button type="button"
                                            @click="toggleFollow()"
                                            :disabled="isSubmittingFollow"
                                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-300 cursor-pointer shadow-2xs disabled:opacity-50"
                                            :class="isFollowing
                                                ? 'bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40'
                                                : 'bg-primary text-white hover:bg-primary-dark shadow-md shadow-primary/20'">
                                        <span x-text="isFollowing ? '✓ Following' : '+ Follow Store (Get 1-Time ₱30 Voucher)'"></span>
                                    </button>
                                @endif

                                @auth
                                    <a href="{{ route('user.messages.buyer-messages', ['contact' => $seller->user_id]) }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                        <span>Chat Now</span>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                        <span>Chat Now</span>
                                    </a>
                                @endauth
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-y-4 gap-x-6 pt-5 text-left">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Chat Response Rate</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ $stats['chat_response_rate'] }}%</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Following</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format($stats['following']) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Followers</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main" x-text="followersCount.toLocaleString()"></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Ratings ({{ $stats['total_ratings_count'] ?? 0 }})</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ $stats['ratings'] }} / 5.0</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Joined</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $stats['joined'] }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Products</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ number_format($stats['products_count']) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($storeVouchers->isNotEmpty())
                    <div class="pt-4 border-t border-border-subtle/70 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">
                                Exclusive Store Vouchers — Claim Here Only
                            </h3>
                            <span class="text-[11px] text-text-muted">Sponsored by {{ $seller->business_name }}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($storeVouchers as $sv)
                                @php
                                    $claimed = in_array($sv->id, $claimedVoucherIds);
                                @endphp
                                <div class="flex rounded-2xl border border-primary/30 bg-surface shadow-2xs overflow-hidden">
                                    <div class="w-28 bg-gradient-to-br from-primary to-primary-dark text-white p-3 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                        <span class="text-[8px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-black/25 mb-1">STORE VOUCHER</span>
                                        <div class="text-sm font-serif font-extrabold">{{ $sv->badge_label }}</div>
                                        <span class="text-[10px] text-white/90 mt-0.5">Min. ₱{{ number_format((float) $sv->min_spend, 0) }}</span>
                                    </div>
                                    <div class="flex-1 p-3 flex flex-col justify-between text-xs min-w-0">
                                        <div>
                                            <span class="font-mono font-extrabold text-primary-dark">{{ $sv->code }}</span>
                                            <p class="font-bold text-text-main truncate mt-0.5">{{ $sv->title }}</p>
                                            <p class="text-[10px] text-text-muted">Expires {{ $sv->ends_at?->format('M d, Y') }}</p>
                                        </div>
                                        <div class="pt-2 flex justify-end">
                                            @auth
                                                @if($claimed)
                                                    <span class="px-3 py-1 rounded-lg bg-surface-subtle text-text-muted font-bold text-[10px]">Claimed in Wallet</span>
                                                @else
                                                    <form action="{{ route('user.coupons.claim') }}" method="POST" class="m-0">
                                                        @csrf
                                                        <input type="hidden" name="code" value="{{ $sv->code }}">
                                                        <input type="hidden" name="seller_profile_id" value="{{ $seller->id }}">
                                                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-[11px] cursor-pointer">
                                                            Claim Voucher
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <a href="{{ route('login') }}" class="px-3.5 py-1.5 rounded-xl bg-primary text-white font-bold text-[11px]">Claim Voucher</a>
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Storefront Filter Console (Main Category -> Dynamic Sub Category + Custom Downward Dropdowns) -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-5 sm:p-7 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-end">

                    <!-- Search Store -->
                    <div class="xl:col-span-3">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Search Store</label>
                        <input type="text"
                               x-model.debounce.150ms="searchQuery"
                               @input="visibleLimit = batchSize"
                               placeholder="Search product of this store"
                               class="w-full bg-surface hover:bg-surface-subtle focus:bg-white text-text-main text-sm font-medium rounded-2xl px-4 py-3 border border-border-subtle focus:border-primary focus:outline-none">
                    </div>

                    <!-- Main Category Custom Downward Dropdown -->
                    <div class="xl:col-span-3 relative" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Main Category</label>
                        <button type="button"
                                @click="open = !open"
                                class="w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl px-4 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between border"
                                :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <span class="truncate" x-text="activeMainCategory || 'All Main Categories'"></span>
                            <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="absolute left-0 right-0 top-full mt-2 z-50 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-64 overflow-y-auto">
                            <button type="button"
                                    @click="selectMainCategory(''); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="activeMainCategory === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                All Main Categories
                            </button>
                            @foreach($storeMainCategories as $mCat)
                                <button type="button"
                                        @click="selectMainCategory('{{ addslashes($mCat) }}'); open = false"
                                        class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                        :class="activeMainCategory === '{{ addslashes($mCat) }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                    {{ $mCat }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Dynamic Sub Category Custom Downward Dropdown -->
                    <div class="xl:col-span-3 relative" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Sub Category</label>
                        <button type="button"
                                @click="open = !open"
                                class="w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl px-4 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between border"
                                :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <span class="truncate" x-text="activeCategory || 'All Sub Categories'"></span>
                            <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="absolute left-0 right-0 top-full mt-2 z-50 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-64 overflow-y-auto">
                            <button type="button"
                                    @click="activeCategory = ''; visibleLimit = batchSize; open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="activeCategory === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                All Sub Categories
                            </button>
                            <template x-for="subCat in availableSubCategories" :key="subCat">
                                <button type="button"
                                        @click="activeCategory = subCat; visibleLimit = batchSize; open = false"
                                        class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                        :class="activeCategory === subCat ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'"
                                        x-text="subCat">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Sort Products Custom Downward Dropdown -->
                    <div class="xl:col-span-3 relative" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Sort Products</label>
                        <button type="button"
                                @click="open = !open"
                                class="w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl px-4 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between border"
                                :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <span class="truncate" x-text="sortLabel"></span>
                            <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="absolute left-0 right-0 top-full mt-2 z-50 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5">
                            <template x-for="opt in [
                                { key: 'newest', label: 'Newest Added' },
                                { key: 'oldest', label: 'Oldest Added' },
                                { key: 'price_asc', label: 'Price: Low to High' },
                                { key: 'price_desc', label: 'Price: High to Low' },
                                { key: 'az', label: 'Name: A to Z' },
                                { key: 'za', label: 'Name: Z to A' }
                            ]" :key="opt.key">
                                <button type="button"
                                        @click="sortBy = opt.key; visibleLimit = batchSize; open = false"
                                        class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                        :class="sortBy === opt.key ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'"
                                        x-text="opt.label">
                                </button>
                            </template>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Products Grid (Per-Batch Scroll Loading) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6 min-h-[360px]">
                @foreach($products as $product)
                    <div x-show="isVisibleInBatch({{ $product->id }})"
                         :style="{ order: getOrder({{ $product->id }}) }"
                         class="bg-white rounded-2xl p-3 border border-border-subtle/60 hover:border-primary/30 hover:shadow-md transition-all duration-300 flex flex-col">
                        <x-dynamic-component :component="$productCardComponent" :product="$product" />
                    </div>
                @endforeach

                <div x-show="filteredAndSortedIds.length === 0" x-cloak class="col-span-full flex flex-col items-center justify-center py-20 px-4 text-center bg-white rounded-3xl border border-border-subtle shadow-xs">
                    <h3 class="text-lg font-extrabold text-text-main mb-1">No Products Found</h3>
                    <p class="text-text-muted text-xs sm:text-sm max-w-sm mx-auto mb-5">
                        No products in this store match your selected category or search filter.
                    </p>
                    <button type="button" @click="resetFilters()" class="px-6 py-2.5 bg-primary text-white rounded-full font-bold text-xs cursor-pointer">
                        Reset Filters
                    </button>
                </div>
            </div>

            <!-- Infinite Scroll Sentinel for Per-Batch Loading -->
            <div x-ref="scrollSentinel"
                 x-show="visibleLimit < filteredAndSortedIds.length"
                 class="py-8 flex flex-col items-center justify-center gap-2 text-xs text-text-muted font-bold">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
                    <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
                </div>
                <span x-text="'Showing ' + Math.min(visibleLimit, filteredAndSortedIds.length) + ' of ' + filteredAndSortedIds.length + ' products — Scroll down for next batch'"></span>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sellerStorefront', ({ products, categoryTree, allSubCategories, initialFollowers, initialFollowingState, followUrl, isAuthenticated, loginUrl }) => ({
                products: products || [],
                categoryTree: categoryTree || {},
                allSubCategories: allSubCategories || [],
                searchQuery: '',
                activeMainCategory: '',
                activeCategory: '',
                sortBy: 'newest',
                batchSize: 12,
                visibleLimit: 12,
                isFollowing: initialFollowingState || false,
                followersCount: initialFollowers || 0,
                isSubmittingFollow: false,
                followBannerMessage: '',
                followBannerError: false,

                initScrollObserver() {
                    this.$nextTick(() => {
                        if (!this.$refs.scrollSentinel || typeof IntersectionObserver === 'undefined') return;
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting && this.visibleLimit < this.filteredAndSortedIds.length) {
                                    setTimeout(() => {
                                        this.visibleLimit += this.batchSize;
                                    }, 180);
                                }
                            });
                        }, { rootMargin: '160px' });
                        observer.observe(this.$refs.scrollSentinel);
                    });
                },

                selectMainCategory(mainCat) {
                    this.activeMainCategory = mainCat;
                    this.activeCategory = '';
                    this.visibleLimit = this.batchSize;
                },

                get availableSubCategories() {
                    if (!this.activeMainCategory) {
                        return this.allSubCategories;
                    }
                    return this.categoryTree[this.activeMainCategory] || [];
                },

                get sortLabel() {
                    const labels = {
                        newest: 'Newest Added',
                        oldest: 'Oldest Added',
                        price_asc: 'Price: Low to High',
                        price_desc: 'Price: High to Low',
                        az: 'Name: A to Z',
                        za: 'Name: Z to A',
                    };
                    return labels[this.sortBy] || 'Newest Added';
                },

                async toggleFollow() {
                    if (!isAuthenticated) {
                        window.location.href = loginUrl;
                        return;
                    }
                    this.isSubmittingFollow = true;
                    this.followBannerMessage = '';
                    this.followBannerError = false;
                    try {
                        const res = await fetch(followUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await res.json();
                        if (!res.ok || data.success === false) {
                            this.followBannerError = true;
                            this.followBannerMessage = data.message || 'Unable to follow store.';
                        } else {
                            this.isFollowing = data.is_following;
                            this.followersCount = data.followers_count;
                            this.followBannerMessage = data.message || '';
                        }
                    } catch (err) {
                        this.followBannerError = true;
                        this.followBannerMessage = 'Network error while updating follow status.';
                    } finally {
                        this.isSubmittingFollow = false;
                    }
                },

                get filteredAndSortedIds() {
                    const query = this.searchQuery.trim().toLowerCase();
                    const mainCat = this.activeMainCategory;
                    const subCat = this.activeCategory;

                    let filtered = this.products.filter(item => {
                        const matchSearch = !query || item.name.includes(query);
                        const matchMain = !mainCat || item.main_category === mainCat;
                        const matchSub = !subCat || item.category === subCat;
                        return matchSearch && matchMain && matchSub;
                    });

                    filtered.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'price_asc': return a.price - b.price;
                            case 'price_desc': return b.price - a.price;
                            case 'oldest': return a.timestamp - b.timestamp;
                            case 'az': return a.name.localeCompare(b.name);
                            case 'za': return b.name.localeCompare(a.name);
                            case 'newest':
                            default: return b.timestamp - a.timestamp;
                        }
                    });

                    return filtered.map(item => item.id);
                },

                isVisibleInBatch(id) {
                    const idx = this.filteredAndSortedIds.indexOf(id);
                    return idx !== -1 && idx < this.visibleLimit;
                },

                getOrder(id) {
                    const idx = this.filteredAndSortedIds.indexOf(id);
                    return idx === -1 ? 9999 : idx;
                },

                resetFilters() {
                    this.searchQuery = '';
                    this.activeMainCategory = '';
                    this.activeCategory = '';
                    this.sortBy = 'newest';
                    this.visibleLimit = this.batchSize;
                }
            }));
        });
    </script>
@endsection