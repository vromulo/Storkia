@php
    $cartItems = collect();
    $alpineItems = [];
    $totalAmount = 0;
    $pricingService = app(\App\Services\SuperDealPricingService::class);

    if (auth()->check()) {
        $cartItems = \App\Models\Cart::with('product')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $basePrice = (float) ($product->price ?? 0);
            $variantStock = (int) ($product->stock_quantity ?? 0);
            $variantImage = (!empty($product->pictures) && is_array($product->pictures))
                ? asset('storage/' . $product->pictures[0])
                : null;

            if ($item->variant && isset($product->variants['items']) && is_array($product->variants['items'])) {
                foreach ($product->variants['items'] as $v) {
                    if (($v['name'] ?? '') === $item->variant) {
                        if (!$item->sub_variant) {
                            $basePrice = (float) ($v['price'] ?? $basePrice);
                            $variantStock = (int) ($v['stock'] ?? $variantStock);
                            $variantImage = (isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $variantImage;
                        } else {
                            if (isset($v['subs']) && is_array($v['subs'])) {
                                foreach ($v['subs'] as $sub) {
                                    if (($sub['name'] ?? '') === $item->sub_variant) {
                                        $basePrice = (float) ($sub['price'] ?? $v['price'] ?? $basePrice);
                                        $variantStock = (int) ($sub['stock'] ?? $variantStock);
                                        $variantImage = (isset($sub['image']) && $sub['image'])
                                            ? asset('storage/' . $sub['image'])
                                            : ((isset($v['image']) && $v['image']) ? asset('storage/' . $v['image']) : $variantImage);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $displayPricing = $pricingService->resolveDisplayPricing($product, $basePrice);
            $itemPrice = (float) ($displayPricing['unit_price'] ?? $basePrice);
            $safeQuantity = min((int) $item->quantity, max(1, $variantStock));
            if ($variantStock <= 0) {
                $safeQuantity = 0;
            }

            $variantLabel = implode(' / ', array_filter([$item->variant, $item->sub_variant]));

            $alpineItems[] = [
                'id'           => (int) $item->id,
                'user_id'      => (int) $item->user_id,
                'product_id'   => (int) $product->id,
                'name'         => (string) $product->name,
                'url'          => route('product.show', $product->id),
                'destroy_url'  => route('cart.destroy', $item->id),
                'variantLabel' => $variantLabel !== '' ? $variantLabel : null,
                'quantity'     => $safeQuantity,
                'price'        => round($itemPrice, 2),
                'maxStock'     => $variantStock,
                'image'        => $variantImage,
                'basePrice'    => round($basePrice, 2),
                'isSyncing'    => false,
            ];

            $totalAmount += ($itemPrice * $safeQuantity);
        }
    }
@endphp

<div
    data-cart-dropdown-wrapper
    x-show="cartOpen"
    x-cloak
    x-data="{
        items: @js($alpineItems),
        totalAmount: {{ round($totalAmount, 2) }},
        syncTimers: {},
        init() {
            @if(auth()->check())
            if (typeof window.Echo !== 'undefined') {
                window.Echo.private('cart.{{ auth()->id() }}')
                    .listen('.CartQuantityUpdated', (e) => {
                        this.syncFromEvent({
                            id: e.cart_id,
                            user_id: e.user_id,
                            quantity: e.quantity
                        });
                    });
            }
            @endif
        },
        formatPrice(val) {
            return Number(val || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        calculateTotal() {
            let newTotal = this.items.reduce((sum, i) => {
                let q = parseInt(i.quantity);
                if (isNaN(q) || q < 0) q = 0;
                return sum + (Number(i.price || 0) * q);
            }, 0);
            this.totalAmount = Math.round(newTotal * 100) / 100;
            if (typeof window.setCartBadge === 'function') {
                window.setCartBadge(this.items.length);
            }
        },
        handleCartAddedEvent(detail) {
            if (!detail) return;
            if (Array.isArray(detail.cart_items)) {
                this.items = detail.cart_items;
                if (typeof detail.total_amount !== 'undefined') {
                    this.totalAmount = Number(detail.total_amount || 0);
                } else {
                    this.calculateTotal();
                }
            }
        },
        broadcastLocalSync(item) {
            if (!item) return;
            window.dispatchEvent(new CustomEvent('cart-updated', {
                detail: {
                    id: Number(item.id),
                    user_id: Number(item.user_id),
                    quantity: Number(item.quantity),
                    source: 'dropdown'
                }
            }));
        },
        queueDatabaseSync(index, delay = 180) {
            const item = this.items[index];
            if (!item) return;
            const cartId = item.id;
            if (this.syncTimers[cartId]) {
                clearTimeout(this.syncTimers[cartId]);
            }
            this.syncTimers[cartId] = setTimeout(() => {
                this.syncDatabase(cartId, item.quantity, index);
            }, delay);
        },
        async syncDatabase(cartId, quantity, index) {
            const item = this.items[index];
            if (!item) return;
            let parsedQty = parseInt(quantity);
            if (isNaN(parsedQty) || parsedQty < 1) parsedQty = 1;
            const maxLimit = Math.max(1, parseInt(item.maxStock) || 1);
            if (parsedQty > maxLimit) parsedQty = maxLimit;

            item.quantity = parsedQty;
            item.isSyncing = true;
            this.calculateTotal();
            this.broadcastLocalSync(item);

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
                        quantity: parsedQty
                    })
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    if (this.items[index] && Number(this.items[index].quantity) !== Number(data.quantity)) {
                        this.items[index].quantity = Number(data.quantity);
                        this.calculateTotal();
                    }
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: {
                            id: Number(data.id),
                            user_id: Number(data.user_id),
                            quantity: Number(data.quantity),
                            source: 'server'
                        }
                    }));
                }
            } catch (error) {
                console.error('Cart sync error:', error);
            } finally {
                if (this.items[index]) {
                    this.items[index].isSyncing = false;
                }
            }
        },
        onQuantityInput(index, event) {
            const item = this.items[index];
            if (!item) return;
            const rawVal = String(event.target.value || '').replace(/[^0-9]/g, '');
            if (rawVal === '') {
                return;
            }
            let val = parseInt(rawVal, 10);
            const limit = Math.max(1, parseInt(item.maxStock) || 1);
            if (val < 1) val = 1;
            if (val > limit) val = limit;
            item.quantity = val;
            event.target.value = val;
            this.calculateTotal();
            this.broadcastLocalSync(item);
            this.queueDatabaseSync(index, 220);
        },
        onQuantityBlur(index, event) {
            const item = this.items[index];
            if (!item) return;
            let val = parseInt(event.target.value, 10);
            const limit = Math.max(1, parseInt(item.maxStock) || 1);
            if (isNaN(val) || val < 1) val = 1;
            if (val > limit) val = limit;
            item.quantity = val;
            event.target.value = val;
            this.calculateTotal();
            this.broadcastLocalSync(item);
            this.queueDatabaseSync(index, 20);
        },
        increment(index) {
            const item = this.items[index];
            if (!item) return;
            let limit = Math.max(1, parseInt(item.maxStock) || 1);
            let current = parseInt(item.quantity) || 1;
            if (current < limit) {
                item.quantity = current + 1;
                this.calculateTotal();
                this.broadcastLocalSync(item);
                this.queueDatabaseSync(index, 80);
            }
        },
        decrement(index) {
            const item = this.items[index];
            if (!item) return;
            let current = parseInt(item.quantity) || 1;
            if (current > 1) {
                item.quantity = current - 1;
                this.calculateTotal();
                this.broadcastLocalSync(item);
                this.queueDatabaseSync(index, 80);
            }
        },
        syncFromEvent(detail) {
            if (!detail) return;
            let item = this.items.find(i => Number(i.id) === Number(detail.id));
            if (item && Number(item.quantity) !== Number(detail.quantity)) {
                item.quantity = Number(detail.quantity);
                this.calculateTotal();
            }
        }
    }"
    @cart-updated.window="syncFromEvent($event.detail)"
    @cart:updated.window="handleCartAddedEvent($event.detail)"
    x-transition:enter="transition ease-out duration-150 transform"
    x-transition:enter-start="opacity-0 translate-y-1 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-100 transform"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-1 scale-95"
    class="absolute right-0 top-full mt-2 w-80 sm:w-90 bg-surface rounded-2xl border border-border-subtle shadow-2xl z-50 overflow-hidden select-none"
>
    <div class="px-5 pt-4 pb-3 flex items-center justify-between">
        <h3 class="font-bold text-lg text-text-main font-sans tracking-tight">Shopping cart</h3>
        <span x-show="items.length > 0"
              class="px-2 py-0.5 rounded-full bg-brand-light/50 text-primary-dark text-[11px] font-bold"
              x-text="items.length + (items.length === 1 ? ' item' : ' items')"></span>
    </div>

    <template x-if="items.length > 0">
        <div>
            <div class="px-5 max-h-[310px] overflow-y-auto divide-y divide-border-subtle/50 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-border-subtle [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-surface-subtle">
                <template x-for="(item, index) in items" :key="item.id">
                    <div class="py-3.5 flex items-start gap-3 group">
                        <div class="w-16 h-16 rounded-xl bg-surface-subtle border border-border-subtle/70 overflow-hidden shrink-0 flex items-center justify-center p-1">
                            <template x-if="item.image">
                                <img :src="item.image" :alt="item.name" class="w-full h-full object-contain">
                            </template>
                            <template x-if="!item.image">
                                <svg class="w-6 h-6 text-text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </template>
                        </div>

                        <div class="flex-1 min-w-0 pr-1">
                            <a :href="item.url"
                               class="text-xs font-semibold text-text-main line-clamp-1 leading-snug hover:text-primary transition-colors"
                               :title="item.name"
                               x-text="item.name"></a>

                            <template x-if="item.variantLabel">
                                <span class="block text-[10px] text-text-muted truncate mt-0.5" x-text="item.variantLabel"></span>
                            </template>

                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-price" x-text="'₱' + formatPrice(item.price * item.quantity)"></span>

                                <template x-if="item.maxStock > 0">
                                    <div class="flex items-center border border-border-subtle focus-within:border-primary rounded-md bg-surface shadow-xs h-[26px] overflow-hidden w-[84px] shrink-0"
                                         :class="{'opacity-70': item.isSyncing}">
                                        <button type="button"
                                                @click.prevent.stop="decrement(index)"
                                                :disabled="item.quantity <= 1"
                                                :class="{'opacity-40 cursor-not-allowed': item.quantity <= 1}"
                                                class="w-6 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-r border-border-subtle outline-none shrink-0 cursor-pointer">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"></path></svg>
                                        </button>

                                        <input type="number"
                                               min="1"
                                               :max="item.maxStock"
                                               :value="item.quantity"
                                               @click.stop
                                               @input="onQuantityInput(index, $event)"
                                               @change="onQuantityBlur(index, $event)"
                                               class="flex-1 w-full h-full text-center border-none bg-transparent text-xs font-bold text-text-main focus:ring-0 p-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">

                                        <button type="button"
                                                @click.prevent.stop="increment(index)"
                                                :disabled="item.quantity >= item.maxStock"
                                                :class="{'opacity-40 cursor-not-allowed': item.quantity >= item.maxStock}"
                                                class="w-6 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-l border-border-subtle outline-none shrink-0 cursor-pointer">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="item.maxStock <= 0">
                                    <span class="text-[10px] font-bold text-red-600 uppercase tracking-tight">Out of Stock</span>
                                </template>
                            </div>

                            <div class="flex items-center justify-between mt-1.5">
                                <span x-show="item.maxStock > 0 && item.maxStock <= 2"
                                      class="text-[10px] font-bold text-red-600 uppercase tracking-tight"
                                      x-text="'LAST ' + (item.maxStock == 1 ? 'ONE' : item.maxStock + ' LEFT')"></span>
                                <span x-show="item.maxStock <= 0"
                                      class="text-[10px] font-bold text-red-600 uppercase tracking-tight">OUT OF STOCK</span>
                                <span x-show="item.maxStock > 2"
                                      class="text-[10px] text-text-muted"
                                      x-text="'₱' + formatPrice(item.price) + ' each'"></span>

                                <form :action="item.destroy_url" method="POST" class="m-0 inline" @click.stop>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-text-muted hover:text-danger p-0.5 transition-colors cursor-pointer" title="Remove item">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-5 pt-3 pb-2 border-t border-border-subtle flex items-center justify-between group/total hover:bg-surface-subtle/40 transition-colors">
                <span class="text-sm font-bold text-text-main">Total</span>
                <span class="text-base font-extrabold text-price">
                    ₱<span x-text="formatPrice(totalAmount)"></span>
                </span>
            </div>

            <div class="p-5 pt-2 space-y-2.5">
                <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-text-main hover:bg-dark-grey text-surface text-xs font-bold rounded-xl shadow-xs transition-colors flex items-center justify-center cursor-pointer active:scale-[0.99]">Checkout</a>
                <a href="{{ route('cart.index') }}" class="w-full py-2.5 px-4 bg-surface border border-border-subtle hover:border-text-main text-text-main text-xs font-bold rounded-xl transition-colors flex items-center justify-center cursor-pointer">View cart</a>
            </div>
        </div>
    </template>

    <template x-if="items.length === 0">
        <div class="px-6 py-8 text-center">
            <div class="w-12 h-12 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </div>
            <p class="text-xs font-medium text-text-muted">Your shopping cart is empty.</p>
        </div>
    </template>
</div>