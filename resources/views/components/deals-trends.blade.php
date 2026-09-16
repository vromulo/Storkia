<section class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 mb-10">

    <!-- 1. LEFT CONTAINER: Super Deals -->
    <div class="lg:col-span-6 bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden group">
        <!-- Subtle background glow -->
        <div class="absolute -top-16 -right-16 w-52 h-52 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 pb-4 border-b border-border-subtle/80">
                <div class="flex items-center gap-2">
                    <!-- Lightning Icon -->
                    <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                    </svg>
                    <h2 class="text-lg sm:text-xl font-bold font-serif text-main uppercase tracking-tight">
                        Super Deals
                    </h2>
                </div>

                <a href="#products-grid" class="text-xs font-semibold text-text-main hover:text-primary transition-colors inline-flex items-center gap-1">
                    Limited-time offers
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <!-- Product Card Grid (3 items) -->
            <div class="grid grid-cols-3 gap-3 sm:gap-4 pt-4">
                @php
                    $dealProducts = isset($products) ? $products->take(3) : collect();
                @endphp

                @forelse($dealProducts as $deal)
                    @php
                        $dealDiscount = $deal->discount > 0 ? $deal->discount : 25;
                        $discounted = $deal->price - ($deal->price * ($dealDiscount / 100));
                    @endphp

                    <div class="group/card relative flex flex-col">
                        <a href="{{ route('product.show', $deal->id) }}" class="block">
                            <!-- Image Container -->
                            <div class="relative w-full aspect-square bg-surface-subtle rounded-xl overflow-hidden mb-2.5 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover/card:shadow-xs">
                                @if(!empty($deal->pictures) && is_array($deal->pictures))
                                    <img src="{{ asset('storage/' . $deal->pictures[0]) }}" alt="{{ $deal->name }}" class="w-full h-full object-contain p-2 group-hover/card:scale-105 transition-transform duration-300">
                                @else
                                    <svg class="w-10 h-10 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                @endif
                            </div>

                            <!-- Product Info -->
                            <div class="space-y-1">
                                <h3 class="text-xs font-normal text-text-main line-clamp-1 leading-snug group-hover/card:text-primary transition-colors" title="{{ $deal->name }}">
                                    {{ $deal->name }}
                                </h3>

                                <!-- Price Row -->
                                <div class="flex items-baseline gap-1.5 flex-wrap">
                                    <span class="text-sm sm:text-base font-extrabold text-primary">
                                        ₱{{ number_format($discounted, 0) }}
                                    </span>
                                    <span class="text-[11px] text-text-muted line-through">
                                        ₱{{ number_format($deal->price, 0) }}
                                    </span>
                                </div>

                                <!-- Limited Time Tag -->
                                <div class="text-[11px] font-bold text-text-main pt-0.5">
                                    -{{ number_format($dealDiscount, 0) }}% limited time
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-xs text-text-muted">
                        Fresh deals are loading soon.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 2. RIGHT CONTAINER: Trends -->
    <div class="lg:col-span-6 bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden group">
        <!-- Subtle warm peach glow -->
        <div class="absolute -bottom-16 -right-16 w-52 h-52 bg-brand-light/20 rounded-full blur-3xl pointer-events-none"></div>

        <div>
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-border-subtle/80">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <h2 class="text-lg sm:text-xl font-bold font-serif text-main uppercase tracking-tight">
                        Trends
                    </h2>
                </div>
                <a href="{{ url('/category/electronics') }}" class="text-xs font-semibold text-text-main hover:text-primary transition-colors inline-flex items-center gap-1">
                    Top Searches
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <!-- Trending Grid (3 Items) -->
            <div class="grid grid-cols-3 gap-3 sm:gap-4 pt-4">
                @php
                    $trendProducts = isset($products) ? $products->skip(3)->take(3) : collect();
                @endphp

                @forelse($trendProducts as $trend)
                    <div class="group/card relative flex flex-col">
                        <a href="{{ route('product.show', $trend->id) }}" class="block">
                            <!-- Image Container -->
                            <div class="relative w-full aspect-square bg-surface-subtle rounded-xl overflow-hidden mb-2.5 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover/card:shadow-xs">
                                @if(!empty($trend->pictures) && is_array($trend->pictures))
                                    <img src="{{ asset('storage/' . $trend->pictures[0]) }}" alt="{{ $trend->name }}" class="w-full h-full object-contain p-2 group-hover/card:scale-105 transition-transform duration-300">
                                @else
                                    <svg class="w-10 h-10 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                @endif
                            </div>

                            <!-- Product Info -->
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