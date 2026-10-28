@extends('layouts.seller', ['title' => 'Storkia - Store Vouchers'])

@section('content')
<div x-data="{
    createModalOpen: {{ $errors->any() ? 'true' : 'false' }},
    editModalOpen: false,
    editingVoucher: null,
    voucherCode: @js(old('code', '')),
    voucherTitle: @js(old('title', '')),
    discountType: @js(old('discount_type', 'fixed')),
    discountValue: @js(old('discount_value', 50)),
    maxDiscountCap: @js(old('max_discount_cap', 100)),
    minSpend: @js(old('min_spend', 300)),
    visibility: @js(old('visibility', 'claimable')),
    productScopeMode: 'all',
    sanitizeCode() {
        this.voucherCode = (this.voucherCode || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
    },
    openEditVoucher(voucher) {
        this.editingVoucher = voucher;
        this.editModalOpen = true;
    }
}" class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Store Vouchers & Promotions</h1>
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

    @if($vouchers->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center space-y-3">
            <h3 class="text-base font-bold text-text-main">No store vouchers created yet</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Boost your store conversion by offering Fixed-Amount or Percentage-Capped store vouchers.
            </p>
            <button type="button"
                    @click="createModalOpen = true"
                    class="px-5 py-2.5 rounded-xl bg-primary-dark text-white text-xs font-bold cursor-pointer">
                + Create Your First Store Voucher
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach($vouchers as $v)
                @php
                    $isOngoing = $v->isCurrentlyOngoing();
                    $hasClaimsOrUses = ($v->claims_count > 0) || ($v->used_count > 0);
                @endphp
                <div class="relative flex rounded-3xl border border-border-subtle bg-surface shadow-xs overflow-hidden {{ $v->status !== 'active' ? 'opacity-65' : '' }}">
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
                                ₱{{ number_format((float) $v->discount_value, 0) }} OFF
                            @endif
                        </div>
                        @if($v->discount_type === 'percentage' && $v->max_discount_cap)
                            <span class="text-[10px] text-white/90 mt-0.5">Cap ₱{{ number_format((float) $v->max_discount_cap, 0) }}</span>
                        @endif
                        <span class="text-[10px] text-white/90 mt-1 font-semibold">
                            Min. Spend ₱{{ number_format((float) $v->min_spend, 0) }}
                        </span>
                    </div>

                    <div class="flex-1 p-5 flex flex-col justify-between min-w-0 text-xs">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono font-extrabold text-sm px-2.5 py-0.5 rounded-lg bg-brand-light/50 text-primary-dark border border-border-subtle">
                                    {{ $v->code }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $isOngoing ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
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
                                            class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                        Edit Limits
                                    </button>

                                    <form action="{{ route('seller.vouchers.end', $v->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('End voucher {{ $v->code }} immediately?')"
                                                class="px-3 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-danger border border-red-200 font-bold cursor-pointer">
                                            End Now
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

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
                    <button type="button" @click="createModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <div class="relative flex rounded-2xl border-2 border-primary/30 bg-surface shadow-sm overflow-hidden">
                    <div class="w-36 bg-gradient-to-br from-primary to-primary-dark text-white p-4 flex flex-col items-center justify-center text-center relative shrink-0 border-r-2 border-dashed border-white/40">
                        <span class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded bg-black/25 mb-1"
                              x-text="visibility === 'code_only' ? 'SECRET CODE' : 'STORE VOUCHER'"></span>
                        <div class="text-xl font-serif font-extrabold leading-tight"
                             x-text="discountType === 'percentage' ? (discountValue || 0) + '% OFF' : '₱' + (discountValue || 0) + ' OFF'"></div>
                        <span class="text-[10px] text-white/90 mt-1" x-text="'Min. ₱' + (minSpend || 0)"></span>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-center text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-extrabold text-xs px-2 py-0.5 rounded bg-brand-light/60 text-primary-dark border border-border-subtle"
                                  x-text="voucherCode || 'VOUCHERCODE'"></span>
                            <span class="text-[10px] text-text-muted font-bold">{{ $profile->business_name }}</span>
                        </div>
                        <div class="font-bold text-sm text-text-main mt-1.5" x-text="voucherTitle || 'Store Promo Voucher'"></div>
                        <div class="text-[11px] text-text-muted mt-0.5">
                            <span x-show="discountType === 'percentage'">Capped at ₱<span x-text="maxDiscountCap || 0"></span> &bull; </span>
                            Minimum spend of ₱<span x-text="minSpend || 0"></span>
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
                                   placeholder="e.g. Payday Handicrafts ₱50 Off"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-sm outline-none focus:border-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" x-model="discountType" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                                <option value="fixed">Fixed Amount (₱)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1"
                                   x-text="discountType === 'percentage' ? 'Discount Percentage (1-99%) *' : 'Discount Amount (₱) *'"></label>
                            <input type="number"
                                   step="0.01"
                                   min="1"
                                   name="discount_value"
                                   x-model="discountValue"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Minimum Spend (₱) <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="1"
                                   name="min_spend"
                                   x-model="minSpend"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                    </div>

                    <div x-show="discountType === 'percentage'" x-cloak>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">
                            Maximum Discount Cap (₱) <span class="text-danger">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               min="1"
                               name="max_discount_cap"
                               x-model="maxDiscountCap"
                               :required="discountType === 'percentage'"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Total Usage Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="total_usage_limit" value="{{ old('total_usage_limit', 100) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Per-Buyer Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="per_buyer_limit" value="{{ old('per_buyer_limit', 1) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Display Mode <span class="text-danger">*</span></label>
                            <select name="visibility" x-model="visibility" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                                <option value="claimable">Claimable in Wallet</option>
                                <option value="code_only">Secret Code-Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Start Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">End Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addMonth()->format('Y-m-d\TH:i')) }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
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
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface border border-border-subtle cursor-pointer">
                                    <input type="checkbox" name="applicable_product_ids[]" value="{{ $prod->id }}" class="rounded text-primary">
                                    <span class="truncate font-medium">{{ $prod->name }} (₱{{ number_format((float) $prod->price, 2) }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-border-subtle">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-sm cursor-pointer">
                            Save & Publish Voucher
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
                    <button type="button" @click="editModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
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
                            <strong class="text-text-main" x-text="'₱' + editingVoucher?.min_spend"></strong>
                        </div>
                        <p class="text-[10px] text-text-muted pt-1">
                            Discount amount and minimum spend are locked to protect buyers who have already claimed this voucher.
                        </p>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Total Usage Limit <span class="text-danger">*</span></label>
                        <input type="number" name="total_usage_limit" :value="editingVoucher?.total_usage_limit" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Extend End Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="ends_at" :value="editingVoucher?.ends_at" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection