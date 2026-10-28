@extends('layouts.seller', ['title' => 'Storkia - Order ' . $sellerOrder->seller_order_number])

@section('content')
@php
    $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
    $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
    $shipment = $sellerOrder->shipment;
    $packages = $shipment?->packages ?? collect();
    $primaryPackage = $packages->first();
    $latestProof = $shipment?->deliveryProofs?->first();
    $payMethod = $sellerOrder->effective_payment_method;
    $paymentSubmission = $sellerOrder->paymentSubmission;
    $extra = $sellerOrder->extra;
    $evidencePhotos = is_array($extra?->dispute_evidence_photos) ? $extra->dispute_evidence_photos : [];
@endphp

<div x-data="{
        rejectModalOpen: false,
        suspiciousModalOpen: false,
        gcashScreenshotModalOpen: false,
        proofModalOpen: false,
        evidencePreviewUrl: '',
        evidenceModalOpen: false,
        handoverMethod: 'courier_pickup',
        manifestConfirmed: false,
        returnDecision: 'approved',
        openEvidenceModal(url) {
            this.evidencePreviewUrl = url;
            this.evidenceModalOpen = true;
        }
     }"
     class="max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('seller.orders.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono">{{ $sellerOrder->seller_order_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $sellerOrder->status === 'pending_buyer_response' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-brand-light/50 text-primary-dark border border-border-subtle' }}">
                        {{ $sellerOrder->status_label }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                        {{ $payMethod }} ({{ strtoupper($sellerOrder->payment_status) }})
                    </span>
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Parent Order: <strong>{{ $sellerOrder->order->order_number ?? 'N/A' }}</strong> &bull;
                    Ordered: <strong>{{ $sellerOrder->created_at?->format('M d, Y h:i A') }}</strong> &bull;
                    Order Total: <strong class="text-primary">₱{{ number_format((float) $sellerOrder->total_amount, 2) }}</strong>
                </p>
            </div>
        </div>

        @if($shipment)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('seller.orders.shipping-label', ['sellerOrder' => $sellerOrder->id, 'print' => 1]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
                    Print Shipping Label ({{ $shipment->tracking_number }})
                </a>
                <a href="{{ route('seller.orders.packing-slip', ['sellerOrder' => $sellerOrder->id, 'print' => 1]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-colors">
                    Print Packing Slip / Manifest
                </a>
            </div>
        @endif
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

    @if($sellerOrder->status === 'pending_buyer_response')
        <div class="p-6 rounded-3xl bg-amber-50 border-2 border-amber-300 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-bold text-amber-950">Order Paused in Pending Queue — Waiting for Buyer Response</h2>
                <span class="px-3 py-1 rounded-full bg-amber-200 text-amber-950 text-xs font-extrabold uppercase">Pending Buyer Action</span>
            </div>
            <p class="text-xs text-amber-900 leading-relaxed">
                You reported the Buyer's GCash payment submission as suspicious on
                <strong>{{ $paymentSubmission?->suspicious_reported_at?->format('M d, Y h:i A') }}</strong>
                (Reason: <em>"{{ $paymentSubmission?->suspicious_reason }}"</em>).
                Order fulfillment is paused until the Buyer responds in their portal by either switching the payment method to <strong>Cash on Delivery (COD)</strong> or <strong>Cancelling the Order</strong>.
            </p>
        </div>
    @endif

    @if($paymentSubmission)
        <div class="p-6 rounded-3xl bg-blue-50/70 border-2 border-blue-200 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-blue-200">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-blue-950">Buyer GCash Online Payment Submission</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $paymentSubmission->status === 'verified' ? 'bg-green-100 text-green-800' : ($paymentSubmission->is_suspicious ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ str_replace('_', ' ', $paymentSubmission->status) }}
                        </span>
                    </div>
                    <p class="text-xs text-blue-800 mt-0.5">
                        Review the sender mobile number, payment timestamp, unique reference number, and WebP screenshot below.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <button type="button"
                            @click.stop="gcashScreenshotModalOpen = true"
                            class="px-4 py-2 rounded-xl bg-white border border-blue-300 hover:bg-blue-100 text-blue-900 text-xs font-bold transition-colors cursor-pointer">
                        View Payment Screenshot (WebP)
                    </button>

                    @if(!$paymentSubmission->switched_to_cod && in_array($sellerOrder->status, ['pending_seller_acceptance', 'accepted', 'preparing'], true))
                        @if($paymentSubmission->status !== 'verified')
                            <form action="{{ route('seller.orders.verify-online-payment', $sellerOrder->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                    Verify Payment
                                </button>
                            </form>
                        @endif

                        <button type="button"
                                @click.stop="suspiciousModalOpen = true"
                                class="px-4 py-2 rounded-xl bg-danger hover:opacity-95 text-white text-xs font-bold shadow-xs transition-opacity cursor-pointer">
                            Report Suspicious Payment
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <div class="p-3.5 rounded-2xl bg-white border border-blue-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Sender GCash Number</span>
                    <p class="font-mono font-bold text-sm text-text-main mt-0.5">{{ $paymentSubmission->sender_gcash_number }}</p>
                    <span class="text-[11px] text-text-muted">To: {{ $paymentSubmission->seller_gcash_name }} ({{ $paymentSubmission->seller_gcash_number }})</span>
                </div>

                <div class="p-3.5 rounded-2xl bg-white border border-blue-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Unique Reference Number</span>
                    <p class="font-mono font-bold text-sm text-primary-dark mt-0.5">{{ $paymentSubmission->reference_number }}</p>
                    <span class="text-[11px] text-text-muted">Ref Code: <strong class="font-mono">{{ $paymentSubmission->reference_code }}</strong></span>
                </div>

                <div class="p-3.5 rounded-2xl bg-white border border-blue-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Date & Time Paid</span>
                    <p class="font-bold text-sm text-text-main mt-0.5">{{ $paymentSubmission->paid_at?->format('M d, Y h:i A') }}</p>
                    <span class="text-[11px] text-text-muted">Submitted {{ $paymentSubmission->created_at?->diffForHumans() }}</span>
                </div>

                <div class="p-3.5 rounded-2xl bg-white border border-blue-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Submitted Amount</span>
                    <p class="font-serif font-extrabold text-base text-primary mt-0.5">₱{{ number_format((float) $paymentSubmission->submitted_amount, 2) }}</p>
                    @if($paymentSubmission->switched_to_cod)
                        <span class="text-[11px] font-bold text-amber-800">Switched to COD on {{ $paymentSubmission->switched_to_cod_at?->format('M d, h:i A') }}</span>
                    @elseif($paymentSubmission->seller_verified_at)
                        <span class="text-[11px] font-bold text-green-700">Verified {{ $paymentSubmission->seller_verified_at->format('M d, h:i A') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($sellerOrder->status === 'pending_seller_acceptance')
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Step 1: Accept or Reject Incoming Order (24-Hour SLA)</h2>
                <p class="text-xs text-text-muted mt-1">
                    Please accept this order before <strong>{{ $sellerOrder->acceptance_deadline_at?->format('M d, Y h:i A') }}</strong>
                    ({{ $sellerOrder->acceptance_deadline_at?->diffForHumans() }}). Unaccepted orders auto-cancel and restore reserved stock.
                </p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
                <button type="button"
                        @click.stop="rejectModalOpen = true"
                        class="px-4 py-2.5 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-danger text-xs font-bold transition-colors cursor-pointer">
                    Reject Order
                </button>
                <form action="{{ route('seller.orders.accept', $sellerOrder->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                        Accept Order & Start Preparing
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if(in_array($sellerOrder->status, ['accepted', 'preparing'], true))
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-5">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Step 2: Verify Product Checklist & Select Logistics Handover Method</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Verify all ordered items below, choose between <strong>First-Mile Courier Pickup</strong> or <strong>Seller Self Drop-Off at Hub</strong>, and generate your Shipping Label.
                </p>
            </div>

            <form action="{{ route('seller.orders.ready-for-pickup', $sellerOrder->id) }}" method="POST" class="space-y-5 text-xs">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label @click="handoverMethod = 'courier_pickup'"
                           :class="handoverMethod === 'courier_pickup' ? 'border-primary bg-primary/5 shadow-xs' : 'border-border-subtle bg-surface-subtle/50'"
                           class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 transition-all">
                        <input type="radio" name="handover_method" value="courier_pickup" x-model="handoverMethod" class="mt-0.5 text-primary">
                        <div>
                            <span class="font-bold text-sm text-primary-dark block">Option A: Request First-Mile Courier Pickup</span>
                            <span class="text-text-muted mt-0.5 block leading-relaxed">
                                The selected Logistics hub will dispatch an active Courier covering <strong>{{ $pickupSnap['municipality'] ?? 'your municipality' }}</strong> to scan the QR label and collect the package from your facility.
                            </span>
                        </div>
                    </label>

                    <label @click="handoverMethod = 'self_drop_off'"
                           :class="handoverMethod === 'self_drop_off' ? 'border-purple-700 bg-purple-50/60 shadow-xs' : 'border-border-subtle bg-surface-subtle/50'"
                           class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 transition-all">
                        <input type="radio" name="handover_method" value="self_drop_off" x-model="handoverMethod" class="mt-0.5 text-purple-700">
                        <div>
                            <span class="font-bold text-sm text-purple-950 block">Option B: Seller Self Drop-Off at Logistics Hub</span>
                            <span class="text-text-muted mt-0.5 block leading-relaxed">
                                Print and attach the QR Shipping Label yourself and bring the package directly to the selected Logistics sorting hub for immediate intake verification.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Select Approved Local Logistics Hub <span class="text-danger">*</span></label>
                        <select name="logistics_hub_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold text-xs outline-none focus:border-primary">
                            @foreach($eligibleHubs as $hub)
                                <option value="{{ $hub->id }}">
                                    {{ $hub->hub_name }} — {{ $hub->municipality }}, {{ $hub->province }} ({{ $hub->parentLogisticsProfile?->business_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Total Packages Count</label>
                        <input type="number" name="package_count" min="1" max="10" value="1" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold text-xs">
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold uppercase tracking-wider text-[11px] text-primary-dark">Mandatory Packing Manifest Checklist</span>
                        <span class="text-[11px] text-text-muted">{{ $sellerOrder->items->sum('quantity') }} Total Unit(s) &bull; {{ number_format((float) $sellerOrder->total_weight, 2) }} kg</span>
                    </div>

                    <div class="overflow-x-auto bg-surface rounded-xl border border-border-subtle">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle text-[10px] uppercase font-bold text-text-muted">
                                    <th class="py-2.5 px-3">Product</th>
                                    <th class="py-2.5 px-3">Variant</th>
                                    <th class="py-2.5 px-3 text-right">Quantity</th>
                                    <th class="py-2.5 px-3 text-right">Weight</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                @foreach($sellerOrder->items as $item)
                                    <tr>
                                        <td class="py-2.5 px-3 font-bold text-text-main">{{ $item->product_name }}</td>
                                        <td class="py-2.5 px-3 text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) ?: 'Standard' }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-dark">&times; {{ $item->quantity }}</td>
                                        <td class="py-2.5 px-3 text-right text-text-muted">{{ number_format((float) $item->line_weight, 2) }} kg</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <label class="flex items-center gap-2.5 pt-1 cursor-pointer">
                        <input type="checkbox" name="manifest_confirmed" value="1" x-model="manifestConfirmed" required class="w-4 h-4 rounded text-primary focus:ring-primary">
                        <span class="font-bold text-text-main">
                            I confirm that all ordered items above have been inspected, packed, and match the packing manifest.
                        </span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="!manifestConfirmed"
                            :class="manifestConfirmed ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold shadow-sm transition-colors">
                        Confirm Package Ready & Generate QR Shipping Label
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($sellerOrder->status === 'disputed')
        <div class="p-6 rounded-3xl bg-red-50 border-2 border-red-200 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-red-950">Buyer Return / Delivery Dispute Request</h2>
                    <p class="text-xs text-red-800 mt-0.5">
                        Reported on {{ $sellerOrder->dispute_reported_at?->format('M d, Y h:i A') }}
                        @if($extra?->dispute_reason_category)
                            &bull; Category: <strong>{{ $extra->dispute_reason_category }}</strong>
                        @endif
                    </p>
                </div>
                @if($extra?->is_escalated_to_admin)
                    <span class="px-3 py-1 rounded-full bg-red-200 text-red-950 text-xs font-extrabold uppercase">Escalated to Admin</span>
                @elseif($extra?->seller_return_decision)
                    <span class="px-3 py-1 rounded-full bg-white border border-red-300 text-red-900 text-xs font-extrabold uppercase">
                        Seller Decision: {{ ucfirst($extra->seller_return_decision) }}
                    </span>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-white border border-red-200 space-y-3 text-xs">
                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Explanation</span>
                    <p class="text-sm font-semibold text-text-main mt-0.5">"{{ $sellerOrder->dispute_reason }}"</p>
                </div>

                @if(!empty($evidencePhotos))
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block mb-2">Uploaded Evidence Photos ({{ count($evidencePhotos) }})</span>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($evidencePhotos as $photoPath)
                                <button type="button"
                                        @click.stop="openEvidenceModal('{{ asset('storage/' . $photoPath) }}')"
                                        class="w-20 h-20 rounded-xl overflow-hidden border border-border-subtle bg-surface-subtle hover:border-primary transition-all cursor-pointer p-1">
                                    <img src="{{ asset('storage/' . $photoPath) }}" alt="Dispute Evidence" class="w-full h-full object-cover rounded-lg">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if(!$extra?->seller_return_decision && !$extra?->is_escalated_to_admin)
                <form action="{{ route('seller.orders.respond-return', $sellerOrder->id) }}" method="POST" class="p-4 rounded-2xl bg-white border border-red-200 space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="p-3 rounded-xl border cursor-pointer flex items-center gap-2 font-bold"
                               :class="returnDecision === 'approved' ? 'border-green-600 bg-green-50 text-green-800' : 'border-border-subtle text-text-muted'">
                            <input type="radio" name="decision" value="approved" x-model="returnDecision">
                            <span>Approve Return & Refund (Restores Stock)</span>
                        </label>
                        <label class="p-3 rounded-xl border cursor-pointer flex items-center gap-2 font-bold"
                               :class="returnDecision === 'rejected' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle text-text-muted'">
                            <input type="radio" name="decision" value="rejected" x-model="returnDecision">
                            <span>Reject Return Request (Requires 10–300 Char Reason)</span>
                        </label>
                    </div>

                    <div x-show="returnDecision === 'rejected'" x-cloak>
                        <label class="block font-bold text-text-muted mb-1">Rejection Reason (10–300 characters) <span class="text-danger">*</span></label>
                        <textarea name="reason"
                                  rows="3"
                                  minlength="10"
                                  maxlength="300"
                                  :required="returnDecision === 'rejected'"
                                  placeholder="Explain clearly why the return request is declined..."
                                  class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer">
                            Submit Return Decision
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-surface rounded-3xl border border-border-subtle overflow-hidden shadow-xs">
                <div class="p-5 bg-surface-subtle/60 border-b border-border-subtle flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">Ordered Items (FIFO Checkout Order)</h3>
                    <span class="text-xs text-text-muted">{{ $sellerOrder->items->sum('quantity') }} item(s)</span>
                </div>

                <div class="divide-y divide-border-subtle/60">
                    @foreach($sellerOrder->items as $item)
                        <div class="p-5 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-14 h-14 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                    @if($item->product_image)
                                        <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                                    @endif
                                    <p class="text-xs text-text-muted">₱{{ number_format((float) $item->discounted_unit_price, 2) }} &times; {{ $item->quantity }}</p>
                                </div>
                            </div>
                            <div class="text-sm font-bold text-text-main shrink-0">
                                ₱{{ number_format((float) $item->line_total, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($extra && $extra->buyer_note)
                    <div class="px-5 py-3.5 bg-brand-light/30 border-t border-border-subtle text-xs">
                        <span class="font-bold uppercase tracking-wider text-primary-dark">Buyer Note to Seller:</span>
                        <p class="text-text-main font-semibold italic mt-0.5">"{{ $extra->buyer_note }}"</p>
                    </div>
                @endif

                <div class="p-5 bg-surface-subtle/40 border-t border-border-subtle space-y-1.5 text-xs sm:text-sm">
                    @if($extra && (float) $extra->original_subtotal_amount > 0 && (float) $extra->original_subtotal_amount !== (float) $sellerOrder->subtotal_amount)
                        <div class="flex justify-between text-text-muted">
                            <span>Original Merchandise Subtotal:</span>
                            <span class="font-semibold text-text-main">₱{{ number_format((float) $extra->original_subtotal_amount, 2) }}</span>
                        </div>
                    @endif
                    @if($extra && (float) $extra->store_voucher_discount > 0)
                        <div class="flex justify-between text-green-700 font-bold">
                            <span>Store Voucher ({{ $extra->store_voucher_code }}):</span>
                            <span>-₱{{ number_format((float) $extra->store_voucher_discount, 2) }}</span>
                        </div>
                    @endif
                    @if($extra && (float) $extra->platform_voucher_discount > 0)
                        <div class="flex justify-between text-purple-700 font-bold">
                            <span>Platform Voucher Share ({{ $extra->platform_voucher_code }}):</span>
                            <span>-₱{{ number_format((float) $extra->platform_voucher_discount, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-text-muted">
                        <span>Net Product Subtotal:</span>
                        <span class="font-semibold text-text-main">₱{{ number_format((float) $sellerOrder->subtotal_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-text-muted">
                        <span>Shipping Fee ({{ number_format((float) $sellerOrder->total_weight, 2) }} kg):</span>
                        <span class="font-semibold text-text-main">
                            ₱{{ number_format((float) $sellerOrder->shipping_fee, 2) }}
                            @if($extra && (float) $extra->shipping_voucher_discount > 0)
                                <span class="text-emerald-700 font-bold">(-₱{{ number_format((float) $extra->shipping_voucher_discount, 2) }} {{ $extra->shipping_voucher_code }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-border-subtle text-base font-extrabold text-primary-dark">
                        <span>Total Order Amount ({{ $payMethod }}):</span>
                        <span class="text-primary">₱{{ number_format((float) $sellerOrder->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            @if($shipment && $shipment->trackingLogs->isNotEmpty())
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                    <h3 class="text-sm font-bold text-primary-dark">Shipment Tracking Timeline ({{ $shipment->tracking_number }})</h3>
                    <div class="relative pl-6 border-l-2 border-primary/30 space-y-5">
                        @foreach($shipment->trackingLogs as $idx => $log)
                            <div class="relative">
                                <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-gray-300' }}"></span>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <span class="text-xs sm:text-sm font-bold text-text-main">{{ $log->title }}</span>
                                    <span class="text-[11px] text-text-muted">{{ $log->occurred_at?->format('M d, Y h:i A') }}</span>
                                </div>
                                <p class="text-xs text-text-muted mt-0.5">{{ $log->description }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        <div class="space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Buyer Delivery Snapshot</h3>
                <p class="text-sm font-bold text-text-main">{{ $deliverySnap['recipient_name'] ?? 'Buyer' }}</p>
                <p class="text-text-muted">{{ $deliverySnap['phone_number'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed">{{ $deliverySnap['full_address'] ?? '' }}</p>
            </div>

            @if($latestProof)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="font-bold uppercase tracking-wider text-green-700">Rider Proof of Delivery</h3>
                    <p class="text-text-muted">Captured: {{ $latestProof->captured_at?->format('M d, Y h:i A') }}</p>
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="w-full py-2.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                        View Delivery Photo Proof
                    </button>
                </div>
            @endif
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="rejectModalOpen"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="rejectModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-danger">Reject / Cancel Order</h3>
                    <button type="button" @click="rejectModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <form action="{{ route('seller.orders.reject', $sellerOrder->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted mb-1">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" required minlength="5" placeholder="e.g. Out of stock / damaged inventory..." class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="rejectModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-danger text-white font-bold cursor-pointer">Confirm Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    @if($paymentSubmission)
        <template x-teleport="body">
            <div x-show="suspiciousModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[190] flex items-center justify-center p-4">
                <div @click="suspiciousModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-base font-bold text-danger">Report Suspicious GCash Payment</h3>
                        <button type="button" @click="suspiciousModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>
                    <p class="text-xs text-text-muted leading-relaxed">
                        Reporting this payment moves the order to the <strong>Pending</strong> tab and notifies the Buyer. The Buyer can choose to continue by switching to <strong>Cash on Delivery (COD)</strong> or cancel the order immediately.
                    </p>
                    <form action="{{ route('seller.orders.report-suspicious-payment', $sellerOrder->id) }}" method="POST" class="space-y-4 text-xs">
                        @csrf
                        <div>
                            <label class="block font-bold text-text-muted mb-1">Explain Why Payment Appears Suspicious <span class="text-danger">*</span></label>
                            <textarea name="suspicious_reason"
                                      rows="3"
                                      required
                                      minlength="5"
                                      placeholder="e.g. Reference number #{{ $paymentSubmission->reference_number }} not found in our GCash transaction history or screenshot amount does not match..."
                                      class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="suspiciousModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-danger text-white font-bold cursor-pointer">Send Report to Buyer & Pause Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div x-show="gcashScreenshotModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4">
                <div @click="gcashScreenshotModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Buyer GCash Screenshot (WebP)</h3>
                            <p class="text-xs text-text-muted">Ref #{{ $paymentSubmission->reference_number }} &bull; Code: {{ $paymentSubmission->reference_code }}</p>
                        </div>
                        <button type="button" @click="gcashScreenshotModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>
                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                        <img src="{{ $paymentSubmission->screenshot_url }}" alt="GCash Payment Screenshot" class="max-h-[60vh] w-auto object-contain">
                    </div>
                </div>
            </div>
        </template>
    @endif

    <template x-teleport="body">
        <div x-show="evidenceModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="evidenceModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Buyer Dispute Evidence Photo</h3>
                    <button type="button" @click="evidenceModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                    <img :src="evidencePreviewUrl" alt="Evidence Photo" class="max-h-[60vh] w-auto object-contain">
                </div>
            </div>
        </div>
    </template>

    @if($latestProof)
        <template x-teleport="body">
            <div x-show="proofModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4">
                <div @click="proofModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-base font-bold text-primary-dark">Rider Delivery Photo Proof</h3>
                        <button type="button" @click="proofModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>
                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                        <img src="{{ route('user.orders.proof-image', $latestProof->id) }}" alt="Proof of Delivery" class="max-h-[60vh] w-auto object-contain">
                    </div>
                </div>
            </div>
        </template>
    @endif

</div>
@endsection