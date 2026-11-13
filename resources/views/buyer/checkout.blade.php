@extends('layouts.app', ['title' => 'Checkout - Storkia'])

@section('content')
@php
    $platformGcash = $platformGcash ?? null;
    if (!$platformGcash && \Illuminate\Support\Facades\Schema::hasTable('platform_gcash_settings')) {
        $platformGcash = \Illuminate\Support\Facades\DB::table('platform_gcash_settings')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    $isPlatformGcashActive = $platformGcash && !empty($platformGcash->gcash_number);
    $buyerWalletBalance = isset($buyerWalletBalance) ? round((float) $buyerWalletBalance, 2) : 0.00;
    $buyerCoinsBalance = isset($buyerCoinsBalance) ? (int) $buyerCoinsBalance : 0;
    $maxRedeemableCoins = isset($maxRedeemableCoins) ? (int) $maxRedeemableCoins : 0;
    $maxRedeemableDiscount = isset($maxRedeemableDiscount)
        ? round((float) $maxRedeemableDiscount, 2)
        : round($maxRedeemableCoins * \App\Services\BuyerRewardService::COIN_TO_PESO_VALUE, 2);
    $overallCoinsUsed = isset($overallCoinsUsed) ? (int) $overallCoinsUsed : 0;
    $overallCoinsDiscount = isset($overallCoinsDiscount) ? (float) $overallCoinsDiscount : 0.00;
    $useStorkiaCoins = isset($useStorkiaCoins) ? (bool) $useStorkiaCoins : false;
    $selectedCartIds = $selectedCartIds ?? [];

    $initialPaymentMethods = [];
    $initialNotes = [];
    $currentStoreVoucherCodes = [];
    $initialPackageMetrics = [];

    foreach ($sellerPackages as $pkg) {
        $spId = (int) $pkg['seller_profile']->id;
        $oldMethod = strtoupper((string) old("payment_methods.{$spId}", 'COD'));
        $initialPaymentMethods[$spId] = in_array($oldMethod, ['COD', 'WALLET'], true) ? $oldMethod : 'COD';
        $initialNotes[$spId] = (string) old("seller_notes.{$spId}", '');
        $currentStoreVoucherCodes[$spId] = $pkg['store_voucher'] ? (string) $pkg['store_voucher']->code : '';

        $initialPackageMetrics[$spId] = [
            'subtotal'          => (float) ($pkg['subtotal'] ?? 0),
            'bundle_discount'   => (float) ($pkg['bundle_discount'] ?? 0),
            'store_discount'    => (float) ($pkg['store_discount'] ?? 0),
            'platform_discount' => (float) ($pkg['platform_discount'] ?? 0),
            'shipping_discount' => (float) ($pkg['shipping_discount'] ?? 0),
            'coins_used'        => (int) ($pkg['coins_used'] ?? 0),
            'coins_discount'    => (float) ($pkg['coins_discount'] ?? 0),
            'shipping_fee'      => (float) ($pkg['shipping']['shipping_fee'] ?? 0),
            'net_subtotal'      => (float) ($pkg['net_subtotal'] ?? 0),
            'net_shipping_fee'  => (float) ($pkg['net_shipping_fee'] ?? 0),
            'total'             => (float) ($pkg['total'] ?? 0),
            'shipping_tier'     => (string) ($pkg['shipping']['tier'] ?? ''),
        ];
    }

    $initialAddressData = $selectedAddress ? [
        'id'           => (int) $selectedAddress->id,
        'full_name'    => trim($selectedAddress->first_name . ' ' . $selectedAddress->last_name),
        'phone_number' => ltrim((string) $selectedAddress->phone_number, '0'),
        'full_address' => collect([
            $selectedAddress->building_details,
            $selectedAddress->street_address,
            $selectedAddress->barangay,
            $selectedAddress->municipality,
            $selectedAddress->province,
            $selectedAddress->postcode,
        ])->filter()->implode(', '),
    ] : null;

    $platformVouchers = $availableVouchers->where('scope', 'platform')->values();
    $shippingVouchers = $availableVouchers->where('scope', 'shipping')->values();

    $appliedPlatformCodeStr = $appliedPlatformVoucher ? (string) $appliedPlatformVoucher->code : '';
    $appliedShippingCodeStr = $appliedShippingVoucher ? (string) $appliedShippingVoucher->code : '';

    $checkoutConfig = [
        'mode'                  => (string) $mode,
        'selectedCartIdsCsv'    => implode(',', $selectedCartIds),
        'topupSenderName'       => (string) (auth()->user()?->full_name ?? ''),
        'buyerWalletBalance'    => (float) $buyerWalletBalance,
        'useStorkiaCoins'       => (bool) $useStorkiaCoins,
        'paymentMethods'        => $initialPaymentMethods,
        'sellerNotes'           => $initialNotes,
        'storeVoucherInputs'    => $currentStoreVoucherCodes,
        'platformVoucherInput'  => $appliedPlatformCodeStr,
        'shippingVoucherInput'  => $appliedShippingCodeStr,
        'selectedAddress'       => $initialAddressData,
        'isAddressServiceable'  => (bool) $isAddressServiceable,
        'packageMetrics'        => $initialPackageMetrics,
        'summary'               => [
            'totalItemsCount'         => (int) $totalItemsCount,
            'overallSubtotal'         => (float) $overallSubtotal,
            'overallShipping'         => (float) $overallShipping,
            'overallStoreDiscount'    => (float) $overallStoreDiscount,
            'overallPlatformDiscount' => (float) $overallPlatformDiscount,
            'overallShippingDiscount' => (float) $overallShippingDiscount,
            'overallCoinsUsed'        => (int) $overallCoinsUsed,
            'overallCoinsDiscount'    => (float) $overallCoinsDiscount,
            'maxRedeemableCoins'      => (int) $maxRedeemableCoins,
            'maxRedeemableDiscount'   => (float) $maxRedeemableDiscount,
            'buyerCoinsBalance'       => (int) $buyerCoinsBalance,
            'overallTotal'            => (float) $overallTotal,
            'appliedPlatformCode'     => $appliedPlatformCodeStr,
            'appliedShippingCode'     => $appliedShippingCodeStr,
        ],
        'liveVoucherNotices'    => array_values($voucherNotices ?? []),
        'bestVouchers'          => $bestVouchers ?? ['store' => [], 'platform' => '', 'shipping' => ''],
        'touchedSlots'          => array_values($touchedSlots ?? []),
        'livePriceSignature'    => (string) $priceSignature,
        'topupUrl'              => route('user.points.wallet-topup'),
        'checkoutIndexUrl'      => route('checkout.index'),
        'csrfToken'             => csrf_token(),
    ];
@endphp

<script>
    window.storkiaCheckoutState = function (cfg) {
        return {
            addressModalOpen: false,
            topupModalOpen: false,
            topupSenderName: cfg.topupSenderName || '',
            topupReferenceNumber: '',
            topupScreenshotName: '',
            topupPreviewUrl: '',
            topupError: '',
            topupSuccessMsg: '',
            isSubmittingTopup: false,
            copiedAdminGcash: false,
            isSubmitting: false,
            isSyncingCheckout: false,
            mode: cfg.mode || 'cart',
            selectedCartIdsCsv: cfg.selectedCartIdsCsv || '',
            buyerWalletBalance: Number(cfg.buyerWalletBalance || 0),
            useStorkiaCoins: Boolean(cfg.useStorkiaCoins),
            paymentMethods: cfg.paymentMethods || {},
            sellerNotes: cfg.sellerNotes || {},
            storeVoucherInputs: cfg.storeVoucherInputs || {},
            platformVoucherInput: cfg.platformVoucherInput || '',
            shippingVoucherInput: cfg.shippingVoucherInput || '',
            selectedAddress: cfg.selectedAddress || null,
            isAddressServiceable: Boolean(cfg.isAddressServiceable),
            packageMetrics: cfg.packageMetrics || {},
            summary: cfg.summary || {},
            liveVoucherNotices: Array.isArray(cfg.liveVoucherNotices) ? cfg.liveVoucherNotices : [],
            livePriceSignature: cfg.livePriceSignature || '',
            bestVouchers: cfg.bestVouchers || { store: {}, platform: '', shipping: '' },
            touchedSlots: Array.isArray(cfg.touchedSlots) ? cfg.touchedSlots : [],

            touchSlot(slot) {
                if (!this.touchedSlots.includes(slot)) {
                    this.touchedSlots.push(slot);
                }
            },

            isBestVoucher(type, code, spId = null) {
                if (!code) return false;
                const best = this.bestVouchers || {};
                if (type === 'store') {
                    const storeMap = best.store || {};
                    return String(storeMap[spId] || '') === String(code);
                }
                return String(best[type] || '') === String(code);
            },

            formatMoney(amount) {
                return Number(amount || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },

            maxRedeemablePesoValue() {
                if (typeof this.summary.maxRedeemableDiscount !== 'undefined') {
                    return Number(this.summary.maxRedeemableDiscount || 0);
                }
                return Math.round(Number(this.summary.maxRedeemableCoins || 0) * 10) / 100;
            },

            packageTotal(spId) {
                return this.packageMetrics[spId] ? Number(this.packageMetrics[spId].total || 0) : 0;
            },

            packageCoinsUsed(spId) {
                return this.packageMetrics[spId] ? Number(this.packageMetrics[spId].coins_used || 0) : 0;
            },

            packageCoinsDiscount(spId) {
                return this.packageMetrics[spId] ? Number(this.packageMetrics[spId].coins_discount || 0) : 0;
            },

            totalWalletRequired() {
                let sum = 0;
                Object.keys(this.paymentMethods).forEach((spId) => {
                    if (this.paymentMethods[spId] === 'WALLET') {
                        sum += this.packageTotal(spId);
                    }
                });
                return Math.round(sum * 100) / 100;
            },

            hasSufficientWalletBalance() {
                return (this.buyerWalletBalance + 0.0001) >= this.totalWalletRequired();
            },

            walletShortfall() {
                return Math.max(0, Math.round((this.totalWalletRequired() - this.buyerWalletBalance) * 100) / 100);
            },

            selectCod(spId) {
                this.paymentMethods[spId] = 'COD';
            },

            selectWallet(spId) {
                this.paymentMethods[spId] = 'WALLET';
            },

            copyAdminNumber(num) {
                if (!num || !navigator.clipboard) return;
                navigator.clipboard.writeText(num);
                this.copiedAdminGcash = true;
                setTimeout(() => { this.copiedAdminGcash = false; }, 2000);
            },

            onTopupScreenshotChange(event) {
                this.topupError = '';
                const file = event.target.files[0];
                if (!file) {
                    this.topupScreenshotName = '';
                    this.topupPreviewUrl = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.topupError = 'Screenshot image must not exceed 5MB.';
                    event.target.value = '';
                    this.topupScreenshotName = '';
                    this.topupPreviewUrl = '';
                    return;
                }
                this.topupScreenshotName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { this.topupPreviewUrl = e.target.result; };
                reader.readAsDataURL(file);
            },

            async submitWalletTopupAjax(event) {
                if (this.isSubmittingTopup) return;
                this.topupError = '';
                this.topupSuccessMsg = '';
                const form = event.target;
                const fd = new FormData(form);

                this.isSubmittingTopup = true;
                try {
                    const res = await fetch(cfg.topupUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': cfg.csrfToken
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        const errText = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not submit top-up receipt.');
                        this.topupError = errText;
                    } else {
                        this.topupSuccessMsg = data.message;
                        this.topupReferenceNumber = '';
                        this.topupScreenshotName = '';
                        this.topupPreviewUrl = '';
                        form.reset();
                    }
                } catch (e) {
                    this.topupError = 'Network error while submitting top-up receipt.';
                } finally {
                    this.isSubmittingTopup = false;
                }
            },

            allSelectedPaymentsValid() {
                return this.hasSufficientWalletBalance();
            },

            buildSyncParams(overrideAddressId = null) {
                const params = new URLSearchParams();
                params.set('mode', this.mode);
                const addrId = overrideAddressId !== null
                    ? overrideAddressId
                    : (this.selectedAddress ? this.selectedAddress.id : '');
                if (addrId) {
                    params.set('address_id', String(addrId));
                }
                if (this.selectedCartIdsCsv) {
                    params.set('cart_ids', this.selectedCartIdsCsv);
                }
                Object.keys(this.storeVoucherInputs).forEach((spId) => {
                    const val = (this.storeVoucherInputs[spId] || '').trim();
                    if (val) {
                        params.set(`store_vouchers[${spId}]`, val.toUpperCase());
                    }
                });
                if ((this.platformVoucherInput || '').trim()) {
                    params.set('platform_voucher_code', this.platformVoucherInput.trim().toUpperCase());
                }
                if ((this.shippingVoucherInput || '').trim()) {
                    params.set('shipping_voucher_code', this.shippingVoucherInput.trim().toUpperCase());
                }
                if (this.useStorkiaCoins) {
                    params.set('use_storkia_coins', '1');
                }
                this.touchedSlots.forEach((slot) => {
                    params.append('touched_slots[]', slot);
                });
                return params;
            },

            async syncCheckoutRealtime(overrideAddressId = null, actionFeedback = '') {
                const cleanParams = this.buildSyncParams(overrideAddressId);
                const fetchParams = new URLSearchParams(cleanParams.toString());
                fetchParams.set('live_preview', '1');

                this.isSyncingCheckout = true;
                if (window.StorkiaLive) {
                    window.StorkiaLive.startProgress();
                }

                try {
                    const response = await fetch(cfg.checkoutIndexUrl + '?' + fetchParams.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) return;
                    const data = await response.json();
                    if (!data || !data.success) return;

                    if (typeof data.buyer_wallet_balance !== 'undefined') {
                        this.buyerWalletBalance = Number(data.buyer_wallet_balance || 0);
                    }
                    if (data.selected_address) {
                        this.selectedAddress = data.selected_address;
                    }
                    this.isAddressServiceable = Boolean(data.is_address_serviceable);

                    if (data.packages && typeof data.packages === 'object') {
                        Object.keys(data.packages).forEach((spId) => {
                            this.packageMetrics[spId] = data.packages[spId];
                            this.storeVoucherInputs[spId] = data.packages[spId].store_voucher_code || '';
                        });
                    }

                    if (data.summary) {
                        this.summary = data.summary;
                        this.platformVoucherInput = data.summary.appliedPlatformCode || '';
                        this.shippingVoucherInput = data.summary.appliedShippingCode || '';
                    }

                    this.liveVoucherNotices = Array.isArray(data.voucher_notices) ? data.voucher_notices : [];
                    if (data.best_vouchers) {
                        this.bestVouchers = data.best_vouchers;
                    }
                    if (data.price_signature) {
                        this.livePriceSignature = data.price_signature;
                    }

                    const nextUrl = cfg.checkoutIndexUrl + '?' + cleanParams.toString();
                    window.history.replaceState({ storkiaLive: true }, document.title, nextUrl);

                    if (window.StorkiaLive) {
                        if (this.liveVoucherNotices.length > 0) {
                            window.StorkiaLive.showToast(this.liveVoucherNotices[0], 'warning');
                        } else if (actionFeedback) {
                            window.StorkiaLive.showToast(actionFeedback, 'success');
                        }
                    }
                } catch (err) {
                    console.error('Real-time checkout sync error:', err);
                } finally {
                    this.isSyncingCheckout = false;
                    if (window.StorkiaLive) {
                        window.StorkiaLive.finishProgress();
                    }
                }
            },

            async changeDeliveryAddress(addressId) {
                this.addressModalOpen = false;
                await this.syncCheckoutRealtime(addressId, 'Delivery address and shipping fees updated.');
            },

            async applyVouchersReload(feedbackMsg = 'Checkout summary updated.') {
                await this.syncCheckoutRealtime(null, feedbackMsg);
            },

            async toggleCoinsOffset() {
                this.useStorkiaCoins = !this.useStorkiaCoins;
                await this.syncCheckoutRealtime(null, this.useStorkiaCoins ? 'Storkia Coins discount applied!' : 'Storkia Coins discount removed.');
            }
        };
    };
</script>

<div x-data="storkiaCheckoutState(@js($checkoutConfig))"
     class="min-h-screen bg-surface-subtle/40 pb-16 lg:pb-20 pt-4 w-full overflow-x-hidden">

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
                @foreach($errors->all() as $err)
                    <p class="font-semibold">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <template x-if="liveVoucherNotices.length > 0">
            <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs sm:text-sm space-y-1">
                <template x-for="(notice, nIdx) in liveVoucherNotices" :key="'vn_' + nIdx">
                    <p class="font-semibold" x-text="notice"></p>
                </template>
            </div>
        </template>

        <form action="{{ route('checkout.store') }}"
              method="POST"
              data-no-live
              @submit="if (isSubmitting || isSyncingCheckout || !allSelectedPaymentsValid()) { $event.preventDefault(); } else { isSubmitting = true; }">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <input type="hidden" name="user_address_id" :value="selectedAddress ? selectedAddress.id : ''">
            <input type="hidden" name="price_signature" :value="livePriceSignature">
            <input type="hidden" name="platform_voucher_code" :value="platformVoucherInput">
            <input type="hidden" name="shipping_voucher_code" :value="shippingVoucherInput">
            <input type="hidden" name="use_storkia_coins" :value="useStorkiaCoins ? '1' : '0'">
            @foreach($selectedCartIds as $cid)
                <input type="hidden" name="cart_ids[]" value="{{ $cid }}">
            @endforeach

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <div class="lg:col-span-8 space-y-5 min-w-0">

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary via-secondary to-primary-dark"></div>
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-primary-dark">Delivery Address</h2>
                                <template x-if="selectedAddress">
                                    <div>
                                        <p class="text-sm font-bold text-text-main mt-1">
                                            <span x-text="selectedAddress.full_name"></span>
                                            <span class="text-text-muted font-normal ml-2">+63 <span x-text="selectedAddress.phone_number"></span></span>
                                        </p>
                                        <p class="text-xs sm:text-sm text-text-muted mt-1 leading-relaxed" x-text="selectedAddress.full_address"></p>
                                    </div>
                                </template>
                                <template x-if="!selectedAddress">
                                    <p class="text-sm text-danger font-semibold mt-1">Please add a delivery address to place your order.</p>
                                </template>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($addresses->count() > 1)
                                    <button type="button" @click.stop="addressModalOpen = true" class="px-4 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:border-primary text-xs font-bold text-text-main cursor-pointer">
                                        Change Address
                                    </button>
                                @endif
                                <a href="{{ route('user.addresses') }}" class="px-4 py-2 rounded-xl bg-primary/10 text-primary text-xs font-bold">
                                    Manage Addresses
                                </a>
                            </div>
                        </div>
                    </div>

                    @foreach($sellerPackages as $pkgIndex => $pkg)
                        @php
                            $sellerProfile = $pkg['seller_profile'];
                            $spId = (int) $sellerProfile->id;
                            $storeVouchersForSeller = $availableVouchers->where('scope', 'store')->where('seller_profile_id', $spId)->values();
                        @endphp

                        <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
                            <div class="p-4 sm:px-6 bg-surface-subtle/60 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2.5 py-0.5 rounded-lg bg-primary-dark text-surface text-xs font-bold">Package {{ $pkgIndex + 1 }}</span>
                                    <span class="font-bold text-sm sm:text-base text-primary-dark">{{ $sellerProfile->business_name }}</span>
                                </div>
                                <span class="text-xs text-text-muted">Ships from: <strong>{{ $sellerProfile->municipality }}, {{ $sellerProfile->province }}</strong></span>
                            </div>

                            <div class="divide-y divide-border-subtle/60">
                                @foreach($pkg['items'] as $item)
                                    <div class="p-4 sm:px-6 flex items-start gap-4">
                                        <div class="w-16 h-16 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                            @if(!empty($item['image_path']))
                                                <img src="{{ asset('storage/' . $item['image_path']) }}" alt="{{ $item['product']->name }}" class="w-full h-full object-contain rounded-lg">
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-semibold text-text-main truncate">{{ $item['product']->name }}</h4>
                                                @if(!empty($item['pricing_label']) && !in_array($item['pricing_label'], ['Regular', 'Regular Price'], true))
                                                    <span class="px-2 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase shrink-0">{{ $item['pricing_label'] }}</span>
                                                @endif
                                            </div>
                                            @if(!empty($item['variant_name']))
                                                <span class="inline-flex mt-1 px-2 py-0.5 rounded bg-surface-subtle border border-border-subtle text-xs text-text-muted">
                                                    {{ implode(' / ', array_filter([$item['variant_name'], $item['sub_variant_name']])) }}
                                                </span>
                                            @endif
                                            <div class="mt-1.5 flex items-center justify-between text-xs text-text-muted">
                                                <span>₱{{ number_format($item['discounted_unit_price'], 2) }} &times; {{ $item['quantity'] }}</span>
                                                <strong class="text-primary text-sm">₱{{ number_format($item['line_total'], 2) }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="p-4 sm:px-6 bg-surface-subtle/30 border-t border-border-subtle space-y-4 text-xs">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="font-bold text-text-muted uppercase tracking-wider">Note to {{ $sellerProfile->business_name }}</label>
                                        <span class="text-[11px] text-text-muted" x-text="(sellerNotes[{{ $spId }}] || '').length + '/200'"></span>
                                    </div>
                                    <input type="text"
                                           name="seller_notes[{{ $spId }}]"
                                           x-model="sellerNotes[{{ $spId }}]"
                                           maxlength="200"
                                           placeholder="Optional message to seller..."
                                           class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                                </div>

                                <div class="space-y-2.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label class="font-bold text-primary-dark uppercase tracking-wider">
                                            Store Voucher ({{ $sellerProfile->business_name }})
                                        </label>
                                        <input type="hidden" name="store_voucher_codes[{{ $spId }}]" :value="storeVoucherInputs[{{ $spId }}]">
                                    </div>

                                    @if($storeVouchersForSeller->isNotEmpty())
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                            <label @click.prevent="touchSlot('store_{{ $spId }}'); if (storeVoucherInputs[{{ $spId }}] !== '') { storeVoucherInputs[{{ $spId }}] = ''; applyVouchersReload('Store voucher removed.'); }"
                                                   :class="!storeVoucherInputs[{{ $spId }}] ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface'"
                                                   class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition-all">
                                                <input type="radio" name="radio_store_voucher_{{ $spId }}" value="" :checked="!storeVoucherInputs[{{ $spId }}]" class="text-primary pointer-events-none">
                                                <span class="font-bold text-text-muted">No Store Voucher</span>
                                            </label>
                                            @foreach($storeVouchersForSeller as $sv)
                                                <label @click.prevent="touchSlot('store_{{ $spId }}'); if (storeVoucherInputs[{{ $spId }}] !== '{{ $sv->code }}') { storeVoucherInputs[{{ $spId }}] = '{{ $sv->code }}'; applyVouchersReload('Store voucher {{ $sv->code }} applied!'); }"
                                                       :class="storeVoucherInputs[{{ $spId }}] === '{{ $sv->code }}' ? 'border-primary ring-2 ring-primary/20 bg-surface' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                                       class="flex rounded-2xl border overflow-hidden cursor-pointer shadow-2xs transition-all">
                                                    <div class="w-24 bg-gradient-to-br from-primary to-primary-dark text-white p-2.5 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                                        <span class="text-[8px] font-extrabold uppercase">STORE</span>
                                                        <span class="font-serif font-extrabold text-xs leading-tight">{{ $sv->badge_label }}</span>
                                                        <span class="text-[9px] text-white/90">Min ₱{{ number_format((float) $sv->min_spend, 0) }}</span>
                                                    </div>
                                                    <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                        <div class="min-w-0">
                                                            <span class="font-mono font-extrabold text-primary-dark block">{{ $sv->code }}</span>
                                                            <span class="text-[11px] text-text-main font-semibold truncate block">{{ $sv->title }}</span>
                                                            <span class="text-[10px] text-text-muted block">Exp {{ $sv->ends_at?->format('M d, Y') }}</span>
                                                            <span x-show="isBestVoucher('store', '{{ $sv->code }}', {{ $spId }})" x-cloak
                                                                  class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-[9px] font-extrabold uppercase tracking-wide">
                                                                &#9733; Most Preferred
                                                            </span>
                                                        </div>
                                                        <input type="radio" name="radio_store_voucher_{{ $spId }}" value="{{ $sv->code }}" :checked="storeVoucherInputs[{{ $spId }}] === '{{ $sv->code }}'" class="text-primary shrink-0 pointer-events-none">
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2">
                                        <input type="text"
                                               x-model="storeVoucherInputs[{{ $spId }}]"
                                               @keydown.enter.prevent="touchSlot('store_{{ $spId }}'); applyVouchersReload('Store voucher code applied.')"
                                               placeholder="Or enter secret store voucher code..."
                                               class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase text-xs outline-none focus:border-primary">
                                        <button type="button"
                                                :disabled="isSyncingCheckout"
                                                @click="touchSlot('store_{{ $spId }}'); applyVouchersReload('Store voucher code applied.')"
                                                class="px-4 py-2.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer disabled:opacity-50">
                                            Apply Code
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 sm:px-6 bg-surface border-t border-border-subtle space-y-3 text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-bold uppercase tracking-wider text-primary-dark block">
                                        Payment Method for {{ $sellerProfile->business_name }} <span class="text-danger">*</span>
                                    </span>
                                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-text-muted font-semibold">
                                        <span x-show="packageCoinsUsed({{ $spId }}) > 0" x-cloak class="px-2 py-0.5 rounded-full bg-brand-light/40 text-primary-dark font-bold">
                                            Coins Share: <span x-text="packageCoinsUsed({{ $spId }})"></span> Coins (-₱<span x-text="formatMoney(packageCoinsDiscount({{ $spId }}))"></span>)
                                        </span>
                                        <span>
                                            Package Total: <strong class="text-primary">₱<span x-text="formatMoney(packageTotal({{ $spId }}))"></span></strong>
                                        </span>
                                    </div>
                                </div>

                                <input type="hidden" name="payment_methods[{{ $spId }}]" :value="paymentMethods[{{ $spId }}]">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div @click="selectCod({{ $spId }})"
                                         :class="paymentMethods[{{ $spId }}] === 'COD' ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface'"
                                         class="p-3.5 rounded-2xl border-2 cursor-pointer flex items-center justify-between gap-3 transition-all">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">COD</div>
                                            <div>
                                                <div class="font-bold text-text-main">Cash on Delivery (COD)</div>
                                                <div class="text-[11px] text-text-muted">Pay cash to Rider upon delivery</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div @click="selectWallet({{ $spId }})"
                                         :class="paymentMethods[{{ $spId }}] === 'WALLET' ? 'border-emerald-600 bg-emerald-50/50' : 'border-border-subtle bg-surface'"
                                         class="p-3.5 rounded-2xl border-2 cursor-pointer flex items-center justify-between gap-3 transition-all">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white font-bold text-[11px] flex items-center justify-center shrink-0">WALLET</div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-text-main">Storkia E-Wallet (Instant Pay)</div>
                                                <div class="text-[11px] text-emerald-800 font-semibold truncate">
                                                    Balance: ₱<span x-text="formatMoney(buyerWalletBalance)"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button"
                                                @click.stop="topupModalOpen = true"
                                                class="px-2.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-extrabold shrink-0 cursor-pointer">
                                            + Top Up via GCash
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5 text-xs">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Platform &amp; Free Shipping Vouchers</h3>
                            <p class="text-xs text-text-muted">Select your claimed ticket voucher below or type a voucher code manually. The voucher marked <strong class="text-amber-800">Most Preferred</strong> saves you the most and is applied automatically when your order qualifies.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-purple-900">Platform Voucher (Admin-Sponsored)</label>
                                @if($platformVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click.prevent="touchSlot('platform'); if (platformVoucherInput !== '') { platformVoucherInput = ''; applyVouchersReload('Platform voucher removed.'); }"
                                               :class="!platformVoucherInput ? 'border-purple-700 bg-purple-50/50' : 'border-border-subtle bg-surface'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition-all">
                                            <input type="radio" name="radio_platform_voucher" value="" :checked="!platformVoucherInput" class="pointer-events-none">
                                            <span class="font-bold text-text-muted">No Platform Voucher</span>
                                        </label>
                                        @foreach($platformVouchers as $pv)
                                            <label @click.prevent="touchSlot('platform'); if (platformVoucherInput !== '{{ $pv->code }}') { platformVoucherInput = '{{ $pv->code }}'; applyVouchersReload('Platform voucher {{ $pv->code }} applied!'); }"
                                                   :class="platformVoucherInput === '{{ $pv->code }}' ? 'border-purple-700 ring-2 ring-purple-200 bg-surface' : 'border-border-subtle bg-surface'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer transition-all">
                                                <div class="w-24 bg-gradient-to-br from-purple-700 to-indigo-900 text-white p-2.5 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                                    <span class="text-[8px] font-extrabold uppercase">PLATFORM</span>
                                                    <span class="font-serif font-extrabold text-xs">{{ $pv->badge_label }}</span>
                                                    <span class="text-[9px] text-white/90">Min ₱{{ number_format((float) $pv->min_spend, 0) }}</span>
                                                </div>
                                                <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                    <div class="min-w-0">
                                                        <span class="font-mono font-extrabold text-purple-900 block">{{ $pv->code }}</span>
                                                        <span class="text-[11px] font-semibold text-text-main truncate block">{{ $pv->title }}</span>
                                                        <span class="text-[10px] text-text-muted block">Exp {{ $pv->ends_at?->format('M d, Y') }}</span>
                                                        <span x-show="isBestVoucher('platform', '{{ $pv->code }}')" x-cloak
                                                              class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-[9px] font-extrabold uppercase tracking-wide">
                                                            &#9733; Most Preferred
                                                        </span>
                                                    </div>
                                                    <input type="radio" name="radio_platform_voucher" value="{{ $pv->code }}" :checked="platformVoucherInput === '{{ $pv->code }}'" class="pointer-events-none">
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                           x-model="platformVoucherInput"
                                           @keydown.enter.prevent="touchSlot('platform'); applyVouchersReload('Platform voucher code applied.')"
                                           placeholder="Enter Platform Code..."
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                    <button type="button"
                                            :disabled="isSyncingCheckout"
                                            @click="touchSlot('platform'); applyVouchersReload('Platform voucher code applied.')"
                                            class="px-3.5 py-2.5 rounded-xl bg-purple-800 text-white font-bold cursor-pointer disabled:opacity-50">
                                        Apply
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-emerald-900">Free Shipping Voucher</label>
                                @if($shippingVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click.prevent="touchSlot('shipping'); if (shippingVoucherInput !== '') { shippingVoucherInput = ''; applyVouchersReload('Free shipping voucher removed.'); }"
                                               :class="!shippingVoucherInput ? 'border-emerald-700 bg-emerald-50/50' : 'border-border-subtle bg-surface'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition-all">
                                            <input type="radio" name="radio_shipping_voucher" value="" :checked="!shippingVoucherInput" class="pointer-events-none">
                                            <span class="font-bold text-text-muted">No Free Shipping Voucher</span>
                                        </label>
                                        @foreach($shippingVouchers as $shv)
                                            <label @click.prevent="touchSlot('shipping'); if (shippingVoucherInput !== '{{ $shv->code }}') { shippingVoucherInput = '{{ $shv->code }}'; applyVouchersReload('Free shipping voucher {{ $shv->code }} applied!'); }"
                                                   :class="shippingVoucherInput === '{{ $shv->code }}' ? 'border-emerald-700 ring-2 ring-emerald-200 bg-surface' : 'border-border-subtle bg-surface'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer transition-all">
                                                <div class="w-24 bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-2.5 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                                    <span class="text-[8px] font-extrabold uppercase">FREE SHIP</span>
                                                    <span class="font-serif font-extrabold text-xs">₱{{ number_format((float) $shv->discount_value, 0) }} OFF</span>
                                                    <span class="text-[9px] text-white/90">Min ₱{{ number_format((float) $shv->min_spend, 0) }}</span>
                                                </div>
                                                <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                    <div class="min-w-0">
                                                        <span class="font-mono font-extrabold text-emerald-900 block">{{ $shv->code }}</span>
                                                        <span class="text-[11px] font-semibold text-text-main truncate block">{{ $shv->title }}</span>
                                                        <span class="text-[10px] text-text-muted block">Exp {{ $shv->ends_at?->format('M d, Y') }}</span>
                                                        <span x-show="isBestVoucher('shipping', '{{ $shv->code }}')" x-cloak
                                                              class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-[9px] font-extrabold uppercase tracking-wide">
                                                            &#9733; Most Preferred
                                                        </span>
                                                    </div>
                                                    <input type="radio" name="radio_shipping_voucher" value="{{ $shv->code }}" :checked="shippingVoucherInput === '{{ $shv->code }}'" class="pointer-events-none">
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                           x-model="shippingVoucherInput"
                                           @keydown.enter.prevent="touchSlot('shipping'); applyVouchersReload('Shipping voucher code applied.')"
                                           placeholder="Enter Shipping Code..."
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                    <button type="button"
                                            :disabled="isSyncingCheckout"
                                            @click="touchSlot('shipping'); applyVouchersReload('Shipping voucher code applied.')"
                                            class="px-3.5 py-2.5 rounded-xl bg-emerald-700 text-white font-bold cursor-pointer disabled:opacity-50">
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="bg-surface rounded-2xl border border-border-subtle p-6 shadow-xs sticky top-24 space-y-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-serif font-bold text-primary-dark">Checkout Summary</h3>
                            <span x-show="isSyncingCheckout" x-cloak class="text-[11px] font-bold text-primary animate-pulse">
                                Updating...
                            </span>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="text-[10px] font-extrabold uppercase text-emerald-800 block">Storkia E-Wallet Balance</span>
                                <strong class="text-base font-serif font-extrabold text-emerald-950">₱<span x-text="formatMoney(buyerWalletBalance)"></span></strong>
                            </div>
                            <button type="button"
                                    @click="topupModalOpen = true"
                                    class="px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-[11px] cursor-pointer shrink-0">
                                + Top Up via GCash
                            </button>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-brand-light/25 border border-primary/25 flex items-center justify-between gap-3 text-xs">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-bold text-primary-dark">
                                        Redeem Storkia Coins (<span x-text="summary.buyerCoinsBalance"></span> available)
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold">
                                        10 Coins = ₱1.00
                                    </span>
                                </div>
                                <div class="text-[11px] text-text-muted mt-1">
                                    <span x-show="summary.maxRedeemableCoins >= 10">
                                        Use <strong class="text-primary" x-text="summary.maxRedeemableCoins"></strong> Coins (-₱<span x-text="formatMoney(maxRedeemablePesoValue())"></span>)
                                    </span>
                                    <span x-show="summary.maxRedeemableCoins < 10 && summary.buyerCoinsBalance > 0 && summary.buyerCoinsBalance < 10">
                                        Minimum 10 Coins (₱1.00) required to redeem at checkout
                                    </span>
                                    <span x-show="summary.maxRedeemableCoins < 10 && (summary.buyerCoinsBalance <= 0 || summary.buyerCoinsBalance >= 10)">
                                        No redeemable coins for this order
                                    </span>
                                </div>
                            </div>
                            <button type="button"
                                    :disabled="summary.maxRedeemableCoins < 10 || isSyncingCheckout"
                                    @click="toggleCoinsOffset()"
                                    :class="useStorkiaCoins ? 'bg-primary text-white' : 'bg-surface border border-border-subtle text-text-muted'"
                                    class="px-3.5 py-1.5 rounded-xl font-extrabold text-xs transition-all cursor-pointer disabled:opacity-40 shrink-0">
                                <span x-text="useStorkiaCoins ? 'Applied ✓' : 'Use Coins'"></span>
                            </button>
                        </div>

                        <div class="space-y-2.5 pt-3 border-t border-border-subtle text-xs sm:text-sm"
                             :class="isSyncingCheckout ? 'opacity-60 transition-opacity' : 'transition-opacity'">
                            <div class="flex justify-between text-text-muted">
                                <span>Merchandise Subtotal (<span x-text="summary.totalItemsCount"></span> items):</span>
                                <span class="font-semibold text-text-main">₱<span x-text="formatMoney(summary.overallSubtotal)"></span></span>
                            </div>
                            <div class="flex justify-between text-text-muted">
                                <span>Shipping Subtotal:</span>
                                <span class="font-semibold text-text-main">₱<span x-text="formatMoney(summary.overallShipping)"></span></span>
                            </div>
                            <div x-show="summary.overallStoreDiscount > 0" x-cloak class="flex justify-between text-green-700 font-bold">
                                <span>Store Voucher / Bundle Discount:</span>
                                <span>-₱<span x-text="formatMoney(summary.overallStoreDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallPlatformDiscount > 0" x-cloak class="flex justify-between text-purple-700 font-bold">
                                <span>Platform Voucher (<span x-text="summary.appliedPlatformCode"></span>):</span>
                                <span>-₱<span x-text="formatMoney(summary.overallPlatformDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallShippingDiscount > 0" x-cloak class="flex justify-between text-emerald-700 font-bold">
                                <span>Shipping Voucher (<span x-text="summary.appliedShippingCode"></span>):</span>
                                <span>-₱<span x-text="formatMoney(summary.overallShippingDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallCoinsDiscount > 0" x-cloak class="flex justify-between text-primary font-bold">
                                <span>Storkia Coins (<span x-text="summary.overallCoinsUsed"></span> Coins):</span>
                                <span>-₱<span x-text="formatMoney(summary.overallCoinsDiscount)"></span></span>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-border-subtle text-base font-bold text-text-main">
                                <span>Total Payable Amount:</span>
                                <span class="text-2xl font-extrabold font-serif text-primary">₱<span x-text="formatMoney(summary.overallTotal)"></span></span>
                            </div>
                        </div>

                        <template x-if="!hasSufficientWalletBalance()">
                            <div class="p-3.5 rounded-2xl bg-red-50 border border-red-200 text-danger text-xs space-y-2">
                                <p class="font-bold">
                                    Insufficient E-Wallet Balance: You need ₱<span x-text="formatMoney(walletShortfall())"></span> more to pay with Storkia E-Wallet.
                                </p>
                                <button type="button"
                                        @click="topupModalOpen = true"
                                        class="w-full py-2 rounded-xl bg-blue-600 text-white font-bold cursor-pointer">
                                    Top Up E-Wallet via Admin GCash
                                </button>
                            </div>
                        </template>

                        <button type="submit"
                                :disabled="isSubmitting || isSyncingCheckout || !selectedAddress || !isAddressServiceable || !allSelectedPaymentsValid()"
                                :class="(isSubmitting || isSyncingCheckout || !selectedAddress || !isAddressServiceable || !allSelectedPaymentsValid())
                                    ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle'
                                    : 'bg-primary hover:bg-primary-dark text-surface cursor-pointer shadow-md'"
                                class="w-full py-4 px-6 font-bold text-sm rounded-xl transition-all">
                            <span x-show="!isSubmitting">Place Order (₱<span x-text="formatMoney(summary.overallTotal)"></span>)</span>
                            <span x-show="isSubmitting" x-cloak>Processing Order...</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <template x-teleport="body">
            <div x-show="topupModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4 bg-black/65 backdrop-blur-xs">
                <div @click.away="topupModalOpen = false"
                     class="bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-extrabold uppercase">Admin GCash Top-Up</span>
                            <h3 class="text-base font-bold text-primary-dark mt-1">Top Up Your Storkia E-Wallet</h3>
                        </div>
                        <button type="button" @click="topupModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                    </div>

                    @if($isPlatformGcashActive)
                        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-extrabold uppercase text-blue-800 block">Send GCash Transfer To Storkia Admin</span>
                                    <div class="font-bold text-sm text-blue-950">{{ $platformGcash->account_name }}</div>
                                    <div class="font-mono font-extrabold text-base text-blue-700">{{ $platformGcash->gcash_number }}</div>
                                </div>
                                <button type="button"
                                        @click="copyAdminNumber('{{ $platformGcash->gcash_number }}')"
                                        class="px-3 py-1.5 rounded-xl bg-blue-600 text-white font-bold text-xs cursor-pointer">
                                    <span x-show="!copiedAdminGcash">Copy Number</span>
                                    <span x-show="copiedAdminGcash" x-cloak>Copied!</span>
                                </button>
                            </div>
                            @if(!empty($platformGcash->qr_image_path))
                                <div class="pt-2 border-t border-blue-200/60 text-center">
                                    <img src="{{ asset('storage/' . $platformGcash->qr_image_path) }}" alt="Admin GCash QR" class="w-40 h-40 object-contain mx-auto rounded-xl bg-white p-1.5 border border-blue-200">
                                </div>
                            @endif
                            <p class="text-[11px] text-blue-900 leading-relaxed">
                                Send any amount to the official Storkia Admin GCash account above, then submit your <strong>Sender Name</strong>, <strong>Reference Number</strong>, and <strong>Receipt Screenshot</strong> below. Admin will verify the screenshot and credit the exact amount to your E-Wallet.
                            </p>
                        </div>
                    @endif

                    <form @submit.prevent="submitWalletTopupAjax($event)" enctype="multipart/form-data" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Sender Account Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="sender_account_name"
                                   x-model="topupSenderName"
                                   required
                                   minlength="2"
                                   maxlength="120"
                                   placeholder="Enter your GCash registered name"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Reference Number <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="reference_number"
                                   x-model="topupReferenceNumber"
                                   required
                                   minlength="4"
                                   maxlength="80"
                                   placeholder="e.g. 9012345678901"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Upload GCash Receipt Screenshot (Max 5MB) <span class="text-danger">*</span></label>
                            <input type="file"
                                   name="screenshot"
                                   required
                                   accept="image/jpeg,image/png,image/jpg,image/webp"
                                   @change="onTopupScreenshotChange($event)"
                                   class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white file:font-bold cursor-pointer">
                            <template x-if="topupPreviewUrl">
                                <div class="mt-2 text-center">
                                    <img :src="topupPreviewUrl" class="max-h-36 mx-auto rounded-xl border border-border-subtle object-contain">
                                </div>
                            </template>
                        </div>

                        <template x-if="topupError">
                            <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-danger font-bold" x-text="topupError"></div>
                        </template>

                        <template x-if="topupSuccessMsg">
                            <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 font-bold" x-text="topupSuccessMsg"></div>
                        </template>

                        <div class="flex items-center justify-between pt-2 border-t border-border-subtle">
                            <a href="{{ route('user.points') }}" class="text-primary font-bold hover:underline">Open Full Wallet &amp; Coins Page &rarr;</a>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="topupModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Close</button>
                                <button type="submit" :disabled="isSubmittingTopup" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold cursor-pointer disabled:opacity-50">
                                    <span x-show="!isSubmittingTopup">Submit Top-Up Receipt</span>
                                    <span x-show="isSubmittingTopup" x-cloak>Submitting...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        @if($addresses->count() > 1)
            <template x-teleport="body">
                <div x-show="addressModalOpen"
                     x-cloak
                     class="fixed inset-0 z-[185] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                    <div @click.away="addressModalOpen = false"
                         class="bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[85vh] overflow-y-auto">
                        <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                            <h3 class="text-base font-bold text-primary-dark">Select Delivery Address</h3>
                            <button type="button" @click="addressModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                        </div>
                        <div class="space-y-3 text-xs">
                            @foreach($addresses as $addr)
                                <div @click="changeDeliveryAddress({{ $addr->id }})"
                                     :class="selectedAddress && Number(selectedAddress.id) === {{ (int) $addr->id }} ? 'border-primary bg-primary/5' : 'border-border-subtle hover:border-primary/40 bg-surface'"
                                     class="p-4 rounded-2xl border-2 cursor-pointer transition-all">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm text-text-main">{{ $addr->first_name }} {{ $addr->last_name }}</span>
                                        @if($addr->is_default)
                                            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">Default</span>
                                        @endif
                                    </div>
                                    <div class="text-text-muted font-mono mt-0.5">+63 {{ ltrim($addr->phone_number, '0') }}</div>
                                    <div class="text-text-main mt-1">
                                        {{ collect([$addr->building_details, $addr->street_address, $addr->barangay, $addr->municipality, $addr->province, $addr->postcode])->filter()->implode(', ') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
        @endif
    </div>
</div>
@endsection