@props(['product', 'fromCategory' => null])

@php
    $productShowUrl = $fromCategory 
        ? route('product.show', [$product->id, 'from_category' => $fromCategory]) 
        : route('product.show', $product->id);
@endphp

<div 
    x-data="{ imageLoaded: false }" 
    x-init="if ($refs.img?.complete) imageLoaded = true"
    class="group relative flex flex-col cursor-pointer w-full h-full"
>
    <!-- Clickable Product Link Wrapper -->
    <a href="{{ $productShowUrl }}" class="block h-full flex flex-col">
        
        <!-- Image Container -->
        <div class="relative w-full aspect-square bg-surface-subtle rounded-2xl overflow-hidden mb-3 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover:shadow-sm">
            @if(!empty($product->pictures) && is_array($product->pictures))
                <!-- Skeleton Loader -->
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
                <svg class="w-10 h-10 sm:w-14 sm:h-14 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            @endif

            @if($product->discount > 0)
                <span class="absolute top-2 sm:top-2.5 left-2 sm:left-2.5 bg-price text-surface text-[10px] sm:text-xs font-bold px-1.5 sm:px-2 py-0.5 rounded-md shadow-xs z-20">
                    -{{ number_format($product->discount, 0) }}%
                </span>
            @endif
        </div>

        <!-- Product Information (Tightened Gap) -->
        <div class="flex flex-col flex-1 justify-start">
            <h3 class="text-xs sm:text-sm font-normal text-text-main line-clamp-2 leading-tight group-hover:text-primary transition-colors" title="{{ $product->name }}">
                {{ $product->name }}
            </h3>

            <div class="mt-0.5">
                @if($product->discount > 0)
                    @php
                        $discountedPrice = $product->price - ($product->price * ($product->discount / 100));
                    @endphp
                    <div class="flex items-baseline gap-1 sm:gap-2 flex-wrap">
                        <span class="text-sm sm:text-lg font-bold text-price">
                            ₱{{ number_format($discountedPrice, 2) }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-text-muted line-through">
                            ₱{{ number_format($product->price, 2) }}
                        </span>
                    </div>
                @else
                    <span class="text-sm sm:text-lg font-bold text-text-main">
                        ₱{{ number_format($product->price, 2) }}
                    </span>
                @endif
            </div>
        </div>
    </a>

    <!-- Floating Wishlist Button -->
    <button type="button" @click.stop class="absolute top-2 sm:top-2.5 right-2 sm:right-2.5 z-20 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-surface shadow-xs border border-border-subtle flex items-center justify-center text-text-main hover:text-primary transition-colors cursor-pointer" aria-label="Add to wishlist">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
    </button>
</div>