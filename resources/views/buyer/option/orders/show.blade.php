@extends('buyer.account-settings', [
    'pageTitle' => 'Order #' . $sellerOrder->seller_order_number,
    'pageSubtitle' => 'Detailed shipment tracking, payment proof, cancellation controls, and order timeline.'
])

@section('option-content')
@php
    $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
    $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
    $shipment = $sellerOrder->shipment;
    $packages = $shipment?->packages ?? collect();
    $latestProof = $shipment?->latestDeliveryProof
        ?? $shipment?->deliveryProofs?->first()
        ?? \App\Models\DeliveryProof::with('rider')->where('seller_order_id', $sellerOrder->id)->latest('id')->first();
    $payMethod = $sellerOrder->effective_payment_method;
    $paymentSubmission = $sellerOrder->paymentSubmission;
    $extra = $sellerOrder->extra;
    $evidencePhotos = is_array($extra?->dispute_evidence_photos) ? $extra->dispute_evidence_photos : [];
    $canBuyerCancel = $sellerOrder->canBuyerCancelPreShipment();
    $isRated = $sellerOrder->isRatedByBuyer();
    $isIdentityVerified = $isIdentityVerified ?? (auth()->user()?->identityVerification?->status === 'verified');
    $proofImageUrl = $latestProof ? route('user.orders.proof-image', $latestProof->id) : '';

    $stepRank = match($sellerOrder->status) {
        'pending_seller_acceptance', 'pending_buyer_response' => 1,
        'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned' => 2,
        'picked_up', 'at_logistics_hub' => 3,
        'out_for_delivery', 'delivery_failed' => 4,
        'delivered', 'completed' => 5,
        default => 0,
    };

    $trackingEvents = collect();

    if ($shipment && $shipment->trackingLogs && $shipment->trackingLogs->isNotEmpty()) {
        foreach ($shipment->trackingLogs as $tLog) {
            $trackingEvents->push((object) [
                'title'       => $tLog->title,
                'description' => $tLog->description,
                'location'    => $tLog->location,
                'actor'       => $tLog->actor_type,
                'occurred_at' => $tLog->occurred_at ?? $tLog->created_at,
                'category'    => 'Shipment',
            ]);
        }
    }

    if ($sellerOrder->statusHistories && $sellerOrder->statusHistories->isNotEmpty()) {
        foreach ($sellerOrder->statusHistories as $hist) {
            $statusTitle = match($hist->to_status) {
                'pending_seller_acceptance' => 'Order Placed — Waiting for Seller Acceptance',
                'pending_buyer_response'    => 'Payment Flagged — Awaiting Buyer Response',
                'accepted', 'preparing'     => 'Seller Accepted Order & Preparing Package',
                'ready_for_pickup'          => 'Package Packed & Ready for Logistics Handover',
                'pickup_assigned'           => 'First-Mile Courier Assigned for Pickup',
                'picked_up'                 => 'Package Picked Up by Courier',
                'at_logistics_hub'          => 'Arrived at Logistics Sorting Hub',
                'out_for_delivery'          => 'Out for Last-Mile Delivery',
                'delivered'                 => 'Package Delivered to Recipient',
                'completed'                 => 'Order Completed & Confirmed',
                'cancelled'                 => 'Order Cancelled',
                'rejected'                  => 'Order Auto-Rejected',
                'disputed'                  => 'Return / Refund Dispute Opened',
                'returned_to_seller'        => 'Returned to Seller (RTS)',
                default                     => 'Status Updated: ' . ucwords(str_replace('_', ' ', (string) $hist->to_status)),
            };

            $trackingEvents->push((object) [
                'title'       => $statusTitle,
                'description' => $hist->remarks,
                'location'    => null,
                'actor'       => $hist->actor_type,
                'occurred_at' => $hist->created_at,
                'category'    => 'Order',
            ]);
        }
    }

    if ($trackingEvents->isEmpty()) {
        $trackingEvents->push((object) [
            'title'       => 'Order Placed Successfully',
            'description' => 'Your order #' . $sellerOrder->seller_order_number . ' has been sent to ' . ($sellerOrder->sellerProfile->business_name ?? 'the seller') . '.',
            'location'    => ($deliverySnap['municipality'] ?? '') . ', ' . ($deliverySnap['province'] ?? ''),
            'actor'       => 'Buyer',
            'occurred_at' => $sellerOrder->created_at,
            'category'    => 'Order',
        ]);
    }

    $trackingEvents = $trackingEvents
        ->sortByDesc(fn ($ev) => $ev->occurred_at ? $ev->occurred_at->timestamp : 0)
        ->values();
@endphp

<script>
    window.storkiaBuyerOrderShowState = function () {
        return {
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
            refundMethod: 'GCASH',
            refundPhone: '',
            sanitizeRefundPhone() {
                let digits = (this.refundPhone || '').replace(/\D/g, '');
                if (digits.startsWith('63') && digits.length === 12) {
                    digits = '0' + digits.slice(2);
                } else if (digits.startsWith('9') && digits.length === 10) {
                    digits = '0' + digits;
                }
                this.refundPhone = digits.slice(0, 11);
            },
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
                        this.disputeFilesError = 'File "' + f.name + '" exceeds the 5MB limit.';
                        event.target.value = '';
                        this.selectedEvidenceNames = [];
                        return;
                    }
                    this.selectedEvidenceNames.push(f.name);
                }
            }
        };
    };
</script>

<div x-data="storkiaBuyerOrderShowState()"
     class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('user.orders') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span>Back to My Orders</span>
        </a>

        <div class="flex flex-wrap items-center gap-2">
            @if($latestProof)
                <button type="button"
                        @click.stop="proofModalOpen = true"
                        class="px-3 py-1 rounded-full text-xs font-bold bg-primary text-white hover:bg-primary-dark shadow-xs transition-colors cursor-pointer inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Inspect Delivery Photo</span>
                </button>
            @endif
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

    @if($sellerOrder->status === 'pending_seller_acceptance' && $sellerOrder->acceptance_deadline_at)
        <div class="p-4 sm:p-5 rounded-2xl bg-brand-light/25 border border-primary/25 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase">24h Seller Response Protection</span>
                    <strong class="text-primary-dark">Awaiting Seller Acceptance</strong>
                </div>
                <p class="text-text-muted">
                    The seller has until <strong>{{ $sellerOrder->acceptance_deadline_at->format('M d, Y h:i A') }}</strong> ({{ $sellerOrder->acceptance_deadline_at->diffForHumans() }}) to accept your order. If they fail to respond in time, the order auto-cancels and you automatically earn <strong>+50 Storkia Coins</strong> in <a href="{{ route('user.points') }}" class="text-primary font-bold underline">My Coins</a>!
                </p>
            </div>
            <a href="{{ route('user.points') }}" class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors shrink-0 self-start sm:self-auto">
                View My Coins &rarr;
            </a>
        </div>
    @endif

    @if($sellerOrder->status === 'completed')
        <div class="p-5 rounded-3xl {{ $isRated ? 'bg-green-50 border-2 border-green-200' : 'bg-amber-50 border-2 border-amber-300' }} flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                @if(!$isRated)
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-200 text-amber-950 text-[10px] font-extrabold uppercase">Next Step: Rate Your Order</span>
                    <h3 class="text-base font-bold text-amber-950 mt-1">Order Received! Rate Product, Condition, Seller, Logistics &amp; Rider</h3>
                    <p class="text-xs text-amber-900 mt-0.5">
                        Leave your 5-point rating to help the community and unlock the <strong>Buy Again</strong> button for this order.
                    </p>
                @else
                    <span class="px-2.5 py-0.5 rounded-full bg-green-200 text-green-900 text-[10px] font-extrabold uppercase">Order Completed &amp; Rated ({{ number_format((float) $sellerOrder->serviceRating->overall_average_rating, 1) }} ★)</span>
                    <h3 class="text-base font-bold text-green-950 mt-1">Thank You for Rating This Order!</h3>
                    <p class="text-xs text-green-800 mt-0.5">
                        Your ratings for the product, packaging condition, seller, logistics hub, and rider have been recorded.
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-4 py-3 rounded-2xl bg-white border border-border-subtle hover:border-primary text-primary-dark text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                        Inspect Delivery Photo
                    </button>
                @endif
                @if(!$isRated)
                    <a href="{{ route('user.orders.rate.show', $sellerOrder->id) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold shadow-sm transition-colors">
                        ★ To Rate Order &amp; Services
                    </a>
                @else
                    <form action="{{ route('user.orders.buy-again', $sellerOrder->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            Buy Again
                        </button>
                    </form>
                @endif
            </div>
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
                            <span class="text-text-muted">Date &amp; Time Submitted:</span>
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
                            Yes, Switch to COD &amp; Continue
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
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-blue-900">Package Delivered — Confirm Receipt or Request Return &amp; Refund</h3>
                    @if($isIdentityVerified)
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-[10px] font-bold">Valid ID Verified ✓</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">Valid ID Required for Refund</span>
                    @endif
                </div>
                <p class="text-xs text-blue-800 mt-1 leading-relaxed">
                    Please inspect your items. If you don't confirm or report a problem by
                    <strong>{{ $sellerOrder->confirmation_deadline_at?->format('M d, Y h:i A') }}</strong>
                    ({{ $sellerOrder->confirmation_deadline_at?->diffForHumans() }}),
                    this order will automatically complete and release commission earnings.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-4 py-2.5 rounded-xl border border-blue-300 bg-white hover:bg-blue-100 text-blue-900 text-xs font-bold transition-colors cursor-pointer">
                        Inspect Delivery Photo
                    </button>
                @endif
                <button type="button"
                        @click.stop="disputeModalOpen = true"
                        class="px-4 py-2.5 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-red-600 text-xs font-bold transition-colors cursor-pointer">
                    Request Return / Refund
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
                <h3 class="text-sm font-bold text-red-900">Return / Refund Dispute Status</h3>
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

            @if($extra?->refund_method)
                <div class="p-3 rounded-xl bg-white border border-red-200 flex flex-wrap items-center justify-between gap-2">
                    <span>
                        Refund Payout Method: <strong>{{ $extra->refund_method }}</strong>
                        @if($extra->refund_account_number)
                            ({{ $extra->refund_account_name }} &bull; <span class="font-mono">{{ $extra->refund_account_number }}</span>)
                        @endif
                    </span>
                    <span class="font-bold text-primary">Refund Amount: ₱{{ number_format((float) ($extra->refund_amount ?: $sellerOrder->total_amount), 2) }}</span>
                </div>
            @endif

            @if(!empty($evidencePhotos))
                <div class="pt-1">
                    <span class="text-[10px] font-bold uppercase text-red-900 block mb-1.5">Submitted Photo Evidence ({{ count($evidencePhotos) }})</span>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($evidencePhotos as $photoPath)
                            <button type="button"
                                    @click.stop="evidencePreviewUrl = '{{ asset('storage/' . ltrim($photoPath, '/')) }}'; evidenceModalOpen = true"
                                    class="w-16 h-16 rounded-xl overflow-hidden border border-red-200 bg-white p-0.5 cursor-pointer">
                                <img src="{{ asset('storage/' . ltrim($photoPath, '/')) }}" alt="Dispute Evidence" class="w-full h-full object-cover rounded-lg">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($extra?->seller_return_decision === 'rejected')
                <div class="p-3.5 rounded-xl bg-white border border-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="font-bold text-danger block">Seller Declined Return Request:</span>
                        <p class="text-text-main mt-0.5">"{{ $extra->seller_return_decision_reason ?? $extra->seller_return_Decision_reason }}"</p>
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

    <!-- Order & Shipment Tracking Progress Stepper + Chronological Timeline -->
    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
            <div>
                <h3 class="text-sm sm:text-base font-bold text-primary-dark">Order &amp; Shipment Tracking</h3>
                <p class="text-xs text-text-muted mt-0.5">
                    @if($shipment)
                        Tracking Number: <strong class="font-mono text-primary-dark">{{ $shipment->tracking_number }}</strong>
                        &bull; Status: <strong class="text-primary">{{ $shipment->status_label }}</strong>
                    @else
                        Current Stage: <strong class="text-primary">{{ $sellerOrder->status_label }}</strong>
                    @endif
                </p>
            </div>
            @if($shipment && $shipment->currentRider)
                <div class="px-3 py-1.5 rounded-xl bg-surface-subtle border border-border-subtle text-xs">
                    <span class="text-text-muted">Assigned Rider:</span>
                    <strong class="text-text-main ml-1">{{ $shipment->currentRider->full_name }}</strong>
                    @if($shipment->currentRider->plate_number)
                        <span class="font-mono text-[11px] text-primary-dark ml-1">({{ $shipment->currentRider->plate_number }})</span>
                    @endif
                </div>
            @endif
        </div>

        @if($stepRank > 0)
            <div class="grid grid-cols-5 gap-2 text-center text-[11px]">
                @foreach([
                    1 => 'Order Placed',
                    2 => 'Seller Preparing',
                    3 => 'At Sorting Hub',
                    4 => 'Out for Delivery',
                    5 => 'Delivered',
                ] as $sNum => $sLabel)
                    <div class="flex flex-col items-center gap-1.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-extrabold border-2 transition-all {{ $stepRank >= $sNum ? 'bg-primary border-primary text-white shadow-xs' : 'bg-surface-subtle border-border-subtle text-text-muted' }}">
                            @if($stepRank > $sNum)
                                &check;
                            @else
                                {{ $sNum }}
                            @endif
                        </div>
                        <span class="font-semibold leading-tight {{ $stepRank >= $sNum ? 'text-primary-dark font-bold' : 'text-text-muted' }}">
                            {{ $sLabel }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="relative pl-6 border-l-2 border-primary/30 space-y-5 pt-2">
            @foreach($trackingEvents as $idx => $ev)
                <div class="relative">
                    <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-gray-300' }}"></span>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase {{ $ev->category === 'Shipment' ? 'bg-primary/10 text-primary' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                                {{ $ev->category }}
                            </span>
                            <span class="text-xs sm:text-sm font-bold {{ $idx === 0 ? 'text-primary-dark' : 'text-text-main' }}">
                                {{ $ev->title }}
                            </span>
                        </div>
                        <span class="text-[11px] font-mono text-text-muted">
                            {{ $ev->occurred_at?->format('M d, Y h:i A') }}
                        </span>
                    </div>
                    @if($ev->description)
                        <p class="text-xs text-text-muted mt-1 leading-relaxed">{{ $ev->description }}</p>
                    @endif
                    @if($ev->location || $ev->actor)
                        <p class="text-[11px] text-text-muted/80 mt-0.5">
                            @if($ev->actor) Updated by: {{ $ev->actor }} @endif
                            @if($ev->location) &bull; {{ $ev->location }} @endif
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Delivery Address Snapshot</h3>
            <p class="text-sm font-bold text-text-main">{{ $deliverySnap['recipient_name'] ?? 'Recipient' }}</p>
            <p class="text-xs text-text-muted">{{ $deliverySnap['phone_number'] ?? '' }}</p>
            <p class="text-xs text-text-main leading-relaxed">{{ $deliverySnap['full_address'] ?? '' }}</p>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Shipment &amp; Payment Info</h3>
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
                @if($latestProof)
                    <div class="flex justify-between gap-2 pt-1 border-t border-border-subtle/50">
                        <span class="text-text-muted">Received By:</span>
                        <span class="font-bold text-green-700 text-right">
                            {{ $latestProof->recipient_name ?: 'Recipient' }}
                            @if($latestProof->recipient_relation)
                                ({{ $latestProof->recipient_relation }})
                            @endif
                        </span>
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
                            class="px-3.5 py-1.5 rounded-xl bg-primary/15 text-primary-dark hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Inspect Delivery Photo</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

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
                                <img src="{{ asset('storage/' . ltrim($item->product_image, '/')) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
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

        <div class="p-4 sm:px-5 bg-surface-subtle/40 border-t border-border-subtle space-y-1.5 text-xs sm:text-sm">
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
        <div x-show="disputeModalOpen"
             x-cloak
             class="fixed inset-0 z-[185] flex items-center justify-center p-4">
            <div @click="disputeModalOpen = false"
                 class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>

            <div @click.stop
                 class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Request Return &amp; Refund</h3>
                    <button type="button" @click="disputeModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                @if(!$isIdentityVerified)
                    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 space-y-3 text-xs">
                        <div class="font-extrabold text-amber-950 text-sm">Valid ID Verification Required for Refunds</div>
                        <p class="text-amber-900 leading-relaxed">
                            To protect both buyers and merchants from fraudulent claims, Storkia requires an approved <strong>Valid Government ID</strong> on your account before submitting a Return &amp; Refund request.
                        </p>
                        <div class="pt-1 flex justify-end gap-2">
                            <button type="button" @click="disputeModalOpen = false" class="px-4 py-2 rounded-xl bg-white border border-amber-300 text-amber-900 font-bold cursor-pointer">
                                Close
                            </button>
                            <a href="{{ route('user.identity-verification') }}" class="px-4 py-2 rounded-xl bg-primary text-white font-bold shadow-xs">
                                Verify Valid ID Now &rarr;
                            </a>
                        </div>
                    </div>
                @else
                    <form action="{{ route('user.orders.report-dispute', $sellerOrder->id) }}"
                          method="POST"
                          enctype="multipart/form-data"
                          @submit="if (isSubmittingDispute || disputeFilesError) { $event.preventDefault(); } else { isSubmittingDispute = true; }"
                          class="space-y-4 text-xs">
                        @csrf

                        <div class="p-2.5 rounded-xl bg-green-50 border border-green-200 text-green-800 font-semibold flex items-center justify-between">
                            <span>✓ Valid Government ID Verified</span>
                            <span>Refundable: ₱{{ number_format((float) $sellerOrder->total_amount, 2) }}</span>
                        </div>

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
                            <label class="block font-bold text-text-muted mb-1">Refund Payout Destination <span class="text-danger">*</span></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="p-2.5 rounded-xl border cursor-pointer flex items-center gap-2 font-bold"
                                       :class="refundMethod === 'GCASH' ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-border-subtle'">
                                    <input type="radio" name="refund_method" value="GCASH" x-model="refundMethod">
                                    <span>GCash Refund</span>
                                </label>
                                <label class="p-2.5 rounded-xl border cursor-pointer flex items-center gap-2 font-bold"
                                       :class="refundMethod === 'WALLET' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle'">
                                    <input type="radio" name="refund_method" value="WALLET" x-model="refundMethod">
                                    <span>Storkia Wallet</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="refundMethod === 'GCASH'" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block font-bold text-text-muted mb-1">GCash Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="refund_account_name" :required="refundMethod === 'GCASH'" placeholder="Juan Dela Cruz" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                            </div>
                            <div>
                                <label class="block font-bold text-text-muted mb-1">GCash Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="refund_account_number" x-model="refundPhone" @input="sanitizeRefundPhone()" maxlength="11" :required="refundMethod === 'GCASH'" placeholder="09171234567" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted mb-1">Describe the Issue (10+ characters) <span class="text-danger">*</span></label>
                            <textarea name="dispute_reason"
                                      rows="3"
                                      required
                                      minlength="10"
                                      maxlength="1000"
                                      placeholder="Explain what went wrong..."
                                      class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted mb-1">Upload 1 to 5 Evidence Photos (Max 5MB each) <span class="text-danger">*</span></label>
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
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="disputeModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="isSubmittingDispute || !!disputeFilesError"
                                    class="px-5 py-2 rounded-xl bg-danger text-white font-bold hover:opacity-90 cursor-pointer disabled:opacity-50">
                                <span x-show="!isSubmittingDispute">Submit Return &amp; Refund</span>
                                <span x-show="isSubmittingDispute" x-cloak>Submitting...</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </template>

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

                <form action="{{ route('user.orders.cancel', $sellerOrder->id) }}"
                      method="POST"
                      @submit="isSubmittingCancel = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted mb-1">Select Cancellation Reason <span class="text-danger">*</span></label>
                        <select name="cancel_reason" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="">-- Choose a reason --</option>
                            <option value="Changed my mind">Changed my mind</option>
                            <option value="Need to change delivery address">Need to change delivery address</option>
                            <option value="Forgot to apply voucher code">Forgot to apply voucher code</option>
                            <option value="Ordered wrong item or variation">Ordered wrong item or variation</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="cancelModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Keep Order</button>
                        <button type="submit" :disabled="isSubmittingCancel" class="px-5 py-2 rounded-xl bg-danger text-white font-bold cursor-pointer">Confirm Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    @if($latestProof)
        <template x-teleport="body">
            <div x-show="proofModalOpen"
                 x-cloak
                 @keydown.escape.window="proofModalOpen = false"
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4">
                <div @click="proofModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Proof of Delivery</h3>
                            <p class="text-xs text-text-muted">Order #{{ $sellerOrder->seller_order_number }} &bull; Tracking #{{ $shipment->tracking_number ?? 'N/A' }}</p>
                        </div>
                        <button type="button" @click="proofModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer text-lg leading-none">&times;</button>
                    </div>

                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[55vh] p-2">
                        <img src="{{ $proofImageUrl }}"
                             alt="Proof of Delivery"
                             class="max-h-[52vh] w-auto object-contain rounded-xl shadow-xs">
                    </div>

                    <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-text-muted block">Received By:</span>
                            <strong class="text-text-main">
                                {{ $latestProof->recipient_name ?: ($deliverySnap['recipient_name'] ?? 'Recipient') }}
                                @if($latestProof->recipient_relation)
                                    ({{ $latestProof->recipient_relation }})
                                @endif
                            </strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Delivered At:</span>
                            <strong class="text-text-main">{{ ($latestProof->captured_at ?? $sellerOrder->delivered_at)?->format('M d, Y h:i A') ?? 'Recorded' }}</strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Delivered By Rider:</span>
                            <strong class="text-text-main">{{ $latestProof->rider?->full_name ?? ($shipment?->currentRider?->full_name ?? 'Assigned Logistics Rider') }}</strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Payment / COD Collected:</span>
                            <strong class="text-primary-dark">
                                @if((float) ($latestProof->cod_collected_amount ?? 0) > 0)
                                    ₱{{ number_format((float) $latestProof->cod_collected_amount, 2) }} (COD)
                                @else
                                    {{ $payMethod }} (₱{{ number_format((float) $sellerOrder->total_amount, 2) }})
                                @endif
                            </strong>
                        </div>
                        @if($latestProof->latitude && $latestProof->longitude)
                            <div class="sm:col-span-2">
                                <span class="text-text-muted block">GPS Delivery Coordinates:</span>
                                <span class="font-mono text-[11px] font-semibold text-text-main">
                                    {{ $latestProof->latitude }}, {{ $latestProof->longitude }}
                                    @if($latestProof->location_accuracy)
                                        (&plusmn;{{ number_format((float) $latestProof->location_accuracy, 1) }}m)
                                    @endif
                                </span>
                            </div>
                        @endif
                        @if($latestProof->notes)
                            <div class="sm:col-span-2">
                                <span class="text-text-muted block">Rider Handover Notes:</span>
                                <p class="text-text-main italic">"{{ $latestProof->notes }}"</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-border-subtle text-xs">
                        <a href="{{ $proofImageUrl }}"
                           target="_blank"
                           class="font-bold text-primary hover:underline">
                            Open Full Image in New Tab &nearr;
                        </a>
                        <button type="button"
                                @click="proofModalOpen = false"
                                class="px-5 py-2 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif

    @if($paymentSubmission)
        <template x-teleport="body">
            <div x-show="gcashScreenshotModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[190] flex items-center justify-center p-4">
                <div @click="gcashScreenshotModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-base font-bold text-primary-dark">Submitted GCash Screenshot (WebP)</h3>
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

</div>
@endsection