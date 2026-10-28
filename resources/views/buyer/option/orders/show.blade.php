@extends('buyer.account-settings', [
    'pageTitle' => 'Order #' . $sellerOrder->seller_order_number,
    'pageSubtitle' => 'Detailed shipment tracking, payment proof, cancellation controls, and order timeline.'
])

@section('option-content')
@php
    $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
    $shipment = $sellerOrder->shipment;
    $packages = $shipment?->packages ?? collect();
    $latestProof = $shipment?->deliveryProofs?->first();
    $payMethod = $sellerOrder->effective_payment_method;
    $paymentSubmission = $sellerOrder->paymentSubmission;
    $extra = $sellerOrder->extra;
    $evidencePhotos = is_array($extra?->dispute_evidence_photos) ? $extra->dispute_evidence_photos : [];
    $canBuyerCancel = $sellerOrder->canBuyerCancelPreShipment();
@endphp

<div x-data="{
        disputeModalOpen: false,
        cancelModalOpen: false,
        proofModalOpen: false,
        gcashScreenshotModalOpen: false,
        evidencePreviewUrl: '',
        evidenceModalOpen: false,
        isSubmittingCancel: false,
        isSubmittingDispute: false,
        disputeFilesError: '',
        selectedEvidenceNames: [],
        validateEvidenceFiles(event) {
            this.disputeFilesError = '';
            this.selectedEvidenceNames = [];
            const files = Array.from(event.target.files || []);
            if (files.length < 1 || files.length > 5) {
                this.disputeFilesError = 'Please select between 1 and 5 evidence photos.';
                event.target.value = '';
                return;
            }
            for (const f of files) {
                if (f.size > 5 * 1024 * 1024) {
                    this.disputeFilesError = `File \"${f.name}\" exceeds the 5MB limit.`;
                    event.target.value = '';
                    this.selectedEvidenceNames = [];
                    return;
                }
                this.selectedEvidenceNames.push(f.name);
            }
        }
     }"
     class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('user.orders') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span>Back to My Orders</span>
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                {{ $payMethod }} ({{ strtoupper($sellerOrder->payment_status) }})
            </span>
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $sellerOrder->status === 'pending_buyer_response' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-brand-light/50 text-primary-dark border border-border-subtle' }}">
                {{ $sellerOrder->status_label }}
            </span>
        </div>
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
        <div class="p-6 rounded-3xl bg-amber-50 border-2 border-amber-300 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-amber-200">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-200 text-amber-950 text-[10px] font-extrabold uppercase">
                        Action Required — Seller Flagged Online Payment
                    </span>
                    <h3 class="text-base font-bold text-amber-950 mt-1">
                        Do You Want to Continue This Transaction via Cash on Delivery (COD)?
                    </h3>
                </div>
                @if($paymentSubmission)
                    <button type="button"
                            @click.stop="gcashScreenshotModalOpen = true"
                            class="px-3.5 py-2 rounded-xl bg-white border border-amber-300 text-amber-950 text-xs font-bold cursor-pointer">
                        View Previous Payment Screenshot
                    </button>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-white border border-amber-200 space-y-2 text-xs">
                <p class="text-amber-950 font-semibold">
                    Seller Report Reason: <em class="text-danger">"{{ $paymentSubmission?->suspicious_reason ?? 'Submitted GCash payment details could not be verified.' }}"</em>
                </p>
                @if($paymentSubmission)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-amber-100 text-[11px]">
                        <div>
                            <span class="text-text-muted">Previous Sender GCash #:</span>
                            <strong class="font-mono text-text-main block">{{ $paymentSubmission->sender_gcash_number }}</strong>
                        </div>
                        <div>
                            <span class="text-text-muted">Previous Reference # / Code:</span>
                            <strong class="font-mono text-primary-dark block">{{ $paymentSubmission->reference_number }} ({{ $paymentSubmission->reference_code }})</strong>
                        </div>
                        <div>
                            <span class="text-text-muted">Date & Time Submitted:</span>
                            <strong class="text-text-main block">{{ $paymentSubmission->paid_at?->format('M d, Y h:i A') }}</strong>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                <p class="text-xs text-amber-900 font-semibold leading-relaxed">
                    Continuing this transaction will switch the payment method to <strong>Cash on Delivery (COD — ₱{{ number_format((float) $sellerOrder->total_amount, 2) }})</strong> so the Seller can accept and ship your order. Or you may cancel the order immediately.
                </p>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <form action="{{ route('user.orders.switch-to-cod', $sellerOrder->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            Yes, Switch to COD & Continue
                        </button>
                    </form>

                    <form action="{{ route('user.orders.cancel-flagged-payment', $sellerOrder->id) }}"
                          method="POST"
                          onsubmit="return confirm('Cancel this order immediately? Reserved stock and any applied vouchers will be restored.');"
                          class="m-0">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-white border border-red-300 hover:bg-red-50 text-danger text-xs font-bold transition-colors cursor-pointer">
                            Cancel Order Immediately
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($canBuyerCancel && $sellerOrder->status !== 'pending_buyer_response')
        <div class="p-4 sm:px-5 rounded-2xl bg-surface border border-border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <span class="font-bold text-text-main">Need to cancel this order before shipment?</span>
                <p class="text-text-muted mt-0.5">
                    Orders can be cancelled before the Seller marks the package Ready for Pickup.
                    (Remaining 7-day buyer cancellation quota: <strong>{{ $cancellationQuota['remaining_buyer'] }} of {{ $cancellationQuota['limit_by_buyer'] }}</strong>)
                </p>
            </div>
            <button type="button"
                    @click.stop="cancelModalOpen = true"
                    class="px-4 py-2.5 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-danger font-bold transition-colors cursor-pointer shrink-0 self-start sm:self-auto">
                Cancel Order
            </button>
        </div>
    @endif

    @if($sellerOrder->status === 'delivered' && !$sellerOrder->has_unresolved_dispute)
        <div class="p-5 rounded-2xl bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-blue-900">Package Delivered — Confirm Receipt or Request Return</h3>
                <p class="text-xs text-blue-800 mt-1 leading-relaxed">
                    Please inspect your items. If you don't confirm or report a problem by
                    <strong>{{ $sellerOrder->confirmation_deadline_at?->format('M d, Y h:i A') }}</strong>
                    ({{ $sellerOrder->confirmation_deadline_at?->diffForHumans() }}),
                    this order will automatically complete.
                </p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
                <button type="button"
                        @click.stop="disputeModalOpen = true"
                        class="px-4 py-2.5 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-red-600 text-xs font-bold transition-colors cursor-pointer">
                    Request Return / Report Problem
                </button>
                <form action="{{ route('user.orders.confirm-receipt', $sellerOrder->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        Confirm Order Received
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($sellerOrder->has_unresolved_dispute || $sellerOrder->status === 'disputed' || $extra?->seller_return_decision)
        <div class="p-5 rounded-2xl bg-red-50 border border-red-200 space-y-3 text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-red-900">Return / Delivery Dispute Status</h3>
                @if($extra?->is_escalated_to_admin)
                    <span class="px-2.5 py-0.5 rounded-full bg-red-200 text-red-950 font-extrabold uppercase text-[10px]">Escalated to Storkia Admin</span>
                @elseif($extra?->seller_return_decision)
                    <span class="px-2.5 py-0.5 rounded-full bg-white border border-red-300 text-red-900 font-extrabold uppercase text-[10px]">
                        Seller Decision: {{ ucfirst($extra->seller_return_decision) }}
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-extrabold uppercase text-[10px]">Awaiting Seller Review</span>
                @endif
            </div>

            <p class="text-red-800">
                Reported on {{ $sellerOrder->dispute_reported_at?->format('M d, Y h:i A') }}:
                <em>"{{ $sellerOrder->dispute_reason }}"</em>
            </p>

            @if(!empty($evidencePhotos))
                <div class="pt-1">
                    <span class="text-[10px] font-bold uppercase text-red-900 block mb-1.5">Submitted Photo Evidence ({{ count($evidencePhotos) }})</span>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($evidencePhotos as $photoPath)
                            <button type="button"
                                    @click.stop="evidencePreviewUrl = '{{ asset('storage/' . $photoPath) }}'; evidenceModalOpen = true"
                                    class="w-16 h-16 rounded-xl overflow-hidden border border-red-200 bg-white p-0.5 cursor-pointer">
                                <img src="{{ asset('storage/' . $photoPath) }}" alt="Dispute Evidence" class="w-full h-full object-cover rounded-lg">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($extra?->seller_return_decision === 'rejected')
                <div class="p-3.5 rounded-xl bg-white border border-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="font-bold text-danger block">Seller Declined Return Request:</span>
                        <p class="text-text-main mt-0.5">"{{ $extra->seller_return_Decision_reason }}"</p>
                    </div>
                    @if(!$extra->is_escalated_to_admin)
                        <form action="{{ route('user.orders.escalate-dispute', $sellerOrder->id) }}" method="POST" class="m-0 shrink-0">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-danger text-white font-bold cursor-pointer">
                                Escalate to Admin
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if(in_array($sellerOrder->status, ['rejected', 'cancelled'], true) && $sellerOrder->rejection_reason)
        <div class="p-5 rounded-2xl bg-red-50 border border-red-200">
            <h3 class="text-sm font-bold text-red-900">Order {{ $sellerOrder->status === 'rejected' ? 'Rejected by Seller' : 'Cancelled' }}</h3>
            <p class="text-xs text-red-800 mt-1">Reason: <em>"{{ $sellerOrder->rejection_reason }}"</em></p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Delivery Address Snapshot</h3>
            <p class="text-sm font-bold text-text-main">{{ $deliverySnap['recipient_name'] ?? 'Recipient' }}</p>
            <p class="text-xs text-text-muted">{{ $deliverySnap['phone_number'] ?? '' }}</p>
            <p class="text-xs text-text-main leading-relaxed">{{ $deliverySnap['full_address'] ?? '' }}</p>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Shipment & Payment Info</h3>
            <div class="text-xs space-y-1">
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Seller Store:</span>
                    <span class="font-bold text-text-main text-right">{{ $sellerOrder->sellerProfile->business_name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Logistics Provider:</span>
                    <span class="font-bold text-text-main text-right">{{ $sellerOrder->logisticsProfile->business_name ?? 'Awaiting Seller Selection' }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Tracking Number:</span>
                    <span class="font-mono font-bold text-primary-dark text-right">{{ $shipment->tracking_number ?? 'Not generated yet' }}</span>
                </div>
                @if($packages->isNotEmpty())
                    <div class="flex justify-between gap-2">
                        <span class="text-text-muted">Package Reference(s):</span>
                        <span class="font-mono font-semibold text-text-main text-right">{{ $packages->pluck('package_reference')->implode(', ') }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Payment Method / Status:</span>
                    <span class="font-bold text-text-main uppercase text-right">{{ $payMethod }} ({{ $sellerOrder->payment_status }})</span>
                </div>
                @if($paymentSubmission)
                    <div class="flex justify-between gap-2">
                        <span class="text-text-muted">GCash Ref # / Code:</span>
                        <span class="font-mono font-bold text-blue-800 text-right">{{ $paymentSubmission->reference_number }} ({{ $paymentSubmission->reference_code }})</span>
                    </div>
                @endif
            </div>

            <div class="pt-2 border-t border-border-subtle flex flex-wrap items-center justify-between gap-2">
                @if($paymentSubmission)
                    <button type="button"
                            @click.stop="gcashScreenshotModalOpen = true"
                            class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 hover:bg-blue-600 hover:text-white text-xs font-bold transition-colors cursor-pointer">
                        View GCash Receipt (WebP)
                    </button>
                @endif

                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                        View Delivery Photo Proof
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($shipment && $shipment->trackingLogs->isNotEmpty())
        <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border-subtle space-y-4">
            <h3 class="text-sm font-bold text-primary-dark">Shipment Tracking Timeline</h3>
            <div class="relative pl-6 border-l-2 border-primary/30 space-y-5">
                @foreach($shipment->trackingLogs as $idx => $log)
                    <div class="relative">
                        <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-gray-300' }}"></span>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <h4 class="text-xs sm:text-sm font-bold {{ $idx === 0 ? 'text-primary-dark' : 'text-text-main' }}">
                                {{ $log->title }}
                            </h4>
                            <span class="text-[11px] text-text-muted">{{ $log->occurred_at?->format('M d, Y h:i A') }}</span>
                        </div>
                        @if($log->description)
                            <p class="text-xs text-text-muted mt-0.5">{{ $log->description }}</p>
                        @endif
                        @if($log->location)
                            <p class="text-[11px] text-text-muted/80 mt-0.5">Location: {{ $log->location }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-surface rounded-2xl border border-border-subtle overflow-hidden">
        <div class="p-4 sm:px-5 bg-surface-subtle/60 border-b border-border-subtle">
            <h3 class="text-sm font-bold text-primary-dark">Purchased Items</h3>
        </div>
        <div class="divide-y divide-border-subtle/60">
            @foreach($sellerOrder->items as $item)
                <div class="p-4 sm:px-5 flex items-center justify-between gap-4">
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
            <div class="px-4 sm:px-5 py-3 bg-brand-light/30 border-t border-border-subtle text-xs">
                <span class="font-bold text-primary-dark">Your Note to Seller:</span>
                <span class="text-text-main italic ml-1">"{{ $extra->buyer_note }}"</span>
            </div>
        @endif

        <div class="p-4 sm:px-5 bg-surface-subtle/40 border-t border-border-subtle space-y-1.5 text-xs sm:text-sm">
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
                    <span>Platform Voucher ({{ $extra->platform_voucher_code }}):</span>
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

    <template x-teleport="body">
        <div x-show="cancelModalOpen"
             x-cloak
             class="fixed inset-0 z-[185] flex items-center justify-center p-4">
            <div @click="cancelModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>

            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Cancel Order #{{ $sellerOrder->seller_order_number }}</h3>
                    <button type="button" @click="cancelModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle text-[11px] text-text-muted space-y-1">
                    <span class="font-bold text-primary-dark block">Anti-Sabotage Cancellation Policy (Rolling {{ $cancellationQuota['window_days'] }}-Day Window):</span>
                    <div>&bull; Max <strong>{{ $cancellationQuota['limit_by_product'] }}</strong> cancellations per same product</div>
                    <div>&bull; Max <strong>{{ $cancellationQuota['limit_by_seller'] }}</strong> cancellations per same seller store</div>
                    <div>&bull; Max <strong>{{ $cancellationQuota['limit_by_buyer'] }}</strong> total cancellations ({{ $cancellationQuota['used_by_buyer'] }} used / {{ $cancellationQuota['remaining_buyer'] }} remaining)</div>
                </div>

                <form action="{{ route('user.orders.cancel', $sellerOrder->id) }}"
                      method="POST"
                      @submit="if (isSubmittingCancel) { $event.preventDefault(); } else { isSubmittingCancel = true; }"
                      class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted mb-1">Select Cancellation Reason <span class="text-danger">*</span></label>
                        <select name="cancel_reason" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold outline-none focus:border-primary">
                            <option value="">-- Choose a reason --</option>
                            <option value="Changed my mind">Changed my mind</option>
                            <option value="Need to change delivery address">Need to change delivery address</option>
                            <option value="Forgot to apply voucher code">Forgot to apply voucher code</option>
                            <option value="Ordered wrong item or variation">Ordered wrong item or variation</option>
                            <option value="Found a better price elsewhere">Found a better price elsewhere</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Additional Remarks (Optional)</label>
                        <textarea name="cancel_notes" rows="2" maxlength="500" placeholder="Optional note for the seller..." class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="cancelModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Keep Order
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingCancel"
                                class="px-5 py-2 rounded-xl bg-danger text-white font-bold hover:opacity-90 cursor-pointer disabled:opacity-50">
                            <span x-show="!isSubmittingCancel">Confirm Cancel Order</span>
                            <span x-show="isSubmittingCancel" x-cloak>Cancelling...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="disputeModalOpen"
             x-cloak
             class="fixed inset-0 z-[185] flex items-center justify-center p-4">
            <div @click="disputeModalOpen = false"
                 class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>

            <div @click.stop
                 class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Request Return / Report Delivery Problem</h3>
                    <button type="button" @click="disputeModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('user.orders.report-dispute', $sellerOrder->id) }}"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="if (isSubmittingDispute || disputeFilesError) { $event.preventDefault(); } else { isSubmittingDispute = true; }"
                      class="space-y-4 text-xs">
                    @csrf

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Issue Category <span class="text-danger">*</span></label>
                        <select name="dispute_reason_category" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="Damaged item">Damaged item</option>
                            <option value="Wrong item or variation received">Wrong item or variation received</option>
                            <option value="Missing quantity / incomplete parcel">Missing quantity / incomplete parcel</option>
                            <option value="Defective or not working">Defective or not working</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1.5">Describe the issue (10+ characters) <span class="text-danger">*</span></label>
                        <textarea name="dispute_reason"
                                  rows="3"
                                  required
                                  minlength="10"
                                  maxlength="1000"
                                  placeholder="Explain what went wrong (e.g., Basket arrived with a broken rim)..."
                                  class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1.5">Upload 1 to 5 Evidence Photos (Max 5MB each) <span class="text-danger">*</span></label>
                        <input type="file"
                               name="evidence_photos[]"
                               multiple
                                required
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               @change="validateEvidenceFiles($event)"
                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
                        <template x-if="disputeFilesError">
                            <p class="text-danger font-bold mt-1" x-text="disputeFilesError"></p>
                        </template>
                        <template x-if="selectedEvidenceNames.length > 0">
                            <p class="text-green-700 font-semibold mt-1" x-text="selectedEvidenceNames.length + ' photo(s) selected for WebP upload'"></p>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="disputeModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingDispute || !!disputeFilesError"
                                class="px-5 py-2 rounded-xl bg-danger text-white font-bold hover:opacity-90 cursor-pointer disabled:opacity-50">
                            <span x-show="!isSubmittingDispute">Submit Return / Dispute</span>
                            <span x-show="isSubmittingDispute" x-cloak>Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    @if($paymentSubmission)
        <template x-teleport="body">
            <div x-show="gcashScreenshotModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[190] flex items-center justify-center p-4">
                <div @click="gcashScreenshotModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Submitted GCash Screenshot (WebP)</h3>
                            <p class="text-xs text-text-muted">Ref #{{ $paymentSubmission->reference_number }} &bull; Code: {{ $paymentSubmission->reference_code }}</p>
                        </div>
                        <button type="button" @click="gcashScreenshotModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>
                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                        <img src="{{ $paymentSubmission->screenshot_url }}" alt="GCash Payment Proof" class="max-h-[60vh] w-auto object-contain">
                    </div>
                </div>
            </div>
        </template>
    @endif

    <template x-teleport="body">
        <div x-show="evidenceModalOpen"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="evidenceModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Dispute Evidence Photo</h3>
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
                 class="fixed inset-0 z-[185] flex items-center justify-center p-4">
                <div @click="proofModalOpen = false"
                     class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>

                <div @click.stop
                     class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Proof of Delivery</h3>
                            <p class="text-xs text-text-muted">Captured {{ $latestProof->captured_at?->format('M d, Y h:i A') }} &bull; Recipient: {{ $latestProof->recipient_name ?: 'Buyer' }}</p>
                        </div>
                        <button type="button" @click="proofModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                        <img src="{{ route('user.orders.proof-image', $latestProof->id) }}" alt="Proof of Delivery" class="max-h-[60vh] w-auto object-contain">
                    </div>
                    @if($latestProof->notes)
                        <p class="text-xs text-text-muted">Rider Notes: <em>"{{ $latestProof->notes }}"</em></p>
                    @endif
                </div>
            </div>
        </template>
    @endif

</div>
@endsection