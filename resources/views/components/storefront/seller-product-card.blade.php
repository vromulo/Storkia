@props(['product', 'fromCategory' => null, 'isWishlisted' => null])

@php
    $productShowUrl = $fromCategory
        ? route('product.show', [$product->id, 'from_category' => $fromCategory])
        : route('product.show', $product->id);

    if ($isWishlisted === null) {
        if (isset($product->is_wishlisted)) {
            $isWishlisted = (bool) $product->is_wishlisted;
        } elseif (auth()->check()) {
            $authUserId = auth()->id();
            if (class_exists(\App\Models\Wishlist::class)) {
                $isWishlisted = \App\Models\Wishlist::where('user_id', $authUserId)
                    ->where('product_id', $product->id)
                    ->exists();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('wishlists')) {
                $isWishlisted = \Illuminate\Support\Facades\DB::table('wishlists')
                    ->where('user_id', $authUserId)
                    ->where('product_id', $product->id)
                    ->exists();
            } else {
                $isWishlisted = false;
            }
        } else {
            $isWishlisted = false;
        }
    } else {
        $isWishlisted = (bool) $isWishlisted;
    }

    $dealService   = app(\App\Services\SuperDealPricingService::class);
    $liveSuperDeal = $dealService->liveSuperDealFor((int) $product->id);
    $isSuperDeal   = $liveSuperDeal !== null;
    $dealUnitPrice = null;
    $dealPercent   = 0;

    if ($isSuperDeal) {
        $regularPrice  = round((float) $product->price, 2);
        $dealUnitPrice = $dealService->superDealUnitPrice($liveSuperDeal, $regularPrice);

        if ($regularPrice > 0 && $dealUnitPrice < $regularPrice) {
            $dealPercent = max(1, (int) round((1 - ($dealUnitPrice / $regularPrice)) * 100));
        } else {
            $dealUnitPrice = null;
        }
    }
@endphp

<div
    x-data="{
        imageLoaded: false,
        isWishlisted: {{ $isWishlisted ? 'true' : 'false' }},
        isTogglingWishlist: false,
        triggerToast(msg, type = 'danger') {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    title: type === 'success' ? 'Wishlist Updated' : 'Notice',
                    message: msg,
                    type: type
                }
            }));
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
        }
    }"
    x-init="if ($refs.img?.complete) imageLoaded = true"
    class="group relative flex flex-col cursor-pointer w-full h-full select-none"
>
    <a href="{{ $productShowUrl }}" class="block h-full flex flex-col cursor-pointer">
        <div class="relative w-full aspect-square bg-surface-subtle rounded-2xl overflow-hidden mb-2.5 border border-border-subtle/70 group-hover:border-primary/40 flex items-center justify-center transition-all duration-300 group-hover:shadow-md">
            @if(!empty($product->pictures) && is_array($product->pictures))
                <div x-show="!imageLoaded" class="absolute inset-0 storkia-skeleton z-10 pointer-events-none"></div>

                <img
                    x-ref="img"
                    src="{{ asset('storage/' . $product->pictures[0]) }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    x-on:load="imageLoaded = true"
                    x-on:error="imageLoaded = true"
                    :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                    class="w-full h-full object-contain p-2 sm:p-3 group-hover:scale-105 transition-all duration-300"
                >
            @else
                <svg class="w-10 h-10 sm:w-14 sm:h-14 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            @endif

            @if($dealPercent > 0)
                <span class="absolute top-2 sm:top-2.5 left-2 sm:left-2.5 bg-price text-surface text-[10px] sm:text-xs font-extrabold px-2 py-0.5 rounded-lg shadow-xs z-20">
                    -{{ $dealPercent }}%
                </span>
            @elseif(!$isSuperDeal && $product->discount > 0)
                <span class="absolute top-2 sm:top-2.5 left-2 sm:left-2.5 bg-price text-surface text-[10px] sm:text-xs font-extrabold px-2 py-0.5 rounded-lg shadow-xs z-20">
                    -{{ number_format($product->discount, 0) }}%
                </span>
            @endif

            @if($isSuperDeal)
                <span class="super-deal-label absolute bottom-2 left-2 z-20 inline-flex items-center gap-1 bg-gradient-to-r from-primary to-primary-dark text-white text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs">
                    <svg class="relative z-10 w-2.5 h-2.5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" /></svg>
                    <span class="relative z-10">SUPER DEAL</span>
                </span>
            @endif
        </div>

        <div class="flex flex-col flex-1 justify-start px-0.5">
            <h3 class="text-xs sm:text-sm font-medium text-text-main line-clamp-2 leading-snug group-hover:text-primary transition-colors" title="{{ $product->name }}">
                {{ $product->name }}
            </h3>

            <div class="mt-1">
                @if($dealUnitPrice !== null)
                    <div class="flex items-baseline gap-1.5 flex-wrap">
                        <span class="text-sm sm:text-lg font-extrabold text-price">
                            &#8369;{{ number_format($dealUnitPrice, 2) }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-text-muted line-through">
                            &#8369;{{ number_format($product->price, 2) }}
                        </span>
                    </div>
                @elseif(!$isSuperDeal && $product->discount > 0)
                    @php
                        $discountedPrice = $product->price - ($product->price * ($product->discount / 100));
                    @endphp
                    <div class="flex items-baseline gap-1.5 flex-wrap">
                        <span class="text-sm sm:text-lg font-extrabold text-price">
                            &#8369;{{ number_format($discountedPrice, 2) }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-text-muted line-through">
                            &#8369;{{ number_format($product->price, 2) }}
                        </span>
                    </div>
                @else
                    <span class="text-sm sm:text-lg font-extrabold text-text-main">
                        &#8369;{{ number_format($product->price, 2) }}
                    </span>
                @endif
            </div>
        </div>
    </a>

    <button
        type="button"
        @click.stop.prevent="toggleWishlist({{ auth()->check() ? 'true' : 'false' }})"
        :disabled="isTogglingWishlist"
        title="Save to Wishlist"
        aria-label="Add to wishlist"
        class="absolute top-2 sm:top-2.5 right-2 sm:right-2.5 z-20 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-surface/95 hover:bg-surface shadow-xs border border-border-subtle hover:border-primary/40 flex items-center justify-center transition-all active:scale-95 cursor-pointer"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-3.5 h-3.5 sm:w-4 sm:h-4 transition-colors"
            :class="isWishlisted ? 'text-danger fill-danger' : 'text-text-main hover:text-danger'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
    </button>
</div>