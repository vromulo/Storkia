@extends('layouts.app', ['title' => 'Official Seller Stores - Storkia'])

@section('content')
@php
    $sortLabels = [
        'popular'  => 'Most Popular',
        'rating'   => 'Highest Rated',
        'products' => 'Most Products',
        'newest'   => 'Newest Stores',
    ];
@endphp

<div x-data="{
        catDropdownOpen: false,
        sortDropdownOpen: false,
        selectedCategory: @js((string) ($category ?? '')),
        selectedSort: @js((string) ($sort ?? 'popular')),
        sortLabels: @js($sortLabels),
        visibleStoresLimit: 9,
        submitDirectoryFilter() {
            this.$nextTick(() => {
                if (this.$refs.directoryFilterForm) {
                    this.$refs.directoryFilterForm.submit();
                }
            });
        }
     }"
     class="bg-surface font-sans antialiased text-text-main min-h-screen pb-16 select-none">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <x-ui.breadcrumbs :items="['Seller Stores']" />

        <div class="rounded-3xl border border-border-subtle bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-8 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-1.5 max-w-2xl">
                <span class="px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                    Verified Storkia Merchants
                </span>
                <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                    Explore Official Seller Stores
                </h1>
                <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                    Browse verified local merchants, claim store vouchers, and follow your favorite stores to unlock an instant <strong>1-Time &#8369;30 OFF Follower Welcome Voucher</strong>!
                </p>
            </div>

            <form x-ref="directoryFilterForm" method="GET" action="{{ route('sellers.index') }}" class="flex flex-wrap items-center gap-2 text-xs w-full lg:w-auto">
                <input type="hidden" name="category" :value="selectedCategory">
                <input type="hidden" name="sort" :value="selectedSort">

                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search store name, city, province..."
                       class="flex-1 sm:w-60 px-4 py-3 rounded-2xl bg-surface text-text-main font-medium outline-none shadow-inner">

                <div class="relative" @click.away="catDropdownOpen = false">
                    <button type="button"
                            @click="catDropdownOpen = !catDropdownOpen; sortDropdownOpen = false"
                            class="px-3.5 py-3 rounded-2xl bg-surface text-text-main font-bold flex items-center gap-2 cursor-pointer shadow-xs">
                        <span x-text="selectedCategory || 'All Categories'"></span>
                        <svg class="w-3.5 h-3.5 text-primary transition-transform" :class="catDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="catDropdownOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-52 bg-surface border border-border-subtle rounded-2xl shadow-2xl z-40 py-1.5 max-h-60 overflow-y-auto custom-scrollbar text-text-main">
                        <button type="button"
                                @click="selectedCategory = ''; catDropdownOpen = false; submitDirectoryFilter()"
                                class="w-full px-4 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                :class="selectedCategory === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                            <span>All Categories</span>
                            <span x-show="selectedCategory === ''" class="text-primary font-extrabold">&#10003;</span>
                        </button>
                        @foreach($categories as $cat)
                            <button type="button"
                                    @click="selectedCategory = '{{ addslashes($cat) }}'; catDropdownOpen = false; submitDirectoryFilter()"
                                    class="w-full px-4 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                    :class="selectedCategory === '{{ addslashes($cat) }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                                <span class="truncate pr-2">{{ $cat }}</span>
                                <span x-show="selectedCategory === '{{ addslashes($cat) }}'" class="text-primary font-extrabold">&#10003;</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="relative" @click.away="sortDropdownOpen = false">
                    <button type="button"
                            @click="sortDropdownOpen = !sortDropdownOpen; catDropdownOpen = false"
                            class="px-3.5 py-3 rounded-2xl bg-surface text-primary-dark font-bold flex items-center gap-2 cursor-pointer shadow-xs">
                        <span x-text="sortLabels[selectedSort] || 'Most Popular'"></span>
                        <svg class="w-3.5 h-3.5 text-primary transition-transform" :class="sortDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="sortDropdownOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-48 bg-surface border border-border-subtle rounded-2xl shadow-2xl z-40 py-1.5 overflow-hidden text-text-main">
                        @foreach($sortLabels as $sVal => $sLabel)
                            <button type="button"
                                    @click="selectedSort = '{{ $sVal }}'; sortDropdownOpen = false; submitDirectoryFilter()"
                                    class="w-full px-4 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                    :class="selectedSort === '{{ $sVal }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                                <span>{{ $sLabel }}</span>
                                <span x-show="selectedSort === '{{ $sVal }}'" class="text-primary font-extrabold">&#10003;</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="px-5 py-3 rounded-2xl bg-primary-dark hover:bg-text-main text-white font-bold transition-colors cursor-pointer">
                    Filter
                </button>
            </form>
        </div>

        @if($sellers->isEmpty())
            <div class="bg-surface-subtle/50 rounded-3xl border border-border-subtle p-16 text-center space-y-3">
                <h3 class="text-base font-bold text-primary-dark">No verified seller stores matched your search</h3>
                <p class="text-xs text-text-muted">Try clearing your search keyword or category filter.</p>
                <a href="{{ route('sellers.index') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition-colors cursor-pointer">
                    Reset Filters
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($sellers as $sIdx => $store)
                    @php
                        $metrics = \App\Http\Controllers\Buyer\SellerController::buildStoreMetrics($store);
                        $isFollowed = in_array((int) $store->id, $followedStoreIds ?? [], true);
                        $previewProducts = ($store->user?->products ?? collect())->take(3);
                    @endphp
                    <div x-show="{{ $sIdx }} < visibleStoresLimit"
                         x-transition
                         class="bg-surface rounded-3xl border border-border-subtle shadow-xs hover:border-primary/50 hover:shadow-md transition-all p-5 sm:p-6 flex flex-col justify-between gap-5">
                        <div class="space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <a href="{{ route('sellers.show', $store->id) }}"
                                       class="w-16 h-16 rounded-full overflow-hidden border-2 border-primary/20 bg-brand-light/30 flex items-center justify-center shrink-0 cursor-pointer">
                                        @if($store->logo_path)
                                            <img src="{{ Storage::url($store->logo_path) }}" alt="{{ $store->business_name }}" class="w-full h-full object-cover">
                                        @else
                                            <span class="text-xl font-serif font-extrabold text-primary-dark">{{ strtoupper(substr($store->business_name, 0, 1)) }}</span>
                                        @endif
                                    </a>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ route('sellers.show', $store->id) }}" class="text-base font-bold text-primary-dark hover:text-primary truncate cursor-pointer">
                                                {{ $store->business_name }}
                                            </a>
                                            @if($store->is_on_vacation)
                                                <span class="px-2 py-0.5 rounded-full bg-brand-light/70 text-primary-dark border border-primary/25 text-[10px] font-bold shrink-0">On Vacation</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-text-muted truncate">{{ $store->line_of_business }} &bull; {{ $store->municipality }}, {{ $store->province }}</p>
                                        <div class="flex flex-wrap items-center gap-3 mt-1.5 text-[11px] text-text-muted">
                                            <span>Rating: <strong class="text-primary">{{ $metrics['ratings'] }} &#9733;</strong></span>
                                            <span>Followers: <strong class="text-text-main">{{ number_format($metrics['followers']) }}</strong></span>
                                            <span>Products: <strong class="text-text-main">{{ $store->approved_products_count }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($previewProducts->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2.5 pt-2 border-t border-border-subtle/70">
                                    @foreach($previewProducts as $prod)
                                        @php
                                            $pic = (!empty($prod->pictures) && is_array($prod->pictures))
                                                ? asset('storage/' . $prod->pictures[0])
                                                : null;
                                        @endphp
                                        <a href="{{ route('product.show', $prod->id) }}"
                                           class="group rounded-2xl border border-border-subtle bg-surface-subtle/50 p-2 flex flex-col items-center text-center hover:border-primary/40 transition-all cursor-pointer">
                                            <div class="w-full aspect-square rounded-xl bg-surface overflow-hidden flex items-center justify-center p-1 mb-1.5">
                                                @if($pic)
                                                    <img src="{{ $pic }}" alt="{{ $prod->name }}" class="w-full h-full object-contain group-hover:scale-105 transition-transform">
                                                @endif
                                            </div>
                                            <span class="text-[11px] font-bold text-price">&#8369;{{ number_format((float) $prod->price, 0) }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle text-center text-xs text-text-muted">
                                    Catalog items coming soon
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-3 border-t border-border-subtle">
                            <a href="{{ route('sellers.show', $store->id) }}"
                               class="flex-1 py-2.5 px-4 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold text-center transition-colors cursor-pointer">
                                Visit Storefront
                            </a>
                            @auth
                                <form action="{{ route('sellers.follow', $store->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit"
                                            class="py-2.5 px-4 rounded-xl border text-xs font-bold transition-colors cursor-pointer {{ $isFollowed ? 'border-border-subtle bg-surface-subtle text-text-main hover:border-danger hover:text-danger' : 'border-primary text-primary hover:bg-primary/10' }}">
                                        {{ $isFollowed ? 'Following' : '+ Follow Store' }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}"
                                   class="py-2.5 px-4 rounded-xl border border-primary text-primary hover:bg-primary/10 text-xs font-bold transition-colors cursor-pointer">
                                    + Follow Store
                                </a>
                            @endauth
                        </div>
                    </div>
                @endforeach
            </div>

            @if($sellers->count() > 9)
                <div class="pt-2 flex justify-center">
                    <button type="button"
                            @click="visibleStoresLimit = visibleStoresLimit >= {{ $sellers->count() }} ? 9 : visibleStoresLimit + 9"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleStoresLimit >= {{ $sellers->count() }} ? 'See Less Stores' : 'See More Stores (' + ({{ $sellers->count() }} - visibleStoresLimit) + ' remaining on this page)'"></span>
                    </button>
                </div>
            @endif

            @if($sellers->hasPages())
                <div class="pt-4">
                    {{ $sellers->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection