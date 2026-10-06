@php
    $cartItems = collect();
    $totalAmount = 0;
    
    if (auth()->check()) {
        // Load recent cart items with associated product relationships
        $cartItems = \App\Models\Cart::with('product')
            ->where('user_id', auth()->id())
            ->latest()
            ->take(6)
            ->get();
            
        $totalAmount = $cartItems->sum(function($item) {
            $price = $item->product->price ?? 0;
            if (($item->product->discount ?? 0) > 0) {
                $price = $price - ($price * ($item->product->discount / 100));
            }
            return $price * $item->quantity;
        });
    }
@endphp

<div 
    x-show="cartOpen" 
    x-cloak
    x-transition:enter="transition ease-out duration-150 transform"
    x-transition:enter-start="opacity-0 translate-y-1 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-100 transform"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-1 scale-95"
    class="absolute right-0 top-full mt-2 w-80 sm:w-90 bg-surface rounded-2xl border border-border-subtle shadow-2xl z-50 overflow-hidden select-none"
>
    <!-- Header -->
    <div class="px-5 pt-4 pb-3">
        <h3 class="font-bold text-lg text-text-main font-sans tracking-tight">Shopping cart</h3>
    </div>

    @if($cartItems->isNotEmpty())
        <!-- Scrollable Item List (Matches the custom scrollbar in reference image) -->
        <div class="px-5 max-h-[310px] overflow-y-auto divide-y divide-border-subtle/50 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-border-subtle [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-surface-subtle">
            @foreach($cartItems as $item)
                @php
                    $product = $item->product;
                    $firstPic = (!empty($product->pictures) && is_array($product->pictures)) 
                        ? asset('storage/' . $product->pictures[0]) 
                        : null;
                        
                    $itemPrice = $product->price ?? 0;
                    if (($product->discount ?? 0) > 0) {
                        $itemPrice = $itemPrice - ($itemPrice * ($product->discount / 100));
                    }
                    $isLowStock = ($product->stock_quantity ?? 0) <= 2;
                @endphp
                <div class="py-3.5 flex items-start gap-3 group">
                    <!-- Product Thumbnail -->
                    <div class="w-16 h-16 rounded-xl bg-surface-subtle border border-border-subtle/70 overflow-hidden shrink-0 flex items-center justify-center p-1">
                        @if($firstPic)
                            <img src="{{ $firstPic }}" alt="{{ $product->name }}" class="w-full h-full object-contain">
                        @else
                            <svg class="w-6 h-6 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        @endif
                    </div>

                    <!-- Item Details -->
                    <div class="flex-1 min-w-0 pr-1">
                        <a href="{{ route('product.show', $product->id) }}" class="text-xs font-semibold text-text-main line-clamp-1 leading-snug hover:text-primary transition-colors" title="{{ $product->name }}">
                            {{ $product->name }}
                        </a>

                        @if($item->variant || $item->sub_variant)
                            <p class="text-[11px] text-text-muted truncate mt-0.5">
                                {{ implode(' · ', array_filter([$item->variant, $item->sub_variant])) }}
                            </p>
                        @endif

                        <!-- Price Row -->
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-sm font-bold text-text-main">
                                ₱{{ number_format($itemPrice, 2) }}
                            </span>
                            <span class="text-xs text-text-muted">
                                Qty: {{ $item->quantity }}
                            </span>
                        </div>

                        <!-- Low Stock Indicator (LAST ONE banner from reference) -->
                        <div class="flex items-center justify-between mt-1">
                            @if($isLowStock)
                                <span class="text-[11px] font-bold text-red-600 uppercase tracking-tight">
                                    LAST {{ $product->stock_quantity == 1 ? 'ONE' : $product->stock_quantity . ' LEFT' }}
                                </span>
                            @else
                                <span></span>
                            @endif

                            <!-- Trash / Delete Action -->
                            <form action="{{ route('cart.destroy', $item->id) }}" method="POST" class="m-0 inline" @click.stop>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-text-muted hover:text-danger p-0.5 transition-colors cursor-pointer" title="Remove item">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Footer Total -->
        <div class="px-5 pt-3 pb-2 border-t border-border-subtle flex items-center justify-between">
            <span class="text-sm font-bold text-text-main">Total</span>
            <span class="text-base font-extrabold text-text-main">₱{{ number_format($totalAmount, 2) }}</span>
        </div>

        <!-- Action Buttons (Checkout / View Cart) -->
        <div class="p-5 pt-2 space-y-2.5">
            <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors flex items-center justify-center cursor-pointer">
                Checkout
            </a>
            <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-surface border border-border-subtle hover:border-text-main text-text-main text-xs font-bold rounded-xl transition-colors flex items-center justify-center cursor-pointer">
                View cart
            </a>
        </div>
    @else
        <!-- Empty State -->
        <div class="px-6 py-8 text-center">
            <div class="w-12 h-12 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <p class="text-xs font-medium text-text-muted">Your shopping cart is empty.</p>
        </div>
    @endif
</div>