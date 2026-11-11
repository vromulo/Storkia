@extends('layouts.seller', ['title' => 'Storkia - Super Deals, Store Sales & Bundles'])

@section('content')
@php
    $isOnVacation = (bool) ($profile->is_on_vacation ?? false);
    $deals = $deals ?? collect();
    $bundles = $bundles ?? collect();

    $productVariantsMap = [];
    foreach ($products as $prod) {
        $raw = $prod->raw_variants;
        $vars = [];
        if (is_array($raw) && !empty($raw['items']) && is_array($raw['items'])) {
            foreach ($raw['items'] as $vItem) {
                if (!empty($vItem['is_archived']) || ($vItem['status'] ?? 'Approved') !== 'Approved') {
                    continue;
                }
                $subs = [];
                if (!empty($vItem['subs']) && is_array($vItem['subs'])) {
                    foreach ($vItem['subs'] as $sItem) {
                        if (!empty($sItem['is_archived']) || ($sItem['status'] ?? 'Approved') !== 'Approved') {
                            continue;
                        }
                        $subs[] = [
                            'name'  => $sItem['name'] ?? '',
                            'price' => (float) ($sItem['price'] ?? $vItem['price'] ?? $prod->price ?? 0),
                            'stock' => (int) ($sItem['stock'] ?? 0),
                        ];
                    }
                }
                $vars[] = [
                    'name'  => $vItem['name'] ?? '',
                    'price' => (float) ($vItem['price'] ?? $prod->price ?? 0),
                    'stock' => (int) ($vItem['stock'] ?? 0),
                    'subs'  => $subs,
                ];
            }
        }
        $productVariantsMap[$prod->id] = $vars;
    }
    $firstProductId = (string) ($products->first()?->id ?? '');

    $promotionsConfig = [
        'dealType'              => (string) old('deal_type', 'super_deal'),
        'bundleType'            => (string) old('bundle_type', 'percentage_off'),
        'selectedDealProductId' => (string) old('product_id', $firstProductId),
        'selectedDealVariant'   => (string) old('variant_name', ''),
        'selectedDealSubVariant'=> (string) old('sub_variant_name', ''),
        'selectedGiftProductId' => (string) old('free_gift_product_id', $firstProductId),
        'selectedGiftVariant'   => (string) old('free_gift_variant_name', ''),
        'selectedGiftSubVariant'=> (string) old('free_gift_sub_variant_name', ''),
        'variantsByProduct'     => $productVariantsMap,
    ];
@endphp

<script>
    window.storkiaPromotionsState = function (cfg) {
        return {
            dealType: cfg.dealType || 'super_deal',
            bundleType: cfg.bundleType || 'percentage_off',
            selectedDealProductId: cfg.selectedDealProductId || '',
            selectedDealVariant: cfg.selectedDealVariant || '',
            selectedDealSubVariant: cfg.selectedDealSubVariant || '',
            selectedGiftProductId: cfg.selectedGiftProductId || '',
            selectedGiftVariant: cfg.selectedGiftVariant || '',
            selectedGiftSubVariant: cfg.selectedGiftSubVariant || '',
            variantsByProduct: cfg.variantsByProduct || {},
            isSubmittingDeal: false,
            isSubmittingBundle: false,
            get dealVariants() {
                return this.variantsByProduct[this.selectedDealProductId] || [];
            },
            get dealSubVariants() {
                const found = this.dealVariants.find(v => v.name === this.selectedDealVariant);
                return found ? (found.subs || []) : [];
            },
            get giftVariants() {
                return this.variantsByProduct[this.selectedGiftProductId] || [];
            },
            get giftSubVariants() {
                const found = this.giftVariants.find(v => v.name === this.selectedGiftVariant);
                return found ? (found.subs || []) : [];
            }
        };
    };
</script>

<div x-data="storkiaPromotionsState(@js($promotionsConfig))" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Super Deals, Store Sales, Bundles &amp; Vacation Mode</h1>
            <p class="text-sm text-text-muted mt-1">
                Schedule fixed-slot <strong>Super Deals</strong> (connected to the Home Page Super Deals container), run Store Sales, create Variant-Aware Bundle Deals, or toggle Store Vacation Mode.
            </p>
        </div>

        <form action="{{ route('seller.store-settings.toggle-vacation') }}" method="POST" class="m-0">
            @csrf
            @method('PATCH')
            <button type="submit"
                    class="px-5 py-3 rounded-2xl text-xs font-extrabold shadow-xs transition-all cursor-pointer flex items-center gap-2 {{ $isOnVacation ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-surface border border-border-subtle hover:border-primary text-text-main' }}">
                <span class="w-2.5 h-2.5 rounded-full {{ $isOnVacation ? 'bg-white' : 'bg-green-500' }}"></span>
                <span>{{ $isOnVacation ? 'Store On Vacation (Click to Re-Open Store)' : 'Store Active (Click to Turn On Vacation Mode)' }}</span>
            </button>
        </form>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-6 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark">1. Create Super Deal Slot or Scheduled Store Sale</h2>
                <p class="text-xs text-text-muted">
                    Scope a deal to an entire product or a specific variant/sub-variant. Super Deal price wins while Super Deal slot stock lasts.
                </p>
            </div>

            <form action="{{ route('seller.promotions.deals.store') }}"
                  method="POST"
                  @submit="isSubmittingDeal = true"
                  class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-2 gap-3">
                    <label class="p-3 rounded-xl border cursor-pointer font-bold flex items-center gap-2"
                           :class="dealType === 'super_deal' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle'">
                        <input type="radio" name="deal_type" value="super_deal" x-model="dealType">
                        <span>Super Deal (Time Slot)</span>
                    </label>
                    <label class="p-3 rounded-xl border cursor-pointer font-bold flex items-center gap-2"
                           :class="dealType === 'store_sale' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle'">
                        <input type="radio" name="deal_type" value="store_sale" x-model="dealType">
                        <span>Scheduled Store Sale</span>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Select Product <span class="text-danger">*</span></label>
                    <select name="product_id"
                            x-model="selectedDealProductId"
                            @change="selectedDealVariant = ''; selectedDealSubVariant = '';"
                            required
                            class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                        @foreach($products as $prod)
                            <option value="{{ $prod->id }}">
                                {{ $prod->name }} — Orig: ₱{{ number_format((float) $prod->price, 2) }} (Stock: {{ $prod->stock_quantity }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div x-show="dealVariants.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Target Variant (Optional)</label>
                        <select name="variant_name"
                                x-model="selectedDealVariant"
                                @change="selectedDealSubVariant = ''"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="">All Variants</option>
                            <template x-for="v in dealVariants" :key="v.name">
                                <option :value="v.name" x-text="v.name + ' (\u20B1' + Number(v.price).toFixed(2) + ' \u2022 Stock: ' + v.stock + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="dealSubVariants.length > 0" x-cloak>
                        <label class="block font-bold text-text-muted uppercase mb-1">Target Sub-Variant (Optional)</label>
                        <select name="sub_variant_name"
                                x-model="selectedDealSubVariant"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="">All Sub-Variants</option>
                            <template x-for="sv in dealSubVariants" :key="sv.name">
                                <option :value="sv.name" x-text="sv.name + ' (\u20B1' + Number(sv.price).toFixed(2) + ' \u2022 Stock: ' + sv.stock + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Discount % (1–99%) <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="99" step="1" name="discount_percentage" value="{{ old('discount_percentage', 20) }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>
                    <div x-show="dealType === 'super_deal'">
                        <label class="block font-bold text-text-muted uppercase mb-1">Super Deal Qty <span class="text-danger">*</span></label>
                        <input type="number" min="1" name="flash_quantity" :required="dealType === 'super_deal'" value="{{ old('flash_quantity', 3) }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Start Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" min="{{ now()->subMinutes(5)->format('Y-m-d\TH:i') }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">End Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addHours(4)->format('Y-m-d\TH:i')) }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="isSubmittingDeal || {{ $products->isEmpty() ? 'true' : 'false' }}"
                            class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmittingDeal">Publish Promotion</span>
                        <span x-show="isSubmittingDeal" x-cloak>Publishing...</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-6 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark">2. Create Bundle Deal (Multi-Item % Off or Buy X Get Y Free)</h2>
                <p class="text-xs text-text-muted">Seller-funded bundle promotions with variant-level Free Gift selection applied automatically at checkout.</p>
            </div>

            <form action="{{ route('seller.promotions.bundles.store') }}"
                  method="POST"
                  @submit="isSubmittingBundle = true"
                  class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Bundle Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" required minlength="3" maxlength="120" placeholder="e.g. Buy 3 Get 15% Off Bundle" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Bundle Mechanic <span class="text-danger">*</span></label>
                        <select name="bundle_type" x-model="bundleType" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                            <option value="percentage_off">Buy N Items Get % Off</option>
                            <option value="buy_x_get_y">Buy X Items Get Free Gift (₱0.00)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Required Quantity (X) <span class="text-danger">*</span></label>
                        <input type="number" name="required_quantity" value="{{ old('required_quantity', 3) }}" min="2" max="20" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>

                    <div x-show="bundleType === 'percentage_off'">
                        <label class="block font-bold text-text-muted uppercase mb-1">Bundle Discount % <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" min="1" max="99" name="discount_percentage" value="{{ old('discount_percentage', 15) }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold text-primary">
                    </div>

                    <div x-show="bundleType === 'buy_x_get_y'" x-cloak>
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Product (Y) <span class="text-danger">*</span></label>
                        <select name="free_gift_product_id"
                                x-model="selectedGiftProductId"
                                @change="selectedGiftVariant = ''; selectedGiftSubVariant = '';"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} (₱{{ number_format((float) $prod->price, 2) }} &rarr; ₱0.00)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-show="bundleType === 'buy_x_get_y' && giftVariants.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Variant</label>
                        <select name="free_gift_variant_name"
                                x-model="selectedGiftVariant"
                                @change="selectedGiftSubVariant = ''"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="">Default / Any Variant</option>
                            <template x-for="gv in giftVariants" :key="gv.name">
                                <option :value="gv.name" x-text="gv.name + ' (Stock: ' + gv.stock + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="giftSubVariants.length > 0" x-cloak>
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Sub-Variant</label>
                        <select name="free_gift_sub_variant_name"
                                x-model="selectedGiftSubVariant"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="">Default / Any Sub-Variant</option>
                            <template x-for="gsv in giftSubVariants" :key="gsv.name">
                                <option :value="gsv.name" x-text="gsv.name + ' (Stock: ' + gsv.stock + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Select Qualifying Products <span class="text-danger">*</span></label>
                    <div class="max-h-32 overflow-y-auto border border-border-subtle rounded-xl p-2.5 bg-surface-subtle grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                        @foreach($products as $prod)
                            <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                                <input type="checkbox" name="applicable_product_ids[]" value="{{ $prod->id }}" checked class="rounded text-primary">
                                <span class="truncate">{{ $prod->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Starts At <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" min="{{ now()->subMinutes(5)->format('Y-m-d\TH:i') }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Ends At <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="ends_at" value="{{ now()->addDays(14)->format('Y-m-d\TH:i') }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="isSubmittingBundle || {{ $products->isEmpty() ? 'true' : 'false' }}"
                            class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmittingBundle">Publish Bundle Deal</span>
                        <span x-show="isSubmittingBundle" x-cloak>Publishing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Scheduled &amp; Running Super Deals / Store Sales (FIFO)</h2>
        </div>

        @if($deals->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No Super Deals or Store Sales created yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-5">Type &amp; Product / Variant</th>
                            <th class="py-3 px-4">Deal Price / Discount</th>
                            <th class="py-3 px-4">Slot Stock</th>
                            <th class="py-3 px-4">Schedule</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($deals as $d)
                            @php
                                $prod = $productsById->get($d->product_id);
                                $starts = \Carbon\Carbon::parse($d->starts_at);
                                $ends = \Carbon\Carbon::parse($d->ends_at);
                                $state = $d->status === 'ended' || now()->gt($ends)
                                    ? 'Ended'
                                    : (now()->lt($starts) ? 'Upcoming' : 'Ongoing');
                                $varScope = implode(' / ', array_filter([$d->variant_name ?? null, $d->sub_variant_name ?? null]));
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase {{ $d->deal_type === 'super_deal' ? 'bg-primary text-white' : 'bg-brand-light/60 text-primary-dark' }}">
                                        {{ $d->deal_type === 'super_deal' ? 'Super Deal' : 'Store Sale' }}
                                    </span>
                                    <div class="font-bold text-text-main mt-1">{{ $prod->name ?? 'Product #' . $d->product_id }}</div>
                                    @if($varScope !== '')
                                        <span class="inline-block mt-0.5 px-2 py-0.5 rounded bg-surface-subtle border border-border-subtle text-[10px] font-bold text-primary-dark">
                                            Variant: {{ $varScope }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-primary text-sm">₱{{ number_format((float) $d->deal_price, 2) }}</div>
                                    <div class="text-text-muted">-{{ number_format((float) $d->discount_percentage, 0) }}% off ₱{{ number_format((float) ($prod->price ?? 0), 2) }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($d->flash_quantity !== null)
                                        <strong>{{ $d->sold_quantity }} / {{ $d->flash_quantity }}</strong> sold
                                    @else
                                        <span class="text-text-muted">All Stock</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-text-muted">
                                    <div>Start: {{ $starts->format('M d, Y h:i A') }}</div>
                                    <div>End: {{ $ends->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $state === 'Ongoing' ? 'bg-green-100 text-green-700' : ($state === 'Upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500') }}">
                                        {{ $state }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($state !== 'Ended')
                                        <div class="inline-flex items-center gap-2">
                                            <form action="{{ route('seller.promotions.deals.update', $d->id) }}" method="POST" class="inline-flex items-center gap-1">
                                                @csrf @method('PATCH')
                                                <input type="number" name="discount_percentage" value="{{ (int) $d->discount_percentage }}" min="1" max="99" class="w-14 p-1.5 rounded-lg border border-border-subtle text-center font-bold">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-surface-subtle border border-border-subtle font-bold text-primary-dark cursor-pointer">Update %</button>
                                            </form>
                                            <form action="{{ route('seller.promotions.deals.end', $d->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-50 text-danger font-bold cursor-pointer">End Now</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Scheduled &amp; Running Bundle Deals (FIFO)</h2>
                <p class="text-xs text-text-muted">Active bundles appear on the Buyer Bundle Deals page and apply automatically at checkout.</p>
            </div>
        </div>

        @if($bundles->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No Bundle Deals created yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-5">Bundle Title &amp; Mechanic</th>
                            <th class="py-3 px-4">Qualifying Products</th>
                            <th class="py-3 px-4">Discount / Free Gift</th>
                            <th class="py-3 px-4">Schedule</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($bundles as $b)
                            @php
                                $bStarts = \Carbon\Carbon::parse($b->starts_at);
                                $bEnds = \Carbon\Carbon::parse($b->ends_at);
                                $bState = $b->status === 'ended' || now()->gt($bEnds)
                                    ? 'Ended'
                                    : (now()->lt($bStarts) ? 'Upcoming' : 'Ongoing');

                                $appIds = is_string($b->applicable_product_ids)
                                    ? (json_decode($b->applicable_product_ids, true) ?: [])
                                    : (array) $b->applicable_product_ids;

                                $qualifyingNames = collect($appIds)
                                    ->map(fn ($pid) => $productsById->get((int) $pid)?->name ?? ('Product #' . $pid))
                                    ->values();

                                $freeGiftProd = $b->free_gift_product_id ? $productsById->get((int) $b->free_gift_product_id) : null;
                                $giftVarLabel = implode(' / ', array_filter([$b->free_gift_variant_name ?? null, $b->free_gift_sub_variant_name ?? null]));
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase {{ $b->bundle_type === 'percentage_off' ? 'bg-primary text-white' : 'bg-purple-100 text-purple-800' }}">
                                        {{ $b->bundle_type === 'percentage_off' ? 'Buy ' . $b->required_quantity . ' Get % Off' : 'Buy ' . $b->required_quantity . ' Get Free Gift' }}
                                    </span>
                                    <div class="font-bold text-text-main mt-1">{{ $b->title }}</div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <div class="text-text-main font-medium line-clamp-2">{{ $qualifyingNames->implode(', ') }}</div>
                                    <div class="text-[11px] text-text-muted">Min Qty: {{ $b->required_quantity }} item(s)</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($b->bundle_type === 'percentage_off')
                                        <span class="font-bold text-primary text-sm">{{ number_format((float) $b->discount_percentage, 1) }}% OFF</span>
                                    @else
                                        <div class="font-bold text-purple-800">Free Gift: {{ $freeGiftProd->name ?? ('Product #' . $b->free_gift_product_id) }}</div>
                                        @if($giftVarLabel !== '')
                                            <div class="text-[10px] font-bold text-primary-dark">Variant: {{ $giftVarLabel }}</div>
                                        @endif
                                        <div class="text-[11px] text-text-muted">100% Off Gift Item</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-text-muted">
                                    <div>Start: {{ $bStarts->format('M d, Y h:i A') }}</div>
                                    <div>End: {{ $bEnds->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $bState === 'Ongoing' ? 'bg-green-100 text-green-700' : ($bState === 'Upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500') }}">
                                        {{ $bState }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($bState !== 'Ended')
                                        <form action="{{ route('seller.promotions.bundles.end', $b->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-50 text-danger font-bold cursor-pointer">
                                                End Now
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection