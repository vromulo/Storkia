@extends('layouts.app', ['title' => 'Shopping Cart - Storkia'])

@section('content')
@php
    $cartItems = collect();
    $groupedCart = collect();
    $initialAlpineData = [];

    if (auth()->check()) {
        $cartItems = \App\Models\Cart::with(['product.sellerProfile'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        // Group cart items by seller/store name
        $groupedCart = $cartItems->groupBy(function($item) {
            return $item->product->sellerProfile->business_name 
                ?? $item->product->user->first_name 
                ?? 'Storkia Direct';
        });

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $basePrice = (float) ($product->price ?? 0);
            $stock = (int) ($product->stock_quantity ?? 0);
            $image = (!empty($product->pictures) && is_array($product->pictures)) 
                ? asset('storage/' . $product->pictures[0]) 
                : null;

            if ($item->variant && isset($product->variants['items'])) {
                foreach ($product->variants['items'] as $v) {
                    if ($v['name'] === $item->variant) {
                        if (!$item->sub_variant) {
                            $basePrice = (float) ($v['price'] ?? $basePrice);
                            $stock = (int) ($v['stock'] ?? $stock);
                            $image = (isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $image;
                        } else {
                            if (isset($v['subs'])) {
                                foreach ($v['subs'] as $sub) {
                                    if ($sub['name'] === $item->sub_variant) {
                                        $basePrice = (float) ($sub['price'] ?? $v['price'] ?? $basePrice);
                                        $stock = (int) ($sub['stock'] ?? $stock);
                                        $image = (isset($sub['image']) && $sub['image']) ? asset('storage/' . $sub['image']) : ((isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $image);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $discountPercent = (float) ($product->discount ?? 0);
            $effectivePrice = $discountPercent > 0 
                ? $basePrice - ($basePrice * ($discountPercent / 100)) 
                : $basePrice;

            $storeName = $item->product->sellerProfile->business_name 
                ?? $item->product->user->first_name 
                ?? 'Storkia Direct';

            $initialAlpineData[] = [
                'id'              => $item->id,
                'store'           => $storeName,
                'title'           => $product->name,
                'variant'         => implode(' / ', array_filter([$item->variant, $item->sub_variant])),
                'image'           => $image,
                'originalPrice'   => $basePrice,
                'discountedPrice' => $effectivePrice,
                'discountPercent' => $discountPercent,
                'quantity'        => (int) $item->quantity,
                'maxStock'        => $stock,
                'selected'        => true,
                'isSyncing'       => false,
            ];
        }
    }
@endphp

<div 
    x-data="cartPageManager(@js($initialAlpineData))" 
    @if(auth()->check())
    x-init="
        if (typeof window.Echo !== 'undefined') {
            window.Echo.private('cart.{{ auth()->id() }}')
                .listen('.CartQuantityUpdated', (e) => {
                    let item = items.find(i => i.id === e.cart_id);
                    if (item && item.quantity !== e.quantity) {
                        item.quantity = e.quantity;
                    }
                });
        }
    "
    @endif
    @cart-updated.window="
        let item = items.find(i => i.id === $event.detail.id);
        if (item && item.quantity !== $event.detail.quantity) {
            item.quantity = $event.detail.quantity;
        }
    "
    class="min-h-screen bg-surface-subtle/40 pb-16 lg:pb-20 pt-4"
>
    <!-- Wider Container -->
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- 1. Header Stepper (Cart > Place Order > Pay > Order Complete) -->
        <nav aria-label="Checkout Stepper" class="w-full bg-surface border border-border-subtle rounded-2xl p-4 sm:p-5 shadow-xs select-none">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <!-- Step 1: Cart (Active) -->
                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary text-surface text-xs font-bold flex items-center justify-center shadow-xs">1</span>
                    <span class="text-sm font-sans tracking-tight">Cart</span>
                </div>
                <div class="flex-1 h-[2px] bg-primary/20 mx-3 sm:mx-6 rounded-full"></div>

                <!-- Step 2: Place Order -->
                <div class="flex items-center gap-2 text-text-muted font-medium">
                    <span class="w-7 h-7 rounded-full bg-surface-subtle border border-border-subtle text-text-muted text-xs font-bold flex items-center justify-center">2</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Place Order</span>
                </div>
                <div class="flex-1 h-[2px] bg-border-subtle mx-3 sm:mx-6 rounded-full"></div>

                <!-- Step 3: Pay -->
                <div class="flex items-center gap-2 text-text-muted font-medium">
                    <span class="w-7 h-7 rounded-full bg-surface-subtle border border-border-subtle text-text-muted text-xs font-bold flex items-center justify-center">3</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Pay</span>
                </div>
                <div class="flex-1 h-[2px] bg-border-subtle mx-3 sm:mx-6 rounded-full"></div>

                <!-- Step 4: Order Complete -->
                <div class="flex items-center gap-2 text-text-muted font-medium">
                    <span class="w-7 h-7 rounded-full bg-surface-subtle border border-border-subtle text-text-muted text-xs font-bold flex items-center justify-center">4</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Order Complete</span>
                </div>
            </div>
        </nav>

        @if($cartItems->isEmpty())
            <!-- Empty Cart Slate -->
            <div class="bg-surface border border-border-subtle rounded-3xl p-16 text-center max-w-xl mx-auto shadow-xs my-10">
                <div class="w-20 h-20 rounded-full bg-surface-subtle text-text-muted flex items-center justify-center mx-auto mb-4 border border-border-subtle">
                    <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-serif font-bold text-primary-dark mb-2">Your Shopping Cart is Empty</h2>
                <p class="text-text-muted text-sm mb-6">Looks like you haven't added anything to your cart yet.</p>
                <a href="/" class="inline-flex px-8 py-3.5 bg-primary hover:bg-primary-dark text-surface font-semibold text-sm rounded-full transition-colors shadow-xs">
                    Start Shopping
                </a>
            </div>
        @else
            <!-- Main Content Grid: Cart Items (Left) vs Summary Panel (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- LEFT COLUMN: Global Select All + Store Grouped Cards -->
                <div class="lg:col-span-8 space-y-4">
                    
                    <!-- Global Master Checkbox Bar -->
                    <div class="bg-surface rounded-2xl border border-border-subtle p-4 sm:p-5 flex items-center justify-between shadow-xs">
                        <label class="flex items-center gap-3.5 cursor-pointer select-none">
                            <input 
                                type="checkbox" 
                                :checked="isAllSelected"
                                @change="toggleSelectAll($event.target.checked)"
                                class="w-5 h-5 rounded-md text-primary focus:ring-primary/30 border-border-subtle cursor-pointer"
                            >
                            <span class="text-base font-bold text-text-main font-sans tracking-tight">
                                ALL ITEMS (<span x-text="items.length"></span>)
                            </span>
                        </label>
                        <div class="text-xs text-text-muted font-medium">
                            <span x-text="selectedCount"></span> of <span x-text="items.length"></span> selected
                        </div>
                    </div>

                    <!-- Store Grouped Items Loop -->
                    @foreach ($groupedCart as $storeName => $itemsInStore)
                        <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
                            <!-- Store Header -->
                            <div class="p-4 sm:px-6 bg-surface-subtle/50 border-b border-border-subtle flex items-center gap-3.5 select-none">
                                <input 
                                    type="checkbox" 
                                    :checked="isStoreSelected('{{ addslashes($storeName) }}')"
                                    @change="toggleStore('{{ addslashes($storeName) }}',$event.target.checked)"
                                    class="w-5 h-5 rounded-md text-primary focus:ring-primary/30 border-border-subtle cursor-pointer"
                                >
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <span class="font-bold text-sm sm:text-base text-primary-dark">
                                        {{ $storeName }}
                                    </span>
                                </div>
                            </div>

                            <!-- Products under this Store -->
                            <div class="divide-y divide-border-subtle/60">
                                @foreach ($itemsInStore as $cartItem)
                                    @php
                                        $alpineIndex = $cartItems->search(fn($ci) => $ci->id === $cartItem->id);
                                    @endphp
                                    <div class="p-4 sm:p-6 flex items-start gap-4 hover:bg-surface-subtle/20 transition-colors">
                                        
                                        <!-- Item Checkbox -->
                                        <div class="pt-2 sm:pt-4">
                                            <input 
                                                type="checkbox" 
                                                x-model="items[{{ $alpineIndex }}].selected"
                                                class="w-5 h-5 rounded-md text-primary focus:ring-primary/30 border-border-subtle cursor-pointer"
                                            >
                                        </div>

                                        <!-- Product Image -->
                                        <a href="{{ route('product.show', $cartItem->product->id) }}" class="w-20 h-20 sm:w-28 sm:h-28 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1.5 group">
                                            @if($initialAlpineData[$alpineIndex]['image'])
                                                <img 
                                                    src="{{ $initialAlpineData[$alpineIndex]['image'] }}" 
                                                    alt="{{ $cartItem->product->name }}" 
                                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform"
                                                >
                                            @else
                                                <svg class="w-8 h-8 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            @endif
                                        </a>

                                        <!-- Item Details & Stepper -->
                                        <div class="flex-1 min-w-0 flex flex-col justify-between self-stretch">
                                            <div>
                                                <a href="{{ route('product.show', $cartItem->product->id) }}" class="text-sm sm:text-base font-semibold text-text-main line-clamp-2 hover:text-primary transition-colors leading-snug">
                                                    {{ $cartItem->product->name }}
                                                </a>

                                                @if(!empty($initialAlpineData[$alpineIndex]['variant']))
                                                    <div class="inline-flex items-center gap-1 mt-1.5 px-2.5 py-1 rounded-md bg-surface-subtle text-xs text-text-muted font-medium border border-border-subtle">
                                                        <span>{{ $initialAlpineData[$alpineIndex]['variant'] }}</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                                                <!-- Pricing -->
                                                <div class="flex items-baseline gap-2 font-sans">
                                                    <span 
                                                        class="text-base sm:text-lg font-semibold tracking-tight"
                                                        :class="items[{{ $alpineIndex }}].discountPercent > 0 ? 'text-price' : 'text-text-main'"
                                                    >
                                                        ₱<span x-text="(items[{{ $alpineIndex }}].discountedPrice * items[{{ $alpineIndex }}].quantity).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                    </span>

                                                    <template x-if="items[{{ $alpineIndex }}].discountPercent > 0">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs text-text-muted font-normal line-through">
                                                                ₱<span x-text="(items[{{ $alpineIndex }}].originalPrice * items[{{ $alpineIndex }}].quantity).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                            </span>
                                                            <span class="text-[10px] font-semibold text-price bg-red-50 border border-red-200 px-1.5 py-0.5 rounded">
                                                                -<span x-text="Math.round(items[{{ $alpineIndex }}].discountPercent)"></span>%
                                                            </span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Stepper Quantity & Delete -->
                                                <div class="flex items-center gap-4">
                                                    <div class="flex items-center border border-border-subtle rounded-lg bg-surface h-8 overflow-hidden w-24">
                                                        <button 
                                                            type="button" 
                                                            @click="decrement({{ $alpineIndex }})"
                                                            :disabled="items[{{ $alpineIndex }}].isSyncing"
                                                            class="w-7 h-full flex items-center justify-center text-text-main hover:bg-surface-subtle border-r border-border-subtle transition-colors cursor-pointer"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                                        </button>
                                                        <input 
                                                            type="number" 
                                                            readonly 
                                                            x-model="items[{{ $alpineIndex }}].quantity" 
                                                            class="w-full text-center text-xs font-bold text-text-main outline-none bg-transparent select-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                        >
                                                        <button 
                                                            type="button" 
                                                            @click="increment({{ $alpineIndex }})"
                                                            :disabled="items[{{ $alpineIndex }}].isSyncing"
                                                            class="w-7 h-full flex items-center justify-center text-text-main hover:bg-surface-subtle border-l border-border-subtle transition-colors cursor-pointer"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                        </button>
                                                    </div>

                                                    <!-- Remove Button -->
                                                    <form action="{{ route('cart.destroy', $cartItem->id) }}" method="POST" class="m-0 inline" onsubmit="return confirm('Remove this item from your cart?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-text-muted hover:text-danger p-1 transition-colors cursor-pointer" title="Remove item">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- RIGHT COLUMN: Desktop Sidebar (>= 1024px) vs. Mobile Bottom Dock (< 1024px) -->
                <div class="lg:col-span-4" 
                     x-data="{ mobileSummaryOpen: false }"
                     @resize.window.debounce.100ms="if (window.innerWidth >= 1024) mobileSummaryOpen = false">
                    
                    <!-- ============================================================ -->
                    <!-- 1. DESKTOP ONLY SIDEBAR (>= 1024px)                          -->
                    <!-- Strictly unaffected by mobile toggles, always visible         -->
                    <!-- ============================================================ -->
                    <div class="hidden lg:block bg-surface rounded-2xl border border-border-subtle p-6 shadow-xs sticky top-24 select-none space-y-6">
                        <div>
                            <h3 class="text-xl font-serif font-bold text-primary-dark">Order Summary</h3>
                            <p class="text-xs text-text-muted mt-0.5">Proceed to checkout and select your shipping address.</p>
                        </div>

                        <!-- Selected Items Thumbnail Strip -->
                        <div>
                            <div class="text-xs font-bold text-text-muted uppercase tracking-wider mb-2.5">
                                Selected Items (<span x-text="selectedCount"></span>)
                            </div>
                            <div class="flex items-center gap-2 overflow-x-auto pt-2.5 pb-2 pr-2 min-h-[56px] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-button]:hidden [&::-webkit-scrollbar-button]:w-0 [&::-webkit-scrollbar-button]:h-0 [&::-webkit-scrollbar-button]:[display:none]">
                                <template x-for="item in selectedItems" :key="item.id">
                                    <div class="relative w-12 h-12 rounded-xl bg-surface-subtle border border-border-subtle shrink-0 flex items-center justify-center p-1 group">
                                        <template x-if="item.image">
                                            <img :src="item.image" :alt="item.title" class="w-full h-full object-contain rounded-lg">
                                        </template>
                                        <template x-if="!item.image">
                                            <svg class="w-4 h-4 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </template>
                                        <!-- Small 'x' remove button -->
                                        <button 
                                            type="button"
                                            @click.stop="item.selected = false"
                                            title="Remove from selection"
                                            class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-text-main hover:bg-price text-surface flex items-center justify-center transition-colors shadow-xs cursor-pointer z-10"
                                        >
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="selectedCount === 0">
                                    <span class="text-xs text-text-muted italic">No items selected</span>
                                </template>
                            </div>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="space-y-3 pt-3 border-t border-border-subtle/70 text-sm">
                            <div class="flex items-center justify-between text-text-muted">
                                <span>Retail Price:</span>
                                <span class="font-semibold text-text-main">
                                    ₱<span x-text="retailTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-text-muted">
                                <span>Promotions / Savings:</span>
                                <span class="font-bold text-price">
                                    -₱<span x-text="savingsTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-border-subtle text-base font-bold text-text-main">
                                <span>Estimated Price:</span>
                                <span 
                                    class="text-2xl font-extrabold font-serif"
                                    :class="savingsTotal > 0 ? 'text-price' : 'text-text-main'"
                                >
                                    ₱<span 
                                        x-data="{
                                            displayValue: estimatedTotal,
                                            animate(target) {
                                                let start = this.displayValue;
                                                let end = target;
                                                let duration = 400;
                                                let startTime = null;
                                                let step = (timestamp) => {
                                                    if (!startTime) startTime = timestamp;
                                                    let progress = Math.min((timestamp - startTime) / duration, 1);
                                                    let ease = 1 - Math.pow(1 - progress, 3);
                                                    this.displayValue = start + (end - start) * ease;
                                                    if (progress < 1) {
                                                        window.requestAnimationFrame(step);
                                                    } else {
                                                        this.displayValue = end;
                                                    }
                                                };
                                                window.requestAnimationFrame(step);
                                            }
                                        }"
                                        x-init="$watch('estimatedTotal', value => animate(value))"
                                        x-text="displayValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"
                                    ></span>
                                </span>
                            </div>
                        </div>

                        <!-- Desktop Checkout CTA -->
                       <div class="pt-0">
                            <form action="#" method="GET">
                                <button 
                                    type="submit"
                                    :disabled="selectedCount === 0"
                                    :class="selectedCount === 0 
                                        ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle' 
                                        : 'bg-text-main hover:bg-dark-grey text-surface cursor-pointer shadow-xs active:scale-[0.99]'"
                                    class="w-full py-3.5 px-6 font-bold text-sm rounded-xl transition-all flex items-center justify-center gap-2"
                                >
                                    <span>Checkout Now</span>
                                    <span x-show="selectedCount > 0" x-text="'(' + selectedCount + ')'"></span>
                                </button>
                            </form>
                            <p class="text-[11px] text-text-muted text-center mt-2">
                                Taxes and shipping vouchers computed during checkout.
                            </p>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- 2. MOBILE & TABLET BOTTOM DOCK (< 1024px)                    -->
                    <!-- No backdrop overlay, pushes up smoothly on toggle            -->
                    <!-- ============================================================ -->
                    <div class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-surface border-t border-border-subtle px-4 py-2 sm:px-5 sm:py-2.5 shadow-[0_-4px_25px_rgba(0,0,0,0.12)] select-none">                        
                        <!-- Slide-Up Details Panel (Expands upward without backdrop) -->
                        <div x-show="mobileSummaryOpen" x-collapse x-cloak class="mb-3">
                            <div class="space-y-4 pb-3 border-b border-border-subtle/70">
                                
                                <!-- Selected Items Thumbnail Strip -->
                                <div>
                                    <div class="text-xs font-bold text-text-muted uppercase tracking-wider mb-2">
                                        Selected Items (<span x-text="selectedCount"></span>)
                                    </div>
                                    <div class="flex items-center gap-2 overflow-x-auto pt-2.5 pb-1 pr-2 min-h-[52px] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-button]:hidden [&::-webkit-scrollbar-button]:w-0 [&::-webkit-scrollbar-button]:h-0 [&::-webkit-scrollbar-button]:[display:none]">
                                        <template x-for="item in selectedItems" :key="item.id">
                                            <div class="relative w-11 h-11 rounded-xl bg-surface-subtle border border-border-subtle shrink-0 flex items-center justify-center p-1 group">
                                                <template x-if="item.image">
                                                    <img :src="item.image" :alt="item.title" class="w-full h-full object-contain rounded-lg">
                                                </template>
                                                <template x-if="!item.image">
                                                    <svg class="w-4 h-4 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                </template>
                                                <button 
                                                    type="button"
                                                    @click.stop="item.selected = false"
                                                    title="Remove from selection"
                                                    class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-text-main hover:bg-price text-surface flex items-center justify-center transition-colors shadow-xs cursor-pointer z-10"
                                                >
                                                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="selectedCount === 0">
                                            <span class="text-xs text-text-muted italic">No items selected</span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Detailed Price Breakdown (Retail & Savings) -->
                                <div class="space-y-1.5 text-xs sm:text-sm">
                                    <div class="flex items-center justify-between text-text-muted">
                                        <span>Retail Price:</span>
                                        <span class="font-semibold text-text-main">
                                            ₱<span x-text="retailTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between text-text-muted">
                                        <span>Promotions / Savings:</span>
                                        <span class="font-bold text-price">
                                            -₱<span x-text="savingsTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Sticky Bottom Bar Row: Total + Toggle Arrow + Checkout CTA -->
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="text-xs font-semibold text-text-muted">Total:</span>
                                    <span 
                                        class="text-lg sm:text-xl font-extrabold font-serif"
                                        :class="savingsTotal > 0 ? 'text-price' : 'text-text-main'"
                                    >
                                        ₱<span 
                                            x-data="{
                                                displayValue: estimatedTotal,
                                                animate(target) {
                                                    let start = this.displayValue;
                                                    let end = target;
                                                    let duration = 400;
                                                    let startTime = null;
                                                    let step = (timestamp) => {
                                                        if (!startTime) startTime = timestamp;
                                                        let progress = Math.min((timestamp - startTime) / duration, 1);
                                                        let ease = 1 - Math.pow(1 - progress, 3);
                                                        this.displayValue = start + (end - start) * ease;
                                                        if (progress < 1) {
                                                            window.requestAnimationFrame(step);
                                                        } else {
                                                            this.displayValue = end;
                                                        }
                                                    };
                                                    window.requestAnimationFrame(step);
                                                }
                                            }"
                                            x-init="$watch('estimatedTotal', value => animate(value))"
                                            x-text="displayValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"
                                        ></span>
                                    </span>

                                    <!-- Chevron Toggle Arrow: points up initially, rotates down 180° when expanded -->
                                    <button 
                                        type="button" 
                                        @click="mobileSummaryOpen = !mobileSummaryOpen"
                                        class="p-1 text-text-muted hover:text-primary transition-colors cursor-pointer rounded-full hover:bg-surface-subtle"
                                        aria-label="Toggle Summary Details"
                                    >
                                        <svg 
                                            class="w-4 h-4 sm:w-5 sm:h-5 transition-transform duration-300"
                                            :class="mobileSummaryOpen ? 'rotate-180' : ''" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke="currentColor" 
                                            stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </div>

                                <template x-if="savingsTotal > 0">
                                    <span class="text-[11px] sm:text-xs text-price font-bold block leading-tight mt-0.5">
                                        Saved: ₱<span x-text="savingsTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </span>
                                </template>
                            </div>

                            <!-- Mobile/Tablet Checkout Button -->
                            <div>
                                <form action="#" method="GET" class="m-0">
                                    <button 
                                        type="submit"
                                        :disabled="selectedCount === 0"
                                        :class="selectedCount === 0 
                                            ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle' 
                                            : 'bg-text-main hover:bg-dark-grey text-surface cursor-pointer shadow-xs active:scale-[0.98]'"
                                        class="py-2.5 px-6 font-bold text-xs sm:text-sm rounded-xl transition-all flex items-center justify-center gap-1.5"
                                    >
                                        <span>Checkout</span>
                                        <span x-show="selectedCount > 0" x-text="'(' + selectedCount + ')'"></span>
                                    </button>
                                </form>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        @endif

    </div>
</div>
@endsection