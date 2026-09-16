@props(['product'])

<div class="group relative flex flex-col cursor-pointer">
    <!-- Clickable Product Link Wrapper -->
    <a href="{{ route('product.show', $product->id) }}" class="block">
        
        <!-- Image Container: Enlarged preview area -->
        <div class="relative w-full aspect-square bg-surface-subtle rounded-2xl overflow-hidden mb-3 border border-border-subtle/60 flex items-center justify-center transition-all duration-300 group-hover:shadow-sm">
            @if(!empty($product->pictures) && is_array($product->pictures))
                <img 
                    src="{{ asset('storage/' . $product->pictures[0]) }}" 
                    alt="{{ $product->name }}" 
                    class="w-full h-full object-contain p-2 sm:p-3 group-hover:scale-105 transition-transform duration-300"
                >
            @else
                <svg class="w-14 h-14 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            @endif

            <!-- Optional Discount Tag -->
            @if($product->discount > 0)
                <span class="absolute top-2.5 left-2.5 bg-primary text-surface text-xs font-bold px-2 py-0.5 rounded-md shadow-xs">
                    -{{ number_format($product->discount, 0) }}%
                </span>
            @endif
        </div>

        <!-- Product Information -->
        <div class="space-y-1.5">
            <!-- Product Title -->
            <h3 class="text-sm font-normal text-text-main line-clamp-2 leading-snug group-hover:text-primary transition-colors" title="{{ $product->name }}">
                {{ $product->name }}
            </h3>

            <!-- Price Row -->
            <div class="pt-0.5">
                @if($product->discount > 0)
                    @php
                        $discountedPrice = $product->price - ($product->price * ($product->discount / 100));
                    @endphp
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg font-bold text-text-main">
                            ₱{{ number_format($discountedPrice, 2) }}
                        </span>
                        <span class="text-xs text-text-muted line-through">
                            ₱{{ number_format($product->price, 2) }}
                        </span>
                    </div>
                @else
                    <span class="text-lg font-bold text-text-main">
                        ₱{{ number_format($product->price, 2) }}
                    </span>
                @endif
            </div>
        </div>
    </a>

    <!-- Floating Wishlist/Heart Button -->
    <button 
        type="button" 
        @click.stop
        class="absolute top-2.5 right-2.5 z-10 w-8 h-8 rounded-full bg-surface shadow-xs border border-border-subtle flex items-center justify-center text-text-main hover:text-primary transition-colors cursor-pointer"
        aria-label="Add to wishlist"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
    </button>
</div>