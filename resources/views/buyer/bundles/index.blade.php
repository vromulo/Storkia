@extends('layouts.app', ['title' => 'Bundle Deals - Storkia'])

@section('content')
@php
    $bundles = $bundles ?? collect();
    $categoriesCount = $categoriesCount ?? [];
    $selectedCategory = $selectedCategory ?? null;
    $bundleTypeFilter = $bundleTypeFilter ?? 'all';
    $totalBundlesAllCategories = array_sum($categoriesCount);
@endphp

<div x-data="{
    builderOpen: false,
    activeBundle: null,
    selections: {},
    freeGiftVariant: '',
    freeGiftSubVariant: '',
    redirectMode: 'cart',
    builderError: '',
    isSubmittingBundle: false,

    openBundleBuilder(bundleData) {
        this.activeBundle = bundleData;
        this.builderError = '';
        this.redirectMode = 'cart';
        let initSelections = {};
        let remainingNeeded = Number(bundleData.required_quantity || 2);

        (bundleData.products || []).forEach((prod, idx) => {
            let defaultVar = '';
            let defaultSub = '';
            let unitPrice = Number(prod.price || 0);
            let maxStock = Number(prod.stock || 0);
            let varImg = prod.image || '';

            if (prod.has_variants && Array.isArray(prod.variants) && prod.variants.length > 0) {
                const firstV = prod.variants[0];
                defaultVar = firstV.name || '';
                unitPrice = Number(firstV.price || unitPrice);
                maxStock = Number(firstV.stock || maxStock);
                varImg = firstV.image || varImg;

                if (Array.isArray(firstV.subs) && firstV.subs.length > 0) {
                    const firstS = firstV.subs[0];
                    defaultSub = firstS.name || '';
                    unitPrice = Number(firstS.price || unitPrice);
                    maxStock = Number(firstS.stock || maxStock);
                }
            }

            let assignQty = 0;
            if (remainingNeeded > 0 && maxStock > 0) {
                assignQty = idx === (bundleData.products.length - 1)
                    ? Math.min(maxStock, remainingNeeded)
                    : Math.min(maxStock, 1);
                remainingNeeded = Math.max(0, remainingNeeded - assignQty);
            }

            initSelections[prod.id] = {
                product_id: prod.id,
                quantity: assignQty,
                variant: defaultVar,
                sub_variant: defaultSub,
                unit_price: unitPrice,
                max_stock: maxStock,
                display_image: varImg
            };
        });

        this.selections = initSelections;

        if (bundleData.free_gift_product) {
            const fg = bundleData.free_gift_product;
            this.freeGiftVariant = '';
            this.freeGiftSubVariant = '';
            if (fg.has_variants && Array.isArray(fg.variants) && fg.variants.length > 0) {
                this.freeGiftVariant = fg.variants[0].name || '';
                if (Array.isArray(fg.variants[0].subs) && fg.variants[0].subs.length > 0) {
                    this.freeGiftSubVariant = fg.variants[0].subs[0].name || '';
                }
            }
        }

        this.builderOpen = true;
    },

    selectProductVariant(prod, varObj) {
        const entry = this.selections[prod.id];
        if (!entry) return;
        entry.variant = varObj.name;
        entry.display_image = varObj.image || prod.image;

        if (Array.isArray(varObj.subs) && varObj.subs.length > 0) {
            const firstSub = varObj.subs[0];
            entry.sub_variant = firstSub.name;
            entry.unit_price = Number(firstSub.price || varObj.price || prod.price);
            entry.max_stock = Number(firstSub.stock || 0);
        } else {
            entry.sub_variant = '';
            entry.unit_price = Number(varObj.price || prod.price);
            entry.max_stock = Number(varObj.stock || 0);
        }

        if (entry.quantity > entry.max_stock) {
            entry.quantity = entry.max_stock;
        }
    },

    selectProductSubVariant(prod, varObj, subObj) {
        const entry = this.selections[prod.id];
        if (!entry) return;
        entry.sub_variant = subObj.name;
        entry.unit_price = Number(subObj.price || varObj.price || prod.price);
        entry.max_stock = Number(subObj.stock || 0);
        if (entry.quantity > entry.max_stock) {
            entry.quantity = entry.max_stock;
        }
    },

    getActiveVariantObj(prod) {
        const entry = this.selections[prod.id];
        if (!entry || !Array.isArray(prod.variants)) return null;
        return prod.variants.find(v => v.name === entry.variant) || null;
    },

    adjustQty(prodId, delta) {
        const entry = this.selections[prodId];
        if (!entry) return;
        const next = Number(entry.quantity || 0) + delta;
        if (next >= 0 && next <= Number(entry.max_stock || 0)) {
            entry.quantity = next;
            this.builderError = '';
        }
    },

    get totalSelectedQty() {
        return Object.values(this.selections).reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    },

    get requiredQty() {
        return this.activeBundle ? Number(this.activeBundle.required_quantity || 2) : 2;
    },

    get isQuotaMet() {
        return this.totalSelectedQty >= this.requiredQty;
    },

    get rawBundleSubtotal() {
        return Object.values(this.selections).reduce((sum, item) => {
            return sum + (Number(item.unit_price || 0) * Number(item.quantity || 0));
        }, 0);
    },

    get estimatedBundleSavings() {
        if (!this.activeBundle || !this.isQuotaMet) return 0;
        if (this.activeBundle.bundle_type === 'percentage_off') {
            const pct = Number(this.activeBundle.discount_percentage || 0);
            return Math.round((this.rawBundleSubtotal * (pct / 100)) * 100) / 100;
        }
        if (this.activeBundle.bundle_type === 'buy_x_get_y' && this.activeBundle.free_gift_product) {
            return Number(this.activeBundle.free_gift_product.price || 0);
        }
        return 0;
    },

    get estimatedPayableSubtotal() {
        if (!this.activeBundle) return 0;
        if (this.activeBundle.bundle_type === 'percentage_off' && this.isQuotaMet) {
            return Math.max(0, Math.round((this.rawBundleSubtotal - this.estimatedBundleSavings) * 100) / 100);
        }
        return Math.round(this.rawBundleSubtotal * 100) / 100;
    },

    submitBundleForm(mode, isAuth) {
        if (!isAuth) {
            window.location.href = '{{ route('login') }}';
            return;
        }
        this.builderError = '';
        if (!this.isQuotaMet) {
            this.builderError = 'Please select at least ' + this.requiredQty + ' item(s) to unlock this bundle deal.';
            return;
        }
        for (const prod of (this.activeBundle?.products || [])) {
            const sel = this.selections[prod.id];
            if (sel && sel.quantity > 0 && prod.has_variants) {
                if (!sel.variant) {
                    this.builderError = 'Please select a ' + (prod.variant_title || 'variant') + ' for ' + prod.name + '.';
                    return;
                }
                const vObj = this.getActiveVariantObj(prod);
                if (vObj && Array.isArray(vObj.subs) && vObj.subs.length > 0 && !sel.sub_variant) {
                    this.builderError = 'Please select a ' + (prod.sub_variant_title || 'sub-variant') + ' for ' + prod.name + '.';
                    return;
                }
            }
        }
        this.redirectMode = mode;
        this.isSubmittingBundle = true;
        this.$nextTick(() => {
            this.$refs.bundleCartForm.submit();
        });
    }
}" class="bg-surface font-sans antialiased text-text-main min-h-screen pb-16">

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <x-ui.breadcrumbs :items="['Bundle Deals']" />

        <!-- Hero Header -->
        <header class="rounded-3xl border border-brand-light bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-8 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="space-y-2 max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                        Multi-Item Store Promotions
                    </span>
                    <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                        Official Store Bundle Deals
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        Mix and match qualifying products, choose your exact variants and sizes, and unlock automatic <strong>Buy N Get % Off</strong> or <strong>Buy X Get a Free Gift (₱0.00)</strong> savings at checkout.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a href="{{ route('deals.index') }}" class="px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold border border-white/20 transition-colors">
                        ⚡ View Super Deals
                    </a>
                    <a href="{{ route('cart.index') }}" class="px-4 py-2.5 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-bold shadow-xs transition-colors">
                        Open Shopping Cart &rarr;
                    </a>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
                @foreach($errors->all() as $err)
                    <p class="font-semibold">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <!-- Category & Bundle Mechanic Filter Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-2 border-b border-border-subtle">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ route('bundles.index', array_filter(['type' => $bundleTypeFilter !== 'all' ? $bundleTypeFilter : null])) }}"
                   class="px-3.5 py-1.5 rounded-full text-xs font-bold border transition-colors whitespace-nowrap {{ $selectedCategory === null ? 'bg-primary-dark text-white border-primary-dark' : 'bg-surface text-text-main border-border-subtle hover:border-primary' }}">
                    All Categories ({{ $totalBundlesAllCategories }})
                </a>
                @foreach($categoriesCount as $catName => $cnt)
                    <a href="{{ route('bundles.index', array_filter(['category' => $catName, 'type' => $bundleTypeFilter !== 'all' ? $bundleTypeFilter : null])) }}"
                       class="px-3.5 py-1.5 rounded-full text-xs font-bold border transition-colors whitespace-nowrap {{ $selectedCategory === $catName ? 'bg-primary-dark text-white border-primary-dark' : 'bg-surface text-text-main border-border-subtle hover:border-primary' }}">
                        {{ $catName }} ({{ $cnt }})
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5 shrink-0 text-xs">
                @foreach([
                    'all'            => 'All Mechanics',
                    'percentage_off' => 'Buy N Get % Off',
                    'buy_x_get_y'    => 'Buy X Get Free Gift',
                ] as $mKey => $mLabel)
                    <a href="{{ route('bundles.index', array_filter(['category' => $selectedCategory, 'type' => $mKey !== 'all' ? $mKey : null])) }}"
                       class="px-3 py-1.5 rounded-xl font-bold border transition-colors {{ $bundleTypeFilter === $mKey ? 'bg-primary text-white border-primary' : 'bg-surface-subtle text-text-muted border-border-subtle hover:text-text-main' }}">
                        {{ $mLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Bundles Grid -->
        @if($bundles->isEmpty())
            <div class="bg-surface-subtle/50 rounded-3xl border border-border-subtle p-16 text-center space-y-3">
                <h2 class="text-base font-bold text-text-main">No active Bundle Deals found for this filter</h2>
                <p class="text-xs text-text-muted max-w-md mx-auto">
                    Check back soon or browse our limited-time Super Deals and store vouchers.
                </p>
                <a href="{{ route('home') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                    Explore All Products
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($bundles as $bundle)
                    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs hover:border-primary/40 transition-all overflow-hidden flex flex-col justify-between">
                        <div>
                            <!-- Bundle Card Top Header -->
                            <div class="p-5 bg-surface-subtle/60 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase {{ $bundle->bundle_type === 'percentage_off' ? 'bg-primary text-white' : 'bg-purple-700 text-white' }}">
                                        @if($bundle->bundle_type === 'percentage_off')
                                            Buy {{ $bundle->required_quantity }} Get {{ rtrim(rtrim(number_format((float) $bundle->discount_percentage, 1), '0'), '.') }}% OFF
                                        @else
                                            Buy {{ $bundle->required_quantity }} Get 1 Free Gift
                                        @endif
                                    </span>
                                    <div>
                                        <h2 class="text-base font-bold text-primary-dark leading-tight">{{ $bundle->title }}</h2>
                                        <a href="{{ route('sellers.show', $bundle->seller->id) }}" class="text-xs font-semibold text-text-muted hover:text-primary">
                                            Store: {{ $bundle->seller->business_name }} ({{ $bundle->category }})
                                        </a>
                                    </div>
                                </div>
                                <span class="text-[11px] font-semibold text-text-muted">
                                    Ends {{ \Carbon\Carbon::parse($bundle->ends_at)->format('M d, Y') }}
                                </span>
                            </div>

                            <!-- Qualifying Products Preview Strip -->
                            <div class="p-5 space-y-4">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold uppercase tracking-wider text-text-muted">
                                        Qualifying Products ({{ count($bundle->products) }}) — Pick Any {{ $bundle->required_quantity }} Item(s)
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    @foreach(array_slice($bundle->products, 0, 6) as $bProd)
                                        <div class="p-2.5 rounded-2xl border border-border-subtle bg-surface-subtle/30 flex flex-col justify-between">
                                            <a href="{{ $bProd['url'] }}" class="block">
                                                <div class="aspect-square rounded-xl bg-white border border-border-subtle overflow-hidden flex items-center justify-center p-1.5 mb-2">
                                                    @if($bProd['image'])
                                                        <img src="{{ $bProd['image'] }}" alt="{{ $bProd['name'] }}" class="w-full h-full object-contain">
                                                    @endif
                                                </div>
                                                <h3 class="text-xs font-semibold text-text-main line-clamp-1">{{ $bProd['name'] }}</h3>
                                            </a>
                                            <div class="mt-1 flex items-center justify-between text-[11px]">
                                                <span class="font-bold text-primary">₱{{ number_format((float) $bProd['price'], 2) }}</span>
                                                @if($bProd['has_variants'])
                                                    <span class="px-1.5 py-0.5 rounded bg-brand-light/50 text-primary-dark text-[9px] font-bold">
                                                        {{ count($bProd['variants']) }} {{ $bProd['variant_title'] }}s
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if($bundle->bundle_type === 'buy_x_get_y' && $bundle->free_gift_product)
                                    <div class="p-3.5 rounded-2xl bg-purple-50 border border-purple-200 flex items-center justify-between gap-3 text-xs">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($bundle->free_gift_product['image'])
                                                <img src="{{ $bundle->free_gift_product['image'] }}" class="w-12 h-12 rounded-xl object-contain bg-white border border-purple-200 p-1 shrink-0">
                                            @endif
                                            <div class="min-w-0">
                                                <span class="text-[10px] font-extrabold uppercase text-purple-800 block">Included Free Gift (100% Off)</span>
                                                <span class="font-bold text-text-main truncate block">{{ $bundle->free_gift_product['name'] }}</span>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-xs line-through text-text-muted block">₱{{ number_format((float) $bundle->free_gift_product['price'], 2) }}</span>
                                            <strong class="text-sm font-extrabold text-purple-800">FREE (₱0.00)</strong>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Card Footer Action -->
                        <div class="p-4 sm:px-5 bg-surface-subtle/40 border-t border-border-subtle flex items-center justify-between gap-3">
                            <span class="text-xs text-text-muted">
                                Select variants, sizes &amp; quantities in the bundle builder
                            </span>
                            <button type="button"
                                    @click="openBundleBuilder(@js($bundle))"
                                    class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                Select Variants &amp; Build Bundle
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Interactive Variant-Aware Bundle Builder Modal -->
    <template x-teleport="body">
        <div x-show="builderOpen && activeBundle"
             x-cloak
             @keydown.escape.window="builderOpen = false"
             class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-5 bg-black/65 backdrop-blur-xs">
            <div @click.away="builderOpen = false"
                 class="bg-surface rounded-3xl max-w-4xl w-full p-5 sm:p-7 shadow-2xl border border-border-subtle flex flex-col max-h-[92vh] space-y-4">

                <!-- Modal Header -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-border-subtle">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase"
                                  x-text="activeBundle?.bundle_type === 'percentage_off' ? ('Buy ' + requiredQty + ' Get ' + activeBundle?.discount_percentage + '% OFF') : ('Buy ' + requiredQty + ' Get Free Gift')"></span>
                            <h3 class="text-base sm:text-lg font-bold text-primary-dark" x-text="activeBundle?.title"></h3>
                        </div>
                        <p class="text-xs text-text-muted mt-0.5">
                            Choose your preferred variants, sub-variants, and quantities below. Minimum required: <strong class="text-text-main" x-text="requiredQty + ' item(s)'"></strong>.
                        </p>
                    </div>
                    <button type="button" @click="builderOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <!-- Progress Bar -->
                <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-text-main">Bundle Progress:</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold"
                              :class="isQuotaMet ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'"
                              x-text="totalSelectedQty + ' / ' + requiredQty + ' item(s) selected'"></span>
                    </div>
                    <div class="text-right">
                        <span class="text-text-muted">Estimated Savings:</span>
                        <strong class="text-primary ml-1" x-text="'-₱' + estimatedBundleSavings.toFixed(2)"></strong>
                    </div>
                </div>

                <template x-if="builderError">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-danger text-xs font-bold" x-text="builderError"></div>
                </template>

                <!-- Scrollable Qualifying Products & Variant Selectors -->
                <form x-ref="bundleCartForm"
                      :action="`{{ url('/bundles') }}/${activeBundle?.id}/add-to-cart`"
                      method="POST"
                      class="flex-1 overflow-y-auto space-y-4 pr-1 custom-scrollbar">
                    @csrf
                    <input type="hidden" name="redirect_to" :value="redirectMode">

                    @php $selIdx = 0; @endphp
                    <template x-for="(prod, pIdx) in (activeBundle?.products || [])" :key="prod.id">
                        <div class="p-4 rounded-2xl border transition-all space-y-3 text-xs"
                             :class="(selections[prod.id]?.quantity || 0) > 0 ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface'">

                            <template x-if="(selections[prod.id]?.quantity || 0) > 0">
                                <div>
                                    <input type="hidden" :name="`selections[${pIdx}][product_id]`" :value="prod.id">
                                    <input type="hidden" :name="`selections[${pIdx}][quantity]`" :value="selections[prod.id].quantity">
                                    <input type="hidden" :name="`selections[${pIdx}][variant]`" :value="selections[prod.id].variant">
                                    <input type="hidden" :name="`selections[${pIdx}][sub_variant]`" :value="selections[prod.id].sub_variant">
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <img :src="selections[prod.id]?.display_image || prod.image"
                                         :alt="prod.name"
                                         class="w-16 h-16 rounded-xl object-contain bg-white border border-border-subtle p-1 shrink-0">
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-sm text-text-main truncate" x-text="prod.name"></h4>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="font-extrabold text-primary text-sm" x-text="'₱' + Number(selections[prod.id]?.unit_price || prod.price).toFixed(2)"></span>
                                            <span class="text-[11px] text-text-muted" x-text="(selections[prod.id]?.max_stock || prod.stock) + ' in stock'"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quantity Stepper -->
                                <div class="flex items-center gap-3 self-end sm:self-center shrink-0">
                                    <span class="text-[11px] font-bold text-text-muted uppercase">Qty:</span>
                                    <div class="flex items-center border border-border-subtle rounded-xl bg-white h-9 overflow-hidden">
                                        <button type="button"
                                                @click="adjustQty(prod.id, -1)"
                                                class="w-8 h-full flex items-center justify-center font-bold text-text-main hover:bg-surface-subtle cursor-pointer">-</button>
                                        <span class="w-10 text-center font-bold text-text-main" x-text="selections[prod.id]?.quantity || 0"></span>
                                        <button type="button"
                                                @click="adjustQty(prod.id, 1)"
                                                class="w-8 h-full flex items-center justify-center font-bold text-text-main hover:bg-surface-subtle cursor-pointer">+</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Variant & Sub-Variant Chips -->
                            <template x-if="prod.has_variants && Array.isArray(prod.variants) && prod.variants.length > 0">
                                <div class="pt-3 border-t border-border-subtle/70 space-y-2.5">
                                    <div>
                                        <span class="text-[10px] font-extrabold uppercase text-text-muted block mb-1.5"
                                              x-text="'Select ' + (prod.variant_title || 'Variant') + ':'"></span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="vObj in prod.variants" :key="vObj.name">
                                                <button type="button"
                                                        @click="selectProductVariant(prod, vObj)"
                                                        :class="selections[prod.id]?.variant === vObj.name
                                                            ? 'border-primary bg-primary text-white font-bold shadow-2xs'
                                                            : 'border-border-subtle bg-white text-text-main hover:border-primary/50'"
                                                        class="px-3 py-1.5 rounded-xl border text-xs transition-all cursor-pointer inline-flex items-center gap-1.5">
                                                    <span x-text="vObj.name"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <template x-if="getActiveVariantObj(prod) && Array.isArray(getActiveVariantObj(prod).subs) && getActiveVariantObj(prod).subs.length > 0">
                                        <div>
                                            <span class="text-[10px] font-extrabold uppercase text-text-muted block mb-1.5"
                                                  x-text="'Select ' + (prod.sub_variant_title || 'Sub-Variant') + ':'"></span>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="sObj in getActiveVariantObj(prod).subs" :key="sObj.name">
                                                    <button type="button"
                                                            @click="selectProductSubVariant(prod, getActiveVariantObj(prod), sObj)"
                                                            :class="selections[prod.id]?.sub_variant === sObj.name
                                                                ? 'border-primary-dark bg-primary-dark text-white font-bold'
                                                                : 'border-border-subtle bg-white text-text-main hover:border-primary/50'"
                                                            class="px-3 py-1.5 rounded-xl border text-xs transition-all cursor-pointer">
                                                        <span x-text="sObj.name + ' (₱' + Number(sObj.price).toFixed(2) + ')'"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Free Gift Variant Selector (If Buy X Get Y) -->
                    <template x-if="activeBundle?.bundle_type === 'buy_x_get_y' && activeBundle?.free_gift_product">
                        <div class="p-4 rounded-2xl bg-purple-50 border-2 border-purple-200 space-y-3 text-xs">
                            <input type="hidden" name="free_gift_variant" :value="freeGiftVariant">
                            <input type="hidden" name="free_gift_sub_variant" :value="freeGiftSubVariant">

                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img :src="activeBundle.free_gift_product.image" class="w-14 h-14 rounded-xl object-contain bg-white border border-purple-200 p-1">
                                    <div>
                                        <span class="px-2 py-0.5 rounded bg-purple-700 text-white text-[10px] font-extrabold uppercase">Free Gift Item (Qty: 1)</span>
                                        <h4 class="font-bold text-sm text-text-main mt-1" x-text="activeBundle.free_gift_product.name"></h4>
                                    </div>
                                </div>
                                <strong class="text-base font-extrabold text-purple-800">₱0.00</strong>
                            </div>

                            <template x-if="activeBundle.free_gift_product.has_variants">
                                <div class="space-y-2 pt-2 border-t border-purple-200">
                                    <span class="text-[10px] font-bold uppercase text-purple-900 block">Select Free Gift Option:</span>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="gv in activeBundle.free_gift_product.variants" :key="gv.name">
                                            <button type="button"
                                                    @click="freeGiftVariant = gv.name; freeGiftSubVariant = (Array.isArray(gv.subs) && gv.subs.length > 0) ? gv.subs[0].name : ''"
                                                    :class="freeGiftVariant === gv.name ? 'bg-purple-700 text-white font-bold' : 'bg-white text-text-main border-purple-200'"
                                                    class="px-3 py-1.5 rounded-xl border text-xs cursor-pointer"
                                                    x-text="gv.name"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </form>

                <!-- Modal Footer Totals & Action Buttons -->
                <div class="pt-4 border-t border-border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                    <div class="space-y-0.5">
                        <div class="text-text-muted">
                            Regular Price: <span class="line-through font-mono" x-text="'₱' + rawBundleSubtotal.toFixed(2)"></span>
                            &bull; Bundle Savings: <strong class="text-price font-mono" x-text="'-₱' + estimatedBundleSavings.toFixed(2)"></strong>
                        </div>
                        <div class="text-sm font-bold text-text-main">
                            Estimated Bundle Subtotal: <strong class="text-xl font-serif font-extrabold text-primary" x-text="'₱' + estimatedPayableSubtotal.toFixed(2)"></strong>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <button type="button"
                                @click="builderOpen = false"
                                class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="button"
                                :disabled="!isQuotaMet || isSubmittingBundle"
                                @click="submitBundleForm('cart', {{ auth()->check() ? 'true' : 'false' }})"
                                :class="(!isQuotaMet || isSubmittingBundle) ? 'opacity-50 cursor-not-allowed bg-gray-300 text-gray-600' : 'bg-primary/15 hover:bg-primary text-primary hover:text-white border border-primary/30 cursor-pointer'"
                                class="px-5 py-2.5 rounded-xl font-bold transition-colors">
                            Add Bundle to Cart
                        </button>
                        <button type="button"
                                :disabled="!isQuotaMet || isSubmittingBundle"
                                @click="submitBundleForm('checkout', {{ auth()->check() ? 'true' : 'false' }})"
                                :class="(!isQuotaMet || isSubmittingBundle) ? 'opacity-50 cursor-not-allowed bg-gray-300 text-gray-600' : 'bg-primary hover:bg-primary-dark text-white shadow-sm cursor-pointer'"
                                class="px-6 py-2.5 rounded-xl font-bold transition-colors">
                            Buy Bundle Now &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection