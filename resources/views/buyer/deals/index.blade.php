@extends('layouts.app', ['title' => 'Super Deals'])

@section('content')
    <div class="bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <x-ui.breadcrumbs :items="['Super Deals']" class="mb-3" />

            {{-- Header: title on the left, live countdown to the next deal that ends on the right --}}
            <header class="rounded-2xl border border-brand-light bg-brand-light/30 px-5 py-5 sm:px-7 sm:py-6 mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-6 h-6 text-primary fill-primary shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                        </svg>
                        <h1 class="text-2xl sm:text-3xl font-serif font-bold text-text-main tracking-tight leading-none">
                            Super Deals
                        </h1>
                    </div>
                    <p class="mt-2 text-sm text-text-muted leading-relaxed">
                        Limited-time prices from verified stores. When the timer or the deal stock runs out, the price goes back to normal.
                    </p>
                </div>

                @if ($nextEndMs)
                    <div
                        x-data="{
                            endAtMs: {{ (int) $nextEndMs }},
                            nowMs: {{ now()->timestamp * 1000 }},
                            text: '--:--:--',
                            tick() {
                                const diff = Math.max(0, Math.floor((this.endAtMs - this.nowMs) / 1000));
                                const d = Math.floor(diff / 86400);
                                const h = String(Math.floor((diff % 86400) / 3600)).padStart(2, '0');
                                const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                                const s = String(diff % 60).padStart(2, '0');
                                this.text = (d > 0 ? d + 'd ' : '') + h + ':' + m + ':' + s;
                                return diff;
                            },
                            start() {
                                if (this.tick() === 0) { return; }
                                const timer = setInterval(() => {
                                    this.nowMs += 1000;
                                    if (this.tick() === 0) {
                                        clearInterval(timer);
                                        // A deal just ended: reload so the list drops it.
                                        setTimeout(() => window.location.reload(), 2000);
                                    }
                                }, 1000);
                            }
                        }"
                        x-init="start()"
                        class="shrink-0"
                    >
                        <p class="text-xs font-medium text-text-muted mb-1">Next deal ends in</p>
                        <span class="inline-block px-4 py-1.5 rounded-xl bg-primary-dark text-white font-mono text-xl sm:text-2xl font-extrabold tracking-wider tabular-nums" x-text="text" aria-live="off">--:--:--</span>
                    </div>
                @endif
            </header>

            @if ($totalAll > 0)
                <x-storefront.promo-filter-bar
                    :categories="$categories"
                    :selected-category="$category"
                    :total="$totalAll"
                    :sorts="$sorts"
                    :current-sort="$sort"
                    default-sort="ending"
                />
            @endif

            @if ($deals->count() > 0)
                <x-storefront.batch-grid :total="$deals->count()" noun="deals">
                    @foreach ($deals as $i => $deal)
                        <div x-show="{{ $i }} < visibleCount">
                            <x-storefront.deal-product-card :deal="$deal" />
                        </div>
                    @endforeach
                </x-storefront.batch-grid>
            @elseif ($totalAll > 0)
                {{-- Deals exist, but not for the selected category --}}
                <div class="py-16 text-center text-text-muted">
                    <h2 class="text-base font-bold text-text-main">No Super Deals in {{ $category }} right now</h2>
                    <p class="text-xs mt-1 max-w-sm mx-auto">Other categories still have deals running.</p>
                    <a href="{{ route('deals.index') }}" class="inline-flex mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                        Show all Super Deals
                    </a>
                </div>
            @else
                <div class="py-20 text-center text-text-muted">
                    <svg class="w-12 h-12 mx-auto mb-3 text-text-muted/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                    </svg>
                    <h2 class="text-base font-bold text-text-main">No Super Deals are running right now</h2>
                    <p class="text-xs mt-1 max-w-sm mx-auto">Sellers add new deals throughout the day. Check back soon, or browse everything in the meantime.</p>
                    <a href="{{ route('home') }}" class="inline-flex mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                        Browse all products
                    </a>
                </div>
            @endif

        </div>
    </div>
@endsection