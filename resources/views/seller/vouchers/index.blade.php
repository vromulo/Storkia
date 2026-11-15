@extends('layouts.seller', ['title' => 'Storkia - Store Vouchers'])

@section('content')
<div x-data="{
    createModalOpen: {{ $errors->any() ? 'true' : 'false' }},
    editModalOpen: false,
    editingVoucher: null,
    endConfirmOpen: false,
    endingVoucherCode: '',
    endingVoucherAction: '',
    visibleVouchersLimit: 6,
    voucherCode: @js(old('code', '')),
    voucherTitle: @js(old('title', '')),
    discountType: @js(old('discount_type', 'fixed')),
    discountTypeOpen: false,
    discountValue: @js(old('discount_value', 50)),
    maxDiscountCap: @js(old('max_discount_cap', 100)),
    minSpend: @js(old('min_spend', 300)),
    visibility: @js(old('visibility', 'claimable')),
    visibilityOpen: false,
    productScopeMode: 'all',
    startsAtDate: '{{ old('starts_at') ? \Carbon\Carbon::parse(old('starts_at'))->format('Y-m-d') : now()->format('Y-m-d') }}',
    startsAtTime: '{{ old('starts_at') ? \Carbon\Carbon::parse(old('starts_at'))->format('H:i') : now()->format('H:i') }}',
    endsAtDate: '{{ old('ends_at') ? \Carbon\Carbon::parse(old('ends_at'))->format('Y-m-d') : now()->addMonth()->format('Y-m-d') }}',
    endsAtTime: '{{ old('ends_at') ? \Carbon\Carbon::parse(old('ends_at'))->format('H:i') : now()->format('H:i') }}',
    editEndsDate: '',
    editEndsTime: '',
    sanitizeCode() {
        this.voucherCode = (this.voucherCode || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
    },
    openEditVoucher(voucher) {
        this.editingVoucher = voucher;
        const parts = String(voucher.ends_at || '').split('T');
        this.editEndsDate = parts[0] || '';
        this.editEndsTime = parts[1] || '23:59';
        this.editModalOpen = true;
    },
    confirmEndVoucher(code, actionUrl) {
        this.endingVoucherCode = code;
        this.endingVoucherAction = actionUrl;
        this.endConfirmOpen = true;
    }
}" class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Store Vouchers &amp; Promotions</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Create visual ticket-style claimable vouchers or secret code-only store vouchers for buyers checking out your products.
            </p>
        </div>

        <button type="button"
                @click="createModalOpen = true"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs sm:text-sm font-bold shadow-sm transition-colors cursor-pointer self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Store Voucher
        </button>
    </div>

    @if($vouchers->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center space-y-3">
            <h3 class="text-base font-bold text-text-main">No store vouchers created yet</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Boost your store conversion by offering Fixed-Amount or Percentage-Capped store vouchers.
            </p>
            <button type="button"
                    @click="createModalOpen = true"
                    class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white text-xs font-bold transition-colors cursor-pointer">
                + Create Your First Store Voucher
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach($vouchers as $vIdx => $v)
                @php
                    $isOngoing = $v->isCurrentlyOngoing();
                    $hasClaimsOrUses = ($v->claims_count > 0) || ($v->used_count > 0);
                @endphp
                <div x-show="{{ $vIdx }} < visibleVouchersLimit"
                     x-transition
                     class="relative flex rounded-3xl border border-border-subtle bg-surface shadow-xs overflow-hidden {{ $v->status !== 'active' ? 'opacity-65' : '' }}">
                    <div class="w-36 sm:w-40 bg-gradient-to-br from-primary to-primary-dark text-white p-5 flex flex-col items-center justify-center text-center relative shrink-0 border-r-2 border-dashed border-white/40">
                        <div class="w-5 h-5 rounded-full bg-surface absolute -top-2.5 -right-2.5 border border-border-subtle"></div>
                        <div class="w-5 h-5 rounded-full bg-surface absolute -bottom-2.5 -right-2.5 border border-border-subtle"></div>
                        <span class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded bg-black/25 mb-2">
                            {{ $v->visibility === 'code_only' ? 'SECRET CODE' : 'CLAIMABLE' }}
                        </span>
                        <div class="text-xl font-serif font-extrabold leading-tight">
                            @if($v->discount_type === 'percentage')
                                {{ rtrim(rtrim(number_format((float) $v->discount_value, 2), '0'), '.') }}% OFF
                            @else
                                &#8369;{{ number_format((float) $v->discount_value, 0) }} OFF
                            @endif
                        </div>
                        @if($v->discount_type === 'percentage' && $v->max_discount_cap)
                            <span class="text-[10px] text-white/90 mt-0.5">Cap &#8369;{{ number_format((float) $v->max_discount_cap, 0) }}</span>
                        @endif
                        <span class="text-[10px] text-white/90 mt-1 font-semibold">
                            Min. Spend &#8369;{{ number_format((float) $v->min_spend, 0) }}
                        </span>
                    </div>

                    <div class="flex-1 p-5 flex flex-col justify-between min-w-0 text-xs">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono font-extrabold text-sm px-2.5 py-0.5 rounded-lg bg-brand-light/50 text-primary-dark border border-border-subtle">
                                    {{ $v->code }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $isOngoing ? 'bg-success/15 text-success' : 'bg-surface-subtle text-text-muted' }}">
                                    {{ $isOngoing ? 'Ongoing' : ($v->status === 'ended' ? 'Ended' : 'Scheduled / Expired') }}
                                </span>
                            </div>
                            <h3 class="font-bold text-sm text-text-main mt-2 truncate">{{ $v->title }}</h3>
                            <p class="text-text-muted text-[11px] mt-0.5">{{ $v->description }}</p>

                            <div class="mt-3 grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-surface-subtle border border-border-subtle text-[11px]">
                                <div>
                                    <span class="text-text-muted">Usage Counter:</span>
                                    <strong class="text-primary-dark ml-1">{{ $v->used_count }} / {{ $v->total_usage_limit }}</strong>
                                </div>
                                <div>
                                    <span class="text-text-muted">Claimed Wallets:</span>
                                    <strong class="text-text-main ml-1">{{ $v->claims_count }}</strong>
                                </div>
                                <div>
                                    <span class="text-text-muted">Per Buyer Limit:</span>
                                    <strong class="text-text-main ml-1">{{ $v->per_buyer_limit }}</strong>
                                </div>
                                <div>
                                    <span class="text-text-muted">Products:</span>
                                    <strong class="text-text-main ml-1">
                                        {{ !empty($v->applicable_product_ids) ? count($v->applicable_product_ids) . ' Selected' : 'All Store Items' }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-border-subtle flex flex-wrap items-center justify-between gap-2">
                            <span class="text-[11px] text-text-muted">
                                {{ $v->starts_at?->format('M d, Y') }} &ndash; {{ $v->ends_at?->format('M d, Y') }}
                            </span>
                            @if($v->status === 'active')
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="openEditVoucher({
                                                id: {{ $v->id }},
                                                code: '{{ $v->code }}',
                                                min_spend: '{{ number_format((float) $v->min_spend, 2) }}',
                                                discount_label: '{{ $v->badge_label }}',
                                                total_usage_limit: {{ (int) $v->total_usage_limit }},
                                                ends_at: '{{ $v->ends_at?->format('Y-m-d\TH:i') }}',
                                                locked: {{ $hasClaimsOrUses ? 'true' : 'false' }}
                                            })"
                                            class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold transition-colors cursor-pointer">
                                        Edit Limits
                                    </button>
                                    <button type="button"
                                            @click="confirmEndVoucher('{{ $v->code }}', '{{ route('seller.vouchers.end', $v->id) }}')"
                                            class="px-3 py-1.5 rounded-xl bg-danger/10 hover:bg-danger text-danger hover:text-white border border-danger/25 font-bold transition-colors cursor-pointer">
                                        End Now
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($vouchers->count() > 6)
            <div class="pt-2 flex justify-center">
                <button type="button"
                        x-show="visibleVouchersLimit < {{ $vouchers->count() }}"
                        @click="visibleVouchersLimit += 6"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                    <span x-text="'See More Vouchers (' + ({{ $vouchers->count() }} - visibleVouchersLimit) + ' remaining)'"></span>
                </button>
            </div>
        @endif
    @endif

    <template x-teleport="body">
        <div x-show="endConfirmOpen"
             x-cloak
             class="fixed inset-0 z-[210] flex items-center justify-center p-4">
            <div @click="endConfirmOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-border-subtle text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-danger/10 text-danger flex items-center justify-center mx-auto border border-danger/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-primary-dark">End Voucher Immediately?</h3>
                <p class="text-xs text-text-muted leading-relaxed">
                    Are you sure you want to end store voucher <strong class="text-primary-dark font-mono" x-text="endingVoucherCode"></strong> right now? Buyers will no longer be able to apply it.
                </p>
                <form :action="endingVoucherAction" method="POST" class="flex gap-2.5 pt-2 m-0">
                    @csrf
                    <button type="button" @click="endConfirmOpen = false" class="flex-1 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs font-bold transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-danger hover:opacity-90 text-white text-xs font-bold shadow-sm transition-opacity cursor-pointer">
                        End Voucher
                    </button>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="createModalOpen"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="createModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle space-y-5 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-lg font-bold text-primary-dark">Create Store Voucher</h3>
                        <p class="text-xs text-text-muted">Live generated ticket preview updates automatically as you type.</p>
                    </div>
                    <button type="button" @click="createModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="relative flex rounded-2xl border-2 border-primary/30 bg-surface shadow-sm overflow-hidden">
                    <div class="w-36 bg-gradient-to-br from-primary to-primary-dark text-white p-4 flex flex-col items-center justify-center text-center relative shrink-0 border-r-2 border-dashed border-white/40">
                        <span class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded bg-black/25 mb-1"
                              x-text="visibility === 'code_only' ? 'SECRET CODE' : 'STORE VOUCHER'"></span>
                        <div class="text-xl font-serif font-extrabold leading-tight"
                             x-text="discountType === 'percentage' ? (discountValue || 0) + '% OFF' : '\u20B1' + (discountValue || 0) + ' OFF'"></div>
                        <span class="text-[10px] text-white/90 mt-1" x-text="'Min. \u20B1' + (minSpend || 0)"></span>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-center text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-extrabold text-xs px-2 py-0.5 rounded bg-brand-light/60 text-primary-dark border border-border-subtle"
                                  x-text="voucherCode || 'VOUCHERCODE'"></span>
                            <span class="text-[10px] text-text-muted font-bold">{{ $profile->business_name }}</span>
                        </div>
                        <div class="font-bold text-sm text-text-main mt-1.5" x-text="voucherTitle || 'Store Promo Voucher'"></div>
                        <div class="text-[11px] text-text-muted mt-0.5">
                            <span x-show="discountType === 'percentage'">Capped at &#8369;<span x-text="maxDiscountCap || 0"></span> &bull; </span>
                            Minimum spend of &#8369;<span x-text="minSpend || 0"></span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('seller.vouchers.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">
                                Voucher Code (4–12 Letters/Numbers) <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="code"
                                   x-model="voucherCode"
                                   @input="sanitizeCode()"
                                   minlength="4"
                                   maxlength="12"
                                   required
                                   placeholder="e.g. MCH50"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono font-bold uppercase text-sm outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">
                                Voucher Display Title <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="title"
                                   x-model="voucherTitle"
                                   maxlength="120"
                                   required
                                   placeholder="e.g. Payday Handicrafts &#8369;50 Off"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-sm outline-none focus:border-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="relative" @click.away="discountTypeOpen = false">
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Discount Type <span class="text-danger">*</span></label>
                            <input type="hidden" name="discount_type" :value="discountType">
                            <button type="button"
                                    @click="discountTypeOpen = !discountTypeOpen"
                                    class="w-full p-3 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between cursor-pointer">
                                <span x-text="discountType === 'percentage' ? 'Percentage (%)' : 'Fixed Amount (\u20B1)'"></span>
                                <svg class="w-4 h-4 text-text-muted transition-transform" :class="discountTypeOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="discountTypeOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl z-30 overflow-hidden">
                                <button type="button" @click="discountType = 'fixed'; discountTypeOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span>Fixed Amount (&#8369;)</span>
                                    <span x-show="discountType === 'fixed'" class="text-primary font-bold">&#10003;</span>
                                </button>
                                <button type="button" @click="discountType = 'percentage'; discountTypeOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span>Percentage (%)</span>
                                    <span x-show="discountType === 'percentage'" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1"
                                   x-text="discountType === 'percentage' ? 'Discount Percentage (1-99%) *' : 'Discount Amount (\u20B1) *'"></label>
                            <input type="number"
                                   step="0.01"
                                   min="1"
                                   name="discount_value"
                                   x-model="discountValue"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Minimum Spend (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="1"
                                   name="min_spend"
                                   x-model="minSpend"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                        </div>
                    </div>

                    <div x-show="discountType === 'percentage'" x-cloak>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">
                            Maximum Discount Cap (&#8369;) <span class="text-danger">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               min="1"
                               name="max_discount_cap"
                               x-model="maxDiscountCap"
                               :required="discountType === 'percentage'"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Total Usage Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="total_usage_limit" value="{{ old('total_usage_limit', 100) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Per-Buyer Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="per_buyer_limit" value="{{ old('per_buyer_limit', 1) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                        </div>
                        <div class="relative" @click.away="visibilityOpen = false">
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Display Mode <span class="text-danger">*</span></label>
                            <input type="hidden" name="visibility" :value="visibility">
                            <button type="button"
                                    @click="visibilityOpen = !visibilityOpen"
                                    class="w-full p-3 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between cursor-pointer">
                                <span x-text="visibility === 'code_only' ? 'Secret Code-Only' : 'Claimable in Wallet'"></span>
                                <svg class="w-4 h-4 text-text-muted transition-transform" :class="visibilityOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="visibilityOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl z-30 overflow-hidden">
                                <button type="button" @click="visibility = 'claimable'; visibilityOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span>Claimable in Wallet</span>
                                    <span x-show="visibility === 'claimable'" class="text-primary font-bold">&#10003;</span>
                                </button>
                                <button type="button" @click="visibility = 'code_only'; visibilityOpen = false" class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                    <span>Secret Code-Only</span>
                                    <span x-show="visibility === 'code_only'" class="text-primary font-bold">&#10003;</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2">
                            <label class="block font-bold text-primary-dark uppercase tracking-wider">Start Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="hidden" name="starts_at" :value="startsAtDate + 'T' + startsAtTime">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" x-model="startsAtDate" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                                <input type="time" x-model="startsAtTime" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                            </div>
                        </div>
                        <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2">
                            <label class="block font-bold text-primary-dark uppercase tracking-wider">End Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="hidden" name="ends_at" :value="endsAtDate + 'T' + endsAtTime">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" x-model="endsAtDate" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                                <input type="time" x-model="endsAtTime" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-border-subtle">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-text-muted uppercase tracking-wider">Product Eligibility</label>
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="all" x-model="productScopeMode" class="text-primary">
                                    <span class="font-semibold">All Store Products</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="selected" x-model="productScopeMode" class="text-primary">
                                    <span class="font-semibold">Selected Products Only</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="productScopeMode === 'selected'" x-cloak class="max-h-40 overflow-y-auto border border-border-subtle rounded-2xl p-3 bg-surface-subtle grid grid-cols-1 sm:grid-cols-2 gap-2 custom-scrollbar">
                            @foreach($products as $prod)
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface border border-border-subtle hover:border-primary/40 cursor-pointer">
                                    <input type="checkbox" name="applicable_product_ids[]" value="{{ $prod->id }}" class="rounded text-primary">
                                    <span class="truncate font-medium">{{ $prod->name }} (&#8369;{{ number_format((float) $prod->price, 2) }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-border-subtle">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-sm transition-colors cursor-pointer">
                            Save &amp; Publish Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="editModalOpen && editingVoucher"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="editModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Edit Store Voucher (<span class="font-mono" x-text="editingVoucher?.code"></span>)</h3>
                    <button type="button" @click="editModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/seller/vouchers') }}/${editingVoucher?.id}`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')
                    <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                        <div class="flex justify-between">
                            <span class="text-text-muted">Discount Amount (Locked):</span>
                            <strong class="text-text-main" x-text="editingVoucher?.discount_label"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Minimum Spend (Locked):</span>
                            <strong class="text-text-main" x-text="'\u20B1' + editingVoucher?.min_spend"></strong>
                        </div>
                        <p class="text-[10px] text-text-muted pt-1">
                            Discount amount and minimum spend are locked to protect buyers who have already claimed this voucher.
                        </p>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Total Usage Limit <span class="text-danger">*</span></label>
                        <input type="number" name="total_usage_limit" :value="editingVoucher?.total_usage_limit" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-text-muted">Extend End Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="hidden" name="ends_at" :value="editEndsDate + 'T' + editEndsTime">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" x-model="editEndsDate" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                            <input type="time" x-model="editEndsTime" required class="p-2.5 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-xs outline-none focus:border-primary cursor-pointer">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection