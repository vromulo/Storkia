@extends('layouts.app', ['title' => $seller->business_name . ' - Official Store'])

@section('content')
@php
    $storeVouchers = $storeVouchers ?? collect();
    $storeBundles = $storeBundles ?? collect();
    $storeReviews = $storeReviews ?? collect();
    $claimedVoucherIds = $claimedVoucherIds ?? [];
    $isOnVacation = (bool) ($seller->is_on_vacation ?? false);

    $sortOptions = [
        'popular'    => [
            'label' => 'Sort: Popular',
            'url'   => route('sellers.show', array_filter(['seller' => $seller->id, 'tab' => 'all', 'category' => $selectedCategory, 'search' => $searchQuery, 'sort' => 'popular'])),
        ],
        'latest'     => [
            'label' => 'Sort: Latest',
            'url'   => route('sellers.show', array_filter(['seller' => $seller->id, 'tab' => 'all', 'category' => $selectedCategory, 'search' => $searchQuery, 'sort' => 'latest'])),
        ],
        'price_asc'  => [
            'label' => 'Price: Low to High',
            'url'   => route('sellers.show', array_filter(['seller' => $seller->id, 'tab' => 'all', 'category' => $selectedCategory, 'search' => $searchQuery, 'sort' => 'price_asc'])),
        ],
        'price_desc' => [
            'label' => 'Price: High to Low',
            'url'   => route('sellers.show', array_filter(['seller' => $seller->id, 'tab' => 'all', 'category' => $selectedCategory, 'search' => $searchQuery, 'sort' => 'price_desc'])),
        ],
    ];
    $currentSortLabel = $sortOptions[$sort]['label'] ?? 'Sort: Popular';
@endphp

<div x-data="{
        sortDropdownOpen: false,
        visibleVouchersLimit: 4,
        visibleBundlesLimit: 4,
        visibleReviewsLimit: 5
     }"
     class="bg-surface font-sans antialiased text-text-main min-h-screen pb-16 select-none">

    @if($isOnVacation)
        <div class="bg-brand-light/40 border-b border-primary/25 py-3 px-4 text-center text-xs sm:text-sm font-bold text-primary-dark">
            &#9728; {{ $seller->business_name }} is currently on Vacation Mode. You can browse products and follow the store, but new checkouts are temporarily paused until the merchant returns.
        </div>
    @endif

    <div class="bg-surface-subtle/60 border-b border-border-subtle">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <x-ui.breadcrumbs :items="[
                ['label' => 'Seller Stores', 'url' => route('sellers.index')],
                $seller->business_name
            ]" class="mb-5" />

            <div class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">

                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-5">
                        <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden border-2 border-primary/20 bg-brand-light/40 flex items-center justify-center shrink-0 shadow-xs">
                            @if($seller->logo_path)
                                <img src="{{ Storage::url($seller->logo_path) }}" alt="{{ $seller->business_name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl sm:text-3xl font-serif font-extrabold text-primary-dark">
                                    {{ strtoupper(substr($seller->business_name, 0, 1)) }}
                                </span>
                            @endif
                            <span class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full {{ $isOnVacation ? 'bg-primary-dark' : 'bg-success' }} ring-2 ring-surface" title="{{ $isOnVacation ? 'On Vacation' : 'Active Store' }}"></span>
                        </div>

                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark tracking-tight">
                                    {{ $seller->business_name }}
                                </h1>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-primary/10 text-primary border border-primary/20">
                                    Verified Store
                                </span>
                            </div>

                            <p class="text-xs text-text-muted flex flex-wrap items-center gap-2">
                                <span>Category: <strong class="text-text-main">{{ $seller->line_of_business }}</strong></span>
                                <span>&bull;</span>
                                <span>Location: <strong class="text-text-main">{{ $seller->municipality }}, {{ $seller->province }}</strong></span>
                            </p>

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                @auth
                                    <form action="{{ route('sellers.follow', $seller->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs {{ $isFollowing ? 'bg-surface-subtle text-text-main border border-border-subtle hover:border-danger hover:text-danger' : 'bg-primary text-white hover:bg-primary-dark' }}">
                                            <span>{{ $isFollowing ? 'Following Store' : '+ Follow Store (Get &#8369;30 Voucher)' }}</span>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-primary text-white hover:bg-primary-dark transition-all cursor-pointer">
                                        + Follow Store (Get &#8369;30 Voucher)
                                    </a>
                                @endauth

                                <a href="{{ auth()->check() ? route('user.messages', ['seller_id' => $seller->id]) : route('login') }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-surface text-text-main border border-border-subtle hover:border-primary hover:text-primary transition-colors cursor-pointer">
                                    Chat Now
                                </a>

                                @if(!auth()->check() || (int) auth()->id() !== (int) $seller->user_id)
                                    <button type="button"
                                            @auth
                                                @click.stop="$dispatch('open-report-seller', { sellerId: {{ $seller->id }}, sellerName: @js($seller->business_name), orderId: null })"
                                            @else
                                                onclick="window.location.href='{{ route('login') }}'"
                                            @endauth
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-text-muted hover:text-danger transition-colors cursor-pointer"
                                            title="Report this seller">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                            <line x1="4" y1="22" x2="4" y2="15"></line>
                                        </svg>
                                        <span>Report</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 pt-4 lg:pt-0 border-t lg:border-t-0 lg:border-l border-border-subtle lg:pl-8 text-xs">
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <span class="text-text-muted">Products:</span>
                            <span class="font-bold text-primary">{{ $metrics['products_count'] }}</span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <span class="text-text-muted">Followers:</span>
                            <span class="font-bold text-primary">{{ number_format($metrics['followers']) }}</span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <span class="text-text-muted">Rating:</span>
                            <span class="font-bold text-primary">{{ $metrics['ratings'] }} ({{ number_format($metrics['ratings_count']) }})</span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <span class="text-text-muted">Chat Response:</span>
                            <span class="font-bold text-primary">{{ $metrics['chat_response_rate'] }}% ({{ $metrics['chat_response_time'] }})</span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <span class="text-text-muted">Joined:</span>
                            <span class="font-bold text-primary">{{ $metrics['joined'] }}</span>
                        </div>
                    </div>

                </div>
            </div>

            @if($storeVouchers->isNotEmpty())
                <div class="mt-5 bg-surface rounded-2xl border border-border-subtle p-4 shadow-2xs">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">
                            Claimable Store Vouchers ({{ $storeVouchers->count() }})
                        </span>
                        <div class="flex items-center gap-3">
                            @if($storeVouchers->count() > 4)
                                <button type="button"
                                        @click="visibleVouchersLimit = visibleVouchersLimit >= {{ $storeVouchers->count() }} ? 4 : {{ $storeVouchers->count() }}"
                                        class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                        x-text="visibleVouchersLimit >= {{ $storeVouchers->count() }} ? 'See Less Vouchers' : 'See All {{ $storeVouchers->count() }} Vouchers'"></button>
                            @endif
                            <a href="{{ route('user.coupons') }}" class="text-xs font-bold text-primary hover:underline cursor-pointer">Open My Voucher Wallet &rarr;</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach($storeVouchers as $vIdx => $v)
                            @php
                                $alreadyClaimed = in_array((int) $v->id, $claimedVoucherIds, true);
                            @endphp
                            <div x-show="{{ $vIdx }} < visibleVouchersLimit"
                                 x-transition
                                 class="flex rounded-xl border border-primary/25 bg-brand-light/15 overflow-hidden text-xs">
                                <div class="w-24 bg-gradient-to-br from-primary to-primary-dark text-white p-3 flex flex-col items-center justify-center text-center shrink-0 border-r border-dashed border-white/40">
                                    <span class="font-serif font-extrabold text-sm leading-tight">{{ $v->badge_label }}</span>
                                    <span class="text-[10px] text-white/85 mt-0.5">Min &#8369;{{ number_format((float) $v->min_spend, 0) }}</span>
                                </div>
                                <div class="flex-1 p-3 flex flex-col justify-between min-w-0">
                                    <div>
                                        <span class="font-mono font-bold text-[11px] text-primary-dark">{{ $v->code }}</span>
                                        <p class="font-semibold text-text-main truncate">{{ $v->title }}</p>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between">
                                        <span class="text-[10px] text-text-muted">Til {{ $v->ends_at?->format('M d') }}</span>
                                        @auth
                                            @if($alreadyClaimed)
                                                <span class="px-2.5 py-1 rounded-lg bg-success/15 text-success font-bold text-[10px]">Claimed</span>
                                            @else
                                                <form action="{{ route('user.coupons.claim') }}" method="POST" class="m-0">
                                                    @csrf
                                                    <input type="hidden" name="code" value="{{ $v->code }}">
                                                    <button type="submit" class="px-3 py-1 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-[10px] cursor-pointer">
                                                        Claim
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <a href="{{ route('login') }}" class="px-3 py-1 rounded-lg bg-primary text-white font-bold text-[10px] cursor-pointer">Claim</a>
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($storeBundles->isNotEmpty())
                <div class="mt-4 bg-surface rounded-2xl border border-border-subtle p-4 shadow-2xs">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">
                            Active Store Bundle Promotions ({{ $storeBundles->count() }})
                        </span>
                        <div class="flex items-center gap-3">
                            @if($storeBundles->count() > 4)
                                <button type="button"
                                        @click="visibleBundlesLimit = visibleBundlesLimit >= {{ $storeBundles->count() }} ? 4 : {{ $storeBundles->count() }}"
                                        class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                        x-text="visibleBundlesLimit >= {{ $storeBundles->count() }} ? 'See Less Bundles' : 'See All {{ $storeBundles->count() }} Bundles'"></button>
                            @endif
                            <a href="{{ route('bundles.index') }}" class="text-xs font-bold text-primary hover:underline cursor-pointer">Build Bundle &amp; Select Variants &rarr;</a>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2.5 text-xs">
                        @foreach($storeBundles as $bIdx => $b)
                            <a x-show="{{ $bIdx }} < visibleBundlesLimit"
                               href="{{ route('bundles.index') }}"
                               class="px-3.5 py-2 rounded-xl bg-brand-light/35 hover:bg-brand-light/60 border border-primary/25 text-primary-dark flex items-center gap-2 transition-colors cursor-pointer">
                                <span class="px-2 py-0.5 rounded bg-primary text-white font-extrabold text-[10px] uppercase">
                                    {{ $b->bundle_type === 'percentage_off' ? 'Buy ' . $b->required_quantity . ' Get ' . number_format((float) $b->discount_percentage, 0) . '% Off' : 'Buy ' . $b->required_quantity . ' Get Free Gift' }}
                                </span>
                                <span class="font-bold">{{ $b->title }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mt-6 pt-2">
                <nav class="flex items-center gap-6 overflow-x-auto no-scrollbar border-b md:border-b-0 border-border-subtle">
                    <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'home']) }}"
                       class="pb-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors cursor-pointer {{ $tab === 'home' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                        Store Home
                    </a>
                    <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'all']) }}"
                       class="pb-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors cursor-pointer {{ $tab === 'all' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                        All Products ({{ $metrics['products_count'] }})
                    </a>
                    <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'reviews']) }}"
                       class="pb-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors cursor-pointer {{ $tab === 'reviews' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                        Buyer Reviews ({{ $storeReviews->count() }})
                    </a>
                    @foreach($categories->take(4) as $catName => $catCount)
                        <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'all', 'category' => $catName]) }}"
                           class="pb-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors cursor-pointer {{ ($tab === 'all' && $selectedCategory === $catName) ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                            {{ $catName }} ({{ $catCount }})
                        </a>
                    @endforeach
                </nav>

                <form action="{{ route('sellers.show', $seller->id) }}" method="GET" class="w-full md:w-72 shrink-0">
                    <input type="hidden" name="tab" value="all">
                    @if($selectedCategory)
                        <input type="hidden" name="category" value="{{ $selectedCategory }}">
                    @endif
                    <div class="relative flex items-center">
                        <input type="text"
                               name="search"
                               value="{{ $searchQuery }}"
                               placeholder="Search in {{ $seller->business_name }}..."
                               class="w-full pl-4 pr-10 py-2 rounded-full bg-surface border border-border-subtle focus:border-primary text-xs text-text-main outline-none transition-colors">
                        <button type="submit" class="absolute right-1.5 p-1.5 rounded-full bg-primary text-white hover:bg-primary-dark transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-10">

        @if($tab === 'home')
            @if($featuredProducts->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg sm:text-xl font-serif font-bold text-primary-dark">Store Featured Highlights</h2>
                            <p class="text-xs text-text-muted">Top picks from {{ $seller->business_name }}</p>
                        </div>
                        <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'all']) }}"
                           class="text-xs font-bold text-primary hover:text-primary-dark flex items-center gap-1 cursor-pointer">
                            <span>View All</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-5">
                        @foreach($featuredProducts as $product)
                            <div class="bg-surface rounded-2xl p-3 border border-border-subtle/60 hover:border-primary/40 transition-all">
                                <x-storefront.seller-product-card :product="$product" :is-wishlisted="in_array($product->id, $wishlistedProductIds)" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        @if($tab === 'reviews')
            <section class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                    <div>
                        <h2 class="text-lg font-serif font-bold text-primary-dark">Verified Buyer Store &amp; Product Reviews</h2>
                        <p class="text-xs text-text-muted">Authentic feedback from completed orders at {{ $seller->business_name }}.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-brand-light/60 text-primary-dark border border-border-subtle font-extrabold text-xs">
                        {{ $metrics['ratings'] }} / 5.0 &#9733;
                    </span>
                </div>

                @if($storeReviews->isEmpty())
                    <div class="py-12 text-center text-xs text-text-muted">No product reviews published for this store yet.</div>
                @else
                    <div class="divide-y divide-border-subtle">
                        @foreach($storeReviews as $rIdx => $rev)
                            @php
                                $revText = (string) ($rev->review_text ?? '');
                                $isLongRev = mb_strlen($revText) > 200 || substr_count($revText, "\n") >= 3;
                            @endphp
                            <div x-show="{{ $rIdx }} < visibleReviewsLimit"
                                 x-data="{ exp: false }"
                                 x-transition
                                 class="py-4 space-y-2 text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-text-main">{{ $rev->buyer?->first_name ?? 'Verified Buyer' }}</span>
                                        <span class="text-primary font-bold">{!! str_repeat('&#9733;', (int) $rev->rating) !!}</span>
                                        @if($rev->purchased_variant_label)
                                            <span class="px-2 py-0.5 rounded-full bg-brand-light/50 text-primary-dark font-bold text-[10px]">
                                                Variant: {{ $rev->purchased_variant_label }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-text-muted">{{ $rev->created_at?->format('M d, Y') }}</span>
                                </div>
                                @if($rev->product)
                                    <a href="{{ route('product.show', $rev->product_id) }}" class="text-primary font-bold hover:underline inline-block cursor-pointer">
                                        Product: {{ $rev->product->name }}
                                    </a>
                                @endif
                                @if($revText !== '')
                                    <div>
                                        <p :class="(!exp && {{ $isLongRev ? 'true' : 'false' }}) ? 'line-clamp-3' : ''" class="text-text-main leading-relaxed whitespace-pre-line select-text">{{ $revText }}</p>
                                        @if($isLongRev)
                                            <button type="button" @click="exp = !exp" class="mt-1 text-[11px] font-extrabold text-primary hover:underline cursor-pointer" x-text="exp ? 'See less' : 'See more'"></button>
                                        @endif
                                    </div>
                                @endif
                                @if($rev->seller_reply)
                                    <div class="p-3 rounded-xl bg-brand-light/30 border border-border-subtle">
                                        <strong class="text-primary-dark block text-[11px]">Seller Response:</strong>
                                        <p class="text-text-main mt-0.5 select-text">{{ $rev->seller_reply }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($storeReviews->count() > 5)
                        <div class="pt-3 flex justify-center">
                            <button type="button"
                                    @click="visibleReviewsLimit = visibleReviewsLimit >= {{ $storeReviews->count() }} ? 5 : visibleReviewsLimit + 5"
                                    class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                                <span x-text="visibleReviewsLimit >= {{ $storeReviews->count() }} ? 'See Less Reviews' : 'See More Reviews (' + ({{ $storeReviews->count() }} - visibleReviewsLimit) + ' remaining)'"></span>
                            </button>
                        </div>
                    @endif
                @endif
            </section>
        @else
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <aside class="lg:col-span-3 xl:col-span-2 bg-surface rounded-2xl border border-border-subtle p-4 space-y-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-dark pb-2 border-b border-border-subtle">
                        Store Categories
                    </h3>
                    <ul class="space-y-1 text-xs font-semibold">
                        <li>
                            <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'all', 'sort' => $sort]) }}"
                               class="flex items-center justify-between px-3 py-2 rounded-xl transition-colors cursor-pointer {{ !$selectedCategory ? 'bg-primary text-white font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                                <span>All Products</span>
                                <span class="text-[11px] opacity-80">{{ $metrics['products_count'] }}</span>
                            </a>
                        </li>
                        @foreach($categories as $catName => $catCount)
                            <li>
                                <a href="{{ route('sellers.show', ['seller' => $seller->id, 'tab' => 'all', 'category' => $catName, 'sort' => $sort]) }}"
                                   class="flex items-center justify-between px-3 py-2 rounded-xl transition-colors cursor-pointer {{ $selectedCategory === $catName ? 'bg-primary text-white font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                                    <span class="truncate pr-2">{{ $catName }}</span>
                                    <span class="text-[11px] opacity-80">{{ $catCount }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </aside>

                <div class="lg:col-span-9 xl:col-span-10 space-y-5">
                    <div class="bg-surface-subtle/70 rounded-2xl border border-border-subtle px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-text-muted font-semibold mr-1">Sort by:</span>
                            @php
                                $baseFilterParams = array_filter([
                                    'seller' => $seller->id,
                                    'tab' => 'all',
                                    'category' => $selectedCategory,
                                    'search' => $searchQuery,
                                ]);
                            @endphp
                            <a href="{{ route('sellers.show', array_merge($baseFilterParams, ['sort' => 'popular'])) }}"
                               class="px-3.5 py-1.5 rounded-xl font-bold transition-colors cursor-pointer {{ $sort === 'popular' ? 'bg-primary text-white' : 'bg-surface text-text-main border border-border-subtle hover:border-primary' }}">
                                Popular
                            </a>
                            <a href="{{ route('sellers.show', array_merge($baseFilterParams, ['sort' => 'latest'])) }}"
                               class="px-3.5 py-1.5 rounded-xl font-bold transition-colors cursor-pointer {{ $sort === 'latest' ? 'bg-primary text-white' : 'bg-surface text-text-main border border-border-subtle hover:border-primary' }}">
                                Latest
                            </a>

                            <div class="relative" @click.away="sortDropdownOpen = false">
                                <button type="button"
                                        @click="sortDropdownOpen = !sortDropdownOpen"
                                        class="px-3.5 py-1.5 rounded-xl bg-surface border border-border-subtle hover:border-primary text-xs font-bold text-text-main flex items-center gap-2 transition-colors cursor-pointer">
                                    <span>{{ $currentSortLabel }}</span>
                                    <svg class="w-3.5 h-3.5 text-primary transition-transform" :class="sortDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="sortDropdownOpen" x-cloak x-transition class="absolute left-0 mt-1.5 w-48 bg-surface border border-border-subtle rounded-2xl shadow-xl z-30 py-1.5 overflow-hidden">
                                    @foreach($sortOptions as $sKey => $sMeta)
                                        <a href="{{ $sMeta['url'] }}"
                                           class="w-full px-4 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer {{ $sort === $sKey ? 'text-primary font-bold bg-primary/5' : 'text-text-main' }}">
                                            <span>{{ $sMeta['label'] }}</span>
                                            @if($sort === $sKey)
                                                <span class="text-primary font-extrabold">&#10003;</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="text-xs text-text-muted font-medium">
                            Showing <strong class="text-text-main">{{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}</strong> of <strong class="text-text-main">{{ $products->total() }}</strong> items
                        </div>
                    </div>

                    @if($products->isEmpty())
                        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
                            <p class="text-base font-bold text-primary-dark">No products matched your filter</p>
                            <a href="{{ route('sellers.show', $seller->id) }}"
                               class="inline-flex items-center px-5 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors cursor-pointer">
                                Reset Store Filters
                            </a>
                        </div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-5">
                            @foreach($products as $product)
                                <div class="bg-surface rounded-2xl p-3 border border-border-subtle/60 hover:border-primary/40 transition-all">
                                    <x-storefront.seller-product-card :product="$product" :is-wishlisted="in_array($product->id, $wishlistedProductIds)" />
                                </div>
                            @endforeach
                        </div>

                        @if($products->hasPages())
                            <div class="pt-6">
                                {{ $products->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </section>
        @endif

    </div>

    <x-storefront.product-modal />
    <x-buyer.report-seller-modal />
</div>
@endsection