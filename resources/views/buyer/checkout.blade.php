@extends('layouts.app', ['title' => 'Checkout - Storkia'])

@section('content')
@php
    $initialPaymentMethods = [];
    $initialGcashState = [];
    $initialNotes = [];
    $currentStoreVoucherCodes = [];

    foreach ($sellerPackages as $pkg) {
        $spId = (int) $pkg['seller_profile']->id;
        $oldMethod = old("payment_methods.{$spId}", 'COD');
        $initialPaymentMethods[$spId] = ($pkg['gcash_enabled'] && $oldMethod === 'GCASH') ? 'GCASH' : 'COD';

        $initialNotes[$spId] = (string) old("seller_notes.{$spId}", '');
        $currentStoreVoucherCodes[$spId] = $pkg['store_voucher']?->code ?? '';

        $initialGcashState[$spId] = [
            'modalOpen'       => false,
            'phase'           => 1,
            'showQr'          => false,
            'senderNumber'    => (string) old("gcash_sender_number.{$spId}", ''),
            'paidAt'          => (string) old("gcash_paid_at.{$spId}", now()->format('Y-m-d\TH:i')),
            'referenceNumber' => (string) old("gcash_reference_number.{$spId}", ''),
            'referenceCode'   => (string) old("gcash_reference_code.{$spId}", ''),
            'hasScreenshot'   => false,
            'screenshotName'  => '',
            'previewUrl'      => '',
            'error'           => '',
        ];
    }

    $platformVouchers = $availableVouchers->where('scope', 'platform')->values();
    $shippingVouchers = $availableVouchers->where('scope', 'shipping')->values();
@endphp

<div x-data="{
        addressModalOpen: false,
        voucherModalOpen: false,
        isSubmitting: false,
        paymentMethods: @js($initialPaymentMethods),
        gcashState: @js($initialGcashState),
        sellerNotes: @js($initialNotes),
        storeVoucherInputs: @js($currentStoreVoucherCodes),
        platformVoucherInput: @js($appliedPlatformVoucher?->code ?? ''),
        shippingVoucherInput: @js($appliedShippingVoucher?->code ?? ''),
        selectCod(spId) {
            this.paymentMethods[spId] = 'COD';
            if (this.gcashState[spId]) {
                this.gcashState[spId].modalOpen = false;
            }
        },
        selectGcash(spId, enabled) {
            if (!enabled) return;
            this.paymentMethods[spId] = 'GCASH';
            this.openGcashModal(spId);
        },
        openGcashModal(spId) {
            if (!this.gcashState[spId]) return;
            this.gcashState[spId].error = '';
            this.gcashState[spId].phase = 1;
            this.gcashState[spId].modalOpen = true;
        },
        proceedToGcashPhase2(spId) {
            if (!this.gcashState[spId]) return;
            this.gcashState[spId].error = '';
            this.gcashState[spId].phase = 2;
        },
        sanitizeSenderPhone(spId) {
            if (!this.gcashState[spId]) return;
            let digits = (this.gcashState[spId].senderNumber || '').replace(/\D/g, '');
            if (digits.startsWith('63') && digits.length === 12) {
                digits = '0' + digits.slice(2);
            } else if (digits.startsWith('9') && digits.length === 10) {
                digits = '0' + digits;
            }
            this.gcashState[spId].senderNumber = digits.slice(0, 11);
        },
        onGcashScreenshotSelect(spId, event) {
            if (!this.gcashState[spId]) return;
            this.gcashState[spId].error = '';
            const file = event.target.files[0];
            if (!file) {
                this.gcashState[spId].hasScreenshot = false;
                this.gcashState[spId].screenshotName = '';
                this.gcashState[spId].previewUrl = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.gcashState[spId].error = 'Screenshot image must not exceed 5MB.';
                event.target.value = '';
                this.gcashState[spId].hasScreenshot = false;
                this.gcashState[spId].screenshotName = '';
                this.gcashState[spId].previewUrl = '';
                return;
            }
            this.gcashState[spId].hasScreenshot = true;
            this.gcashState[spId].screenshotName = file.name;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.gcashState[spId].previewUrl = e.target.result;
            };
            reader.readAsDataURL(file);
        },
        isGcashComplete(spId) {
            const st = this.gcashState[spId];
            if (!st) return false;
            return /^09\d{9}$/.test(st.senderNumber || '')
                && (st.paidAt || '').trim() !== ''
                && (st.referenceNumber || '').trim().length >= 4
                && (st.referenceCode || '').trim().length >= 2
                && st.hasScreenshot === true;
        },
        saveGcashModal(spId) {
            const st = this.gcashState[spId];
            if (!st) return;
            this.sanitizeSenderPhone(spId);
            if (!/^09\d{9}$/.test(st.senderNumber || '')) {
                st.error = 'Please enter a valid 11-digit Sender GCash number starting with 09.';
                return;
            }
            if (!(st.paidAt || '').trim()) {
                st.error = 'Please select the payment date and time.';
                return;
            }
            if ((st.referenceNumber || '').trim().length < 4) {
                st.error = 'Please enter the unique GCash Reference Number (at least 4 characters).';
                return;
            }
            if ((st.referenceCode || '').trim().length < 2) {
                st.error = 'Please enter the GCash Reference Code.';
                return;
            }
            if (!st.hasScreenshot) {
                st.error = 'Please upload a screenshot of your GCash payment receipt.';
                return;
            }
            st.error = '';
            st.modalOpen = false;
        },
        allSelectedPaymentsValid() {
            for (const spId of Object.keys(this.paymentMethods)) {
                if (this.paymentMethods[spId] === 'GCASH' && !this.isGcashComplete(spId)) {
                    return false;
                }
            }
            return true;
        },
        applyVouchersReload() {
            const params = new URLSearchParams();
            params.set('mode', '{{ $mode }}');
            @if($selectedAddress)
                params.set('address_id', '{{ $selectedAddress->id }}');
            @endif
            @if(!empty($selectedCartIds))
                params.set('cart_ids', '{{ implode(',', $selectedCartIds) }}');
            @endif
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
            window.location.href = `{{ route('checkout.index') }}?` + params.toString();
        }
     }"
     class="min-h-screen bg-surface-subtle/40 pb-16 lg:pb-20 pt-4 w-full overflow-x-hidden">

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <nav aria-label="Checkout Stepper" class="w-full bg-surface border border-border-subtle rounded-2xl p-4 sm:p-5 shadow-xs select-none">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <a href="{{ route('cart.index') }}" class="flex items-center gap-2 text-primary font-bold hover:opacity-80 transition-opacity">
                    <span class="w-7 h-7 rounded-full bg-primary/15 text-primary text-xs font-bold flex items-center justify-center">✓</span>
                    <span class="text-sm font-sans tracking-tight">Cart</span>
                </a>
                <div class="flex-1 h-[2px] bg-primary mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary text-surface text-xs font-bold flex items-center justify-center shadow-xs">2</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Place Order</span>
                </div>
                <div class="flex-1 h-[2px] bg-primary/30 mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-primary-dark font-semibold">
                    <span class="w-7 h-7 rounded-full bg-brand-light/40 border border-primary/30 text-primary-dark text-xs font-bold flex items-center justify-center">3</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">COD / GCash</span>
                </div>
                <div class="flex-1 h-[2px] bg-border-subtle mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-text-muted font-medium">
                    <span class="w-7 h-7 rounded-full bg-surface-subtle border border-border-subtle text-text-muted text-xs font-bold flex items-center justify-center">4</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Order Complete</span>
                </div>
            </div>
        </nav>

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
                @foreach($errors->all() as $err)
                    <p class="font-semibold">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        @if(!empty($voucherNotices))
            <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs sm:text-sm space-y-1">
                @foreach($voucherNotices as $notice)
                    <p class="font-semibold">{{ $notice }}</p>
                @endforeach
            </div>
        @endif

        @if($selectedAddress && !$isAddressServiceable)
            <div class="p-4 rounded-2xl bg-red-50 border-2 border-red-300 text-red-800 text-xs sm:text-sm font-bold flex items-center justify-between gap-4">
                <span>
                    Unserviceable Delivery Area: {{ $selectedAddress->municipality }}, {{ $selectedAddress->province }} is currently outside our partner logistics service coverage. Please select a serviceable address to place your order.
                </span>
                <button type="button" @click="addressModalOpen = true" class="px-4 py-2 rounded-xl bg-red-600 text-white text-xs font-bold shrink-0 cursor-pointer">
                    Change Address
                </button>
            </div>
        @endif

        <form action="{{ route('checkout.store') }}"
              method="POST"
              enctype="multipart/form-data"
              @submit="if (isSubmitting || !allSelectedPaymentsValid()) { $event.preventDefault(); } else { isSubmitting = true; }">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <input type="hidden" name="user_address_id" value="{{ $selectedAddress?->id }}">
            <input type="hidden" name="price_signature" value="{{ $priceSignature }}">
            <input type="hidden" name="platform_voucher_code" :value="platformVoucherInput">
            <input type="hidden" name="shipping_voucher_code" :value="shippingVoucherInput">
            @foreach($selectedCartIds as $cid)
                <input type="hidden" name="cart_ids[]" value="{{ $cid }}">
            @endforeach

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <div class="lg:col-span-8 space-y-5 min-w-0">

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary via-secondary to-primary-dark"></div>
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-base sm:text-lg font-bold text-primary-dark">Delivery Address</h2>
                                        @if($selectedAddress?->is_default)
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary rounded-full border border-primary/20">Default</span>
                                        @endif
                                        @if($selectedAddress && !$isAddressServiceable)
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700 rounded-full border border-red-200">Unserviceable</span>
                                        @endif
                                    </div>

                                    @if($selectedAddress)
                                        <p class="text-sm font-bold text-text-main mt-2">
                                            {{ $selectedAddress->first_name }} {{ $selectedAddress->last_name }}
                                            <span class="text-text-muted font-normal ml-2">+63 {{ ltrim($selectedAddress->phone_number, '0') }}</span>
                                        </p>
                                        <p class="text-xs sm:text-sm text-text-muted mt-1 leading-relaxed">
                                            {{ collect([$selectedAddress->building_details, $selectedAddress->street_address, $selectedAddress->barangay, $selectedAddress->municipality, $selectedAddress->province, $selectedAddress->postcode])->filter()->implode(', ') }}
                                        </p>
                                    @else
                                        <p class="text-sm text-danger font-semibold mt-2">
                                            You have no saved delivery address yet. Please add a delivery address to calculate exact shipping fees and place your order.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if($addresses->count() > 1)
                                    <button type="button"
                                            @click.stop="addressModalOpen = true"
                                            class="px-4 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-xs font-bold text-text-main transition-colors cursor-pointer">
                                        Change Address
                                    </button>
                                @endif
                                <a href="{{ route('user.addresses') }}"
                                   class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold transition-colors">
                                    {{ $addresses->isEmpty() ? '+ Add Address' : 'Manage Addresses' }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="flex items-center justify-between px-1">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-text-muted">
                                Seller Shipments ({{ count($sellerPackages) }} {{ Str::plural('Package', count($sellerPackages)) }})
                            </h3>
                            <span class="text-xs text-text-muted">Ordered First-In, First-Out &bull; Independent seller fulfillment</span>
                        </div>

                        @foreach($sellerPackages as $pkgIndex => $pkg)
                            @php
                                $sellerProfile = $pkg['seller_profile'];
                                $spId = (int) $sellerProfile->id;
                                $shipping = $pkg['shipping'];
                                $gcashEnabled = (bool) $pkg['gcash_enabled'];
                                $gcashAcc = $pkg['gcash_account'];
                                $storeVouchersForSeller = $availableVouchers->where('scope', 'store')->where('seller_profile_id', $spId)->values();
                            @endphp
                            <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
                                <div class="p-4 sm:px-6 bg-surface-subtle/60 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5">
                                        <span class="px-2.5 py-0.5 rounded-lg bg-primary-dark text-surface text-xs font-bold">
                                            Package {{ $pkgIndex + 1 }}
                                        </span>
                                        <span class="font-bold text-sm sm:text-base text-primary-dark">
                                            {{ $sellerProfile->business_name }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-text-muted flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        <span>Ships from: <strong class="text-text-main">{{ $sellerProfile->municipality }}, {{ $sellerProfile->province }}</strong></span>
                                    </div>
                                </div>

                                <div class="divide-y divide-border-subtle/60">
                                    @foreach($pkg['items'] as $item)
                                        <div class="p-4 sm:px-6 flex items-start gap-4">
                                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                                @if($item['image_path'])
                                                    <img src="{{ asset('storage/' . $item['image_path']) }}" alt="{{ $item['product']->name }}" class="w-full h-full object-contain rounded-lg">
                                                @else
                                                    <svg class="w-6 h-6 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                @endif
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-sm sm:text-base font-semibold text-text-main line-clamp-1">
                                                    {{ $item['product']->name }}
                                                </h4>

                                                @if($item['variant_name'])
                                                    <span class="inline-flex items-center mt-1 px-2.5 py-0.5 rounded-md bg-surface-subtle border border-border-subtle text-xs text-text-muted font-medium">
                                                        {{ implode(' / ', array_filter([$item['variant_name'], $item['sub_variant_name']])) }}
                                                    </span>
                                                @endif

                                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                                                    <div>
                                                        <span>Unit Price: <strong class="text-text-main">₱{{ number_format($item['discounted_unit_price'], 2) }}</strong></span>
                                                        @if($item['discount_percent'] > 0)
                                                            <span class="line-through ml-1">₱{{ number_format($item['unit_price'], 2) }}</span>
                                                        @endif
                                                        <span class="mx-1.5">&bull;</span>
                                                        <span>Weight: {{ number_format($item['unit_weight'], 2) }} kg &times; {{ $item['quantity'] }}</span>
                                                    </div>
                                                    <div class="text-sm font-bold text-text-main">
                                                        &times; {{ $item['quantity'] }} = <span class="text-primary">₱{{ number_format($item['line_total'], 2) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="p-4 sm:px-6 bg-surface-subtle/30 border-t border-border-subtle grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="font-bold text-text-muted uppercase tracking-wider">Note to {{ $sellerProfile->business_name }}</label>
                                            <span class="text-[11px] text-text-muted" x-text="(sellerNotes[{{ $spId }}] || '').length + '/200'"></span>
                                        </div>
                                        <input type="text"
                                               name="seller_notes[{{ $spId }}]"
                                               x-model="sellerNotes[{{ $spId }}]"
                                               maxlength="200"
                                               placeholder="Optional message to seller (e.g. Paki-wrap po as gift)..."
                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Store Voucher ({{ $sellerProfile->business_name }})</label>
                                        <input type="hidden" name="store_voucher_codes[{{ $spId }}]" :value="storeVoucherInputs[{{ $spId }}]">
                                        <div class="flex items-center gap-2">
                                            <input type="text"
                                                   x-model="storeVoucherInputs[{{ $spId }}]"
                                                   placeholder="Enter Store Voucher Code..."
                                                   class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase text-xs outline-none focus:border-primary">
                                            <button type="button"
                                                    @click="applyVouchersReload()"
                                                    class="px-3.5 py-2.5 rounded-xl bg-primary-dark text-white font-bold text-xs cursor-pointer shrink-0">
                                                Apply
                                            </button>
                                        </div>
                                        @if($storeVouchersForSeller->isNotEmpty())
                                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                                @foreach($storeVouchersForSeller as $sv)
                                                    <button type="button"
                                                            @click="storeVoucherInputs[{{ $spId }}] = (storeVoucherInputs[{{ $spId }}] === '{{ $sv->code }}' ? '' : '{{ $sv->code }}'); applyVouchersReload();"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[11px] font-bold transition-all cursor-pointer {{ $pkg['store_voucher']?->code === $sv->code ? 'border-primary bg-primary text-white' : 'border-primary/40 bg-primary/5 text-primary-dark hover:bg-primary/10' }}">
                                                        <span class="font-mono">{{ $sv->code }}</span>
                                                        <span>({{ $sv->badge_label }} &bull; Min ₱{{ number_format((float) $sv->min_spend, 0) }})</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="p-4 sm:px-6 bg-surface border-t border-border-subtle space-y-3 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold uppercase tracking-wider text-primary-dark">Payment Method for {{ $sellerProfile->business_name }} <span class="text-danger">*</span></span>
                                        <input type="hidden" name="payment_methods[{{ $spId }}]" :value="paymentMethods[{{ $spId }}]">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div @click="selectCod({{ $spId }})"
                                             :class="paymentMethods[{{ $spId }}] === 'COD' ? 'border-primary bg-primary/5 shadow-xs' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                             class="p-3.5 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
                                                    COD
                                                </div>
                                                <div>
                                                    <div class="font-bold text-text-main">Cash on Delivery (COD)</div>
                                                    <div class="text-[11px] text-text-muted">Pay cash to Rider upon delivery</div>
                                                </div>
                                            </div>
                                            <span x-show="paymentMethods[{{ $spId }}] === 'COD'" class="px-2.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-bold">Selected</span>
                                        </div>

                                        <div @click="selectGcash({{ $spId }}, {{ $gcashEnabled ? 'true' : 'false' }})"
                                             :class="!{{ $gcashEnabled ? 'true' : 'false' }}
                                                ? 'border-border-subtle bg-surface-subtle opacity-60 cursor-not-allowed'
                                                : (paymentMethods[{{ $spId }}] === 'GCASH' ? 'border-blue-600 bg-blue-50/50 shadow-xs cursor-pointer' : 'border-border-subtle bg-surface hover:border-blue-500 cursor-pointer')"
                                             class="p-3.5 rounded-2xl border-2 transition-all flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl bg-blue-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                                    GCash
                                                </div>
                                                <div>
                                                    <div class="font-bold text-text-main">Online Payment (GCash)</div>
                                                    @if($gcashEnabled)
                                                        <div class="text-[11px] text-blue-700 font-semibold">
                                                            <span x-show="!isGcashComplete({{ $spId }})">Tap to view GCash & upload receipt</span>
                                                            <span x-show="isGcashComplete({{ $spId }})" x-cloak class="text-green-700">✓ Ref #<span x-text="gcashState[{{ $spId }}].referenceNumber"></span> attached</span>
                                                        </div>
                                                    @else
                                                        <div class="text-[11px] text-danger font-semibold">Unavailable — Seller has not added a GCash account</div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($gcashEnabled)
                                                <button type="button"
                                                        x-show="paymentMethods[{{ $spId }}] === 'GCASH'"
                                                        @click.stop="openGcashModal({{ $spId }})"
                                                        class="px-2.5 py-1 rounded-lg bg-blue-600 text-white text-[10px] font-bold cursor-pointer shrink-0">
                                                    <span x-text="isGcashComplete({{ $spId }}) ? 'Edit Proof' : 'Fill Form'"></span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($gcashEnabled && $gcashAcc)
                                    <div x-show="gcashState[{{ $spId }}] && gcashState[{{ $spId }}].modalOpen"
                                         x-cloak
                                         class="fixed inset-0 z-[170] flex items-center justify-center p-4">
                                        <div @click="gcashState[{{ $spId }}].modalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>

                                        <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-border-subtle space-y-5 max-h-[92vh] overflow-y-auto">
                                            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                                <div>
                                                    <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-extrabold uppercase">
                                                        GCash Online Payment — <span x-text="gcashState[{{ $spId }}].phase === 1 ? 'Phase 1 of 2' : 'Phase 2 of 2'"></span>
                                                    </span>
                                                    <h3 class="text-base font-bold text-primary-dark mt-1">{{ $sellerProfile->business_name }}</h3>
                                                </div>
                                                <button type="button" @click="gcashState[{{ $spId }}].modalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                                            </div>

                                            <div x-show="gcashState[{{ $spId }}].phase === 1" class="space-y-4 text-xs">
                                                <div class="flex items-center justify-between p-3 rounded-2xl bg-surface-subtle border border-border-subtle">
                                                    <span class="font-bold text-text-main">Want to scan the Seller's GCash QR Code instead?</span>
                                                    <button type="button"
                                                            @click="gcashState[{{ $spId }}].showQr = !gcashState[{{ $spId }}].showQr"
                                                            :class="gcashState[{{ $spId }}].showQr ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 border border-blue-300'"
                                                            class="px-3 py-1.5 rounded-xl font-bold text-[11px] transition-colors cursor-pointer">
                                                        <span x-text="gcashState[{{ $spId }}].showQr ? 'Show Account Name & Number' : 'View GCash QR Code Instead'"></span>
                                                    </button>
                                                </div>

                                                <div x-show="!gcashState[{{ $spId }}].showQr" class="p-5 rounded-2xl bg-blue-50 border border-blue-200 space-y-3 text-center">
                                                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-blue-700 block">Send Exact GCash Amount To</span>
                                                    <div class="text-2xl font-serif font-extrabold text-blue-950">₱{{ number_format($pkg['total'], 2) }}</div>
                                                    <div class="pt-2 border-t border-blue-200/80 space-y-1 text-left">
                                                        <div class="flex justify-between">
                                                            <span class="text-blue-800">GCash Account Name:</span>
                                                            <strong class="text-blue-950 text-sm">{{ $gcashAcc->account_name }}</strong>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-blue-800">GCash Mobile Number:</span>
                                                            <strong class="font-mono text-blue-950 text-sm">{{ $gcashAcc->gcash_number }}</strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div x-show="gcashState[{{ $spId }}].showQr" x-cloak class="p-5 rounded-2xl bg-blue-50 border border-blue-200 text-center space-y-3">
                                                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-blue-700 block">Seller GCash QR Code (₱{{ number_format($pkg['total'], 2) }})</span>
                                                    @if($gcashAcc->qr_image_path)
                                                        <div class="bg-white p-3 rounded-2xl border border-blue-200 inline-block mx-auto">
                                                            <img src="{{ asset('storage/' . $gcashAcc->qr_image_path) }}" alt="Seller GCash QR" class="w-52 h-52 object-contain mx-auto">
                                                        </div>
                                                        <p class="text-[11px] text-blue-900 font-semibold">
                                                            Account: {{ $gcashAcc->account_name }} ({{ $gcashAcc->gcash_number }})
                                                        </p>
                                                    @else
                                                        <div class="p-6 rounded-2xl bg-white border border-blue-200 text-text-muted space-y-1">
                                                            <p class="font-bold text-danger text-sm">GCash QR Code Image Unavailable</p>
                                                            <p class="text-xs">
                                                                This seller did not upload a QR Code image in their panel. Please send your payment using their GCash Account Name (<strong>{{ $gcashAcc->account_name }}</strong>) and GCash Number (<strong class="font-mono">{{ $gcashAcc->gcash_number }}</strong>).
                                                            </p>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="flex justify-end gap-2 pt-2">
                                                    <button type="button"
                                                            @click="selectCod({{ $spId }})"
                                                            class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                                        Switch Back to COD
                                                    </button>
                                                    <button type="button"
                                                            @click="proceedToGcashPhase2({{ $spId }})"
                                                            class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm cursor-pointer">
                                                        Next: Enter Payment Details (Phase 2) &rarr;
                                                    </button>
                                                </div>
                                            </div>

                                            <div x-show="gcashState[{{ $spId }}].phase === 2" x-cloak class="space-y-4 text-xs">
                                                <p class="text-text-muted">
                                                    All fields below are required. Uploaded payment screenshots are automatically optimized to WebP.
                                                </p>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                    <div>
                                                        <label class="block font-bold text-text-muted mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                                                        <input type="text"
                                                               name="gcash_sender_number[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].senderNumber"
                                                               @input="sanitizeSenderPhone({{ $spId }})"
                                                               maxlength="11"
                                                               placeholder="09171234567"
                                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                                                    </div>

                                                    <div>
                                                        <label class="block font-bold text-text-muted mb-1">Date & Time Paid <span class="text-danger">*</span></label>
                                                        <input type="datetime-local"
                                                               name="gcash_paid_at[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].paidAt"
                                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                    <div>
                                                        <label class="block font-bold text-text-muted mb-1">Unique Reference Number <span class="text-danger">*</span></label>
                                                        <input type="text"
                                                               name="gcash_reference_number[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].referenceNumber"
                                                               maxlength="80"
                                                               placeholder="e.g. 1002394857123"
                                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase">
                                                    </div>

                                                    <div>
                                                        <label class="block font-bold text-text-muted mb-1">Reference Code <span class="text-danger">*</span></label>
                                                        <input type="text"
                                                               name="gcash_reference_code[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].referenceCode"
                                                               maxlength="80"
                                                               placeholder="e.g. GC-2026-ABCD"
                                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase">
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block font-bold text-text-muted mb-1">Upload Payment Screenshot (Converted to WebP) <span class="text-danger">*</span></label>
                                                    <input type="file"
                                                           name="gcash_screenshot[{{ $spId }}]"
                                                           accept="image/jpeg,image/png,image/jpg,image/webp"
                                                           @change="onGcashScreenshotSelect({{ $spId }}, $event)"
                                                           class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-bold cursor-pointer">
                                                    <template x-if="gcashState[{{ $spId }}].previewUrl">
                                                        <div class="mt-2 flex items-center gap-3 p-2 rounded-xl bg-surface-subtle border border-border-subtle">
                                                            <img :src="gcashState[{{ $spId }}].previewUrl" alt="Receipt Preview" class="w-14 h-14 object-contain rounded-lg bg-white border border-border-subtle">
                                                            <span class="text-[11px] font-bold text-green-700" x-text="gcashState[{{ $spId }}].screenshotName"></span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <template x-if="gcashState[{{ $spId }}].error">
                                                    <p class="text-danger font-bold text-xs" x-text="gcashState[{{ $spId }}].error"></p>
                                                </template>

                                                <div class="flex items-center justify-between gap-2 pt-3 border-t border-border-subtle">
                                                    <button type="button"
                                                            @click="gcashState[{{ $spId }}].phase = 1"
                                                            class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                                        &larr; Back to Phase 1
                                                    </button>
                                                    <button type="button"
                                                            @click="saveGcashModal({{ $spId }})"
                                                            class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm cursor-pointer">
                                                        Confirm Payment Proof
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="p-4 sm:px-6 bg-brand-light/15 border-t border-border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-text-main">Shipping Tier:</span>
                                            <span class="px-2 py-0.5 rounded-full bg-surface border border-border-subtle font-bold text-primary-dark">
                                                {{ $shipping['tier_label'] }} (Base ₱{{ number_format($shipping['base_fee'], 2) }})
                                            </span>
                                        </div>
                                        <p class="text-text-muted">
                                            Package Weight: <strong>{{ number_format($shipping['total_weight'], 2) }} kg</strong>
                                            @if(($shipping['chargeable_excess_kg'] ?? 0) > 0)
                                                (Excess {{ number_format($shipping['excess_weight'], 2) }} kg billed as {{ number_format($shipping['chargeable_excess_kg'], 0) }} kg &times; ₱10.00 = +₱{{ number_format($shipping['weight_fee'], 2) }})
                                            @else
                                                (No excess weight surcharge &le; 1.00 kg)
                                            @endif
                                        </p>
                                    </div>

                                    <div class="text-right space-y-0.5 shrink-0">
                                        <div class="text-text-muted">
                                            Items Subtotal: <span class="font-semibold text-text-main">₱{{ number_format($pkg['subtotal'], 2) }}</span>
                                        </div>
                                        @if($pkg['store_discount'] > 0)
                                            <div class="text-green-700 font-bold">
                                                Store Voucher ({{ $pkg['store_voucher']?->code }}): -₱{{ number_format($pkg['store_discount'], 2) }}
                                            </div>
                                        @endif
                                        @if($pkg['platform_discount'] > 0)
                                            <div class="text-purple-700 font-bold">
                                                Platform Voucher Share: -₱{{ number_format($pkg['platform_discount'], 2) }}
                                            </div>
                                        @endif
                                        <div class="text-text-muted">
                                            Shipping Fee: <span class="font-semibold text-text-main">₱{{ number_format($pkg['net_shipping_fee'], 2) }}</span>
                                            @if($pkg['shipping_discount'] > 0)
                                                <span class="text-green-700 font-bold">(-₱{{ number_format($pkg['shipping_discount'], 2) }} Free Ship)</span>
                                            @endif
                                        </div>
                                        <div class="text-sm font-extrabold text-primary-dark pt-0.5">
                                            Seller Order Total: ₱{{ number_format($pkg['total'], 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-base font-bold text-primary-dark">Storkia Platform & Free Shipping Vouchers</h3>
                                <p class="text-xs text-text-muted">Stack up to 1 Store Voucher per seller + 1 Platform Voucher + 1 Shipping Voucher.</p>
                            </div>
                            <a href="{{ route('user.coupons') }}" class="text-xs font-bold text-primary hover:underline">
                                Open My Voucher Wallet &rarr;
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2">
                                <label class="block font-bold uppercase tracking-wider text-purple-900">Platform Voucher Code</label>
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                           x-model="platformVoucherInput"
                                           placeholder="e.g. PLAT100"
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase text-xs outline-none focus:border-primary">
                                    <button type="button"
                                            @click="applyVouchersReload()"
                                            class="px-3.5 py-2.5 rounded-xl bg-purple-800 text-white font-bold cursor-pointer">
                                        Apply
                                    </button>
                                </div>
                                @if($platformVouchers->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5 pt-1">
                                        @foreach($platformVouchers as $pv)
                                            <button type="button"
                                                    @click="platformVoucherInput = (platformVoucherInput === '{{ $pv->code }}' ? '' : '{{ $pv->code }}'); applyVouchersReload();"
                                                    class="px-2.5 py-1 rounded-lg border text-[11px] font-bold cursor-pointer {{ $appliedPlatformVoucher?->code === $pv->code ? 'border-purple-800 bg-purple-800 text-white' : 'border-purple-300 bg-purple-50 text-purple-900' }}">
                                                {{ $pv->code }} ({{ $pv->badge_label }} &bull; Min ₱{{ number_format((float) $pv->min_spend, 0) }})
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2">
                                <label class="block font-bold uppercase tracking-wider text-emerald-900">Free Shipping Voucher Code</label>
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                           x-model="shippingVoucherInput"
                                           placeholder="e.g. FREESHIP"
                                           class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase text-xs outline-none focus:border-primary">
                                    <button type="button"
                                            @click="applyVouchersReload()"
                                            class="px-3.5 py-2.5 rounded-xl bg-emerald-700 text-white font-bold cursor-pointer">
                                        Apply
                                    </button>
                                </div>
                                @if($shippingVouchers->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5 pt-1">
                                        @foreach($shippingVouchers as $shv)
                                            <button type="button"
                                                    @click="shippingVoucherInput = (shippingVoucherInput === '{{ $shv->code }}' ? '' : '{{ $shv->code }}'); applyVouchersReload();"
                                                    class="px-2.5 py-1 rounded-lg border text-[11px] font-bold cursor-pointer {{ $appliedShippingVoucher?->code === $shv->code ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }}">
                                                {{ $shv->code }} (Up to ₱{{ number_format((float) $shv->discount_value, 0) }} &bull; Min ₱{{ number_format((float) $shv->min_spend, 0) }})
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>

                <div class="lg:col-span-4">
                    <div class="bg-surface rounded-2xl border border-border-subtle p-6 shadow-xs sticky top-24 space-y-5">
                        <div>
                            <h3 class="text-xl font-serif font-bold text-primary-dark">Checkout Summary</h3>
                            <p class="text-xs text-text-muted mt-0.5">Review your multi-seller order total before placing order.</p>
                        </div>

                        <div class="space-y-2.5 pt-3 border-t border-border-subtle text-xs sm:text-sm">
                            <div class="flex items-center justify-between text-text-muted">
                                <span>Merchandise Subtotal ({{ $totalItemsCount }} items):</span>
                                <span class="font-semibold text-text-main">₱{{ number_format($overallSubtotal, 2) }}</span>
                            </div>

                            <div class="flex items-center justify-between text-text-muted">
                                <span>Shipping Subtotal ({{ count($sellerPackages) }} {{ Str::plural('Shipment', count($sellerPackages)) }}):</span>
                                <span class="font-semibold text-text-main">₱{{ number_format($overallShipping, 2) }}</span>
                            </div>

                            @if($overallStoreDiscount > 0)
                                <div class="flex items-center justify-between text-green-700 font-bold">
                                    <span>Store Voucher Discount:</span>
                                    <span>-₱{{ number_format($overallStoreDiscount, 2) }}</span>
                                </div>
                            @endif

                            @if($overallPlatformDiscount > 0)
                                <div class="flex items-center justify-between text-purple-700 font-bold">
                                    <span>Platform Voucher ({{ $appliedPlatformVoucher?->code }}):</span>
                                    <span>-₱{{ number_format($overallPlatformDiscount, 2) }}</span>
                                </div>
                            @endif

                            @if($overallShippingDiscount > 0)
                                <div class="flex items-center justify-between text-emerald-700 font-bold">
                                    <span>Shipping Voucher ({{ $appliedShippingVoucher?->code }}):</span>
                                    <span>-₱{{ number_format($overallShippingDiscount, 2) }}</span>
                                </div>
                            @endif

                            <div class="flex items-center justify-between pt-3 border-t border-border-subtle text-base font-bold text-text-main">
                                <span>Total Payable Amount:</span>
                                <span class="text-2xl font-extrabold font-serif text-primary">
                                    ₱{{ number_format($overallTotal, 2) }}
                                </span>
                            </div>
                        </div>

                        <div x-show="!allSelectedPaymentsValid()" x-cloak class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 font-semibold">
                            Please complete the 2-Phase GCash Payment Form & upload your receipt screenshot for each store where Online Payment is selected.
                        </div>

                        <button type="submit"
                                :disabled="isSubmitting || {{ ($selectedAddress && $isAddressServiceable) ? 'false' : 'true' }} || !allSelectedPaymentsValid()"
                                :class="(isSubmitting || {{ ($selectedAddress && $isAddressServiceable) ? 'false' : 'true' }} || !allSelectedPaymentsValid())
                                    ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle'
                                    : 'bg-primary hover:bg-primary-dark text-surface cursor-pointer shadow-md'"
                                class="w-full py-4 px-6 font-bold text-sm rounded-xl transition-all flex items-center justify-center gap-2">
                            <span x-show="!isSubmitting">Place Order (₱{{ number_format($overallTotal, 2) }})</span>
                            <span x-show="isSubmitting" x-cloak>Processing Order...</span>
                        </button>

                        <p class="text-[11px] text-text-muted text-center leading-relaxed">
                            By placing this order, stock is reserved immediately and each seller has 24 hours to review and accept your order.
                        </p>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <template x-teleport="body">
        <div x-show="addressModalOpen"
             x-cloak
             class="fixed inset-0 z-[150] flex items-center justify-center p-4">
            <div @click="addressModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop
                 class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Select Delivery Address</h3>
                    <button type="button" @click="addressModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                    @foreach($addresses as $addr)
                        <a href="{{ route('checkout.index', array_filter(['mode' => $mode, 'address_id' => $addr->id, 'cart_ids' => implode(',', $selectedCartIds)])) }}"
                           class="block p-4 rounded-2xl border transition-all {{ $selectedAddress && $selectedAddress->id === $addr->id ? 'border-primary bg-primary/5' : 'border-border-subtle hover:border-primary/40' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-text-main">{{ $addr->first_name }} {{ $addr->last_name }}</span>
                                @if($addr->is_default)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-primary/10 text-primary font-bold">Default</span>
                                @endif
                            </div>
                            <p class="text-xs text-text-muted mt-1">+63 {{ ltrim($addr->phone_number, '0') }}</p>
                            <p class="text-xs text-text-main mt-1">
                                {{ collect([$addr->building_details, $addr->street_address, $addr->barangay, $addr->municipality, $addr->province, $addr->postcode])->filter()->implode(', ') }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>
@endsection