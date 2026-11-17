@extends('buyer.account-settings', [
    'pageTitle' => 'Return & Refund',
    'pageSubtitle' => 'Request ' . $returnRequest->request_number . ' for Order #' . $sellerOrder->seller_order_number . '.'
])

@section('option-content')
@php
    $rr = $returnRequest;

    $tone = match ($rr->status) {
        'refunded'                                  => 'bg-success/10 border-success/25 text-text-main',
        'rejected', 'closed', 'rejected_by_seller'  => 'bg-danger/10 border-danger/25 text-danger',
        'refund_pending'                            => 'bg-primary/10 border-primary/25 text-primary-dark',
        default                                     => 'bg-brand-light/35 border-primary/25 text-primary-dark',
    };

    $badge = match ($rr->status) {
        'refunded'                                  => 'bg-success text-white',
        'rejected', 'closed', 'rejected_by_seller'  => 'bg-danger text-white',
        'refund_pending'                            => 'bg-primary-dark text-white',
        default                                     => 'bg-primary text-white',
    };

    $photos = is_array($rr->evidence_photos) ? $rr->evidence_photos : [];

    $steps = ['Return approved', 'Rider assigned', 'Picked up from you', 'At sorting hub', 'On the way to seller', 'Delivered to seller'];
    $stepIndex = $returnShipment
        ? match ($returnShipment->status) {
            'awaiting_rider_assignment'             => 0,
            'rider_assigned', 'pickup_failed'       => 1,
            'picked_up_from_buyer'                  => 2,
            'received_at_hub', 'courier_assigned'   => 3,
            'out_for_return_delivery'               => 4,
            'delivered_to_seller'                   => 5,
            default                                 => 0,
        }
        : null;

    $refundAmount = number_format((float) $rr->refund_amount, 2);
    $fmt = fn ($date) => $date?->format('M d, Y h:i A');

    [$nextTitle, $nextText] = match ($rr->status) {
        'pending_seller_response' => [
            'Waiting for the seller',
            'The seller has until ' . $fmt($rr->seller_response_deadline_at) . ' to respond. If they do not respond in time, your request is approved automatically.',
        ],
        'rejected_by_seller' => [
            'The seller rejected your request',
            'You can escalate this to Storkia Admin, who will review both sides and make the final decision.',
        ],
        'escalated' => [
            'Storkia Admin is reviewing your case',
            'We are reviewing the evidence from you and the seller. You will be notified as soon as a decision is made.',
        ],
        'return_in_transit' => [
            'Send the item back',
            'Pack the item with everything it came with and keep it ready for the rider. '
                . ($rr->handover_deadline_at ? 'Please hand it over by ' . $fmt($rr->handover_deadline_at) . '. ' : '')
                . 'If the parcel cannot be handed over in time, the request is closed and the order completes without a refund.',
        ],
        'awaiting_seller_confirmation' => [
            'Your item reached the seller',
            'The seller is checking the returned item. They must confirm by ' . $fmt($rr->seller_receipt_deadline_at) . ', or it is confirmed automatically. Your refund follows right after.',
        ],
        'receipt_disputed' => [
            'Returned item under admin review',
            'The seller reported a problem with the returned item. Storkia Admin will review it and decide.',
        ],
        'refund_pending' => [
            'Your refund is on its way',
            '₱' . $refundAmount . ' will be sent to your GCash'
                . ($rr->refund_account_number ? ' (' . $rr->refund_account_name . ' • ' . $rr->refund_account_number . ')' : '')
                . ($rr->refund_due_at ? ' by ' . $fmt($rr->refund_due_at) : '') . '.',
        ],
        'refunded' => [
            'Refund sent',
            '₱' . $refundAmount . ' was sent to your GCash on ' . $fmt($rr->refunded_at) . ($rr->refund_reference ? ' (Ref #' . $rr->refund_reference . ')' : '') . '.',
        ],
        'rejected' => [
            'Request rejected',
            'This request was rejected and the order was completed without a refund.',
        ],
        'closed' => [
            'Request closed',
            'This request was closed and the order was completed without a refund.',
        ],
        default => ['Status: ' . $rr->status_label, ''],
    };
@endphp

<div x-data="{
        showAllEvents: false,
        photoModalOpen: false,
        photoModalUrl: '',
        openPhoto(url) {
            this.photoModalUrl = url;
            this.photoModalOpen = true;
        }
     }"
     class="space-y-5 select-none">

    <a href="{{ route('user.orders.show', $sellerOrder->id) }}"
       class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors cursor-pointer">
        &larr; Back to Order #{{ $sellerOrder->seller_order_number }}
    </a>

    <div class="bg-surface rounded-2xl border border-border-subtle p-5 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase {{ $badge }}">{{ $rr->status_label }}</span>
                <span class="text-xs font-bold text-text-main">{{ $rr->resolution_label }}</span>
            </div>
            <p class="text-xs text-text-muted">
                <span class="font-mono font-bold text-primary-dark select-text">{{ $rr->request_number }}</span> &bull; {{ $sellerOrder->sellerProfile?->business_name ?? 'Seller' }} &bull; Sent {{ $fmt($rr->created_at) }}
            </p>
        </div>
        <div class="text-right">
            <p class="text-[10px] font-bold uppercase text-text-muted">Refund amount</p>
            <p class="text-lg font-extrabold text-price">&#8369;{{ $refundAmount }}</p>
        </div>
    </div>

    <div class="p-5 rounded-2xl border {{ $tone }} space-y-3 text-xs">
        <div>
            <h3 class="text-sm font-bold">{{ $nextTitle }}</h3>
            @if($nextText)
                <p class="mt-1 leading-relaxed">{{ $nextText }}</p>
            @endif
        </div>

        @if($rr->canBuyerEscalate())
            <form action="{{ route('user.returns.escalate', $rr->id) }}" method="POST" class="space-y-2 m-0">
                @csrf
                <label class="block font-bold text-primary-dark">Anything to add for the Admin? (optional)</label>
                <textarea name="escalation_note"
                          rows="2"
                          maxlength="500"
                          placeholder="Tell us why you disagree with the seller's decision."
                          class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-text-main outline-none focus:border-primary">{{ old('escalation_note') }}</textarea>
                <button type="submit" class="px-4 py-2 rounded-xl bg-danger text-white font-bold cursor-pointer hover:opacity-90 transition-opacity">
                    Escalate to Storkia Admin
                </button>
            </form>
        @endif
    </div>

    @if($returnShipment)
        <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-primary-dark">Return Tracking</h3>
                    <p class="text-xs text-text-muted mt-0.5">
                        <span class="font-mono font-bold text-primary-dark select-text">{{ $returnShipment->tracking_number }}</span>
                        &bull; {{ $returnShipment->status_label }}
                    </p>
                </div>
                @if($returnShipment->rider)
                    <p class="text-xs text-text-muted">
                        Pickup rider: <strong class="text-text-main">{{ $returnShipment->rider->full_name }}</strong>
                    </p>
                @endif
            </div>

            @if($returnShipment->status === 'cancelled')
                <p class="text-xs font-bold text-danger">This return was cancelled.</p>
            @else
                <ol class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                    @foreach($steps as $i => $label)
                        @php $done = $stepIndex !== null && $i <= $stepIndex; @endphp
                        <li class="flex sm:flex-col items-center sm:text-center gap-2">
                            <span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[11px] font-extrabold
                                         {{ $done ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                                {{ $done ? '✓' : $i + 1 }}
                            </span>
                            <span class="text-[11px] font-bold {{ $done ? 'text-primary-dark' : 'text-text-muted' }}">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if($returnShipment->status === 'pickup_failed')
                <p class="p-3 rounded-xl bg-brand-light/40 border border-primary/30 text-xs text-primary-dark font-semibold">
                    The rider could not collect your parcel{{ $returnShipment->pickup_failure_reason ? ' (' . $returnShipment->pickup_failure_reason . ')' : '' }}.
                    Logistics will arrange another attempt. Please make sure you are reachable.
                    (Attempt {{ $returnShipment->pickup_attempts_count }} of {{ \App\Models\ReturnShipment::MAX_PICKUP_ATTEMPTS }})
                </p>
            @endif

            @if($returnShipment->events->isNotEmpty())
                <div class="space-y-3 pt-1">
                    @foreach($returnShipment->events as $evIdx => $event)
                        <div x-show="{{ $evIdx }} < 5 || showAllEvents"
                             x-transition
                             class="flex gap-3 text-xs">
                            <span class="mt-1 w-2 h-2 rounded-full bg-primary shrink-0"></span>
                            <div class="min-w-0">
                                <p class="font-bold text-text-main">{{ $event->title }}</p>
                                @if($event->description)
                                    <p class="text-text-muted leading-relaxed">{{ $event->description }}</p>
                                @endif
                                <p class="text-[11px] text-text-muted mt-0.5">
                                    {{ $fmt($event->occurred_at) }}@if($event->location) &bull; {{ $event->location }}@endif
                                </p>
                            </div>
                        </div>
                    @endforeach

                    @if($returnShipment->events->count() > 5)
                        <div class="pt-1">
                            <button type="button"
                                    @click="showAllEvents = !showAllEvents"
                                    class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                    x-text="showAllEvents ? 'See Less Tracking Events' : 'See All {{ $returnShipment->events->count() }} Tracking Events'"></button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-3 text-xs">
        <h3 class="text-sm sm:text-base font-bold text-primary-dark">Your Request</h3>
        <div>
            <p class="font-bold text-text-muted uppercase text-[10px]">Reason</p>
            <p class="text-text-main font-semibold">{{ $rr->reason_category }}</p>
        </div>
        <div>
            <p class="font-bold text-text-muted uppercase text-[10px]">Your description</p>
            <p class="text-text-main leading-relaxed whitespace-pre-line select-text">{{ $rr->reason }}</p>
        </div>
        @if(!empty($photos))
            <div>
                <p class="font-bold text-text-muted uppercase text-[10px] mb-1.5">Photo evidence ({{ count($photos) }})</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($photos as $photoPath)
                        @php $fullPhotoUrl = asset('storage/' . ltrim($photoPath, '/')); @endphp
                        <button type="button"
                                @click="openPhoto('{{ $fullPhotoUrl }}')"
                                class="w-16 h-16 rounded-xl overflow-hidden border border-border-subtle hover:border-primary bg-surface p-0.5 transition-colors cursor-pointer">
                            <img src="{{ $fullPhotoUrl }}" alt="Evidence photo" class="w-full h-full object-cover rounded-lg">
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($rr->seller_decision || $rr->admin_decision || $rr->seller_receipt_outcome)
        <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-3 text-xs">
            <h3 class="text-sm sm:text-base font-bold text-primary-dark">Decisions</h3>

            @if($rr->seller_decision)
                <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle">
                    <p class="font-bold text-text-main">
                        Seller:
                        @switch($rr->seller_decision)
                            @case('approved_return') Approved. Please send the item back. @break
                            @case('approved_refund_only') Approved a refund. You can keep the item. @break
                            @default Rejected the request.
                        @endswitch
                    </p>
                    @if($rr->seller_decision_reason)
                        <p class="text-text-muted mt-0.5 select-text">"{{ $rr->seller_decision_reason }}"</p>
                    @endif
                </div>
            @endif

            @if($rr->seller_receipt_outcome)
                <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle">
                    <p class="font-bold text-text-main">
                        Seller's check of the returned item:
                        {{ $rr->seller_receipt_outcome === 'received_ok' ? 'Received in good order.' : 'Reported a problem.' }}
                    </p>
                    @if($rr->seller_receipt_notes)
                        <p class="text-text-muted mt-0.5 select-text">"{{ $rr->seller_receipt_notes }}"</p>
                    @endif
                </div>
            @endif

            @if($rr->admin_decision)
                <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle">
                    <p class="font-bold text-text-main">
                        Storkia Admin:
                        @switch($rr->admin_decision)
                            @case('approved_return') Approved the return. @break
                            @case('approved_refund_only') Approved your refund. @break
                            @default Rejected the request.
                        @endswitch
                    </p>
                    @if($rr->admin_remarks)
                        <p class="text-text-muted mt-0.5 select-text">"{{ $rr->admin_remarks }}"</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if($rr->refund_status !== 'not_due')
        <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-2 text-xs">
            <h3 class="text-sm sm:text-base font-bold text-primary-dark">Refund</h3>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-text-muted">
                    Paid to GCash:
                    <strong class="text-text-main">{{ $rr->refund_account_name }}</strong>
                    <span class="font-mono select-text">&bull; {{ $rr->refund_account_number }}</span>
                </span>
                <span class="font-extrabold text-price">&#8369;{{ $refundAmount }}</span>
            </div>
            @if($rr->refund_status === 'refunded')
                <p class="text-success font-bold">
                    Sent on {{ $fmt($rr->refunded_at) }}@if($rr->refund_reference) &bull; GCash Ref #{{ $rr->refund_reference }}@endif
                </p>
            @else
                <p class="text-primary-dark font-bold">
                    Waiting for Storkia to send it
                    @if($rr->refund_due_at) &bull; due by {{ $fmt($rr->refund_due_at) }}@endif
                </p>
            @endif
        </div>
    @endif

    <template x-teleport="body">
        <div x-show="photoModalOpen"
             x-cloak
             @keydown.escape.window="photoModalOpen = false"
             class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs cursor-zoom-out"
             @click="photoModalOpen = false">
            <img :src="photoModalUrl" alt="Evidence photo" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl bg-surface" @click.stop>
        </div>
    </template>
</div>
@endsection