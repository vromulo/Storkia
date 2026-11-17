@extends('layouts.app', ['title' => 'Super Deals & Flash Promotions - Storkia'])

@section('content')
@php
    $claimableVouchers = $claimableVouchers ?? collect();
    $claimedVoucherIds = $claimedVoucherIds ?? [];
    $bundles = $bundles ?? collect();
    $initialLeft = (int) ($slotSecondsLeft ?? 3600);
@endphp

<div x-data="{
        secondsLeft: {{ $initialLeft }},
        formattedTimer: '00:00:00',
        showAllVouchers: false,
        visibleDealsLimit: 12,
        startCountdown() {
            this.updateDisplay();
            setInterval(() => {
                if (this.secondsLeft > 0) {
                    this.secondsLeft--;
                } else {
                    this.secondsLeft = 7200;
                }
                this.updateDisplay();
            }, 1000);
        },
        updateDisplay() {
            const hrs = String(Math.floor(this.secondsLeft / 3600)).padStart(2, '0');
            const mins = String(Math.floor((this.secondsLeft % 3600) / 60)).padStart(2, '0');
            const secs = String(this.secondsLeft % 60).padStart(2, '0');
            this.formattedTimer = `${hrs}:${mins}:${secs}`;
        }
     }"
     x-init="startCountdown()"
     class="bg-surface font-sans antialiased text-text-main min-h-screen pb-16 select-none">

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
        <x-ui.breadcrumbs :items="['Super Deals']" />

        <header class="relative overflow-hidden rounded-3xl border border-brand-light bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-10 shadow-sm">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-xs text-xs font-extrabold uppercase tracking-wider">
                        <span>&#9889; Fixed-Slot Super Deals &amp; Store Promotions</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-serif font-bold tracking-tight">
                        Limited-Quantity Super Deals
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        Grab time-slot Super Deals before the slot stock runs out, claim stackable Store &amp; Free Shipping Vouchers, and unlock Multi-Item Bundle Discounts.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 shrink-0 bg-black/25 backdrop-blur-md p-4 sm:p-5 rounded-2xl border border-white/20">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-white/80 block">Current Slot Ends In</span>
                        <div class="font-mono font-extrabold text-2xl sm:text-3xl tracking-wider text-brand-light mt-0.5" x-text="formattedTimer">01:45:12</div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <a href="{{ route('bundles.index') }}"
                           class="px-4 py-2 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-extrabold transition-colors shadow-xs text-center cursor-pointer">
                            Explore Bundle Deals ({{ $bundles->count() }}) &rarr;
                        </a>
                        <a href="{{ route('user.coupons') }}"
                           class="px-4 py-1.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-[11px] font-bold transition-colors text-center cursor-pointer">
                            Open My Voucher Wallet
                        </a>
                    </div>
                </div>
            </div>
        </header>

        @if($claimableVouchers->isNotEmpty())
            <section class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">Stackable Checkout Savings</span>
                        <h2 class="text-lg font-serif font-bold text-primary-dark">Claim Store, Platform &amp; Free Shipping Vouchers</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($claimableVouchers->count() > 4)
                            <button type="button"
                                    @click="showAllVouchers = !showAllVouchers"
                                    class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                    x-text="showAllVouchers ? 'See Less Vouchers' : 'See All ({{ $claimableVouchers->count() }})'"></button>
                        @endif
                        <a href="{{ route('user.coupons') }}" class="text-xs font-bold text-primary hover:underline cursor-pointer">View All in Voucher Center &rarr;</a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    @foreach($claimableVouchers as $vIdx => $v)
                        @php
                            $isClaimed = in_array((int) $v->id, $claimedVoucherIds, true);
                            $stubBg = match($v->scope) {
                                'shipping' => 'from-primary-dark to-primary',
                                'platform' => 'from-primary via-primary-dark to-secondary',
                                default    => 'from-primary to-primary-dark',
                            };
                        @endphp
                        <div x-show="{{ $vIdx }} < 4 || showAllVouchers"
                             x-transition
                             class="flex rounded-2xl border border-border-subtle bg-surface overflow-hidden shadow-2xs">
                            <div class="w-24 bg-gradient-to-br {{ $stubBg }} text-white p-3 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                <span class="text-[8px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-black/25 mb-1">{{ strtoupper($v->scope) }}</span>
                                <span class="font-serif font-extrabold text-sm leading-tight">{{ $v->badge_label }}</span>
                                <span class="text-[9px] text-white/90 mt-0.5">Min &#8369;{{ number_format((float) $v->min_spend, 0) }}</span>
                            </div>
                            <div class="flex-1 p-3 flex flex-col justify-between text-xs min-w-0">
                                <div>
                                    <span class="font-mono font-extrabold text-[11px] text-primary-dark">{{ $v->code }}</span>
                                    <p class="font-bold text-text-main truncate mt-0.5">{{ $v->title }}</p>
                                    <p class="text-[10px] text-text-muted truncate">
                                        {{ $v->sellerProfile->business_name ?? 'Storkia Platform-Wide' }}
                                    </p>
                                </div>
                                <div class="pt-2 flex items-center justify-between">
                                    <span class="text-[10px] text-text-muted">{{ $v->total_usage_limit - $v->used_count }} left</span>
                                    @auth
                                        @if($isClaimed)
                                            <span class="px-2.5 py-1 rounded-lg bg-success/15 text-success font-bold text-[10px]">In Wallet</span>
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
            </section>
        @endif

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ route('deals.index', ['sort' => $sortBy]) }}"
                   class="px-4 py-2 rounded-full text-xs sm:text-sm font-bold border transition-colors whitespace-nowrap cursor-pointer {{ !$selectedCategory ? 'bg-primary text-white border-primary' : 'bg-surface text-text-main border-border-subtle hover:border-primary' }}">
                    All Categories ({{ $totalDealsCount }})
                </a>
                @foreach($categoriesCount as $catName => $count)
                    <a href="{{ route('deals.index', ['category' => $catName, 'sort' => $sortBy]) }}"
                       class="px-4 py-2 rounded-full text-xs sm:text-sm font-bold border transition-colors whitespace-nowrap cursor-pointer {{ $selectedCategory === $catName ? 'bg-primary text-white border-primary' : 'bg-surface text-text-main border-border-subtle hover:border-primary' }}">
                        {{ $catName }} ({{ $count }})
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2 shrink-0 text-xs">
                <span class="text-text-muted font-semibold">Sort by:</span>
                @foreach([
                    'discount_desc' => 'Biggest Discount',
                    'price_asc'     => 'Lowest Price',
                    'price_desc'    => 'Highest Price',
                    'newest'        => 'Newest',
                ] as $key => $label)
                    <a href="{{ route('deals.index', array_filter(['category' => $selectedCategory, 'sort' => $key])) }}"
                       class="px-3 py-1.5 rounded-xl font-bold border transition-colors cursor-pointer {{ $sortBy === $key ? 'bg-primary-dark text-white border-primary-dark' : 'bg-surface-subtle text-text-main border-border-subtle hover:border-primary' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if($products->isEmpty())
            <div class="py-16 text-center bg-surface-subtle/40 rounded-3xl border border-border-subtle space-y-3">
                <p class="text-base font-bold text-primary-dark">No discounted products found in this category.</p>
                <a href="{{ route('deals.index') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors cursor-pointer">
                    View All Super Deals
                </a>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-5 lg:gap-6">
                @foreach($products as $pIdx => $product)
                    @php
                        $flashDeal = $product->getAttribute('active_promotional_deal');
                        $flashLimit = $flashDeal && $flashDeal->flash_quantity !== null ? (int) $flashDeal->flash_quantity : null;
                        $flashSold = $flashDeal ? (int) $flashDeal->sold_quantity : 0;
                        $flashPct = ($flashLimit && $flashLimit > 0) ? min(100, round(($flashSold / $flashLimit) * 100)) : null;
                    @endphp
                    <div x-show="{{ $pIdx }} < visibleDealsLimit"
                         x-transition
                         class="bg-surface rounded-2xl p-3 border border-border-subtle/70 hover:border-primary/40 transition-all flex flex-col justify-between">
                        <x-storefront.seller-product-card
                            :product="$product"
                            :is-wishlisted="in_array((int) $product->id, $wishlistedProductIds, true)"
                        />
                        @if($flashPct !== null)
                            <div class="mt-2 pt-2 border-t border-border-subtle/60 space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-extrabold text-primary-dark">
                                    <span>&#9889; SUPER DEAL SLOT</span>
                                    <span>{{ $flashSold }}/{{ $flashLimit }} Claimed</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-brand-light/50 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-primary to-danger rounded-full" style="width: {{ max(12, $flashPct) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($products->count() > 12)
                <div class="pt-4 flex justify-center">
                    <button type="button"
                            @click="visibleDealsLimit = visibleDealsLimit >= {{ $products->count() }} ? 12 : visibleDealsLimit + 12"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleDealsLimit >= {{ $products->count() }} ? 'See Less Super Deals' : 'See More Super Deals (' + ({{ $products->count() }} - visibleDealsLimit) + ' remaining on this page)'"></span>
                    </button>
                </div>
            @endif

            @if($products->hasPages())
                <div class="pt-6">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>

    <x-storefront.product-modal />
</div>
@endsection