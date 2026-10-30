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
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-primary-dark">Delivery Address</h2>
                                @if($selectedAddress)
                                    <p class="text-sm font-bold text-text-main mt-1">
                                        {{ $selectedAddress->first_name }} {{ $selectedAddress->last_name }}
                                        <span class="text-text-muted font-normal ml-2">+63 {{ ltrim($selectedAddress->phone_number, '0') }}</span>
                                    </p>
                                    <p class="text-xs sm:text-sm text-text-muted mt-1 leading-relaxed">
                                        {{ collect([$selectedAddress->building_details, $selectedAddress->street_address, $selectedAddress->barangay, $selectedAddress->municipality, $selectedAddress->province, $selectedAddress->postcode])->filter()->implode(', ') }}
                                    </p>
                                @else
                                    <p class="text-sm text-danger font-semibold mt-1">Please add a delivery address to place your order.</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if($addresses->count() > 1)
                                    <button type="button" @click.stop="addressModalOpen = true" class="px-4 py-2 rounded-xl border border-border-subtle bg-surface-subtle text-xs font-bold text-text-main cursor-pointer">
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
                            $shipping = $pkg['shipping'];
                            $gcashEnabled = (bool) $pkg['gcash_enabled'];
                            $gcashAcc = $pkg['gcash_account'];
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
                                            @if($item['image_path'])
                                                <img src="{{ asset('storage/' . $item['image_path']) }}" alt="{{ $item['product']->name }}" class="w-full h-full object-contain rounded-lg">
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-semibold text-text-main truncate">{{ $item['product']->name }}</h4>
                                            @if($item['variant_name'])
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
                                            Store Voucher ({{ $sellerProfile->business_name }} — Select Ticket Radio or Type Code)
                                        </label>
                                        <input type="hidden" name="store_voucher_codes[{{ $spId }}]" :value="storeVoucherInputs[{{ $spId }}]">
                                    </div>

                                    @if($storeVouchersForSeller->isNotEmpty())
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                            <label @click="storeVoucherInputs[{{ $spId }}] = ''; applyVouchersReload();"
                                                   :class="!storeVoucherInputs[{{ $spId }}] ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface'"
                                                   class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer">
                                                <input type="radio" name="radio_store_voucher_{{ $spId }}" value="" :checked="!storeVoucherInputs[{{ $spId }}]" class="text-primary">
                                                <span class="font-bold text-text-muted">No Store Voucher</span>
                                            </label>

                                            @foreach($storeVouchersForSeller as $sv)
                                                <label @click="storeVoucherInputs[{{ $spId }}] = '{{ $sv->code }}'; applyVouchersReload();"
                                                       :class="storeVoucherInputs[{{ $spId }}] === '{{ $sv->code }}' ? 'border-primary ring-2 ring-primary/20 bg-surface' : 'border-border-subtle bg-surface hover:border-primary/40'"
                                                       class="flex rounded-2xl border overflow-hidden cursor-pointer shadow-2xs">
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
                                                        </div>
                                                        <input type="radio" name="radio_store_voucher_{{ $spId }}" value="{{ $sv->code }}" :checked="storeVoucherInputs[{{ $spId }}] === '{{ $sv->code }}'" class="text-primary shrink-0">
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2">
                                        <input type="text"
                                               x-model="storeVoucherInputs[{{ $spId }}]"
                                               placeholder="Or enter secret store voucher code..."
                                               class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase text-xs outline-none focus:border-primary">
                                        <button type="button" @click="applyVouchersReload()" class="px-4 py-2.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">
                                            Apply Code
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 sm:px-6 bg-surface border-t border-border-subtle space-y-3 text-xs">
                                <span class="font-bold uppercase tracking-wider text-primary-dark block">Payment Method for {{ $sellerProfile->business_name }} <span class="text-danger">*</span></span>
                                <input type="hidden" name="payment_methods[{{ $spId }}]" :value="paymentMethods[{{ $spId }}]">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div @click="selectCod({{ $spId }})"
                                         :class="paymentMethods[{{ $spId }}] === 'COD' ? 'border-primary bg-primary/5' : 'border-border-subtle bg-surface'"
                                         class="p-3.5 rounded-2xl border-2 cursor-pointer flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center">COD</div>
                                            <div>
                                                <div class="font-bold text-text-main">Cash on Delivery (COD)</div>
                                                <div class="text-[11px] text-text-muted">Pay cash to Rider upon delivery</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div @click="selectGcash({{ $spId }}, {{ $gcashEnabled ? 'true' : 'false' }})"
                                         :class="!{{ $gcashEnabled ? 'true' : 'false' }}
                                            ? 'border-border-subtle bg-surface-subtle opacity-60 cursor-not-allowed'
                                            : (paymentMethods[{{ $spId }}] === 'GCASH' ? 'border-blue-600 bg-blue-50/50 cursor-pointer' : 'border-border-subtle bg-surface cursor-pointer')"
                                         class="p-3.5 rounded-2xl border-2 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-blue-600 text-white font-bold text-xs flex items-center justify-center">GCash</div>
                                            <div>
                                                <div class="font-bold text-text-main">Online Payment (GCash)</div>
                                                @if($gcashEnabled)
                                                    <div class="text-[11px] text-blue-700 font-semibold">
                                                        <span x-show="!isGcashComplete({{ $spId }})">Tap to view GCash &amp; upload receipt</span>
                                                        <span x-show="isGcashComplete({{ $spId }})" x-cloak class="text-green-700">✓ Ref #<span x-text="gcashState[{{ $spId }}].referenceNumber"></span> attached</span>
                                                    </div>
                                                @else
                                                    <div class="text-[11px] text-danger font-semibold">Unavailable — Seller has no GCash account</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($gcashEnabled && $gcashAcc)
                                <div x-show="gcashState[{{ $spId }}] && gcashState[{{ $spId }}].modalOpen"
                                     x-cloak
                                     class="fixed inset-0 z-[170] flex items-center justify-center p-4">
                                    <div @click="gcashState[{{ $spId }}].modalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>

                                    <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[92vh] overflow-y-auto">
                                        <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                            <h3 class="text-base font-bold text-primary-dark">GCash Online Payment — {{ $sellerProfile->business_name }}</h3>
                                            <button type="button" @click="gcashState[{{ $spId }}].modalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                                        </div>

                                        <div x-show="gcashState[{{ $spId }}].phase === 1" class="space-y-4 text-xs">
                                            <div class="flex items-center justify-between p-3 rounded-2xl bg-surface-subtle border border-border-subtle">
                                                <span class="font-bold text-text-main">View Seller GCash QR Code instead?</span>
                                                <button type="button"
                                                        @click="gcashState[{{ $spId }}].showQr = !gcashState[{{ $spId }}].showQr"
                                                        class="px-3 py-1.5 rounded-xl bg-blue-600 text-white font-bold text-[11px] cursor-pointer">
                                                    <span x-text="gcashState[{{ $spId }}].showQr ? 'Show Account Name & Number' : 'View GCash QR Code'"></span>
                                                </button>
                                            </div>

                                            <div x-show="!gcashState[{{ $spId }}].showQr" class="p-4 rounded-2xl bg-blue-50 border border-blue-200 space-y-2">
                                                <div class="text-xl font-serif font-extrabold text-blue-950">Amount: ₱{{ number_format($pkg['total'], 2) }}</div>
                                                <div>Account Name: <strong>{{ $gcashAcc->account_name }}</strong></div>
                                                <div>GCash Number: <strong class="font-mono">{{ $gcashAcc->gcash_number }}</strong></div>
                                            </div>

                                            <div x-show="gcashState[{{ $spId }}].showQr" x-cloak class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-center">
                                                @if($gcashAcc->qr_image_path)
                                                    <img src="{{ asset('storage/' . $gcashAcc->qr_image_path) }}" alt="Seller GCash QR" class="w-48 h-48 object-contain mx-auto bg-white p-2 rounded-xl">
                                                @else
                                                    <p class="font-bold text-danger">QR Code image unavailable — please use Account Name &amp; Number.</p>
                                                @endif
                                            </div>

                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="selectCod({{ $spId }})" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Switch to COD</button>
                                                <button type="button" @click="proceedToGcashPhase2({{ $spId }})" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-bold cursor-pointer">Next: Enter Payment Proof &rarr;</button>
                                            </div>
                                        </div>

                                        <div x-show="gcashState[{{ $spId }}].phase === 2" x-cloak class="space-y-3 text-xs">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block font-bold text-text-muted mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="gcash_sender_number[{{ $spId }}]" x-model="gcashState[{{ $spId }}].senderNumber" @input="sanitizeSenderPhone({{ $spId }})" maxlength="11" placeholder="09171234567" class="w-full p-2.5 rounded-xl border border-border-subtle font-mono">
                                                </div>
                                                <div>
                                                    <label class="block font-bold text-text-muted mb-1">Date &amp; Time Paid <span class="text-danger">*</span></label>
                                                    <input type="datetime-local" name="gcash_paid_at[{{ $spId }}]" x-model="gcashState[{{ $spId }}].paidAt" class="w-full p-2.5 rounded-xl border border-border-subtle">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block font-bold text-text-muted mb-1">Unique Reference Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="gcash_reference_number[{{ $spId }}]" x-model="gcashState[{{ $spId }}].referenceNumber" maxlength="80" class="w-full p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                                </div>
                                                <div>
                                                    <label class="block font-bold text-text-muted mb-1">Reference Code <span class="text-danger">*</span></label>
                                                    <input type="text" name="gcash_reference_code[{{ $spId }}]" x-model="gcashState[{{ $spId }}].referenceCode" maxlength="80" class="w-full p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Upload Payment Screenshot <span class="text-danger">*</span></label>
                                                <input type="file" name="gcash_screenshot[{{ $spId }}]" accept="image/jpeg,image/png,image/jpg,image/webp" @change="onGcashScreenshotSelect({{ $spId }}, $event)" class="w-full p-2 rounded-xl border border-border-subtle">
                                            </div>

                                            <template x-if="gcashState[{{ $spId }}].error">
                                                <p class="text-danger font-bold" x-text="gcashState[{{ $spId }}].error"></p>
                                            </template>

                                            <div class="flex justify-between pt-2">
                                                <button type="button" @click="gcashState[{{ $spId }}].phase = 1" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">&larr; Back</button>
                                                <button type="button" @click="saveGcashModal({{ $spId }})" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-bold cursor-pointer">Confirm Proof</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5 text-xs">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Platform &amp; Free Shipping Vouchers (Ticket Radio Selector + Code Input)</h3>
                            <p class="text-xs text-text-muted">Select your claimed ticket voucher below or type a voucher code manually.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-purple-900">Platform Voucher (Admin-Sponsored)</label>
                                @if($platformVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click="platformVoucherInput = ''; applyVouchersReload();"
                                               :class="!platformVoucherInput ? 'border-purple-700 bg-purple-50/50' : 'border-border-subtle bg-surface'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="radio_platform_voucher" value="" :checked="!platformVoucherInput">
                                            <span class="font-bold text-text-muted">No Platform Voucher</span>
                                        </label>
                                        @foreach($platformVouchers as $pv)
                                            <label @click="platformVoucherInput = '{{ $pv->code }}'; applyVouchersReload();"
                                                   :class="platformVoucherInput === '{{ $pv->code }}' ? 'border-purple-700 ring-2 ring-purple-200 bg-surface' : 'border-border-subtle bg-surface'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer">
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
                                                    </div>
                                                    <input type="radio" name="radio_platform_voucher" value="{{ $pv->code }}" :checked="platformVoucherInput === '{{ $pv->code }}'">
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="platformVoucherInput" placeholder="Enter Platform Code..." class="flex-1 p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                    <button type="button" @click="applyVouchersReload()" class="px-3.5 py-2.5 rounded-xl bg-purple-800 text-white font-bold cursor-pointer">Apply</button>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <label class="block font-bold uppercase tracking-wider text-emerald-900">Free Shipping Voucher</label>
                                @if($shippingVouchers->isNotEmpty())
                                    <div class="space-y-2">
                                        <label @click="shippingVoucherInput = ''; applyVouchersReload();"
                                               :class="!shippingVoucherInput ? 'border-emerald-700 bg-emerald-50/50' : 'border-border-subtle bg-surface'"
                                               class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="radio_shipping_voucher" value="" :checked="!shippingVoucherInput">
                                            <span class="font-bold text-text-muted">No Free Shipping Voucher</span>
                                        </label>
                                        @foreach($shippingVouchers as $shv)
                                            <label @click="shippingVoucherInput = '{{ $shv->code }}'; applyVouchersReload();"
                                                   :class="shippingVoucherInput === '{{ $shv->code }}' ? 'border-emerald-700 ring-2 ring-emerald-200 bg-surface' : 'border-border-subtle bg-surface'"
                                                   class="flex rounded-2xl border overflow-hidden cursor-pointer">
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
                                                    </div>
                                                    <input type="radio" name="radio_shipping_voucher" value="{{ $shv->code }}" :checked="shippingVoucherInput === '{{ $shv->code }}'">
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="shippingVoucherInput" placeholder="Enter Shipping Code..." class="flex-1 p-2.5 rounded-xl border border-border-subtle font-mono uppercase">
                                    <button type="button" @click="applyVouchersReload()" class="px-3.5 py-2.5 rounded-xl bg-emerald-700 text-white font-bold cursor-pointer">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="lg:col-span-4">
                    <div class="bg-surface rounded-2xl border border-border-subtle p-6 shadow-xs sticky top-24 space-y-5">
                        <h3 class="text-xl font-serif font-bold text-primary-dark">Checkout Summary</h3>

                        <div class="space-y-2.5 pt-3 border-t border-border-subtle text-xs sm:text-sm">
                            <div class="flex justify-between text-text-muted">
                                <span>Merchandise Subtotal ({{ $totalItemsCount }} items):</span>
                                <span class="font-semibold text-text-main">₱{{ number_format($overallSubtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-text-muted">
                                <span>Shipping Subtotal:</span>
                                <span class="font-semibold text-text-main">₱{{ number_format($overallShipping, 2) }}</span>
                            </div>
                            @if($overallStoreDiscount > 0)
                                <div class="flex justify-between text-green-700 font-bold">
                                    <span>Store Voucher Discount:</span>
                                    <span>-₱{{ number_format($overallStoreDiscount, 2) }}</span>
                                </div>
                            @endif
                            @if($overallPlatformDiscount > 0)
                                <div class="flex justify-between text-purple-700 font-bold">
                                    <span>Platform Voucher ({{ $appliedPlatformVoucher?->code }}):</span>
                                    <span>-₱{{ number_format($overallPlatformDiscount, 2) }}</span>
                                </div>
                            @endif
                            @if($overallShippingDiscount > 0)
                                <div class="flex justify-between text-emerald-700 font-bold">
                                    <span>Shipping Voucher ({{ $appliedShippingVoucher?->code }}):</span>
                                    <span>-₱{{ number_format($overallShippingDiscount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between pt-3 border-t border-border-subtle text-base font-bold text-text-main">
                                <span>Total Payable Amount:</span>
                                <span class="text-2xl font-extrabold font-serif text-primary">₱{{ number_format($overallTotal, 2) }}</span>
                            </div>
                        </div>

                        <button type="submit"
                                :disabled="isSubmitting || {{ ($selectedAddress && $isAddressServiceable) ? 'false' : 'true' }} || !allSelectedPaymentsValid()"
                                :class="(isSubmitting || {{ ($selectedAddress && $isAddressServiceable) ? 'false' : 'true' }} || !allSelectedPaymentsValid())
                                    ? 'bg-surface-subtle text-text-muted cursor-not-allowed border border-border-subtle'
                                    : 'bg-primary hover:bg-primary-dark text-surface cursor-pointer shadow-md'"
                                class="w-full py-4 px-6 font-bold text-sm rounded-xl transition-all">
                            <span x-show="!isSubmitting">Place Order (₱{{ number_format($overallTotal, 2) }})</span>
                            <span x-show="isSubmitting" x-cloak>Processing Order...</span>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection