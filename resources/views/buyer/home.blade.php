@extends('layouts.app')

@section('content')
    @vite(['resources/css/buyer/home.css'])
    @php
        $sellers = $sellers ?? \App\Models\SellerProfile::take(6)->get();
        $batchSize = isset($batchSize) ? (int) $batchSize : 12;
        $initialProducts = $initialProducts ?? $products->take($batchSize)->values();
        $youMayAlsoLike = $youMayAlsoLike ?? $products->take(12);
        $totalProductsCount = isset($totalProductsCount) ? (int) $totalProductsCount : $products->count();
        $initialLoadedIds = $initialProducts->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $isBuyerRole = auth()->check() && auth()->user()->role === 'Buyer';
        $chestWallet = null;
        if ($isBuyerRole) {
            $chestWallet = $rewardWallet ?? app(\App\Services\BuyerRewardService::class)->getOrCreateWallet((int) auth()->id());
        }
        $canOpenDailyChest = $chestWallet ? $chestWallet->canOpenDailyChest() : false;
        $chestCooldownSeconds = $chestWallet ? $chestWallet->secondsUntilNextDailyChest() : 0;
    @endphp

    <style>
        @keyframes storkiaChestShake {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg) scale(1); }
            20% { transform: translate3d(-10px, -4px, 0) rotate(-8deg) scale(1.06); }
            40% { transform: translate3d(10px, -6px, 0) rotate(8deg) scale(1.08); }
            60% { transform: translate3d(-8px, 2px, 0) rotate(-6deg) scale(1.05); }
            80% { transform: translate3d(8px, 0, 0) rotate(6deg) scale(1.03); }
        }
        @keyframes storkiaRewardPopIn {
            0% { transform: scale(0.25); opacity: 0; }
            65% { transform: scale(1.12); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes storkiaChestFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-7px); }
        }
        .animate-chest-shake {
            animation: storkiaChestShake 0.42s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }
        .animate-reward-stage-pop {
            animation: storkiaRewardPopIn 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .animate-chest-float {
            animation: storkiaChestFloat 2.8s ease-in-out infinite;
        }
    </style>

    <div x-data="{
            modalOpen: false,
            activeProduct: null,
            batchSize: {{ (int) $batchSize }},
            visibleCount: {{ (int) $initialProducts->count() }},
            totalProducts: {{ (int) $totalProductsCount }},
            loadedIds: @js($initialLoadedIds),
            currentPage: 1,
            hasMore: {{ $initialProducts->count() < $totalProductsCount ? 'true' : 'false' }},
            isLoadingMore: false,
            batchCooldown: false,
            batchError: false,
            userHasScrolled: false,
            observer: null,
            initBatchScroll() {
                const onScroll = () => {
                    if (window.scrollY > 40) {
                        this.userHasScrolled = true;
                    }
                    if (!this.userHasScrolled || this.isLoadingMore || this.batchCooldown || !this.hasMore) {
                        return;
                    }
                    const sentinel = this.$refs.homeScrollSentinel;
                    if (!sentinel) return;
                    const rect = sentinel.getBoundingClientRect();
                    const viewHeight = window.innerHeight || document.documentElement.clientHeight;
                    if (rect.top <= viewHeight + 120) {
                        this.loadNextBatch();
                    }
                };

                window.addEventListener('scroll', onScroll, { passive: true });

                this.$nextTick(() => {
                    if (!this.$refs.homeScrollSentinel || typeof IntersectionObserver === 'undefined') return;
                    this.observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (
                                entry.isIntersecting &&
                                this.userHasScrolled &&
                                this.hasMore &&
                                !this.isLoadingMore &&
                                !this.batchCooldown
                            ) {
                                this.loadNextBatch();
                            }
                        });
                    }, { rootMargin: '120px' });
                    this.observer.observe(this.$refs.homeScrollSentinel);
                });
            },
            async loadNextBatch() {
                if (this.isLoadingMore || this.batchCooldown || !this.hasMore || this.visibleCount >= this.totalProducts) {
                    return;
                }

                this.isLoadingMore = true;
                this.batchError = false;

                try {
                    const nextPage = this.currentPage + 1;
                    const params = new URLSearchParams({
                        batch: '1',
                        page: String(nextPage),
                        exclude: this.loadedIds.join(',')
                    });

                    const response = await fetch(`{{ route('home') }}?${params.toString()}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Batch request failed');
                    }

                    const data = await response.json();
                    const grid = this.$refs.productsGrid;

                    if (grid && data.html) {
                        const temp = document.createElement('div');
                        temp.innerHTML = data.html;

                        Array.from(temp.children).forEach((cardEl) => {
                            const pid = Number(cardEl.getAttribute('data-product-id') || 0);
                            if (pid > 0 && this.loadedIds.includes(pid)) {
                                return;
                            }
                            if (pid > 0 && !grid.querySelector(`[data-product-id='${pid}']`)) {
                                this.loadedIds.push(pid);
                                grid.appendChild(cardEl);
                                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                                    window.Alpine.initTree(cardEl);
                                }
                            }
                        });
                    }

                    this.currentPage = nextPage;
                    if (typeof data.total === 'number') {
                        this.totalProducts = data.total;
                    }
                    this.visibleCount = this.loadedIds.length;
                    this.hasMore = Boolean(data.has_more) && this.visibleCount < this.totalProducts && Number(data.count || 0) > 0;
                } catch (e) {
                    this.batchError = true;
                } finally {
                    this.isLoadingMore = false;
                    this.batchCooldown = true;
                    setTimeout(() => {
                        this.batchCooldown = false;
                    }, 450);
                }
            }
         }"
         x-init="initBatchScroll()"
         class="relative bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">

        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-10 lg:py-6 animate-fade-in-up space-y-10">
            <x-storefront.hero-carousel />

            <div class="my-6 sm:my-8">
                <div class="flex justify-between items-center mb-4 border-b border-border-subtle/80 pb-3">
                    <h2 class="text-base sm:text-xl font-bold font-serif text-main uppercase tracking-wide">
                        Our Sellers
                    </h2>
                    <a href="{{ route('sellers.index') }}" class="inline-flex items-center gap-1 text-xs sm:text-sm font-bold text-primary hover:text-primary-dark transition-colors">
                        See More
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
                    @forelse($sellers as $seller)
                        <x-storefront.seller-card :seller="$seller" :is-home="true" />
                    @empty
                        <div class="col-span-full text-center py-6 text-text-muted font-medium text-xs sm:text-sm">
                            No sellers available at the moment.
                        </div>
                    @endforelse
                </div>
            </div>

            <x-storefront.deals-trends :products="$products" />

            <x-storefront.bundle-deals-card />

            @if($youMayAlsoLike->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2 border-b border-border-subtle/80 pb-3">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                                Personalized Recommendations
                            </span>
                            <h2 class="text-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide mt-1">
                                You May Also Like
                            </h2>
                        </div>
                        <p class="text-xs text-text-muted">
                            @auth
                                Curated from your recent orders, cart preferences, and followed stores
                            @else
                                Trending picks across top verified stores
                            @endauth
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-5">
                        @foreach($youMayAlsoLike->take(6) as $recProduct)
                            <x-storefront.seller-product-card :product="$recProduct" />
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="space-y-5" data-live-ignore>
                <div class="flex items-center justify-between border-b border-border-subtle/80 pb-3">
                    <h2 class="text-xl sm:text-2xl font-bold font-serif text-main uppercase tracking-wide">
                        Picked For You (All Products)
                    </h2>
                    <span class="text-xs font-bold text-text-muted" x-text="'Showing ' + Math.min(visibleCount, totalProducts) + ' of ' + totalProducts + ' products'"></span>
                </div>

                <div id="products-grid"
                     x-ref="productsGrid"
                     class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-5 lg:gap-6">
                    @forelse($initialProducts as $product)
                        <div data-product-id="{{ (int) $product->id }}" class="transition-all duration-300 ease-out">
                            <x-storefront.seller-product-card :product="$product" />
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-text-muted font-medium text-sm">
                            No products available at the moment.
                        </div>
                    @endforelse
                </div>

                <div x-show="isLoadingMore"
                     x-cloak
                     class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-5 lg:gap-6 pt-2">
                    <template x-for="n in Math.min(batchSize, Math.max(1, totalProducts - visibleCount))" :key="'skel_' + n">
                        <div class="flex flex-col space-y-2.5">
                            <div class="w-full aspect-square rounded-2xl bg-surface-subtle border border-border-subtle/60 animate-pulse"></div>
                            <div class="h-3.5 w-4/5 rounded bg-surface-subtle animate-pulse"></div>
                            <div class="h-4 w-2/5 rounded bg-surface-subtle animate-pulse"></div>
                        </div>
                    </template>
                </div>

                <div x-ref="homeScrollSentinel"
                     x-show="hasMore && visibleCount < totalProducts"
                     class="py-8 flex flex-col items-center justify-center gap-3 text-xs font-bold text-text-muted">
                    <div x-show="isLoadingMore" class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
                        <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
                    </div>
                    <span x-text="isLoadingMore
                        ? ('Loading next batch of ' + Math.min(batchSize, totalProducts - visibleCount) + ' products...')
                        : ('Loaded ' + Math.min(visibleCount, totalProducts) + ' of ' + totalProducts + ' products — Scroll down for next batch')"></span>

                    <button type="button"
                            x-show="!isLoadingMore"
                            @click="userHasScrolled = true; loadNextBatch()"
                            class="px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/25 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="batchError ? 'Retry Loading Batch' : ('Load Next ' + Math.min(batchSize, totalProducts - visibleCount) + ' Products')"></span>
                    </button>
                </div>

                <div x-show="!hasMore && totalProducts > 0"
                     x-cloak
                     class="py-6 text-center text-xs font-bold text-text-muted">
                    <span x-text="'All ' + totalProducts + ' products loaded'"></span>
                </div>
            </section>
        </div>

        @if($isBuyerRole)
            <div x-data="{
                    chestModalOpen: false,
                    canOpen: {{ $canOpenDailyChest ? 'true' : 'false' }},
                    cooldownSec: {{ (int) $chestCooldownSeconds }},
                    cooldownLabel: '',
                    tapCount: 0,
                    isShaking: false,
                    phase: 'closed',
                    currentChestFrame: '{{ asset('game-assets/chest-frame-1.png') }}',
                    rewardStageImage: '',
                    coinsWon: 0,
                    isRequestingServer: false,
                    initChest() {
                        this.updateCooldownTimer();
                        setInterval(() => {
                            if (this.cooldownSec > 0) {
                                this.cooldownSec--;
                                if (this.cooldownSec <= 0) {
                                    this.canOpen = true;
                                }
                                this.updateCooldownTimer();
                            }
                        }, 1000);
                    },
                    updateCooldownTimer() {
                        if (this.cooldownSec <= 0) {
                            this.cooldownLabel = 'OPEN';
                            return;
                        }
                        const h = String(Math.floor(this.cooldownSec / 3600)).padStart(2, '0');
                        const m = String(Math.floor((this.cooldownSec % 3600) / 60)).padStart(2, '0');
                        const s = String(this.cooldownSec % 60).padStart(2, '0');
                        this.cooldownLabel = `${h}:${m}:${s}`;
                    },
                    openChestModal() {
                        if (!this.canOpen) {
                            window.location.href = '{{ route('user.points') }}';
                            return;
                        }
                        this.tapCount = 0;
                        this.phase = 'closed';
                        this.currentChestFrame = '{{ asset('game-assets/chest-frame-1.png') }}';
                        this.rewardStageImage = '';
                        this.coinsWon = 0;
                        this.chestModalOpen = true;
                    },
                    async tapClosedChest() {
                        if (this.phase !== 'closed' || this.isRequestingServer) return;

                        this.isShaking = false;
                        setTimeout(() => { this.isShaking = true; }, 10);
                        setTimeout(() => { this.isShaking = false; }, 430);

                        this.tapCount++;

                        if (this.tapCount < 3) {
                            return;
                        }

                        this.isRequestingServer = true;

                        try {
                            const res = await fetch('{{ route('user.points.chest') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });
                            const data = await res.json();

                            if (!res.ok || !data.success) {
                                this.chestModalOpen = false;
                                this.isRequestingServer = false;
                                return;
                            }

                            this.coinsWon = Number(data.coins_won || 15);
                            const stage = Number(data.stage || 1);
                            this.rewardStageImage = stage === 3
                                ? '{{ asset('game-assets/chest-for-reward-stage-3.png') }}'
                                : (stage === 2
                                    ? '{{ asset('game-assets/chest-for-reward-stage-2.png') }}'
                                    : '{{ asset('game-assets/chest-for-reward-stage-1.png') }}');

                            this.canOpen = false;
                            this.cooldownSec = Number(data.cooldown_sec || 86400);
                            this.updateCooldownTimer();

                            setTimeout(() => {
                                this.phase = 'opening';
                                this.currentChestFrame = '{{ asset('game-assets/chest-frame-2.png') }}';
                            }, 380);

                            setTimeout(() => {
                                this.currentChestFrame = '{{ asset('game-assets/chest-frame-3.png') }}';
                            }, 820);

                            setTimeout(() => {
                                this.phase = 'reward';
                                this.isRequestingServer = false;
                            }, 1300);
                        } catch (e) {
                            this.isRequestingServer = false;
                            this.chestModalOpen = false;
                        }
                    }
                 }"
                 x-init="initChest()"
                 class="fixed bottom-24 right-5 sm:right-7 z-[65] select-none">

                <button type="button"
                        @click="openChestModal()"
                        class="group relative flex flex-col items-center justify-center focus:outline-none cursor-pointer animate-chest-float"
                        :title="canOpen ? 'Tap to open your Daily Treasure Chest!' : ('Next Daily Chest in ' + cooldownLabel)">
                    <div class="relative w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center">
                        <span x-show="canOpen"
                              class="absolute inset-0 rounded-full bg-amber-400/35 blur-md animate-pulse"></span>
                        <img src="{{ asset('game-assets/chest-frame-1.png') }}"
                             alt="Daily Chest"
                             class="relative z-10 w-14 h-14 sm:w-16 sm:h-16 object-contain drop-shadow-[0_6px_12px_rgba(0,0,0,0.35)] transition-transform duration-200 group-hover:scale-110"
                             :class="!canOpen ? 'opacity-75 grayscale-[30%]' : ''">
                    </div>
                    <span class="mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider shadow-md border border-white/40"
                          :class="canOpen ? 'bg-primary text-white' : 'bg-primary-dark text-brand-light font-mono'"
                          x-text="canOpen ? 'Daily Chest' : cooldownLabel"></span>
                </button>

                <template x-teleport="body">
                    <div x-show="chestModalOpen"
                         x-cloak
                         x-transition.opacity
                         @click.self="if (phase === 'reward') chestModalOpen = false"
                         class="fixed inset-0 z-[250] flex items-center justify-center bg-black/80 backdrop-blur-md p-4 select-none">

                        <div class="relative flex flex-col items-center justify-center">
                            <template x-if="phase !== 'reward'">
                                <div @click="tapClosedChest()"
                                     class="cursor-pointer flex flex-col items-center justify-center">
                                    <img :src="currentChestFrame"
                                         alt="Treasure Chest"
                                         :class="isShaking ? 'animate-chest-shake' : ''"
                                         class="w-64 h-64 sm:w-80 sm:h-80 object-contain drop-shadow-[0_20px_35px_rgba(0,0,0,0.65)] transition-transform duration-150 active:scale-95">
                                </div>
                            </template>

                            <template x-if="phase === 'reward'">
                                <div @click="chestModalOpen = false"
                                     class="cursor-pointer flex flex-col items-center justify-center animate-reward-stage-pop">
                                    <img :src="rewardStageImage"
                                         alt="Coin Reward"
                                         class="w-72 h-72 sm:w-96 sm:h-96 object-contain drop-shadow-[0_25px_45px_rgba(255,195,0,0.45)]">
                                    <a href="{{ route('user.points') }}"
                                       @click.stop
                                       class="mt-4 px-5 py-2 rounded-full bg-amber-400 hover:bg-amber-300 text-primary-dark font-serif font-extrabold text-lg shadow-xl transition-transform hover:scale-105">
                                        +<span x-text="coinsWon"></span> Storkia Coins
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>
@endsection