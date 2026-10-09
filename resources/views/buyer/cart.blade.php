@extends('layouts.app')

@section('content')
    <!-- Alpine.js & Custom Styles -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @php
        // Pre-process Cart Items for Alpine.js State Management
        $alpineItems = [];

        foreach ($cartItems as$item) {
            $product =$item->product;

            // Default Fallbacks
            $basePrice =$product->price;
            $variantStock =$product->stock_quantity;

            $variantImage = (!empty($product->pictures) && is_array($product->pictures))
                ? asset('storage/' . $product->pictures[0])
                : 'https://placehold.co/150x150/F6D8BD/5D3140?text=Product';

            // Extract Variant Pricing & Stock
            if ($item->variant && isset($product->variants['items'])) {
                foreach ($product->variants['items'] as$v) {
                    if ($v['name'] ===$item->variant) {
                        if (!$item->sub_variant) {$basePrice = $v['price'] ?? $basePrice;
                            $variantStock = $v['stock'] ?? $variantStock;

                            $variantImage = (isset($v['image']) &&$v['image'])
                                ? asset('storage/' . $v['image'])
                                : $variantImage;
                        } else {
                            if (isset($v['subs'])) {
                                foreach ($v['subs'] as$sub) {
                                    if ($sub['name'] ===$item->sub_variant) {
                                        $basePrice =$sub['price'] ?? $v['price'] ?? $basePrice;
                                        $variantStock = $sub['stock'] ?? $variantStock;

                                        $variantImage = (isset($sub['image']) &&$sub['image'])
                                            ? asset('storage/' . $sub['image'])
                                            : (
                                                (isset($v['image']) &&$v['image'])
                                                    ? asset('storage/' . $v['image'])
                                                    : $variantImage
                                            );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Apply Discount & Normalize Data
            $finalPrice =$product->discount > 0
                ? $basePrice - ($basePrice * ($product->discount / 100))                 :$basePrice;

            $safeQuantity = min($item->quantity,
                max(1, $variantStock)
            );

            if ($variantStock <= 0) {$safeQuantity = 0;
            }

            $alpineItems[] = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'price' => $finalPrice,
                'quantity' => $safeQuantity,
                'maxStock' => $variantStock,
                'selected' => $variantStock > 0,                 'image' =>$variantImage,
                'basePrice' => $basePrice,
                'isSyncing' => false,
            ];
        }
    @endphp

    <div x-data="{
            selectAll: true,
            items: @js($alpineItems),
            totalAmount: 0,
            targetTotalAmount: 0,
            slideDirection: 'up',
            animationFrame: null,

            get formattedTotalChars() {
                return this.totalAmount
                    .toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })
                    .split('')
                    .reverse();
            },

            get selectedCount() {
                return this.items.filter(i => i.selected).length;
            },

            get activeItems() {
                return this.items.filter(i => i.maxStock > 0);
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

            syncFromEvent(detail) {
                let item = this.items.find(i => i.id === detail.id);
                if (item && item.user_id === detail.user_id && item.quantity !== detail.quantity) {
                    item.quantity = detail.quantity;
                    this.calculateTotal();
                }
            },

            toggleAll() {
                this.activeItems.forEach(i => i.selected = this.selectAll);
                this.calculateTotal();
            },

            checkItem() {
                this.selectAll = this.activeItems.length > 0 && this.activeItems.every(i => i.selected);
                this.calculateTotal();
            },

            calculateTotal() {
                let newTotal = this.items.filter(i => i.selected).reduce((sum, i) => {
                    let q = parseInt(i.quantity);
                    if (isNaN(q) || q < 1) q = 1;
                    return sum + (i.price * q);
                }, 0);

                newTotal = Math.round(newTotal * 100) / 100;

                if (newTotal === this.targetTotalAmount) return;

                if (newTotal > this.targetTotalAmount) {
                    this.slideDirection = 'up';
                } else if (newTotal < this.targetTotalAmount) {
                    this.slideDirection = 'down';
                }

                this.animateTotal(newTotal);
            },

            animateTotal(newTotal) {
                if (this.totalAmount === 0 && this.targetTotalAmount === 0) {
                    this.totalAmount = newTotal;
                    this.targetTotalAmount = newTotal;
                    return;
                }

                this.targetTotalAmount = newTotal;
                let start = this.totalAmount;
                let end = newTotal;
                let duration = 300; 
                let startTime = null;

                if (this.animationFrame) cancelAnimationFrame(this.animationFrame);

                const animate = (currentTime) => {
                    if (!startTime) startTime = currentTime;
                    let progress = Math.min((currentTime - startTime) / duration, 1);
                    
                    this.totalAmount = Number((start + (end - start) * progress).toFixed(2));

                    if (progress < 1) {
                        this.animationFrame = requestAnimationFrame(animate);
                    } else {
                        this.totalAmount = end;
                    }
                };
                
                this.animationFrame = requestAnimationFrame(animate);
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

            init() {
                this.checkItem();
            }
        }"
        x-on:cart-updated.window="syncFromEvent($event.detail)"
        class="bg-surface-subtle font-sans antialiased text-text-main min-h-screen relative pb-16"
    >

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Progress Steps -->
            <div class="flex items-center justify-center mb-10 overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden text-sm">
                <div class="flex items-center space-x-2 sm:space-x-4 shrink-0">
                    <div class="flex items-center text-primary-dark font-bold">
                        <span class="w-6 h-6 rounded-full bg-primary-dark text-surface flex items-center justify-center text-[11px] mr-2 shadow-xs">1</span>
                        <span class="tracking-wide">Cart</span>
                    </div>
                    <svg class="w-4 h-4 text-border-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                    <div class="flex items-center text-text-muted font-medium">
                        <span class="w-6 h-6 rounded-full bg-surface border border-border-subtle text-text-muted flex items-center justify-center text-[11px] mr-2">2</span>
                        <span>Place Order</span>
                    </div>
                    <svg class="w-4 h-4 text-border-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                    <div class="flex items-center text-text-muted font-medium">
                        <span class="w-6 h-6 rounded-full bg-surface border border-border-subtle text-text-muted flex items-center justify-center text-[11px] mr-2">3</span>
                        <span>Pay</span>
                    </div>
                    <svg class="w-4 h-4 text-border-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                    <div class="flex items-center text-text-muted font-medium">
                        <span class="w-6 h-6 rounded-full bg-surface border border-border-subtle text-text-muted flex items-center justify-center text-[11px] mr-2">4</span>
                        <span>Order Complete</span>
                    </div>
                </div>
            </div>

            <!-- Main Cart Container -->
            <div class="w-full mb-12 relative">
                <!-- Action Header -->
                <div class="bg-surface border border-border-subtle p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between mb-6 rounded-2xl shadow-sm relative">
                    <!-- Select All Checkbox -->
                    <div class="flex items-center">
                        <input type="checkbox" x-model="selectAll" x-on:change="toggleAll()" class="w-5 h-5 text-primary border-border-subtle rounded focus:ring-primary mr-4 cursor-pointer transition-colors shadow-xs">
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-text-main leading-tight">ALL ITEMS</span>
                            <span class="text-xs text-text-muted font-medium"><span x-text="items.length"></span> items in cart</span>
                        </div>
                    </div>

                    <!-- Total Price & Checkout -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8 mt-4 sm:mt-0 w-full sm:w-auto">
                        <div class="text-right flex flex-row sm:flex-col justify-between sm:justify-end items-center sm:items-end w-full sm:w-auto border-t sm:border-t-0 border-border-subtle pt-3 sm:pt-0">
                            <span class="text-[11px] text-text-muted font-bold uppercase tracking-wider mb-0 sm:mb-0.5">Total Amount</span>
                            
                            <!-- Sliding Padlock Animation Wrapper for Total Amount -->
                            <div class="flex flex-row-reverse justify-start items-center h-8 sm:h-10 overflow-hidden font-black text-primary text-2xl sm:text-3xl leading-none tabular-nums tracking-wide">
                                <template x-for="(char, index) in formattedTotalChars" :key="index">
                                    <div class="relative flex items-center justify-center h-full overflow-hidden">
                                        <span class="invisible" x-text="char"></span>
                                        <template x-if="slideDirection === 'up'">
                                            <template x-for="c in [char]" :key="'up-' + index + '-' + c">
                                                <span class="absolute inset-0 flex items-center justify-center" x-text="c" x-transition:enter="transition-transform ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition-transform ease-in duration-300" x-transition:leave-start="translate-y-0" x-transition:leave-end="-translate-y-full"></span>
                                            </template>
                                        </template>
                                        <template x-if="slideDirection === 'down'">
                                            <template x-for="c in [char]" :key="'down-' + index + '-' + c">
                                                <span class="absolute inset-0 flex items-center justify-center" x-text="c" x-transition:enter="transition-transform ease-out duration-300" x-transition:enter-start="-translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition-transform ease-in duration-300" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"></span>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                                <span class="mr-1">₱</span>
                            </div>
                        </div>

                        <button type="button" :disabled="selectedCount === 0" :class="selectedCount === 0 ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle shadow-none' : 'bg-primary hover:bg-primary-dark text-white border border-transparent shadow-md hover:shadow-lg'" class="px-8 py-3.5 font-bold rounded-xl transition-all duration-300 flex items-center justify-center shrink-0 w-full sm:w-auto h-12">
                            <span x-text="'Checkout (' + selectedCount + ')'"></span>
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Database Cart Items Loop -->
                <div class="space-y-4">
                    @forelse($cartItems as $index => $item)
                        @php
                            $product =$item->product;
                            $sellerName =$product->user->sellerProfile->business_name ?? 'Official Store';
                            $data =$alpineItems[$index] ?? [];$variantStock = $data['maxStock'] ?? $product->stock_quantity;
                            $variantImage = $data['image'] ?? '';$basePrice = $data['basePrice'] ?? $product->price;
                        @endphp

                        <div :class="items[{{ $index }}].selected ? 'border-primary ring-1 ring-primary/20 bg-surface' : 'border-border-subtle bg-surface hover:border-primary/40'" class="border p-5 flex flex-col md:flex-row md:items-start gap-6 rounded-xl shadow-xs transition-all group">
                            <!-- Checkbox & Image -->
                            <div class="flex items-center gap-4 shrink-0">
                                <input type="checkbox" x-model="items[{{ $index }}].selected" x-on:change="checkItem()" :disabled="items[{{ $index }}].maxStock <= 0" class="w-5 h-5 text-primary border-border-subtle rounded focus:ring-primary cursor-pointer shrink-0 mt-5 md:mt-0 transition-colors shadow-xs disabled:opacity-50 disabled:cursor-not-allowed">
                                <div class="w-24 h-24 sm:w-28 sm:h-28 bg-surface-subtle border border-border-subtle relative rounded-lg overflow-hidden shrink-0">
                                    <img src="{{ $variantImage }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    @if ($variantStock <= 5 &&$variantStock > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-danger text-surface text-[10px] text-center font-bold py-0.5 shadow-sm">Only {{ $variantStock }} Left</div>
                                    @elseif ($variantStock == 0)
                                        <div class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
                                            <span class="bg-text-main text-white text-[10px] font-bold px-2 py-1 rounded shadow-md uppercase tracking-wider">Out of Stock</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Item Details -->
                            <div class="flex-1 mt-1 min-w-0">
                                <div class="text-xs font-bold text-primary-dark uppercase mb-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                    {{ $sellerName }}
                                </div>
                                <a href="{{ route('product.show', $product->id) }}" class="text-sm sm:text-base font-bold text-text-main hover:text-primary transition-colors cursor-pointer mb-2 line-clamp-2 block pr-4">{{ $product->name }}</a>
                                
                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                    @if ($item->variant)
                                        <div class="bg-surface-subtle border border-border-subtle px-3 py-1.5 rounded-md text-text-main flex items-center gap-1.5 text-xs font-semibold">
                                            <span>{{ $item->variant }}</span>
                                            @if ($item->sub_variant)
                                                <span class="text-text-muted">|</span>
                                                <span>{{ $item->sub_variant }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($product->discount > 0)
                                        <span class="text-[10px] font-bold px-2 py-1 bg-primary/10 text-primary border border-primary/20 rounded-md tracking-wide">{{ $product->discount }}% OFF</span>
                                    @endif
                                    
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-md {{ $variantStock > 5 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                        <span x-text="items[{{ $index }}].maxStock > 0 ? items[{{ $index }}].maxStock + ' in stock' : 'Unavailable'"></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Price & Actions -->
                            <div class="flex items-center justify-between md:justify-end gap-6 mt-4 md:mt-0 md:w-1/3 md:pt-1">
                                <div class="flex flex-col text-right">
                                    @if ($product->discount > 0)
                                        <span class="text-xs text-text-muted line-through font-bold">₱{{ number_format($basePrice, 2) }}</span>
                                    @endif
                                    <span class="text-lg sm:text-xl font-bold" :class="items[{{ $index }}].maxStock > 0 ? 'text-primary' : 'text-text-muted'">
                                        ₱<span x-text="(items[{{ $index }}].price * (parseInt(items[{{$index }}].quantity) || 1)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </span>
                                </div>

                                <div class="flex flex-col gap-3 items-end">
                                    <template x-if="items[{{ $index }}].maxStock <= 0">
                                        <div class="px-3 py-1.5 bg-surface-subtle text-text-muted text-xs font-bold rounded-lg border border-border-subtle cursor-not-allowed">Out of Stock</div>
                                    </template>

                                    <!-- Stepper Input (Readonly to prevent manual typing overrides) -->
                                    <template x-if="items[{{ $index }}].maxStock > 0">
                                        <div class="flex items-center border border-border-subtle rounded-md bg-surface shadow-xs h-[34px] overflow-hidden w-[104px] shrink-0"
                                             :class="{'opacity-50': items[{{ $index }}].isSyncing}">
                                            <button type="button" x-on:click="decrement({{ $index }})" :disabled="items[{{ $index }}].isSyncing" class="w-8 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-r border-border-subtle outline-none shrink-0" :class="{'cursor-not-allowed': items[{{ $index }}].isSyncing, 'cursor-pointer': !items[{{$index }}].isSyncing}"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"></path></svg></button>
                                            
                                            <!-- Readonly input for forced tap-only interaction -->
                                            <input type="number" readonly x-model="items[{{ $index }}].quantity" class="flex-1 w-full h-full text-center border-none bg-transparent text-sm font-bold text-text-main focus:ring-0 p-0 outline-none select-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                            
                                            <button type="button" x-on:click="increment({{ $index }})" :disabled="items[{{ $index }}].isSyncing" class="w-8 text-text-main hover:text-primary hover:bg-surface-subtle transition-colors h-full flex items-center justify-center border-l border-border-subtle outline-none shrink-0" :class="{'cursor-not-allowed': items[{{ $index }}].isSyncing, 'cursor-pointer': !items[{{$index }}].isSyncing}"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg></button>
                                        </div>
                                    </template>

                                    <!-- Action Icons -->
                                    <div class="flex items-center gap-1.5 text-text-muted mt-1">
                                        <button class="p-1.5 hover:text-primary hover:bg-primary/10 rounded-md transition-colors outline-none" title="Find Similar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></button>
                                        <button class="p-1.5 hover:text-primary hover:bg-primary/10 rounded-md transition-colors outline-none" title="Move to Wishlist"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg></button>
                                        
                                        <!-- Delete Form -->
                                        <form action="{{ route('cart.destroy', $item->id) }}" method="POST" class="inline m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 hover:text-danger hover:bg-danger/10 rounded-md transition-colors outline-none cursor-pointer" title="Delete Item"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-surface border border-border-subtle p-10 text-center text-text-muted rounded-xl shadow-xs">
                            <div class="w-16 h-16 bg-surface-subtle border border-border-subtle rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                                <svg class="w-8 h-8 text-border-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <p class="text-xl font-bold text-text-main tracking-tight">Your cart is currently empty</p>
                            <p class="text-sm mt-2 mb-6 font-medium">Looks like you haven't added anything yet.</p>
                            <a href="{{ route('home') }}" class="inline-block px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary-dark transition-all shadow-md hover:shadow-lg">Continue Shopping</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- You Might Like Section -->
            <div class="mt-16 border-t border-border-subtle/80 pt-10">
                <h2 class="text-center text-xl sm:text-2xl font-black font-sans text-text-main mb-8">
                    You Might Like to Fill it With
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 lg:gap-5">
                    @forelse($suggestedProducts as $product)
                        <x-storefront.seller-product-card :product="$product" />
                    @empty
                        <div class="col-span-full text-center text-text-muted py-8 text-sm font-medium">
                            No suggestions available at this time.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection