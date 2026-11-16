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
            'subtotal'                    => (float) ($pkg['subtotal'] ?? 0),
            'bundle_discount'             => (float) ($pkg['bundle_discount'] ?? 0),
            'store_discount'              => (float) ($pkg['store_discount'] ?? 0),
            'platform_discount'           => (float) ($pkg['platform_discount'] ?? 0),
            'shipping_discount'           => (float) ($pkg['shipping_discount'] ?? 0),
            'coins_used'                  => (int) ($pkg['coins_used'] ?? 0),
            'coins_discount'              => (float) ($pkg['coins_discount'] ?? 0),
            'shipping_fee'                => (float) ($pkg['shipping']['shipping_fee'] ?? 0),
            'distance_km'                 => (float) ($pkg['shipping']['distance_km'] ?? 0),
            'net_subtotal'                => (float) ($pkg['net_subtotal'] ?? 0),
            'net_shipping_fee'            => (float) ($pkg['net_shipping_fee'] ?? 0),
            'total'                       => (float) ($pkg['total'] ?? 0),
            'shipping_tier'               => (string) ($pkg['shipping']['tier'] ?? ''),
            'shipping_tier_label'         => (string) ($pkg['shipping']['tier_label'] ?? ''),
            'is_serviceable'              => (bool) ($pkg['shipping']['is_serviceable'] ?? false),
            'unserviceable_reason'        => $pkg['shipping']['unserviceable_reason'] ?? null,
            'route_badge_label'           => $pkg['shipping']['route_badge_label'] ?? null,
            'requires_inter_hub_transfer' => (bool) ($pkg['shipping']['requires_inter_hub_transfer'] ?? false),
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
        'initialServerErrors'   => $errors->all(),
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
            syncAbortController: null,
            syncRequestId: 0,
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
            initialServerErrors: Array.isArray(cfg.initialServerErrors) ? cfg.initialServerErrors : [],
            livePriceSignature: cfg.livePriceSignature || '',
            bestVouchers: cfg.bestVouchers || { store: {}, platform: '', shipping: '' },
            touchedSlots: Array.isArray(cfg.touchedSlots) ? cfg.touchedSlots : [],
            _wsHandler: null,
            notify(message, type = 'success') {
                if (!message) return;
                if (window.StorkiaLive && typeof window.StorkiaLive.showToast === 'function') {
                    window.StorkiaLive.showToast(message, type === 'danger' ? 'error' : type);
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        title: type === 'danger' || type === 'error' ? 'Alert' : (type === 'warning' ? 'Notice' : 'Success'),
                        message: message,
                        type: type
                    }
                }));
            },
            init() {
                if (this.initialServerErrors.length > 0) {
                    this.notify(this.initialServerErrors[0], 'danger');
                } else if (this.liveVoucherNotices.length > 0) {
                    this.notify(this.liveVoucherNotices[0], 'warning');
                }
                this._wsHandler = (event) => {
                    const detail = (event && event.detail) || {};
                    if (
                        detail.scope === 'wallet' ||
                        detail.scope === 'voucher' ||
                        detail.scope === 'address' ||
                        (detail.scope === 'notification' && String(detail.title || '').toLowerCase().includes('top-up'))
                    ) {
                        this.syncCheckoutRealtime(null, '');
                    }
                };
                window.addEventListener('storkia:ws-event', this._wsHandler);
            },
            destroy() {
                if (this._wsHandler) {
                    window.removeEventListener('storkia:ws-event', this._wsHandler);
                }
                if (this.syncAbortController) {
                    this.syncAbortController.abort();
                }
            },
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
            packageIsServiceable(spId) {
                if (!this.packageMetrics[spId]) return this.isAddressServiceable;
                return this.packageMetrics[spId].is_serviceable !== false;
            },
            packageRouteBadge(spId) {
                return this.packageMetrics[spId] ? (this.packageMetrics[spId].route_badge_label || '') : '';
            },
            packageUnserviceableReason(spId) {
                return this.packageMetrics[spId] ? (this.packageMetrics[spId].unserviceable_reason || '') : '';
            },
            firstUnserviceablePackageReason() {
                const keys = Object.keys(this.packageMetrics || {});
                for (let i = 0; i < keys.length; i++) {
                    const m = this.packageMetrics[keys[i]];
                    if (m && m.is_serviceable === false && m.unserviceable_reason) {
                        return m.unserviceable_reason;
                    }
                }
                return '';
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
                this.notify('GCash number copied to clipboard.', 'success');
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
                    this.notify(this.topupError, 'danger');
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
                        this.notify(errText, 'danger');
                    } else {
                        this.topupSuccessMsg = data.message;
                        this.topupReferenceNumber = '';
                        this.topupScreenshotName = '';
                        this.topupPreviewUrl = '';
                        form.reset();
                        this.notify(data.message, 'success');
                    }
                } catch (e) {
                    this.topupError = 'Network error while submitting top-up receipt.';
                    this.notify(this.topupError, 'danger');
                } finally {
                    this.isSubmittingTopup = false;
                }
            },
            allSelectedPaymentsValid() {
                return this.hasSufficientWalletBalance();
            },
            checkoutBlockers() {
                const reasons = [];
                const pkgUnserviceable = this.firstUnserviceablePackageReason();
                if (!this.selectedAddress || !this.selectedAddress.id) {
                    reasons.push('Add or select a delivery address.');
                } else if (!this.isAddressServiceable || pkgUnserviceable !== '') {
                    reasons.push(pkgUnserviceable || 'Your selected delivery address is outside our active Last-Mile Logistics Hub & Barangay Rider coverage zones. Please select or add another address.');
                }
                if (!this.hasSufficientWalletBalance()) {
                    reasons.push('Insufficient Storkia E-Wallet balance (Need ₱' + this.formatMoney(this.walletShortfall()) + ' more). Top up via GCash or switch payment method to Cash on Delivery (COD).');
                }
                if (this.isSyncingCheckout) {
                    reasons.push('Updating checkout totals and shipping fees, please wait...');
                }
                return reasons;
            },
            handlePlaceOrderClick(event) {
                const blockers = this.checkoutBlockers();
                if (this.isSubmitting || blockers.length > 0) {
                    event.preventDefault();
                    if (blockers.length > 0) {
                        this.notify(blockers[0], 'danger');
                    }
                }
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
                const requestId = ++this.syncRequestId;
                if (this.syncAbortController) {
                    this.syncAbortController.abort();
                }
                const controller = new AbortController();
                this.syncAbortController = controller;
                const cleanParams = this.buildSyncParams(overrideAddressId);
                const fetchParams = new URLSearchParams(cleanParams.toString());
                fetchParams.set('live_preview', '1');
                this.isSyncingCheckout = true;
                try {
                    const response = await fetch(cfg.checkoutIndexUrl + '?' + fetchParams.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        signal: controller.signal
                    });
                    if (requestId !== this.syncRequestId) return;
                    let data = null;
                    try {
                        data = await response.json();
                    } catch (_) {
                        data = null;
                    }
                    if (!response.ok || !data || !data.success) {
                        if (data && data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        const errMsg = (data && data.message) ? data.message : 'Unable to update checkout preview.';
                        this.notify(errMsg, 'danger');
                        return;
                    }
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
                    const rebuiltCleanParams = this.buildSyncParams(overrideAddressId);
                    const nextUrl = cfg.checkoutIndexUrl + '?' + rebuiltCleanParams.toString();
                    window.history.replaceState({ storkiaLive: true }, document.title, nextUrl);

                    if (this.liveVoucherNotices.length > 0) {
                        this.notify(this.liveVoucherNotices[0], 'warning');
                    } else if (actionFeedback) {
                        this.notify(actionFeedback, 'success');
                    }
                } catch (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    this.notify('Network error while updating checkout.', 'danger');
                } finally {
                    if (requestId === this.syncRequestId) {
                        this.isSyncingCheckout = false;
                        this.syncAbortController = null;
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
                if (Number(this.summary.maxRedeemableCoins || 0) < 10) {
                    this.notify('You need at least 10 Storkia Coins (₱1.00) eligible for this order to redeem coins.', 'warning');
                    return;
                }
                this.useStorkiaCoins = !this.useStorkiaCoins;
                await this.syncCheckoutRealtime(null, this.useStorkiaCoins ? 'Storkia Coins discount applied!' : 'Storkia Coins discount removed.');
            }
        };
    };
</script>

<div x-data="storkiaCheckoutState(@js($checkoutConfig))"
     class="min-h-screen bg-surface-subtle/40 pb-16 lg:pb-20 pt-4 w-full overflow-x-hidden select-none">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if($errors->any())
            <div data-storkia-flash-error class="p-4 sm:p-5 rounded-2xl bg-danger/10 border border-danger/30 text-danger text-xs sm:text-sm space-y-1 shadow-2xs">
                <div class="font-extrabold uppercase tracking-wider text-xs">Unable to Place Order:</div>
                @foreach($errors->all() as $err)
                    <p class="font-semibold">&bull; {{ $err }}</p>
                @endforeach
            </div>
        @endif

        <template x-if="liveVoucherNotices.length > 0">
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 text-xs sm:text-sm space-y-1 shadow-2xs">
                <div class="font-extrabold uppercase tracking-wider text-[11px] text-amber-900">Checkout Notice:</div>
                <template x-for="(notice, nIdx) in liveVoucherNotices" :key="'v_notice_' + nIdx">
                    <p class="font-semibold" x-text="'• ' + notice"></p>
                </template>
            </div>
        </template>

        <form action="{{ route('checkout.store') }}"
              method="POST"
              data-no-live
              @submit="if (isSubmitting || checkoutBlockers().length > 0) { $event.preventDefault(); if (checkoutBlockers().length > 0) notify(checkoutBlockers()[0], 'danger'); } else { isSubmitting = true; }">
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

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs relative overflow-hidden"
                         :class="(!isAddressServiceable || firstUnserviceablePackageReason() !== '') ? 'border-danger ring-1 ring-danger/30' : 'border-border-subtle'">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary via-secondary to-primary-dark"></div>
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base sm:text-lg font-bold text-primary-dark">Delivery Address</h2>
                                    <template x-if="selectedAddress">
                                        <span :class="(isAddressServiceable && firstUnserviceablePackageReason() === '') ? 'bg-success/15 text-success border-success/25' : 'bg-danger/10 text-danger border-danger/25'"
                                              class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border"
                                              x-text="(isAddressServiceable && firstUnserviceablePackageReason() === '') ? 'Serviceable Route Verified' : 'Unserviceable Route'"></span>
                                    </template>
                                </div>
                                <template x-if="selectedAddress">
                                    <div>
                                        <p class="text-sm font-bold text-text-main mt-1">
                                            <span x-text="selectedAddress.full_name"></span>
                                            <span class="text-text-muted font-normal ml-2">+63 <span x-text="selectedAddress.phone_number"></span></span>
                                        </p>
                                        <p class="text-xs sm:text-sm text-text-muted mt-1 leading-relaxed" x-text="selectedAddress.full_address"></p>
                                        <template x-if="!isAddressServiceable || firstUnserviceablePackageReason() !== ''">
                                            <div class="mt-2.5 p-3 rounded-xl bg-danger/10 border border-danger/30 text-xs text-danger font-bold leading-relaxed"
                                                 x-text="firstUnserviceablePackageReason() || 'This delivery address is currently outside our partner logistics and barangay rider serviceable zones. Please select or add another address.'">
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!selectedAddress">
                                    <p class="text-sm text-danger font-semibold mt-1">Please add a delivery address to place your order.</p>
                                </template>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($addresses->count() > 1)
                                    <button type="button" @click.stop="addressModalOpen = true" class="px-4 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:border-primary text-xs font-bold text-text-main transition-colors cursor-pointer">
                                        Change Address
                                    </button>
                                @endif
                                <a href="{{ route('user.addresses') }}" class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary hover:text-white text-primary text-xs font-bold transition-colors cursor-pointer">
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
                        <div class="bg-surface rounded-2xl border shadow-xs overflow-hidden transition-colors"
                             :class="packageIsServiceable({{ $spId }}) ? 'border-border-subtle' : 'border-danger ring-1 ring-danger/30'">
                            <div class="p-4 sm:px-6 bg-surface-subtle/60 border-b border-border-subtle space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5">
                                        <span class="px-2.5 py-0.5 rounded-lg bg-primary-dark text-surface text-xs font-bold">Package {{ $pkgIndex + 1 }}</span>
                                        <span class="font-bold text-sm sm:text-base text-primary-dark">{{ $sellerProfile->business_name }}</span>
                                    </div>
                                    <span class="text-xs text-text-muted">Ships from: <strong>{{ $sellerProfile->municipality }}, {{ $sellerProfile->province }}</strong></span>
                                </div>
                                <template x-if="packageIsServiceable({{ $spId }}) && packageRouteBadge({{ $spId }})">
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-success/10 text-success border border-success/25 text-[11px] font-bold">
                                        <span x-text="'Route: ' + packageRouteBadge({{ $spId }})"></span>
                                    </div>
                                </template>
                                <template x-if="!packageIsServiceable({{ $spId }})">
                                    <div class="p-3 rounded-xl bg-danger/10 border border-danger/30 text-xs text-danger font-bold leading-relaxed"
                                         x-text="packageUnserviceableReason({{ $spId }}) || 'Unserviceable route between seller and your delivery address.'">
                                    </div>
                                </template>
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
                                                <span>&#8369;{{ number_format($item['discounted_unit_price'], 2) }} &times; {{ $item['quantity'] }}</span>
                                                <strong class="text-price text-sm">&#8369;{{ number_format($item['line_total'], 2) }}</strong>
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
                                                   :class="!storeVoucherInputs[{{ $spId }}] ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface hover:border-primary/40'"
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
                                                        <span class="text-[9px] text-white/90">Min &#8369;{{ number_format((float) $sv->min_spend, 0) }}</span>
                                                    </div>
                                                    <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                        <div class="min-w-0">
                                                            <span class="font-mono font-extrabold text-primary-dark block">{{ $sv->code }}</span>
                                                            <span class="text-[11px] text-text-main font-semibold truncate block">{{ $sv->title }}</span>
                                                            <span class="text-[10px] text-text-muted block">Exp {{ $sv->ends_at?->format('M d, Y') }}</span>
                                                            <span x-show="isBestVoucher('store', '{{ $sv->code }}', {{ $spId }})" x-cloak
                                                                  class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-brand-light/70 text-primary-dark border border-primary/25 text-[9px] font-extrabold uppercase tracking-wide">
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
                                               class="px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer disabled:opacity-50">
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
                                        <span x-show="packageCoinsUsed({{ $spId }}) > 0" x-cloak class="px-2 py-0.5 rounded-full bg-brand-light/50 text-primary-dark font-bold">
                                            Coins Share: <span x-text="packageCoinsUsed({{ $spId }})"></span> Coins (-&#8369;<span x-text="formatMoney(packageCoinsDiscount({{ $spId }}))"></span>)
                                        </span>
                                        <span>
                                            Package Total: <strong class="text-price">&#8369;<span x-text="formatMoney(packageTotal({{ $spId }}))"></span></strong>
                                        </span>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_methods[{{ $spId }}]" :value="paymentMethods[{{ $spId }}]">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div @click="selectCod({{ $spId }})"
                                         :class="paymentMethods[{{ $spId }}] === 'COD' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-border-subtle bg-surface hover:border-primary/40'"
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
                                         :class="paymentMethods[{{ $spId }}] === 'WALLET' ? 'border-primary-dark bg-brand-light/25 ring-1 ring-primary-dark/20' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                         class="p-3.5 rounded-2xl border-2 cursor-pointer flex items-center justify-between gap-3 transition-all">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-xl bg-primary-dark text-white font-bold text-[10px] flex items-center justify-center shrink-0">WALLET</div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-text-main">Storkia E-Wallet (Instant Pay)</div>
                                                <div class="text-[11px] text-primary-dark font-semibold truncate">
                                                    Balance: &#8369;<span x-text="formatMoney(buyerWalletBalance)"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button"
                                                @click.stop="topupModalOpen = true"
                                                class="px-2.5 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-[10px] font-extrabold shrink-0 transition-colors cursor-pointer">
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
                            <p class="text-xs text-text-muted">Select your claimed ticket voucher below or type a voucher code manually. The voucher marked <strong class="text-primary">Most Preferred</strong> saves you the most and is applied automatically when your order qualifies.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-primary-dark">Platform Voucher (Admin-Sponsored)</label>
                                @if($platformVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click.prevent="touchSlot('platform'); if (platformVoucherInput !== '') { platformVoucherInput = ''; applyVouchersReload('Platform voucher removed.'); }"
                                               :class="!platformVoucherInput ? 'border-primary-dark bg-brand-light/20' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition-all">
                                            <input type="radio" name="radio_platform_voucher" value="" :checked="!platformVoucherInput" class="text-primary pointer-events-none">
                                            <span class="font-bold text-text-muted">No Platform Voucher</span>
                                        </label>
                                        @foreach($platformVouchers as $pv)
                                            <label @click.prevent="touchSlot('platform'); if (platformVoucherInput !== '{{ $pv->code }}') { platformVoucherInput = '{{ $pv->code }}'; applyVouchersReload('Platform voucher {{ $pv->code }} applied!'); }"
                                                   :class="platformVoucherInput === '{{ $pv->code }}' ? 'border-primary-dark ring-2 ring-primary/20 bg-surface' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer transition-all">
                                                <div class="w-24 bg-gradient-to-br from-primary-dark to-primary text-white p-2.5 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                                    <span class="text-[8px] font-extrabold uppercase">PLATFORM</span>
                                                    <span class="font-serif font-extrabold text-xs">{{ $pv->badge_label }}</span>
                                                    <span class="text-[9px] text-white/90">Min &#8369;{{ number_format((float) $pv->min_spend, 0) }}</span>
                                                </div>
                                                <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                    <div class="min-w-0">
                                                        <span class="font-mono font-extrabold text-primary-dark block">{{ $pv->code }}</span>
                                                        <span class="text-[11px] font-semibold text-text-main truncate block">{{ $pv->title }}</span>
                                                        <span class="text-[10px] text-text-muted block">Exp {{ $pv->ends_at?->format('M d, Y') }}</span>
                                                        <span x-show="isBestVoucher('platform', '{{ $pv->code }}')" x-cloak
                                                              class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-brand-light/70 text-primary-dark border border-primary/25 text-[9px] font-extrabold uppercase tracking-wide">
                                                            &#9733; Most Preferred
                                                        </span>
                                                    </div>
                                                    <input type="radio" name="radio_platform_voucher" value="{{ $pv->code }}" :checked="platformVoucherInput === '{{ $pv->code }}'" class="text-primary pointer-events-none">
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
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase outline-none focus:border-primary">
                                    <button type="button"
                                            :disabled="isSyncingCheckout"
                                            @click="touchSlot('platform'); applyVouchersReload('Platform voucher code applied.')"
                                            class="px-3.5 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer disabled:opacity-50">
                                        Apply
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-primary-dark">Free Shipping Voucher</label>
                                @if($shippingVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click.prevent="touchSlot('shipping'); if (shippingVoucherInput !== '') { shippingVoucherInput = ''; applyVouchersReload('Free shipping voucher removed.'); }"
                                               :class="!shippingVoucherInput ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition-all">
                                            <input type="radio" name="radio_shipping_voucher" value="" :checked="!shippingVoucherInput" class="text-primary pointer-events-none">
                                            <span class="font-bold text-text-muted">No Free Shipping Voucher</span>
                                        </label>
                                        @foreach($shippingVouchers as $shv)
                                            <label @click.prevent="touchSlot('shipping'); if (shippingVoucherInput !== '{{ $shv->code }}') { shippingVoucherInput = '{{ $shv->code }}'; applyVouchersReload('Free shipping voucher {{ $shv->code }} applied!'); }"
                                                   :class="shippingVoucherInput === '{{ $shv->code }}' ? 'border-primary ring-2 ring-primary/20 bg-surface' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer transition-all">
                                                <div class="w-24 bg-gradient-to-br from-primary to-secondary text-white p-2.5 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                                                    <span class="text-[8px] font-extrabold uppercase">FREE SHIP</span>
                                                    <span class="font-serif font-extrabold text-xs">&#8369;{{ number_format((float) $shv->discount_value, 0) }} OFF</span>
                                                    <span class="text-[9px] text-white/90">Min &#8369;{{ number_format((float) $shv->min_spend, 0) }}</span>
                                                </div>
                                                <div class="flex-1 p-2.5 flex items-center justify-between gap-2 min-w-0">
                                                    <div class="min-w-0">
                                                        <span class="font-mono font-extrabold text-primary-dark block">{{ $shv->code }}</span>
                                                        <span class="text-[11px] font-semibold text-text-main truncate block">{{ $shv->title }}</span>
                                                        <span class="text-[10px] text-text-muted block">Exp {{ $shv->ends_at?->format('M d, Y') }}</span>
                                                        <span x-show="isBestVoucher('shipping', '{{ $shv->code }}')" x-cloak
                                                              class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full bg-brand-light/70 text-primary-dark border border-primary/25 text-[9px] font-extrabold uppercase tracking-wide">
                                                            &#9733; Most Preferred
                                                        </span>
                                                    </div>
                                                    <input type="radio" name="radio_shipping_voucher" value="{{ $shv->code }}" :checked="shippingVoucherInput === '{{ $shv->code }}'" class="text-primary pointer-events-none">
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
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase outline-none focus:border-primary">
                                    <button type="button"
                                            :disabled="isSyncingCheckout"
                                            @click="touchSlot('shipping'); applyVouchersReload('Shipping voucher code applied.')"
                                            class="px-3.5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors cursor-pointer disabled:opacity-50">
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
                        </div>

                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle flex items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="text-[10px] font-extrabold uppercase text-text-muted block">Storkia E-Wallet Balance</span>
                                <strong class="text-base font-serif font-extrabold text-primary-dark">&#8369;<span x-text="formatMoney(buyerWalletBalance)"></span></strong>
                            </div>
                            <button type="button"
                                    @click="topupModalOpen = true"
                                    class="px-3 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-[11px] transition-colors cursor-pointer shrink-0">
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
                                        10 Coins = &#8369;1.00
                                    </span>
                                </div>
                                <div class="text-[11px] text-text-muted mt-1">
                                    <span x-show="summary.maxRedeemableCoins >= 10">
                                        Use <strong class="text-primary" x-text="summary.maxRedeemableCoins"></strong> Coins (-&#8369;<span x-text="formatMoney(maxRedeemablePesoValue())"></span>)
                                    </span>
                                    <span x-show="summary.maxRedeemableCoins < 10 && summary.buyerCoinsBalance > 0 && summary.buyerCoinsBalance < 10">
                                        Minimum 10 Coins (&#8369;1.00) required to redeem at checkout
                                    </span>
                                    <span x-show="summary.maxRedeemableCoins < 10 && (summary.buyerCoinsBalance <= 0 || summary.buyerCoinsBalance >= 10)">
                                        No redeemable coins for this order
                                    </span>
                                </div>
                            </div>
                            <button type="button"
                                    :disabled="isSyncingCheckout"
                                    @click="toggleCoinsOffset()"
                                    :class="summary.maxRedeemableCoins < 10 ? 'bg-surface-subtle border border-border-subtle text-text-muted opacity-50 cursor-not-allowed' : (useStorkiaCoins ? 'bg-primary text-white cursor-pointer' : 'bg-surface border border-border-subtle text-text-main hover:border-primary cursor-pointer')"
                                    class="px-3.5 py-1.5 rounded-xl font-extrabold text-xs transition-all shrink-0">
                                <span x-text="useStorkiaCoins ? 'Applied &#10003;' : 'Use Coins'"></span>
                            </button>
                        </div>

                        <div class="space-y-2.5 pt-3 border-t border-border-subtle text-xs sm:text-sm transition-opacity">
                            <div class="flex justify-between text-text-muted">
                                <span>Merchandise Subtotal (<span x-text="summary.totalItemsCount"></span> items):</span>
                                <span class="font-semibold text-text-main">&#8369;<span x-text="formatMoney(summary.overallSubtotal)"></span></span>
                            </div>
                            <div class="flex justify-between text-text-muted">
                                <span>Shipping Subtotal:</span>
                                <span class="font-semibold text-text-main">&#8369;<span x-text="formatMoney(summary.overallShipping)"></span></span>
                            </div>
                            <div x-show="summary.overallStoreDiscount > 0" x-cloak class="flex justify-between text-primary font-bold">
                                <span>Store Voucher / Bundle Discount:</span>
                                <span>-&#8369;<span x-text="formatMoney(summary.overallStoreDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallPlatformDiscount > 0" x-cloak class="flex justify-between text-primary-dark font-bold">
                                <span>Platform Voucher (<span x-text="summary.appliedPlatformCode"></span>):</span>
                                <span>-&#8369;<span x-text="formatMoney(summary.overallPlatformDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallShippingDiscount > 0" x-cloak class="flex justify-between text-primary font-bold">
                                <span>Shipping Voucher (<span x-text="summary.appliedShippingCode"></span>):</span>
                                <span>-&#8369;<span x-text="formatMoney(summary.overallShippingDiscount)"></span></span>
                            </div>
                            <div x-show="summary.overallCoinsDiscount > 0" x-cloak class="flex justify-between text-primary font-bold">
                                <span>Storkia Coins (<span x-text="summary.overallCoinsUsed"></span> Coins):</span>
                                <span>-&#8369;<span x-text="formatMoney(summary.overallCoinsDiscount)"></span></span>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-border-subtle text-base font-bold text-text-main">
                                <span>Total Payable Amount:</span>
                                <span class="text-2xl font-extrabold font-serif text-price">&#8369;<span x-text="formatMoney(summary.overallTotal)"></span></span>
                            </div>
                        </div>

                        <template x-if="!hasSufficientWalletBalance()">
                            <div class="p-3.5 rounded-2xl bg-danger/10 border border-danger/30 text-danger text-xs space-y-2">
                                <p class="font-bold">
                                    Insufficient E-Wallet Balance: You need &#8369;<span x-text="formatMoney(walletShortfall())"></span> more to pay with Storkia E-Wallet.
                                </p>
                                <button type="button"
                                        @click="topupModalOpen = true"
                                        class="w-full py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors cursor-pointer">
                                    Top Up E-Wallet via Admin GCash
                                </button>
                            </div>
                        </template>

                        <template x-if="checkoutBlockers().length > 0">
                            <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 text-xs space-y-1.5">
                                <div class="font-extrabold uppercase tracking-wider text-[11px] flex items-center gap-1.5 text-amber-900">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Complete These Steps to Place Order:</span>
                                </div>
                                <ul class="list-disc list-inside space-y-1 font-semibold text-amber-900">
                                    <template x-for="(reason, rIdx) in checkoutBlockers()" :key="'blk_' + rIdx">
                                        <li x-text="reason"></li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        <button type="submit"
                                @click="handlePlaceOrderClick($event)"
                                :aria-disabled="isSubmitting || checkoutBlockers().length > 0"
                                :class="(isSubmitting || checkoutBlockers().length > 0)
                                    ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle opacity-70'
                                    : 'bg-primary hover:bg-primary-dark text-surface cursor-pointer shadow-md active:scale-[0.99]'"
                                class="w-full py-4 px-6 font-bold text-sm rounded-xl transition-all">
                            <span x-show="!isSubmitting">Place Order (&#8369;<span x-text="formatMoney(summary.overallTotal)"></span>)</span>
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
                            <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase">Admin GCash Top-Up</span>
                            <h3 class="text-base font-bold text-primary-dark mt-1">Top Up Your Storkia E-Wallet</h3>
                        </div>
                        <button type="button" @click="topupModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                    </div>

                    <template x-if="topupError">
                        <div class="p-3.5 rounded-2xl bg-danger/10 border border-danger/30 text-danger text-xs font-bold" x-text="topupError"></div>
                    </template>

                    <template x-if="topupSuccessMsg">
                        <div class="p-3.5 rounded-2xl bg-green-50 border border-green-200 text-green-800 text-xs font-bold" x-text="topupSuccessMsg"></div>
                    </template>

                    @if($isPlatformGcashActive)
                        <div class="p-4 rounded-2xl bg-brand-light/25 border border-primary/25 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-extrabold uppercase text-primary block">Send GCash Transfer To Storkia Admin</span>
                                    <div class="font-bold text-sm text-primary-dark">{{ $platformGcash->account_name }}</div>
                                    <div class="font-mono font-extrabold text-base text-primary">{{ $platformGcash->gcash_number }}</div>
                                </div>
                                <button type="button"
                                        @click="copyAdminNumber('{{ $platformGcash->gcash_number }}')"
                                        class="px-3 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-xs transition-colors cursor-pointer">
                                    <span x-show="!copiedAdminGcash">Copy Number</span>
                                    <span x-show="copiedAdminGcash" x-cloak>Copied!</span>
                                </button>
                            </div>
                            @if(!empty($platformGcash->qr_image_path))
                                <div class="pt-2 border-t border-border-subtle text-center">
                                    <img src="{{ asset('storage/' . $platformGcash->qr_image_path) }}" alt="Admin GCash QR" class="w-40 h-40 object-contain mx-auto rounded-xl bg-surface p-1.5 border border-border-subtle">
                                </div>
                            @endif
                            <p class="text-[11px] text-text-main leading-relaxed">
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
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold outline-none focus:border-primary">
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
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Upload GCash Receipt Screenshot (Max 5MB) <span class="text-danger">*</span></label>
                            <input type="file"
                                   name="screenshot"
                                   required
                                   accept="image/jpeg,image/png,image/jpg,image/webp"
                                   @change="onTopupScreenshotChange($event)"
                                   class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:font-bold cursor-pointer">
                            <template x-if="topupPreviewUrl">
                                <div class="mt-2 text-center">
                                    <img :src="topupPreviewUrl" class="max-h-36 mx-auto rounded-xl border border-border-subtle object-contain">
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-border-subtle">
                            <a href="{{ route('user.points') }}" class="text-primary font-bold hover:underline cursor-pointer">Open Full Wallet &amp; Coins Page &rarr;</a>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="topupModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">Close</button>
                                <button type="submit" :disabled="isSubmittingTopup" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors cursor-pointer disabled:opacity-50">
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
                         class="bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[85vh] overflow-y-auto custom-scrollbar">
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
                                            <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">Default</span>
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