@extends('buyer.account-settings', [
    'pageTitle' => 'My Vouchers & Coupons',
    'pageSubtitle' => 'Claim store, platform, and free shipping vouchers into your wallet and apply them at checkout.'
])

@section('option-content')
<div x-data="{
    activeTab: 'wallet',
    copiedCode: '',
    copyVoucherCode(code) {
        navigator.clipboard.writeText(code);
        this.copiedCode = code;
        setTimeout(() => {
            if (this.copiedCode === code) this.copiedCode = '';
        }, 2000);
    }
}" class="space-y-6 select-none min-w-0 max-w-full">

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

    <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                    Storkia Voucher Wallet
                </span>
                <h3 class="text-lg sm:text-xl font-serif font-bold mt-1">Have a Secret or Store Voucher Code?</h3>
                <p class="text-xs text-white/85 mt-0.5">
                    Enter any claimable or code-only voucher code (e.g. MCH50, PLAT100, FREESHIP) to save it to your wallet.
                </p>
            </div>

            <form action="{{ route('user.coupons.claim') }}" method="POST" class="flex items-center gap-2 w-full md:w-auto">
                @csrf
                <input type="text"
                       name="code"
                       required
                       maxlength="20"
                       placeholder="ENTER VOUCHER CODE"
                       class="flex-1 md:w-56 px-4 py-3 rounded-2xl bg-white text-text-main font-mono font-bold text-xs uppercase tracking-wider outline-none placeholder:text-text-muted/60 shadow-inner">
                <button type="submit"
                        class="px-5 py-3 rounded-2xl bg-text-main hover:bg-dark-grey text-white text-xs font-extrabold uppercase tracking-wider shadow-md transition-colors cursor-pointer shrink-0">
                    Claim Voucher
                </button>
            </form>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-subtle pb-3">
        <div class="flex items-center gap-2">
            <button type="button"
                    @click="activeTab = 'wallet'"
                    :class="activeTab === 'wallet' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer">
                My Claimed Wallet ({{ $claimedRecords->count() }})
            </button>
            <button type="button"
                    @click="activeTab = 'discover'"
                    :class="activeTab === 'discover' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer">
                Discover Claimable Vouchers ({{ $discoverableVouchers->count() }})
            </button>
        </div>

        <a href="{{ route('cart.index') }}" class="text-xs font-bold text-primary hover:underline">
            Go to Shopping Cart &rarr;
        </a>
    </div>

    <div x-show="activeTab === 'wallet'" class="space-y-4">
        @if($claimedRecords->isEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
                <h3 class="text-base font-bold text-text-main">Your Voucher Wallet is Empty</h3>
                <p class="text-xs text-text-muted max-w-md mx-auto">
                    Switch to the "Discover Claimable Vouchers" tab or enter a promo code above to claim discounts for your next checkout.
                </p>
                <button type="button"
                        @click="activeTab = 'discover'"
                        class="px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold cursor-pointer">
                    Browse Available Vouchers
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($claimedRecords as $claim)
                    @php
                        $v = $claim->voucher;
                        if (!$v) continue;
                        $isExhausted = (int) $claim->times_used >= (int) $v->per_buyer_limit || (int) $v->used_count >= (int) $v->total_usage_limit;
                        $isExpired = !$v->isCurrentlyOngoing();
                        $stubColor = match($v->scope) {
                            'shipping' => 'from-emerald-600 to-teal-700',
                            'platform' => 'from-purple-700 to-indigo-900',
                            default    => 'from-primary to-primary-dark',
                        };
                        $scopeLabel = match($v->scope) {
                            'shipping' => 'FREE SHIPPING',
                            'platform' => 'STORKIA VOUCHER',
                            default    => 'STORE VOUCHER',
                        };
                    @endphp
                    <div class="relative flex rounded-2xl border border-border-subtle bg-surface shadow-xs overflow-hidden {{ ($isExhausted || $isExpired) ? 'opacity-60 grayscale' : '' }}">
                        <div class="w-32 sm:w-36 bg-gradient-to-br {{ $stubColor }} text-white p-4 flex flex-col items-center justify-center text-center relative shrink-0 border-r-2 border-dashed border-white/40">
                            <div class="w-4 h-4 rounded-full bg-surface absolute -top-2 -right-2 border border-border-subtle"></div>
                            <div class="w-4 h-4 rounded-full bg-surface absolute -bottom-2 -right-2 border border-border-subtle"></div>

                            <span class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded bg-black/25 mb-1.5">
                                {{ $scopeLabel }}
                            </span>
                            <div class="text-lg sm:text-xl font-serif font-extrabold leading-tight">
                                @if($v->discount_type === 'percentage')
                                    {{ rtrim(rtrim(number_format((float) $v->discount_value, 2), '0'), '.') }}% OFF
                                @else
                                    ₱{{ number_format((float) $v->discount_value, 0) }} OFF
                                @endif
                            </div>
                            <span class="text-[10px] text-white/90 mt-1 font-medium">
                                Min. ₱{{ number_format((float) $v->min_spend, 0) }}
                            </span>
                        </div>

                        <div class="flex-1 p-4 flex flex-col justify-between min-w-0 text-xs">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="font-mono font-extrabold text-xs px-2 py-0.5 rounded bg-brand-light/50 text-primary-dark border border-border-subtle">
                                            {{ $v->code }}
                                        </span>
                                        <h4 class="font-bold text-sm text-text-main mt-1.5 truncate">{{ $v->title }}</h4>
                                    </div>
                                    @if($isExhausted)
                                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold shrink-0">Used</span>
                                    @elseif($isExpired)
                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-bold shrink-0">Expired</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold shrink-0">Ready</span>
                                    @endif
                                </div>

                                <p class="text-[11px] text-text-muted mt-1 line-clamp-2">
                                    {{ $v->description }}
                                    @if($v->sellerProfile)
                                        &bull; Store: <strong>{{ $v->sellerProfile->business_name }}</strong>
                                    @endif
                                </p>
                            </div>

                            <div class="pt-3 mt-3 border-t border-border-subtle flex items-center justify-between gap-2">
                                <div class="text-[10px] text-text-muted">
                                    Valid til {{ $v->ends_at?->format('M d, Y') }}
                                    &bull; Used {{ $claim->times_used }}/{{ $v->per_buyer_limit }}
                                </div>

                                @if(!$isExhausted && !$isExpired)
                                    <button type="button"
                                            @click="copyVoucherCode('{{ $v->code }}')"
                                            class="px-3 py-1.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-[11px] font-bold transition-colors cursor-pointer shrink-0">
                                        <span x-show="copiedCode !== '{{ $v->code }}'">Copy Code</span>
                                        <span x-show="copiedCode === '{{ $v->code }}'" x-cloak>Copied!</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div x-show="activeTab === 'discover'" x-cloak class="space-y-4">
        @if($discoverableVouchers->isEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">All Available Vouchers Claimed!</h3>
                <p class="text-xs text-text-muted">You have already claimed all public vouchers into your wallet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($discoverableVouchers as $v)
                    @php
                        $stubColor = match($v->scope) {
                            'shipping' => 'from-emerald-600 to-teal-700',
                            'platform' => 'from-purple-700 to-indigo-900',
                            default    => 'from-primary to-primary-dark',
                        };
                        $scopeLabel = match($v->scope) {
                            'shipping' => 'FREE SHIPPING',
                            'platform' => 'STORKIA VOUCHER',
                            default    => 'STORE VOUCHER',
                        };
                    @endphp
                    <div class="relative flex rounded-2xl border border-border-subtle bg-surface shadow-xs overflow-hidden hover:border-primary/50 transition-all">
                        <div class="w-32 sm:w-36 bg-gradient-to-br {{ $stubColor }} text-white p-4 flex flex-col items-center justify-center text-center relative shrink-0 border-r-2 border-dashed border-white/40">
                            <div class="w-4 h-4 rounded-full bg-surface absolute -top-2 -right-2 border border-border-subtle"></div>
                            <div class="w-4 h-4 rounded-full bg-surface absolute -bottom-2 -right-2 border border-border-subtle"></div>

                            <span class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded bg-black/25 mb-1.5">
                                {{ $scopeLabel }}
                            </span>
                            <div class="text-lg sm:text-xl font-serif font-extrabold leading-tight">
                                @if($v->discount_type === 'percentage')
                                    {{ rtrim(rtrim(number_format((float) $v->discount_value, 2), '0'), '.') }}% OFF
                                @else
                                    ₱{{ number_format((float) $v->discount_value, 0) }} OFF
                                @endif
                            </div>
                            <span class="text-[10px] text-white/90 mt-1 font-medium">
                                Min. ₱{{ number_format((float) $v->min_spend, 0) }}
                            </span>
                        </div>

                        <div class="flex-1 p-4 flex flex-col justify-between min-w-0 text-xs">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono font-extrabold text-xs px-2 py-0.5 rounded bg-brand-light/50 text-primary-dark border border-border-subtle">
                                        {{ $v->code }}
                                    </span>
                                    <span class="text-[10px] font-bold text-text-muted">
                                        {{ $v->total_usage_limit - $v->used_count }} left
                                    </span>
                                </div>
                                <h4 class="font-bold text-sm text-text-main mt-1.5 truncate">{{ $v->title }}</h4>
                                <p class="text-[11px] text-text-muted mt-1 line-clamp-2">
                                    {{ $v->description }}
                                    @if($v->sellerProfile)
                                        &bull; Store: <strong>{{ $v->sellerProfile->business_name }}</strong>
                                    @endif
                                </p>
                            </div>

                            <div class="pt-3 mt-3 border-t border-border-subtle flex items-center justify-between gap-2">
                                <span class="text-[10px] text-text-muted">Valid til {{ $v->ends_at?->format('M d, Y') }}</span>
                                <form action="{{ route('user.coupons.claim') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="code" value="{{ $v->code }}">
                                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-[11px] font-bold shadow-xs transition-colors cursor-pointer">
                                        Claim to Wallet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection