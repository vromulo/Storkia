@php
    $activeSuperDeals = collect();
    $slotEndTimeMs = now()->endOfDay()->timestamp * 1000;

    if (\Illuminate\Support\Facades\Schema::hasTable('super_deals')) {
        $rawDeals = \Illuminate\Support\Facades\DB::table('super_deals')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->limit(3)
            ->get();

        if ($rawDeals->isNotEmpty()) {
            $firstEnd = \Carbon\Carbon::parse($rawDeals->first()->ends_at);
            $slotEndTimeMs = $firstEnd->timestamp * 1000;

            foreach ($rawDeals as $rd) {
                $prod = \App\Models\Product::find($rd->product_id);
                if ($prod && (int) $prod->stock_quantity > 0) {
                    $remQty = $rd->flash_quantity !== null
                        ? max(0, (int) $rd->flash_quantity - (int) $rd->sold_quantity)
                        : (int) $prod->stock_quantity;

                    if ($remQty > 0) {
                        $prod->super_deal_price = $rd->deal_price !== null
                            ? (float) $rd->deal_price
                            : round((float) $prod->price * (1 - ((float) ($rd->discount_percentage ?? 20) / 100)), 2);
                        $prod->super_deal_left = $remQty;
                        $activeSuperDeals->push($prod);
                    }
                }
            }
        }
    }

    $dealProducts = $activeSuperDeals->isNotEmpty()
        ? $activeSuperDeals
        : (isset($products) ? $products->take(3) : collect());
@endphp

<section x-data="{
    endAtMs: {{ $slotEndTimeMs }},
    nowMs: {{ now()->timestamp * 1000 }},
    countdownText: '00:00:00',
    initTimer() {
        this.tick();
        setInterval(() => {
            this.nowMs += 1000;
            this.tick();
        }, 1000);
    },
    tick() {
        const diff = Math.max(0, Math.floor((this.endAtMs - this.nowMs) / 1000));
        const h = String(Math.floor(diff / 3600)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        this.countdownText = `${h}:${m}:${s}`;
    }
}" x-init="initTimer()" class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 mb-10">

    <!-- 1. LEFT CONTAINER: Super Deals -->
    <div class="lg:col-span-6 bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -top-16 -right-16 w-52 h-52 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-border-subtle/80">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                    </svg>
                    <h2 class="text-lg sm:text-xl font-bold font-serif text-main uppercase tracking-tight">
                        Super Deals
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-lg bg-primary-dark text-white font-mono text-xs font-extrabold tracking-wider" x-text="countdownText"></span>
                </div>

                <a href="#products-grid" class="text-xs font-semibold text-text-main hover:text-primary transition-colors inline-flex items-center gap-1">
                    Limited-time offers
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-3 gap-3 sm:gap-4 pt-4">
                @forelse($dealProducts as $deal)
                    @php
                        $dealDiscount = $deal->discount > 0 ? $deal->discount : 25;
                        $discounted = isset($deal->super_deal_price)
                            ? (float) $deal->super_deal_price
                            : ($deal->price - ($deal->price * ($dealDiscount / 100)));
                        $pctOff = $deal->price > 0
                            ? max(1, round((1 - ($discounted / $deal->price)) * 100))
                            : $dealDiscount;
                        $unitsLeft = $deal->super_deal_left ?? (int) ($deal->stock_quantity ?? 10);
                    @endphp

                    <div class="group/card relative flex flex-col">
                        <a href="{{ route('product.show', $deal->id) }}" class="block">
                            <div class="relative w-full aspect-square bg-surface-subtle rounded-xl overflow-hidden mb-2.5 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover/card:shadow-xs">
                                @if(!empty($deal->pictures) && is_array($deal->pictures))
                                    <img src="{{ asset('storage/' . $deal->pictures[0]) }}" alt="{{ $deal->name }}" class="w-full h-full object-contain p-2 group-hover/card:scale-105 transition-transform duration-300">
                                @endif
                                <span class="absolute top-1.5 right-1.5 px-2 py-0.5 rounded-full bg-danger text-white text-[10px] font-extrabold">
                                    -{{ number_format($pctOff, 0) }}%
                                </span>
                            </div>

                            <div class="space-y-1">
                                <h3 class="text-xs font-normal text-text-main line-clamp-1 leading-snug group-hover/card:text-primary transition-colors" title="{{ $deal->name }}">
                                    {{ $deal->name }}
                                </h3>

                                <div class="flex items-baseline gap-1.5 mt-1">
                                    <span class="text-sm sm:text-base font-extrabold text-price">
                                        ₱{{ number_format($discounted, 2) }}
                                    </span>
                                    <span class="text-[11px] text-text-muted line-through">
                                        ₱{{ number_format($deal->price, 2) }}
                                    </span>
                                </div>

                                <div class="text-[11px] font-bold text-primary pt-0.5">
                                    {{ $unitsLeft }} left at Super Deal price
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-xs text-text-muted">
                        Fresh Super Deals are loading soon.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 2. RIGHT CONTAINER: Trends -->
    <div class="lg:col-span-6 bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -bottom-16 -right-16 w-52 h-52 bg-brand-light/20 rounded-full blur-3xl pointer-events-none"></div>

        <div>
            <div class="flex items-center justify-between pb-4 border-b border-border-subtle/80">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <h2 class="text-lg sm:text-xl font-bold font-serif text-main uppercase tracking-tight">
                        Trends
                    </h2>
                </div>
                <a href="#products-grid" class="text-xs font-semibold text-text-main hover:text-primary transition-colors inline-flex items-center gap-1">
                    Top Searches
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-3 gap-3 sm:gap-4 pt-4">
                @php
                    $trendProducts = isset($products) ? $products->skip(3)->take(3) : collect();
                @endphp

                @forelse($trendProducts as $trend)
                    <div class="group/card relative flex flex-col">
                        <a href="{{ route('product.show', $trend->id) }}" class="block">
                            <div class="relative w-full aspect-square bg-surface-subtle rounded-xl overflow-hidden mb-2.5 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover/card:shadow-xs">
                                @if(!empty($trend->pictures) && is_array($trend->pictures))
                                    <img src="{{ asset('storage/' . $trend->pictures[0]) }}" alt="{{ $trend->name }}" class="w-full h-full object-contain p-2 group-hover/card:scale-105 transition-transform duration-300">
                                @endif
                            </div>

                            <div class="space-y-1">
                                <h3 class="text-xs font-normal text-text-main line-clamp-1 leading-snug group-hover/card:text-primary transition-colors" title="{{ $trend->name }}">
                                    {{ $trend->name }}
                                </h3>

                                <div class="pt-0.5">
                                    <span class="text-sm sm:text-base font-extrabold text-text-main">
                                        ₱{{ number_format($trend->price, 0) }}
                                    </span>
                                </div>

                                <div class="text-[11px] font-bold text-primary pt-0.5">
                                    Hot search item
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-xs text-text-muted">
                        Trending products loading...
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</section>