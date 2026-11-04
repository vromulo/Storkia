@extends('layouts.app', ['title' => 'Checkout - Storkia'])

@section('content')
@php
    $platformGcash = null;
    if (\Illuminate\Support\Facades\Schema::hasTable('platform_gcash_settings')) {
        $platformGcash = \Illuminate\Support\Facades\DB::table('platform_gcash_settings')
            ->orderByDesc('id')
            ->first();
    }

    $isPlatformGcashActive = $platformGcash && $platformGcash->is_active && !empty($platformGcash->gcash_number);
    $nowManilaInput = now()->timezone('Asia/Manila')->format('Y-m-d\TH:i');
    $nowManilaDisplay = now()->timezone('Asia/Manila')->format('M d, Y h:i A');

    $initialPaymentMethods = [];
    $initialGcashState = [];
    $initialNotes = [];
    $currentStoreVoucherCodes = [];

    foreach ($sellerPackages as $pkg) {
        $spId = (int) $pkg['seller_profile']->id;
        $pkgGcashEnabled = $isPlatformGcashActive || !empty($pkg['gcash_enabled']);
        $oldMethod = old("payment_methods.{$spId}", 'COD');
        $initialPaymentMethods[$spId] = ($pkgGcashEnabled && $oldMethod === 'GCASH') ? 'GCASH' : 'COD';
        $initialNotes[$spId] = (string) old("seller_notes.{$spId}", '');
        $currentStoreVoucherCodes[$spId] = $pkg['store_voucher']?->code ?? '';

        $initialGcashState[$spId] = [
            'modalOpen'       => false,
            'phase'           => 1,
            'manualStep'      => 1,
            'showQr'          => false,
            'copiedNumber'    => false,
            'senderNumber'    => (string) old("gcash_sender_number.{$spId}", ''),
            'paidAt'          => (string) old("gcash_paid_at.{$spId}", $nowManilaInput),
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
        phTime: '{{ now()->timezone('Asia/Manila')->format('h:i:s A') }}',
        phDate: '{{ now()->timezone('Asia/Manila')->format('M d, Y') }}',
        clockTimer: null,
        paymentMethods: @js($initialPaymentMethods),
        gcashState: @js($initialGcashState),
        sellerNotes: @js($initialNotes),
        storeVoucherInputs: @js($currentStoreVoucherCodes),
        platformVoucherInput: @js($appliedPlatformVoucher?->code ?? ''),
        shippingVoucherInput: @js($appliedShippingVoucher?->code ?? ''),

        initManilaClock() {
            const updateClock = () => {
                try {
                    const now = new Date();
                    this.phTime = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'Asia/Manila',
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    }).format(now);
                    this.phDate = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'Asia/Manila',
                        month: 'short',
                        day: '2-digit',
                        year: 'numeric'
                    }).format(now);
                } catch (e) {}
            };
            updateClock();
            this.clockTimer = setInterval(updateClock, 1000);
        },

        copyEscrowNumber(spId, numberStr) {
            if (!numberStr || !navigator.clipboard) return;
            navigator.clipboard.writeText(numberStr);
            if (this.gcashState[spId]) {
                this.gcashState[spId].copiedNumber = true;
                setTimeout(() => {
                    if (this.gcashState[spId]) this.gcashState[spId].copiedNumber = false;
                }, 2000);
            }
        },

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
            this.gcashState[spId].manualStep = 1;
            this.gcashState[spId].modalOpen = true;
        },

        proceedToGcashPhase2(spId) {
            if (!this.gcashState[spId]) return;
            this.gcashState[spId].error = '';
            this.gcashState[spId].phase = 2;
            this.gcashState[spId].manualStep = 3;
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

        changeDeliveryAddress(addressId) {
            const params = new URLSearchParams(window.location.search);
            params.set('mode', '{{ $mode }}');
            params.set('address_id', String(addressId));
            @if(!empty($selectedCartIds))
                params.set('cart_ids', '{{ implode(',', $selectedCartIds) }}');
            @endif
            window.location.href = `{{ route('checkout.index') }}?` + params.toString();
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
     x-init="initManilaClock()"
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

                    <!-- Delivery Address Card -->
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
                            $shipping = $pkg['shipping'];
                            $fallbackAcc = $pkg['gcash_account'] ?? null;
                            $gcashEnabled = $isPlatformGcashActive || !empty($pkg['gcash_enabled']);

                            $escrowAccountName = $isPlatformGcashActive
                                ? $platformGcash->account_name
                                : ($fallbackAcc->account_name ?? 'Storkia Escrow Administration');
                            $escrowGcashNumber = $isPlatformGcashActive
                                ? $platformGcash->gcash_number
                                : ($fallbackAcc->gcash_number ?? '');
                            $escrowQrPath = $isPlatformGcashActive
                                ? ($platformGcash->qr_image_path ?? null)
                                : ($fallbackAcc->qr_image_path ?? null);
                            $escrowInstructions = $isPlatformGcashActive
                                ? ($platformGcash->instructions ?? 'Send the exact package total to the official Storkia Admin Escrow GCash account and upload your receipt screenshot.')
                                : 'Send the exact package total via GCash and upload your receipt screenshot.';

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

                            <!-- Package Payment Method Selection -->
                            <div class="p-4 sm:px-6 bg-surface border-t border-border-subtle space-y-3 text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-bold uppercase tracking-wider text-primary-dark block">
                                        Payment Method for {{ $sellerProfile->business_name }} <span class="text-danger">*</span>
                                    </span>
                                    <span class="text-[11px] text-text-muted font-semibold">
                                        Package Total: <strong class="text-primary">₱{{ number_format($pkg['total'], 2) }}</strong>
                                    </span>
                                </div>

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
                                                <div class="font-bold text-text-main">Online Payment (Storkia Admin GCash Escrow)</div>
                                                @if($gcashEnabled)
                                                    <div class="text-[11px] text-blue-700 font-semibold">
                                                        <span x-show="!isGcashComplete({{ $spId }})">Tap to open Cellphone GCash Guide &amp; upload receipt</span>
                                                        <span x-show="isGcashComplete({{ $spId }})" x-cloak class="text-green-700">✓ Ref #<span x-text="gcashState[{{ $spId }}].referenceNumber"></span> attached</span>
                                                    </div>
                                                @else
                                                    <div class="text-[11px] text-danger font-semibold">Unavailable — Admin GCash Escrow not yet configured</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive Cellphone GCash User Manual + Proof Upload Modal -->
                            @if($gcashEnabled)
                                <div x-show="gcashState[{{ $spId }}] && gcashState[{{ $spId }}].modalOpen"
                                     x-cloak
                                     class="fixed inset-0 z-[170] flex items-center justify-center p-3 sm:p-5">
                                    <div @click="gcashState[{{ $spId }}].modalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>

                                    <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-4xl w-full p-5 sm:p-7 shadow-2xl border border-border-subtle space-y-5 max-h-[94vh] overflow-y-auto custom-scrollbar">
                                        <!-- Modal Header with Live Asia/Manila Clock -->
                                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-border-subtle">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-extrabold uppercase">
                                                        Storkia Admin Escrow
                                                    </span>
                                                    <h3 class="text-base sm:text-lg font-bold text-primary-dark">
                                                        GCash Online Payment &amp; Cellphone User Manual
                                                    </h3>
                                                </div>
                                                <p class="text-xs text-text-muted mt-0.5">
                                                    Package: <strong>{{ $sellerProfile->business_name }}</strong> &bull;
                                                    Amount Due: <strong class="text-blue-700">₱{{ number_format($pkg['total'], 2) }}</strong>
                                                </p>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-mono text-[11px] flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                                    <span>PH Time (Asia/Manila): <strong x-text="phTime"></strong></span>
                                                </div>
                                                <button type="button" @click="gcashState[{{ $spId }}].modalOpen = false" class="p-1.5 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                                            <!-- LEFT COLUMN: Interactive Visual Cellphone User Manual -->
                                            <div class="lg:col-span-5 flex flex-col items-center">
                                                <div class="w-full max-w-[310px] rounded-[36px] border-[6px] border-slate-900 bg-slate-950 shadow-2xl overflow-hidden text-xs select-none">
                                                    <!-- Smartphone Top Status Bar (Live Asia/Manila Timezone) -->
                                                    <div class="bg-slate-950 text-white px-4 pt-2 pb-1.5 flex items-center justify-between text-[10px] font-mono">
                                                        <div class="flex items-center gap-1">
                                                            <span class="font-bold" x-text="phTime"></span>
                                                            <span class="text-[8px] text-emerald-400 font-bold">PHT</span>
                                                        </div>
                                                        <div class="w-14 h-3 rounded-full bg-black border border-slate-800"></div>
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-[9px] font-bold">5G</span>
                                                            <div class="w-4 h-2.5 rounded-xs border border-white p-0.5 flex items-center">
                                                                <div class="w-full h-full bg-emerald-400"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- GCash App Blue Header -->
                                                    <div class="bg-gradient-to-r from-blue-700 to-blue-600 text-white px-4 py-3 space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-extrabold tracking-tight text-sm">GCash Escrow Manual</span>
                                                            <span class="px-2 py-0.5 rounded-full bg-white/20 text-[9px] font-bold"
                                                                  x-text="'Step ' + gcashState[{{ $spId }}].manualStep + ' of 3'"></span>
                                                        </div>
                                                        <!-- Step Switcher Pills inside Phone -->
                                                        <div class="grid grid-cols-3 gap-1 bg-blue-900/50 p-1 rounded-xl text-[10px] font-bold">
                                                            <button type="button"
                                                                    @click="gcashState[{{ $spId }}].manualStep = 1"
                                                                    :class="gcashState[{{ $spId }}].manualStep === 1 ? 'bg-white text-blue-800 shadow-2xs' : 'text-white/80'"
                                                                    class="py-1 rounded-lg transition-all cursor-pointer">
                                                                1. Send
                                                            </button>
                                                            <button type="button"
                                                                    @click="gcashState[{{ $spId }}].manualStep = 2"
                                                                    :class="gcashState[{{ $spId }}].manualStep === 2 ? 'bg-white text-blue-800 shadow-2xs' : 'text-white/80'"
                                                                    class="py-1 rounded-lg transition-all cursor-pointer">
                                                                2. Receipt
                                                            </button>
                                                            <button type="button"
                                                                    @click="gcashState[{{ $spId }}].manualStep = 3; gcashState[{{ $spId }}].phase = 2"
                                                                    :class="gcashState[{{ $spId }}].manualStep === 3 ? 'bg-white text-blue-800 shadow-2xs' : 'text-white/80'"
                                                                    class="py-1 rounded-lg transition-all cursor-pointer">
                                                                3. Proof
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Phone Screen Body -->
                                                    <div class="bg-slate-50 text-slate-900 p-4 min-h-[340px] flex flex-col justify-between space-y-3">
                                                        <!-- Phone Step 1: Send Money / QR -->
                                                        <div x-show="gcashState[{{ $spId }}].manualStep === 1" class="space-y-3">
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-[10px] font-extrabold uppercase text-blue-700">Step 1: Open GCash &rarr; Express Send</span>
                                                                <button type="button"
                                                                        @click="gcashState[{{ $spId }}].showQr = !gcashState[{{ $spId }}].showQr"
                                                                        class="text-[10px] font-bold text-blue-600 underline cursor-pointer">
                                                                    <span x-text="gcashState[{{ $spId }}].showQr ? 'Show Number' : 'Show QR'"></span>
                                                                </button>
                                                            </div>

                                                            <div x-show="!gcashState[{{ $spId }}].showQr" class="p-3 rounded-2xl bg-white border border-blue-200 shadow-2xs space-y-2">
                                                                <div>
                                                                    <span class="text-[9px] uppercase font-bold text-slate-400 block">Send To Storkia Admin Escrow</span>
                                                                    <div class="font-bold text-xs text-slate-900">{{ $escrowAccountName }}</div>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100">
                                                                    <div>
                                                                        <span class="text-[9px] uppercase font-bold text-slate-400 block">GCash Number (11 Digits)</span>
                                                                        <span class="font-mono font-extrabold text-sm text-blue-800">{{ $escrowGcashNumber }}</span>
                                                                    </div>
                                                                    <button type="button"
                                                                            @click="copyEscrowNumber({{ $spId }}, '{{ $escrowGcashNumber }}')"
                                                                            class="px-2.5 py-1 rounded-lg bg-blue-600 text-white text-[10px] font-bold cursor-pointer">
                                                                        <span x-show="!gcashState[{{ $spId }}].copiedNumber">Copy</span>
                                                                        <span x-show="gcashState[{{ $spId }}].copiedNumber" x-cloak>Copied!</span>
                                                                    </button>
                                                                </div>
                                                                <div class="pt-1 border-t border-slate-100 flex items-center justify-between">
                                                                    <span class="text-[10px] font-bold text-slate-500">Exact Amount:</span>
                                                                    <span class="font-serif font-extrabold text-base text-blue-700">₱{{ number_format($pkg['total'], 2) }}</span>
                                                                </div>
                                                            </div>

                                                            <div x-show="gcashState[{{ $spId }}].showQr" x-cloak class="p-3 rounded-2xl bg-white border border-blue-200 text-center space-y-2">
                                                                @if($escrowQrPath)
                                                                    <img src="{{ asset('storage/' . $escrowQrPath) }}" alt="Storkia Admin GCash QR" class="w-40 h-40 object-contain mx-auto rounded-xl border border-slate-200 p-1">
                                                                    <p class="text-[10px] text-slate-600 font-semibold">Scan in GCash App &rarr; Pay QR</p>
                                                                @else
                                                                    <p class="text-[11px] font-bold text-amber-700 py-8">No QR image uploaded — please send using the 11-digit GCash number.</p>
                                                                @endif
                                                            </div>

                                                            <p class="text-[10px] text-slate-600 leading-relaxed">
                                                                {{ $escrowInstructions }}
                                                            </p>
                                                        </div>

                                                        <!-- Phone Step 2: Simulated GCash Receipt Guide -->
                                                        <div x-show="gcashState[{{ $spId }}].manualStep === 2" x-cloak class="space-y-2.5">
                                                            <span class="text-[10px] font-extrabold uppercase text-blue-700 block">Step 2: Save Your GCash Receipt</span>
                                                            <div class="p-3 rounded-2xl bg-white border-2 border-dashed border-blue-300 space-y-2 text-[11px]">
                                                                <div class="text-center pb-2 border-b border-slate-100">
                                                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center mx-auto mb-1">&check;</div>
                                                                    <div class="font-extrabold text-blue-900">{{ $escrowAccountName }}</div>
                                                                    <div class="font-mono text-[10px] text-slate-500">{{ $escrowGcashNumber }}</div>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-slate-500">Total Amount Sent</span>
                                                                    <strong class="text-slate-900">₱{{ number_format($pkg['total'], 2) }}</strong>
                                                                </div>
                                                                <div class="p-1.5 rounded-lg bg-amber-50 border border-amber-300 flex justify-between items-center">
                                                                    <span class="text-[10px] font-bold text-amber-900">1. Date &amp; Time (PH):</span>
                                                                    <span class="font-mono text-[10px] font-bold text-amber-950" x-text="phDate + ' ' + phTime"></span>
                                                                </div>
                                                                <div class="p-1.5 rounded-lg bg-blue-50 border border-blue-300 flex justify-between items-center">
                                                                    <span class="text-[10px] font-bold text-blue-900">2. Ref No. (13 Digits):</span>
                                                                    <span class="font-mono text-[10px] font-extrabold text-blue-950">e.g. 9012345678901</span>
                                                                </div>
                                                                <div class="p-1.5 rounded-lg bg-emerald-50 border border-emerald-300 flex justify-between items-center">
                                                                    <span class="text-[10px] font-bold text-emerald-900">3. Reference Code:</span>
                                                                    <span class="font-mono text-[10px] font-extrabold text-emerald-950">e.g. GC-8841</span>
                                                                </div>
                                                            </div>
                                                            <p class="text-[10px] text-slate-600 leading-snug">
                                                                Tap the download icon on the top-right of your GCash receipt screen to save the screenshot to your phone.
                                                            </p>
                                                        </div>

                                                        <!-- Phone Step 3: Proof Verification -->
                                                        <div x-show="gcashState[{{ $spId }}].manualStep === 3" x-cloak class="space-y-2.5">
                                                            <span class="text-[10px] font-extrabold uppercase text-blue-700 block">Step 3: Review Attached Proof</span>
                                                            <div class="p-3 rounded-2xl bg-white border border-slate-200 space-y-2 text-[11px]">
                                                                <div class="flex justify-between">
                                                                    <span class="text-slate-500">Sender Number:</span>
                                                                    <strong class="font-mono text-slate-900" x-text="gcashState[{{ $spId }}].senderNumber || '09XX XXX XXXX'"></strong>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-slate-500">Reference No:</span>
                                                                    <strong class="font-mono text-blue-700" x-text="gcashState[{{ $spId }}].referenceNumber || 'Not entered'"></strong>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-slate-500">Reference Code:</span>
                                                                    <strong class="font-mono text-slate-900" x-text="gcashState[{{ $spId }}].referenceCode || 'Not entered'"></strong>
                                                                </div>
                                                                <template x-if="gcashState[{{ $spId }}].previewUrl">
                                                                    <div class="pt-2 border-t border-slate-100 text-center">
                                                                        <img :src="gcashState[{{ $spId }}].previewUrl" alt="Receipt Preview" class="max-h-32 mx-auto rounded-lg border border-slate-200 object-contain">
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>

                                                        <!-- Bottom Phone Navigation Bar -->
                                                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                                                            <button type="button"
                                                                    @click="if (gcashState[{{ $spId }}].manualStep > 1) gcashState[{{ $spId }}].manualStep--"
                                                                    :disabled="gcashState[{{ $spId }}].manualStep <= 1"
                                                                    class="text-[10px] font-bold text-slate-600 disabled:opacity-30 cursor-pointer">
                                                                &larr; Prev Guide Step
                                                            </button>
                                                            <div class="w-16 h-1 rounded-full bg-slate-300"></div>
                                                            <button type="button"
                                                                    @click="if (gcashState[{{ $spId }}].manualStep < 3) { gcashState[{{ $spId }}].manualStep++; if (gcashState[{{ $spId }}].manualStep === 3) gcashState[{{ $spId }}].phase = 2; }"
                                                                    :disabled="gcashState[{{ $spId }}].manualStep >= 3"
                                                                    class="text-[10px] font-bold text-blue-600 disabled:opacity-30 cursor-pointer">
                                                                Next Step &rarr;
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- RIGHT COLUMN: Payment Details & Proof Submission Form -->
                                            <div class="lg:col-span-7 space-y-4 text-xs">
                                                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <span class="text-[10px] font-extrabold uppercase text-blue-800 block">Storkia Admin GCash Escrow</span>
                                                        <div class="text-sm font-bold text-blue-950">{{ $escrowAccountName }} &bull; <span class="font-mono">{{ $escrowGcashNumber }}</span></div>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="text-[10px] font-bold text-blue-700 block">Exact Amount to Send</span>
                                                        <strong class="text-lg font-serif font-extrabold text-blue-950">₱{{ number_format($pkg['total'], 2) }}</strong>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                    <div>
                                                        <label class="block font-bold text-text-muted uppercase mb-1">
                                                            Your Sender GCash Number (11 Digits) <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text"
                                                               name="gcash_sender_number[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].senderNumber"
                                                               @input="sanitizeSenderPhone({{ $spId }})"
                                                               maxlength="11"
                                                               placeholder="09171234567"
                                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-sm outline-none focus:border-blue-600">
                                                    </div>

                                                    <div>
                                                        <label class="block font-bold text-text-muted uppercase mb-1">
                                                            Date &amp; Time Paid (Asia/Manila) <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="datetime-local"
                                                               name="gcash_paid_at[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].paidAt"
                                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold outline-none focus:border-blue-600">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                    <div>
                                                        <label class="block font-bold text-text-muted uppercase mb-1">
                                                            Unique GCash Reference No. <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text"
                                                               name="gcash_reference_number[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].referenceNumber"
                                                               maxlength="80"
                                                               placeholder="e.g. 9012345678901"
                                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold outline-none focus:border-blue-600">
                                                    </div>

                                                    <div>
                                                        <label class="block font-bold text-text-muted uppercase mb-1">
                                                            Reference Code <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text"
                                                               name="gcash_reference_code[{{ $spId }}]"
                                                               x-model="gcashState[{{ $spId }}].referenceCode"
                                                               maxlength="80"
                                                               placeholder="e.g. GC-8841"
                                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold outline-none focus:border-blue-600">
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block font-bold text-text-muted uppercase mb-1">
                                                        Upload GCash Receipt Screenshot (JPG / PNG / WEBP — Max 5MB) <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="file"
                                                           name="gcash_screenshot[{{ $spId }}]"
                                                           accept="image/jpeg,image/png,image/jpg,image/webp"
                                                           @change="onGcashScreenshotSelect({{ $spId }}, $event)"
                                                           class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white file:font-bold cursor-pointer">
                                                </div>

                                                <template x-if="gcashState[{{ $spId }}].error">
                                                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-danger font-bold" x-text="gcashState[{{ $spId }}].error"></div>
                                                </template>

                                                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-border-subtle">
                                                    <button type="button"
                                                            @click="selectCod({{ $spId }})"
                                                            class="px-4 py-2.5 rounded-xl bg-surface-subtle hover:bg-red-50 text-text-muted hover:text-danger font-bold cursor-pointer">
                                                        Switch Package to COD Instead
                                                    </button>

                                                    <button type="button"
                                                            @click="saveGcashModal({{ $spId }})"
                                                            class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm cursor-pointer">
                                                        Confirm &amp; Attach GCash Proof
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Platform & Free Shipping Vouchers -->
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

                <!-- Checkout Summary Sidebar -->
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
                                    <span>Store Voucher / Bundle Discount:</span>
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

        <!-- Change Delivery Address Modal -->
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
                                     class="p-4 rounded-2xl border-2 cursor-pointer transition-all {{ ($selectedAddress && $selectedAddress->id === $addr->id) ? 'border-primary bg-primary/5' : 'border-border-subtle hover:border-primary/40 bg-surface' }}">
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