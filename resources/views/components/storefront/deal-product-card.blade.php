{{--
    Product card for the Super Deals page. Same look as seller-product-card, but it prices
    from the deal (not product.discount) and shows how much of the deal is left.

    $deal comes from Buyer\DealController::loadActiveDeals().
--}}
@props(['deal'])

@php
    $product  = $deal->product;
    $almostGone = $deal->has_flash_cap && $deal->claimed_percent >= 80;
    $endsIn   = $deal->ends_at->diffForHumans(now(), [
        'parts'  => 2,
        'short'  => true,
        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
    ]);
@endphp

<div
    x-data="{
        imageLoaded: false,
        isWishlisted: {{ $deal->is_wishlisted ? 'true' : 'false' }},
        isTogglingWishlist: false,
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
                    this.$dispatch('toast', { title: 'Success', message: data.message, type: 'success' });
                }
            } catch (e) {
                this.$dispatch('toast', { title: 'Notice', message: 'Unable to update wishlist right now.', type: 'danger' });
            } finally {
                this.isTogglingWishlist = false;
            }
        }
    }"
    x-init="if ($refs.img?.complete) imageLoaded = true"
    class="group relative flex flex-col w-full h-full"
>
    <a href="{{ route('product.show', $product->id) }}" class="block h-full flex flex-col">

        <div class="relative w-full aspect-square bg-surface-subtle rounded-2xl overflow-hidden mb-3 border border-border-subtle/60 flex items-center justify-center transition-shadow duration-300 group-hover:shadow-sm">
            @if (!empty($product->pictures) && is_array($product->pictures))
                <div x-show="!imageLoaded" class="absolute inset-0 bg-gray-200/80 animate-pulse z-10 pointer-events-none"></div>
                <img
                    x-ref="img"
                    src="{{ asset('storage/' . $product->pictures[0]) }}"
                    alt="{{ $product->name }}"
                    x-on:load="imageLoaded = true"
                    x-on:error="imageLoaded = true"
                    :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                    class="w-full h-full object-contain p-2 sm:p-3 group-hover:scale-105 transition-all duration-300"
                >
            @else
                <svg class="w-10 h-10 sm:w-14 sm:h-14 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            @endif

            @if ($deal->percent_off > 0)
                <span class="absolute top-2 sm:top-2.5 left-2 sm:left-2.5 bg-price text-surface text-[10px] sm:text-xs font-bold px-1.5 sm:px-2 py-0.5 rounded-md shadow-xs z-20">
                    -{{ $deal->percent_off }}%
                </span>
            @endif

            <span class="absolute bottom-2 left-2 z-20 inline-flex items-center gap-1 bg-primary-dark/90 text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
                Ends in {{ $endsIn }}
            </span>
        </div>

        <div class="flex flex-col flex-1 justify-start">
            <h3 class="text-xs sm:text-sm font-normal text-text-main line-clamp-2 leading-tight group-hover:text-primary transition-colors" title="{{ $product->name }}">
                {{ $product->name }}
            </h3>

            <div class="mt-0.5 flex items-baseline gap-1 sm:gap-2 flex-wrap">
                <span class="text-sm sm:text-lg font-bold text-price">₱{{ number_format($deal->deal_price, 2) }}</span>
                @if ($deal->deal_price < $deal->original_price)
                    <span class="text-[10px] sm:text-xs text-text-muted line-through">₱{{ number_format($deal->original_price, 2) }}</span>
                @endif
            </div>

            @if ($deal->has_flash_cap)
                <div class="mt-1.5">
                    <div class="h-1.5 rounded-full bg-primary/15 overflow-hidden" role="progressbar"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $deal->claimed_percent }}"
                         aria-label="Share of the deal already claimed">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $deal->claimed_percent }}%"></div>
                    </div>
                    <p class="mt-1 text-[11px] font-semibold {{ $almostGone ? 'text-price' : 'text-primary' }}">
                        {{ $almostGone ? 'Almost gone, ' : '' }}{{ number_format($deal->remaining) }} left at this price
                    </p>
                </div>
            @else
                <p class="mt-1.5 text-[11px] font-semibold text-text-muted">While supplies last</p>
            @endif
        </div>
    </a>

    <button
        type="button"
        @click.stop.prevent="toggleWishlist({{ auth()->check() ? 'true' : 'false' }})"
        :disabled="isTogglingWishlist"
        title="Save to Wishlist"
        aria-label="Add to wishlist"
        class="absolute top-2 sm:top-2.5 right-2 sm:right-2.5 z-20 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-surface hover:bg-white shadow-xs border border-border-subtle flex items-center justify-center transition-transform active:scale-95 cursor-pointer"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 transition-colors"
             :class="isWishlisted ? 'text-danger fill-danger' : 'text-text-main hover:text-danger'"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
    </button>
</div>