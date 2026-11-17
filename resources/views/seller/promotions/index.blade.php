@extends('layouts.seller', ['title' => 'Storkia - Super Deals, Store Sales & Bundles'])

@section('content')
@php
    $isOnVacation = (bool) ($profile->is_on_vacation ?? false);
    $deals = $deals ?? collect();
    $bundles = $bundles ?? collect();

    $timeOptions = [];
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 30] as $m) {
            $val = sprintf('%02d:%02d', $h, $m);
            $label = \Carbon\Carbon::createFromTime($h, $m)->format('h:i A');
            $timeOptions[] = ['val' => $val, 'label' => $label];
        }
    }
    $timeOptions[] = ['val' => '23:59', 'label' => '11:59 PM'];

    $productVariantsMap = [];
    $productOptionsList = [];
    foreach ($products as $prod) {
        $productOptionsList[] = [
            'id'    => (string) $prod->id,
            'name'  => (string) $prod->name,
            'price' => (float) ($prod->price ?? 0),
            'stock' => (int) ($prod->stock_quantity ?? 0),
        ];
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

    $defaultDealStart = old('starts_at') ? \Carbon\Carbon::parse(old('starts_at')) : now();
    $defaultDealEnd = old('ends_at') ? \Carbon\Carbon::parse(old('ends_at')) : now()->addHours(4);

    $promotionsConfig = [
        'dealType'               => (string) old('deal_type', 'super_deal'),
        'bundleType'             => (string) old('bundle_type', 'percentage_off'),
        'selectedDealProductId'  => (string) old('product_id', $firstProductId),
        'selectedDealVariant'    => (string) old('variant_name', ''),
        'selectedDealSubVariant' => (string) old('sub_variant_name', ''),
        'selectedGiftProductId'  => (string) old('free_gift_product_id', $firstProductId),
        'selectedGiftVariant'    => (string) old('free_gift_variant_name', ''),
        'selectedGiftSubVariant' => (string) old('free_gift_sub_variant_name', ''),
        'variantsByProduct'      => $productVariantsMap,
        'productsList'           => $productOptionsList,
        'dealStartDate'          => $defaultDealStart->format('Y-m-d'),
        'dealStartTime'          => $defaultDealStart->format('H:i'),
        'dealEndDate'            => $defaultDealEnd->format('Y-m-d'),
        'dealEndTime'            => $defaultDealEnd->format('H:i'),
        'bundleStartDate'        => now()->format('Y-m-d'),
        'bundleStartTime'        => now()->format('H:i'),
        'bundleEndDate'          => now()->addDays(14)->format('Y-m-d'),
        'bundleEndTime'          => '23:59',
        'timeOptions'            => $timeOptions,
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
            productsList: Array.isArray(cfg.productsList) ? cfg.productsList : [],
            timeOptions: Array.isArray(cfg.timeOptions) ? cfg.timeOptions : [],
            dealStartDate: cfg.dealStartDate || '',
            dealStartTime: cfg.dealStartTime || '00:00',
            dealEndDate: cfg.dealEndDate || '',
            dealEndTime: cfg.dealEndTime || '23:59',
            bundleStartDate: cfg.bundleStartDate || '',
            bundleStartTime: cfg.bundleStartTime || '00:00',
            bundleEndDate: cfg.bundleEndDate || '',
            bundleEndTime: cfg.bundleEndTime || '23:59',
            activeCalTarget: null,
            activeTimeTarget: null,
            calYear: new Date().getFullYear(),
            calMonth: new Date().getMonth(),
            dealProductOpen: false,
            dealVariantOpen: false,
            dealSubVariantOpen: false,
            bundleTypeOpen: false,
            giftProductOpen: false,
            giftVariantOpen: false,
            giftSubVariantOpen: false,
            showAllQualifying: false,
            dealsTableExpanded: false,
            bundlesTableExpanded: false,
            isSubmittingDeal: false,
            isSubmittingBundle: false,
            openCal(target) {
                this.activeTimeTarget = null;
                if (this.activeCalTarget === target) {
                    this.activeCalTarget = null;
                    return;
                }
                this.activeCalTarget = target;
                const map = {
                    deal_start: this.dealStartDate,
                    deal_end: this.dealEndDate,
                    bundle_start: this.bundleStartDate,
                    bundle_end: this.bundleEndDate,
                };
                const iso = map[target] || '';
                const d = iso ? new Date(iso + 'T00:00:00') : new Date();
                if (!isNaN(d.getTime())) {
                    this.calYear = d.getFullYear();
                    this.calMonth = d.getMonth();
                }
            },
            get calMonthLabel() {
                const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                return (months[this.calMonth] || '') + ' ' + this.calYear;
            },
            get calDays() {
                const firstDay = new Date(this.calYear, this.calMonth, 1).getDay();
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const cells = [];
                for (let i = 0; i < firstDay; i++) cells.push({ day: null, iso: '' });
                const map = {
                    deal_start: this.dealStartDate,
                    deal_end: this.dealEndDate,
                    bundle_start: this.bundleStartDate,
                    bundle_end: this.bundleEndDate,
                };
                const currentIso = map[this.activeCalTarget] || '';
                for (let d = 1; d <= daysInMonth; d++) {
                    const m = String(this.calMonth + 1).padStart(2, '0');
                    const dayStr = String(d).padStart(2, '0');
                    const iso = `${this.calYear}-${m}-${dayStr}`;
                    cells.push({ day: d, iso: iso, selected: iso === currentIso });
                }
                return cells;
            },
            prevCal() {
                if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; } else { this.calMonth--; }
            },
            nextCal() {
                if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; } else { this.calMonth++; }
            },
            pickCal(cell) {
                if (!cell || !cell.iso) return;
                if (this.activeCalTarget === 'deal_start') this.dealStartDate = cell.iso;
                else if (this.activeCalTarget === 'deal_end') this.dealEndDate = cell.iso;
                else if (this.activeCalTarget === 'bundle_start') this.bundleStartDate = cell.iso;
                else if (this.activeCalTarget === 'bundle_end') this.bundleEndDate = cell.iso;
                this.activeCalTarget = null;
            },
            get selectedDealProductLabel() {
                const found = this.productsList.find(p => String(p.id) === String(this.selectedDealProductId));
                if (!found) return 'Select product...';
                return `${found.name} — Orig: \u20B1${Number(found.price).toFixed(2)} (Stock: ${found.stock})`;
            },
            get selectedGiftProductLabel() {
                const found = this.productsList.find(p => String(p.id) === String(this.selectedGiftProductId));
                if (!found) return 'Select free gift...';
                return `${found.name} (\u20B1${Number(found.price).toFixed(2)} \u2192 \u20B10.00)`;
            },
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
                    class="px-5 py-3 rounded-2xl text-xs font-extrabold shadow-xs transition-all cursor-pointer flex items-center gap-2 {{ $isOnVacation ? 'bg-primary-dark hover:bg-primary text-white' : 'bg-surface border border-border-subtle hover:border-primary text-text-main' }}">
                <span class="w-2.5 h-2.5 rounded-full {{ $isOnVacation ? 'bg-brand-light' : 'bg-success' }}"></span>
                <span>{{ $isOnVacation ? 'Store On Vacation (Click to Re-Open Store)' : 'Store Active (Click to Turn On Vacation Mode)' }}</span>
            </button>
        </form>
    </div>

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
                    <label class="p-3 rounded-xl border cursor-pointer font-bold flex items-center gap-2 transition-colors"
                           :class="dealType === 'super_deal' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle hover:border-primary/40'">
                        <input type="radio" name="deal_type" value="super_deal" x-model="dealType" class="text-primary">
                        <span>Super Deal (Time Slot)</span>
                    </label>
                    <label class="p-3 rounded-xl border cursor-pointer font-bold flex items-center gap-2 transition-colors"
                           :class="dealType === 'store_sale' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle hover:border-primary/40'">
                        <input type="radio" name="deal_type" value="store_sale" x-model="dealType" class="text-primary">
                        <span>Scheduled Store Sale</span>
                    </label>
                </div>

                <div class="relative" @click.away="dealProductOpen = false">
                    <label class="block font-bold text-text-muted uppercase mb-1">Select Product <span class="text-danger">*</span></label>
                    <input type="hidden" name="product_id" :value="selectedDealProductId" required>
                    <button type="button"
                            @click="dealProductOpen = !dealProductOpen"
                            class="w-full p-3 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                        <span class="truncate pr-2" x-text="selectedDealProductLabel"></span>
                        <svg class="w-4 h-4 text-text-muted shrink-0 transition-transform" :class="dealProductOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="dealProductOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-2xl shadow-xl max-h-56 overflow-y-auto custom-scrollbar z-30 divide-y divide-border-subtle/40">
                        <template x-for="prod in productsList" :key="'dp_' + prod.id">
                            <button type="button"
                                    @click="selectedDealProductId = String(prod.id); selectedDealVariant = ''; selectedDealSubVariant = ''; dealProductOpen = false"
                                    class="w-full px-4 py-2.5 text-left hover:bg-brand-light/30 flex items-center justify-between gap-2 cursor-pointer">
                                <span class="truncate font-semibold text-text-main" x-text="prod.name + ' — Orig: \u20B1' + Number(prod.price).toFixed(2) + ' (Stock: ' + prod.stock + ')'"></span>
                                <span x-show="String(selectedDealProductId) === String(prod.id)" class="text-primary font-bold shrink-0">&#10003;</span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="dealVariants.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative" @click.away="dealVariantOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Target Variant (Optional)</label>
                        <input type="hidden" name="variant_name" :value="selectedDealVariant">
                        <button type="button"
                                @click="dealVariantOpen = !dealVariantOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate" x-text="selectedDealVariant || 'All Variants'"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="dealVariantOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-48 overflow-y-auto custom-scrollbar z-30">
                            <button type="button" @click="selectedDealVariant = ''; selectedDealSubVariant = ''; dealVariantOpen = false" class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold cursor-pointer">All Variants</button>
                            <template x-for="v in dealVariants" :key="v.name">
                                <button type="button"
                                        @click="selectedDealVariant = v.name; selectedDealSubVariant = ''; dealVariantOpen = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span class="truncate" x-text="v.name + ' (\u20B1' + Number(v.price).toFixed(2) + ' \u2022 Stock: ' + v.stock + ')'"></span>
                                    <span x-show="selectedDealVariant === v.name" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="dealSubVariants.length > 0" x-cloak class="relative" @click.away="dealSubVariantOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Target Sub-Variant (Optional)</label>
                        <input type="hidden" name="sub_variant_name" :value="selectedDealSubVariant">
                        <button type="button"
                                @click="dealSubVariantOpen = !dealSubVariantOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate" x-text="selectedDealSubVariant || 'All Sub-Variants'"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="dealSubVariantOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-48 overflow-y-auto custom-scrollbar z-30">
                            <button type="button" @click="selectedDealSubVariant = ''; dealSubVariantOpen = false" class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold cursor-pointer">All Sub-Variants</button>
                            <template x-for="sv in dealSubVariants" :key="sv.name">
                                <button type="button"
                                        @click="selectedDealSubVariant = sv.name; dealSubVariantOpen = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span class="truncate" x-text="sv.name + ' (\u20B1' + Number(sv.price).toFixed(2) + ' \u2022 Stock: ' + sv.stock + ')'"></span>
                                    <span x-show="selectedDealSubVariant === sv.name" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Discount % (1–99%) <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="99" step="1" name="discount_percentage" value="{{ old('discount_percentage', 20) }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>
                    <div x-show="dealType === 'super_deal'">
                        <label class="block font-bold text-text-muted uppercase mb-1">Super Deal Qty <span class="text-danger">*</span></label>
                        <input type="number" min="1" name="flash_quantity" :required="dealType === 'super_deal'" value="{{ old('flash_quantity', 3) }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 relative" @click.away="if (activeCalTarget === 'deal_start' || activeCalTarget === 'deal_end') activeCalTarget = null; if (activeTimeTarget === 'deal_start' || activeTimeTarget === 'deal_end') activeTimeTarget = null">
                    <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5">
                        <label class="block font-bold text-primary-dark uppercase">Start Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="hidden" name="starts_at" :value="dealStartDate + 'T' + dealStartTime">
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button"
                                    @click="openCal('deal_start')"
                                    class="p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                <span x-text="dealStartDate"></span>
                                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </button>
                            <div class="relative">
                                <button type="button"
                                        @click="activeCalTarget = null; activeTimeTarget = activeTimeTarget === 'deal_start' ? null : 'deal_start'"
                                        class="w-full p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                    <span x-text="dealStartTime"></span>
                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <div x-show="activeTimeTarget === 'deal_start'" x-cloak class="absolute right-0 top-full mt-1 w-40 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-44 overflow-y-auto custom-scrollbar z-40 py-1">
                                    <template x-for="tOpt in timeOptions" :key="'dst_' + tOpt.val">
                                        <button type="button"
                                                @click="dealStartTime = tOpt.val; activeTimeTarget = null"
                                                class="w-full px-3 py-1.5 text-left hover:bg-brand-light/30 font-mono text-xs flex items-center justify-between cursor-pointer"
                                                :class="dealStartTime === tOpt.val ? 'text-primary font-extrabold bg-primary/5' : 'text-text-main'">
                                            <span x-text="tOpt.label"></span>
                                            <span x-show="dealStartTime === tOpt.val" class="text-primary">&#10003;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5">
                        <label class="block font-bold text-primary-dark uppercase">End Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="hidden" name="ends_at" :value="dealEndDate + 'T' + dealEndTime">
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button"
                                    @click="openCal('deal_end')"
                                    class="p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                <span x-text="dealEndDate"></span>
                                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </button>
                            <div class="relative">
                                <button type="button"
                                        @click="activeCalTarget = null; activeTimeTarget = activeTimeTarget === 'deal_end' ? null : 'deal_end'"
                                        class="w-full p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                    <span x-text="dealEndTime"></span>
                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <div x-show="activeTimeTarget === 'deal_end'" x-cloak class="absolute right-0 top-full mt-1 w-40 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-44 overflow-y-auto custom-scrollbar z-40 py-1">
                                    <template x-for="tOpt in timeOptions" :key="'det_' + tOpt.val">
                                        <button type="button"
                                                @click="dealEndTime = tOpt.val; activeTimeTarget = null"
                                                class="w-full px-3 py-1.5 text-left hover:bg-brand-light/30 font-mono text-xs flex items-center justify-between cursor-pointer"
                                                :class="dealEndTime === tOpt.val ? 'text-primary font-extrabold bg-primary/5' : 'text-text-main'">
                                            <span x-text="tOpt.label"></span>
                                            <span x-show="dealEndTime === tOpt.val" class="text-primary">&#10003;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="activeCalTarget === 'deal_start' || activeCalTarget === 'deal_end'"
                         x-cloak
                         x-transition
                         class="absolute left-0 top-full mt-2 w-72 bg-surface border border-border-subtle rounded-2xl shadow-2xl p-4 z-50">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click="prevCal()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span class="text-xs font-extrabold text-primary-dark" x-text="calMonthLabel"></span>
                            <button type="button" @click="nextCal()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-[10px] font-bold text-text-muted text-center mb-1">
                            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(cell, cIdx) in calDays" :key="'dcal_' + cIdx">
                                <div>
                                    <template x-if="cell.day !== null">
                                        <button type="button"
                                                @click="pickCal(cell)"
                                                :class="cell.selected ? 'bg-primary text-white font-extrabold' : 'text-text-main hover:bg-brand-light/40 font-semibold'"
                                                class="w-8 h-8 rounded-lg text-xs flex items-center justify-center transition-colors cursor-pointer"
                                                x-text="cell.day"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="isSubmittingDeal || {{ $products->isEmpty() ? 'true' : 'false' }}"
                            class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50">
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
                        <input type="text" name="title" value="{{ old('title') }}" required minlength="3" maxlength="120" placeholder="e.g. Buy 3 Get 15% Off Bundle" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>
                    <div class="relative" @click.away="bundleTypeOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Bundle Mechanic <span class="text-danger">*</span></label>
                        <input type="hidden" name="bundle_type" :value="bundleType">
                        <button type="button"
                                @click="bundleTypeOpen = !bundleTypeOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-bold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate" x-text="bundleType === 'buy_x_get_y' ? 'Buy X Items Get Free Gift (\u20B10.00)' : 'Buy N Items Get % Off'"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="bundleTypeOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl z-30 overflow-hidden">
                            <button type="button" @click="bundleType = 'percentage_off'; bundleTypeOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                <span>Buy N Items Get % Off</span>
                                <span x-show="bundleType === 'percentage_off'" class="text-primary font-bold">&#10003;</span>
                            </button>
                            <button type="button" @click="bundleType = 'buy_x_get_y'; bundleTypeOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                <span>Buy X Items Get Free Gift (&#8369;0.00)</span>
                                <span x-show="bundleType === 'buy_x_get_y'" class="text-primary font-bold">&#10003;</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Required Quantity (X) <span class="text-danger">*</span></label>
                        <input type="number" name="required_quantity" value="{{ old('required_quantity', 3) }}" min="2" max="20" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>
                    <div x-show="bundleType === 'percentage_off'">
                        <label class="block font-bold text-text-muted uppercase mb-1">Bundle Discount % <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" min="1" max="99" name="discount_percentage" value="{{ old('discount_percentage', 15) }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold text-primary outline-none focus:border-primary">
                    </div>
                    <div x-show="bundleType === 'buy_x_get_y'" x-cloak class="relative" @click.away="giftProductOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Product (Y) <span class="text-danger">*</span></label>
                        <input type="hidden" name="free_gift_product_id" :value="selectedGiftProductId">
                        <button type="button"
                                @click="giftProductOpen = !giftProductOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate pr-2" x-text="selectedGiftProductLabel"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="giftProductOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-48 overflow-y-auto custom-scrollbar z-30">
                            <template x-for="prod in productsList" :key="'gp_' + prod.id">
                                <button type="button"
                                        @click="selectedGiftProductId = String(prod.id); selectedGiftVariant = ''; selectedGiftSubVariant = ''; giftProductOpen = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span class="truncate" x-text="prod.name + ' (\u20B1' + Number(prod.price).toFixed(2) + ' \u2192 \u20B10.00)'"></span>
                                    <span x-show="String(selectedGiftProductId) === String(prod.id)" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div x-show="bundleType === 'buy_x_get_y' && giftVariants.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative" @click.away="giftVariantOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Variant</label>
                        <input type="hidden" name="free_gift_variant_name" :value="selectedGiftVariant">
                        <button type="button"
                                @click="giftVariantOpen = !giftVariantOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate" x-text="selectedGiftVariant || 'Default / Any Variant'"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="giftVariantOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-48 overflow-y-auto custom-scrollbar z-30">
                            <button type="button" @click="selectedGiftVariant = ''; selectedGiftSubVariant = ''; giftVariantOpen = false" class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold cursor-pointer">Default / Any Variant</button>
                            <template x-for="gv in giftVariants" :key="gv.name">
                                <button type="button"
                                        @click="selectedGiftVariant = gv.name; selectedGiftSubVariant = ''; giftVariantOpen = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span class="truncate" x-text="gv.name + ' (Stock: ' + gv.stock + ')'"></span>
                                    <span x-show="selectedGiftVariant === gv.name" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="giftSubVariants.length > 0" x-cloak class="relative" @click.away="giftSubVariantOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Free Gift Sub-Variant</label>
                        <input type="hidden" name="free_gift_sub_variant_name" :value="selectedGiftSubVariant">
                        <button type="button"
                                @click="giftSubVariantOpen = !giftSubVariantOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span class="truncate" x-text="selectedGiftSubVariant || 'Default / Any Sub-Variant'"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="giftSubVariantOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-48 overflow-y-auto custom-scrollbar z-30">
                            <button type="button" @click="selectedGiftSubVariant = ''; giftSubVariantOpen = false" class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold cursor-pointer">Default / Any Sub-Variant</button>
                            <template x-for="gsv in giftSubVariants" :key="gsv.name">
                                <button type="button"
                                        @click="selectedGiftSubVariant = gsv.name; giftSubVariantOpen = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span class="truncate" x-text="gsv.name + ' (Stock: ' + gsv.stock + ')'"></span>
                                    <span x-show="selectedGiftSubVariant === gsv.name" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-text-muted uppercase">Select Qualifying Products <span class="text-danger">*</span></label>
                        @if($products->count() > 6)
                            <button type="button"
                                    @click="showAllQualifying = !showAllQualifying"
                                    class="text-[11px] font-bold text-primary hover:underline cursor-pointer"
                                    x-text="showAllQualifying ? 'See Less' : 'See All ({{ $products->count() }})'"></button>
                        @endif
                    </div>
                    <div class="border border-border-subtle rounded-xl p-2.5 bg-surface-subtle grid grid-cols-1 sm:grid-cols-2 gap-1.5"
                         :class="showAllQualifying ? 'max-h-64 overflow-y-auto custom-scrollbar' : ''">
                        @foreach($products as $pIdx => $prod)
                            <label x-show="{{ $pIdx }} < 6 || showAllQualifying"
                                   class="flex items-center gap-2 text-xs font-medium p-1.5 rounded-lg bg-surface border border-border-subtle/70 hover:border-primary/40 cursor-pointer">
                                <input type="checkbox" name="applicable_product_ids[]" value="{{ $prod->id }}" checked class="rounded text-primary">
                                <span class="truncate">{{ $prod->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 relative" @click.away="if (activeCalTarget === 'bundle_start' || activeCalTarget === 'bundle_end') activeCalTarget = null; if (activeTimeTarget === 'bundle_start' || activeTimeTarget === 'bundle_end') activeTimeTarget = null">
                    <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5">
                        <label class="block font-bold text-primary-dark uppercase">Starts At <span class="text-danger">*</span></label>
                        <input type="hidden" name="starts_at" :value="bundleStartDate + 'T' + bundleStartTime">
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button"
                                    @click="openCal('bundle_start')"
                                    class="p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                <span x-text="bundleStartDate"></span>
                                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </button>
                            <div class="relative">
                                <button type="button"
                                        @click="activeCalTarget = null; activeTimeTarget = activeTimeTarget === 'bundle_start' ? null : 'bundle_start'"
                                        class="w-full p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                    <span x-text="bundleStartTime"></span>
                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <div x-show="activeTimeTarget === 'bundle_start'" x-cloak class="absolute right-0 top-full mt-1 w-40 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-44 overflow-y-auto custom-scrollbar z-40 py-1">
                                    <template x-for="tOpt in timeOptions" :key="'bst_' + tOpt.val">
                                        <button type="button"
                                                @click="bundleStartTime = tOpt.val; activeTimeTarget = null"
                                                class="w-full px-3 py-1.5 text-left hover:bg-brand-light/30 font-mono text-xs flex items-center justify-between cursor-pointer"
                                                :class="bundleStartTime === tOpt.val ? 'text-primary font-extrabold bg-primary/5' : 'text-text-main'">
                                            <span x-text="tOpt.label"></span>
                                            <span x-show="bundleStartTime === tOpt.val" class="text-primary">&#10003;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5">
                        <label class="block font-bold text-primary-dark uppercase">Ends At <span class="text-danger">*</span></label>
                        <input type="hidden" name="ends_at" :value="bundleEndDate + 'T' + bundleEndTime">
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button"
                                    @click="openCal('bundle_end')"
                                    class="p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                <span x-text="bundleEndDate"></span>
                                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </button>
                            <div class="relative">
                                <button type="button"
                                        @click="activeCalTarget = null; activeTimeTarget = activeTimeTarget === 'bundle_end' ? null : 'bundle_end'"
                                        class="w-full p-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-xs text-primary-dark flex items-center justify-between cursor-pointer">
                                    <span x-text="bundleEndTime"></span>
                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <div x-show="activeTimeTarget === 'bundle_end'" x-cloak class="absolute right-0 top-full mt-1 w-40 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-44 overflow-y-auto custom-scrollbar z-40 py-1">
                                    <template x-for="tOpt in timeOptions" :key="'bet_' + tOpt.val">
                                        <button type="button"
                                                @click="bundleEndTime = tOpt.val; activeTimeTarget = null"
                                                class="w-full px-3 py-1.5 text-left hover:bg-brand-light/30 font-mono text-xs flex items-center justify-between cursor-pointer"
                                                :class="bundleEndTime === tOpt.val ? 'text-primary font-extrabold bg-primary/5' : 'text-text-main'">
                                            <span x-text="tOpt.label"></span>
                                            <span x-show="bundleEndTime === tOpt.val" class="text-primary">&#10003;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="activeCalTarget === 'bundle_start' || activeCalTarget === 'bundle_end'"
                         x-cloak
                         x-transition
                         class="absolute left-0 top-full mt-2 w-72 bg-surface border border-border-subtle rounded-2xl shadow-2xl p-4 z-50">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click="prevCal()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span class="text-xs font-extrabold text-primary-dark" x-text="calMonthLabel"></span>
                            <button type="button" @click="nextCal()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-[10px] font-bold text-text-muted text-center mb-1">
                            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(cell, cIdx) in calDays" :key="'bcal_' + cIdx">
                                <div>
                                    <template x-if="cell.day !== null">
                                        <button type="button"
                                                @click="pickCal(cell)"
                                                :class="cell.selected ? 'bg-primary text-white font-extrabold' : 'text-text-main hover:bg-brand-light/40 font-semibold'"
                                                class="w-8 h-8 rounded-lg text-xs flex items-center justify-center transition-colors cursor-pointer"
                                                x-text="cell.day"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="isSubmittingBundle || {{ $products->isEmpty() ? 'true' : 'false' }}"
                            class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer disabled:opacity-50">
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
                        @foreach($deals as $dIdx => $d)
                            @php
                                $prod = $productsById->get($d->product_id);
                                $starts = \Carbon\Carbon::parse($d->starts_at);
                                $ends = \Carbon\Carbon::parse($d->ends_at);
                                $state = $d->status === 'ended' || now()->gt($ends)
                                    ? 'Ended'
                                    : (now()->lt($starts) ? 'Upcoming' : 'Ongoing');
                                $varScope = implode(' / ', array_filter([$d->variant_name ?? null, $d->sub_variant_name ?? null]));
                            @endphp
                            <tr x-show="{{ $dIdx }} < 6 || dealsTableExpanded" class="hover:bg-surface-subtle/30">
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
                                    <div class="font-bold text-price text-sm">&#8369;{{ number_format((float) $d->deal_price, 2) }}</div>
                                    <div class="text-text-muted">-{{ number_format((float) $d->discount_percentage, 0) }}% off &#8369;{{ number_format((float) ($prod->price ?? 0), 2) }}</div>
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
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $state === 'Ongoing' ? 'bg-success/15 text-success' : ($state === 'Upcoming' ? 'bg-brand-light/70 text-primary-dark' : 'bg-surface-subtle text-text-muted') }}">
                                        {{ $state }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($state !== 'Ended')
                                        <div class="inline-flex items-center gap-2">
                                            <form action="{{ route('seller.promotions.deals.update', $d->id) }}" method="POST" class="inline-flex items-center gap-1">
                                                @csrf @method('PATCH')
                                                <input type="number" name="discount_percentage" value="{{ (int) $d->discount_percentage }}" min="1" max="99" class="w-14 p-1.5 rounded-lg border border-border-subtle text-center font-bold outline-none focus:border-primary">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle font-bold text-primary-dark transition-colors cursor-pointer">Update %</button>
                                            </form>
                                            <form action="{{ route('seller.promotions.deals.end', $d->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-danger/10 hover:bg-danger text-danger hover:text-white font-bold transition-colors cursor-pointer">End Now</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($deals->count() > 6)
                <div class="p-4 border-t border-border-subtle flex justify-center bg-surface-subtle/30">
                    <button type="button"
                            @click="dealsTableExpanded = !dealsTableExpanded"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="dealsTableExpanded ? 'See Less Deals' : 'See More Deals (' + ({{ $deals->count() }} - 6) + ' more)'"></span>
                    </button>
                </div>
            @endif
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
                        @foreach($bundles as $bIdx => $b)
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
                            <tr x-show="{{ $bIdx }} < 6 || bundlesTableExpanded" class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase {{ $b->bundle_type === 'percentage_off' ? 'bg-primary text-white' : 'bg-primary-dark text-white' }}">
                                        {{ $b->bundle_type === 'percentage_off' ? 'Buy ' . $b->required_quantity . ' Get % Off' : 'Buy ' . $b->required_quantity . ' Get Free Gift' }}
                                    </span>
                                    <div class="font-bold text-text-main mt-1">{{ $b->title }}</div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs" x-data="{ qualExpanded: false }">
                                    <div :class="!qualExpanded ? 'line-clamp-2' : ''" class="text-text-main font-medium">{{ $qualifyingNames->implode(', ') }}</div>
                                    @if($qualifyingNames->count() > 2)
                                        <button type="button" @click="qualExpanded = !qualExpanded" class="text-[10px] font-bold text-primary hover:underline cursor-pointer" x-text="qualExpanded ? 'See less' : 'See all ({{ $qualifyingNames->count() }})'"></button>
                                    @endif
                                    <div class="text-[11px] text-text-muted">Min Qty: {{ $b->required_quantity }} item(s)</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($b->bundle_type === 'percentage_off')
                                        <span class="font-bold text-primary text-sm">{{ number_format((float) $b->discount_percentage, 1) }}% OFF</span>
                                    @else
                                        <div class="font-bold text-primary-dark">Free Gift: {{ $freeGiftProd->name ?? ('Product #' . $b->free_gift_product_id) }}</div>
                                        @if($giftVarLabel !== '')
                                            <div class="text-[10px] font-bold text-primary">Variant: {{ $giftVarLabel }}</div>
                                        @endif
                                        <div class="text-[11px] text-text-muted">100% Off Gift Item</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-text-muted">
                                    <div>Start: {{ $bStarts->format('M d, Y h:i A') }}</div>
                                    <div>End: {{ $bEnds->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $bState === 'Ongoing' ? 'bg-success/15 text-success' : ($bState === 'Upcoming' ? 'bg-brand-light/70 text-primary-dark' : 'bg-surface-subtle text-text-muted') }}">
                                        {{ $bState }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($bState !== 'Ended')
                                        <form action="{{ route('seller.promotions.bundles.end', $b->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-danger/10 hover:bg-danger text-danger hover:text-white font-bold transition-colors cursor-pointer">
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

            @if($bundles->count() > 6)
                <div class="p-4 border-t border-border-subtle flex justify-center bg-surface-subtle/30">
                    <button type="button"
                            @click="bundlesTableExpanded = !bundlesTableExpanded"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="bundlesTableExpanded ? 'See Less Bundles' : 'See More Bundles (' + ({{ $bundles->count() }} - 6) + ' more)'"></span>
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection