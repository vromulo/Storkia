@php
    $cartItems = collect();
    $alpineItems = [];
    $totalAmount = 0;
    
    if (auth()->check()) {
        $cartItems = \App\Models\Cart::with('product')
            ->where('user_id', auth()->id())
            ->latest()
            ->take(6)
            ->get();
            
        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $basePrice = $product->price ?? 0;
            $variantStock = $product->stock_quantity ?? 0;
            $variantImage = (!empty($product->pictures) && is_array($product->pictures)) ? asset('storage/' . $product->pictures[0]) : null;

            if ($item->variant && isset($product->variants['items'])) {
                foreach ($product->variants['items'] as $v) {
                    if ($v['name'] === $item->variant) {
                        if (!$item->sub_variant) {
                            $basePrice = $v['price'] ?? $basePrice;
                            $variantStock = $v['stock'] ?? $variantStock;
                            $variantImage = (isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $variantImage;
                        } else {
                            if (isset($v['subs'])) {
                                foreach ($v['subs'] as $sub) {
                                    if ($sub['name'] === $item->sub_variant) {
                                        $basePrice = $sub['price'] ?? $v['price'] ?? $basePrice;
                                        $variantStock = $sub['stock'] ?? $variantStock;
                                        $variantImage = (isset($sub['image']) && $sub['image']) ? asset('storage/' . $sub['image']) : ((isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $variantImage);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $itemPrice = $basePrice;
            if (($product->discount ?? 0) > 0) {
                $itemPrice = $itemPrice - ($itemPrice * ($product->discount / 100));
            }

            $safeQuantity = min($item->quantity, max(1, $variantStock));
            if ($variantStock <= 0) {
                $safeQuantity = 0;
            }

            $alpineItems[] = [
                'id'        => $item->id,
                'user_id'   => $item->user_id,
                'quantity'  => $safeQuantity,
                'price'     => $itemPrice,
                'maxStock'  => $variantStock,
                'image'     => $variantImage,
                'basePrice' => $basePrice,
                'isSyncing' => false,
            ];

            $totalAmount += ($itemPrice * $safeQuantity);
        }
    }
@endphp

<div 
    x-show="cartOpen" 
    x-cloak
    x-data="{
        items: @js($alpineItems),
        totalAmount: {{ $totalAmount }},

        calculateTotal() {
            let newTotal = this.items.reduce((sum, i) => {
                let q = parseInt(i.quantity);
                if (isNaN(q) || q < 1) q = 1;
                return sum + (i.price * q);
            }, 0);
            this.totalAmount = Math.round(newTotal * 100) / 100;
        },

        async syncDatabase(cartId, quantity, index) {
            if (this.items[index].isSyncing) return;
            this.items[index].isSyncing = true;
            
            try {
                const updateUrl = `{{ route('cart.update', ':id') }}`.replace(':id', cartId);
                const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || `{{ csrf_token() }}`;
                
                const response = await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ 
                        _method: 'PATCH',
                        quantity: parseInt(quantity) 
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    window.dispatchEvent(new CustomEvent('cart-updated', { 
                        detail: { 
                            id: data.id, 
                            user_id: data.user_id,
                            quantity: data.quantity 
                        } 
                    }));
                } else {
                    console.error('Failed to sync cart quantity:', data);
                }
            } catch (error) {
                console.error('Fetch error during sync:', error);
            } finally {
                this.items[index].isSyncing = false;
            }
        },

        increment(index) {
            if (this.items[index].isSyncing) return;
            let limit = Math.min(parseInt(this.items[index].maxStock), 1000000);
            let current = parseInt(this.items[index].quantity) || 1;
            if (current < limit) {
                this.items[index].quantity = current + 1;
                this.calculateTotal();
                this.syncDatabase(this.items[index].id, this.items[index].quantity, index);
            }
        },

        decrement(index) {
            if (this.items[index].isSyncing) return;
            let current = parseInt(this.items[index].quantity) || 1;
            if (current > 1) {
                this.items[index].quantity = current - 1;
                this.calculateTotal();
                this.syncDatabase(this.items[index].id, this.items[index].quantity, index);
            }
        },

        syncFromEvent(detail) {
            let item = this.items.find(i => i.id === detail.id);
            if (item && item.user_id === detail.user_id && item.quantity !== detail.quantity) {
                item.quantity = detail.quantity;
                this.calculateTotal();
            }
        }
    }"
    @cart-updated.window="syncFromEvent($event.detail)"
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

    @if ($cartItems->isNotEmpty())
        <!-- Scrollable Item List -->
        <div class="px-5 max-h-[310px] overflow-y-auto divide-y divide-border-subtle/50 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-border-subtle [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-surface-subtle">
            @foreach ($cartItems as $index =>$item)
                @php
                    $product =$item->product;
                    $data = $alpineItems[$index] ?? [];
                    
                    $variantStock =$data['maxStock'] ?? 0;
                    $variantImage =$data['image'] ?? null;
                    $itemPrice =$data['price'] ?? 0;
                    
                    $isLowStock = $variantStock > 0 &&$variantStock <= 2;
                @endphp
                <div class="py-3.5 flex items-start gap-3 group">
                    <!-- Product Thumbnail -->
                    <div class="w-16 h-16 rounded-xl bg-surface-subtle border border-border-subtle/70 overflow-hidden shrink-0 flex items-center justify-center p-1">
                        @if ($variantImage)
                            <img src="{{ $variantImage }}" alt="{{ $product->name }}" class="w-full h-full object-contain">
                        @else
                            <svg class="w-6 h-6 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        @endif
                    </div>

                    <!-- Item Details -->
                    <div class="flex-1 min-w-0 pr-1">
                        <a href="{{ route('product.show', $product->id) }}" class="text-xs font-semibold text-text-main line-clamp-1 leading-snug hover:text-primary transition-colors" title="{{ $product->name }}">{{ $product->name }}</a>
                        @if ($item->variant || $item->sub_variant)
                            <p class="text-[11px] text-text-muted truncate mt-0.5">{{ implode(' · ', array_filter([$item->variant,$item->sub_variant])) }}</p>
                        @endif

                        <!-- Price & Stepper Row -->
                        <div class="mt-1.5 flex items-center justify-between">
                            <span class="text-sm font-bold text-text-main">₱{{ number_format($itemPrice, 2) }}</span>
                            
                            <template x-if="items[{{ $index }}].maxStock > 0">
                                <div class="flex items-center border border-border-subtle rounded-md bg-surface shadow-xs h-[26px] overflow-hidden w-[72px] shrink-0"
                                     :class="{'opacity-50': items[{{ $index }}].isSyncing}">
                                    <button type="button" @click.prevent.stop="decrement({{ $index }})" :disabled="items[{{ $index }}].isSyncing" :class="{'cursor-not-allowed': items[{{ $index }}].isSyncing}" class="w-6 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-r border-border-subtle outline-none shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"></path></svg></button>
                                    
                                    <!-- Readonly input for forced tap-only interaction -->
                                    <input type="number" readonly x-model="items[{{ $index }}].quantity" @click.stop class="flex-1 w-full h-full text-center border-none bg-transparent text-xs font-bold text-text-main focus:ring-0 p-0 outline-none select-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                    
                                    <button type="button" @click.prevent.stop="increment({{ $index }})" :disabled="items[{{ $index }}].isSyncing" :class="{'cursor-not-allowed': items[{{ $index }}].isSyncing}" class="w-6 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-l border-border-subtle outline-none shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg></button>
                                </div>
                            </template>
                            
                            <template x-if="items[{{ $index }}].maxStock <= 0">
                                <span class="text-[10px] font-bold text-red-600 uppercase tracking-tight">Out of Stock</span>
                            </template>
                        </div>

                        <!-- Low Stock Indicator & Actions -->
                        <div class="flex items-center justify-between mt-1.5">
                            @if ($isLowStock)
                                <span class="text-[10px] font-bold text-red-600 uppercase tracking-tight">LAST <span x-text="items[{{ $index }}].maxStock == 1 ? 'ONE' : items[{{$index }}].maxStock + ' LEFT'"></span></span>
                            @elseif ($variantStock == 0)
                                <span class="text-[10px] font-bold text-red-600 uppercase tracking-tight">OUT OF STOCK</span>
                            @else
                                <span></span>
                            @endif

                            <form action="{{ route('cart.destroy', $item->id) }}" method="POST" class="m-0 inline" @click.stop>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-text-muted hover:text-danger p-0.5 transition-colors cursor-pointer" title="Remove item"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Footer Total -->
        <div class="px-5 pt-3 pb-2 border-t border-border-subtle flex items-center justify-between">
            <span class="text-sm font-bold text-text-main">Total</span>
            <span class="text-base font-extrabold text-text-main">₱<span x-text="totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})">{{ number_format($totalAmount, 2) }}</span></span>
        </div>

        <!-- Action Buttons -->
        <div class="p-5 pt-2 space-y-2.5">
            <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors flex items-center justify-center cursor-pointer">Checkout</a>
            <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-surface border border-border-subtle hover:border-text-main text-text-main text-xs font-bold rounded-xl transition-colors flex items-center justify-center cursor-pointer">View cart</a>
        </div>
    @else
        <!-- Empty State -->
        <div class="px-6 py-8 text-center">
            <div class="w-12 h-12 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </div>
            <p class="text-xs font-medium text-text-muted">Your shopping cart is empty.</p>
        </div>
    @endif
</div>