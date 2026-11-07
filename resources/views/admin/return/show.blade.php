@extends('layouts.admin', ['title' => 'Storkia Admin - Return ' . $returnRequest->request_number])

@section('content')
@php
    $order = $sellerOrder?->order;
    $buyer = $sellerOrder?->buyer;
    $evidencePhotos = is_array($returnRequest->evidence_photos) ? $returnRequest->evidence_photos : [];
    $receiptPhotos = is_array($returnRequest->seller_receipt_photos) ? $returnRequest->seller_receipt_photos : [];
    $snapshot = is_array($returnRequest->settlement_snapshot) ? $returnRequest->settlement_snapshot : [];
    $payMethod = $sellerOrder?->effective_payment_method;

    $badgeClass = match($returnRequest->status) {
        'refunded' => 'bg-green-100 text-green-700 border-green-200',
        'rejected', 'closed' => 'bg-gray-100 text-gray-700 border-gray-200',
        'escalated', 'receipt_disputed' => 'bg-red-100 text-red-700 border-red-200',
        'refund_pending' => 'bg-amber-100 text-amber-800 border-amber-200',
        default => 'bg-blue-100 text-blue-700 border-blue-200',
    };

    $refundStatusLabel = match($returnRequest->refund_status) {
        \App\Models\ReturnRequest::REFUND_PENDING_ADMIN => 'Waiting for GCash payout',
        \App\Models\ReturnRequest::REFUND_REFUNDED      => 'Paid to buyer',
        default                                         => 'Not due yet',
    };

    $decisionLabel = function (?string $decision) {
        return match($decision) {
            \App\Models\ReturnRequest::DECISION_APPROVED_RETURN      => 'Approved: return and refund',
            \App\Models\ReturnRequest::DECISION_APPROVED_REFUND_ONLY => 'Approved: refund only',
            \App\Models\ReturnRequest::DECISION_REJECTED             => 'Rejected',
            default                                                  => null,
        };
    };
@endphp

<div x-data="{
        photoOpen: false,
        photoUrl: '',
        decision: '',
        refundSent: false,
        openPhoto(url) {
            this.photoUrl = url;
            this.photoOpen = true;
        }
     }"
     class="p-8 lg:p-10 max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.returns.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono">{{ $returnRequest->request_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">{{ $returnRequest->status_label }}</span>
                    @if($payoutOverdue)
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase bg-red-600 text-white">Payout Overdue</span>
                    @endif
                    @if($returnRequest->auto_approved)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Auto-approved</span>
                    @endif
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Seller Order: <strong>{{ $sellerOrder?->seller_order_number ?? 'N/A' }}</strong> &bull;
                    Parent Order: <strong>{{ $order?->order_number ?? 'N/A' }}</strong> &bull;
                    Requested {{ $returnRequest->created_at?->format('M d, Y h:i A') }} &bull;
                    Payment: <strong>{{ $payMethod }}</strong>
                </p>
            </div>
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

    @if($canResolve)
        <div class="p-6 rounded-3xl bg-red-50/70 border-2 border-red-200 space-y-4">
            <div>
                <h2 class="text-base font-bold text-red-950">
                    {{ $isReceiptDispute ? 'Decide: Is the Returned Parcel Acceptable?' : 'Decide: Seller Rejected, Buyer Escalated' }}
                </h2>
                <p class="text-xs text-red-900 mt-0.5 leading-relaxed">
                    @if($isReceiptDispute)
                        The parcel was returned to the seller, who says it is not as expected. Review the buyer's and the seller's photos below.
                        Siding with the buyer refunds them (the parcel is not sent again). Siding with the seller closes the request without a refund.
                    @else
                        The seller rejected this request and the buyer escalated it. Review the evidence below, then decide.
                    @endif
                </p>
            </div>

            <form action="{{ route('admin.returns.resolve', $returnRequest->id) }}" method="POST" class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @if($canApproveReturn)
                        <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5 font-bold"
                               :class="decision === '{{ \App\Models\ReturnRequest::DECISION_APPROVED_RETURN }}' ? 'border-green-600 bg-green-50 text-green-800' : 'border-border-subtle bg-white text-text-muted'">
                            <input type="radio" name="decision" value="{{ \App\Models\ReturnRequest::DECISION_APPROVED_RETURN }}" x-model="decision" class="mt-0.5">
                            <span>Approve return &amp; refund<span class="block font-normal mt-0.5">The buyer sends the item back, then is refunded.</span></span>
                        </label>
                    @endif

                    <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5 font-bold"
                           :class="decision === '{{ \App\Models\ReturnRequest::DECISION_APPROVED_REFUND_ONLY }}' ? 'border-green-600 bg-green-50 text-green-800' : 'border-border-subtle bg-white text-text-muted'">
                        <input type="radio" name="decision" value="{{ \App\Models\ReturnRequest::DECISION_APPROVED_REFUND_ONLY }}" x-model="decision" class="mt-0.5">
                        <span>
                            {{ $isReceiptDispute ? 'Side with the buyer: refund' : 'Approve refund only' }}
                            <span class="block font-normal mt-0.5">The buyer is refunded{{ $isReceiptDispute ? '.' : ' and keeps the item.' }}</span>
                        </span>
                    </label>

                    <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5 font-bold"
                           :class="decision === '{{ \App\Models\ReturnRequest::DECISION_REJECTED }}' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle bg-white text-text-muted'">
                        <input type="radio" name="decision" value="{{ \App\Models\ReturnRequest::DECISION_REJECTED }}" x-model="decision" class="mt-0.5">
                        <span>
                            {{ $isReceiptDispute ? 'Side with the seller: no refund' : 'Reject the request' }}
                            <span class="block font-normal mt-0.5">Closed without a refund; the order completes normally.</span>
                        </span>
                    </label>
                </div>

                <div x-show="decision === '{{ \App\Models\ReturnRequest::DECISION_APPROVED_RETURN }}'" x-cloak class="p-3.5 rounded-xl bg-white border border-border-subtle text-text-muted leading-relaxed">
                    A return parcel is created for Logistics. The refund becomes due once the seller confirms the parcel, and the seller's wallet is charged when it is settled.
                </div>
                <div x-show="decision === '{{ \App\Models\ReturnRequest::DECISION_APPROVED_REFUND_ONLY }}'" x-cloak class="p-3.5 rounded-xl bg-white border border-border-subtle text-text-muted leading-relaxed">
                    The refund of &#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }} goes straight to the payout queue, and the seller's wallet is charged immediately.
                </div>
                <div x-show="decision === '{{ \App\Models\ReturnRequest::DECISION_REJECTED }}'" x-cloak class="p-3.5 rounded-xl bg-white border border-border-subtle text-text-muted leading-relaxed">
                    The buyer and the seller are told the request was rejected. No money moves.
                </div>

                <div>
                    <label class="block font-bold text-text-muted mb-1">Remarks (5 to 500 characters, sent to both parties) <span class="text-danger">*</span></label>
                    <textarea name="remarks"
                              rows="3"
                              required
                              minlength="5"
                              maxlength="500"
                              placeholder="Explain your decision based on the evidence..."
                              class="w-full p-3 rounded-xl border border-border-subtle bg-white outline-none focus:border-primary">{{ old('remarks') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="decision === ''"
                            :class="decision !== '' ? 'bg-primary-dark hover:bg-text-main text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold transition-colors">
                        Submit Decision
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($canPayRefund)
        <div class="p-6 rounded-3xl bg-amber-50 border-2 {{ $payoutOverdue ? 'border-red-400' : 'border-amber-300' }} space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-amber-950">Pay the Refund to the Buyer's GCash</h2>
                    <p class="text-xs text-amber-900 mt-0.5">
                        Send the amount below from the Storkia GCash account, then record the GCash reference number.
                        @if($returnRequest->refund_due_at)
                            Payout {{ $payoutOverdue ? 'was due' : 'is due' }} by <strong>{{ $returnRequest->refund_due_at->format('M d, Y h:i A') }}</strong>.
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold uppercase text-amber-900 block">Amount to Send</span>
                    <span class="text-2xl font-serif font-extrabold text-primary">&#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-4 rounded-2xl bg-white border border-amber-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">GCash Account Name</span>
                    <p class="font-bold text-sm text-text-main mt-0.5">{{ $returnRequest->refund_account_name ?: 'Not provided' }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-white border border-amber-200">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">GCash Number</span>
                    <p class="font-mono font-bold text-sm text-text-main mt-0.5">{{ $returnRequest->refund_account_number ?: 'Not provided' }}</p>
                </div>
            </div>

            <form action="{{ route('admin.returns.refund-paid', $returnRequest->id) }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-text-muted mb-1">GCash Reference Number <span class="text-danger">*</span></label>
                        <input type="text" name="refund_reference" value="{{ old('refund_reference') }}" required minlength="4" maxlength="60"
                               placeholder="e.g. 1234 567 890123"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-white font-mono outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted mb-1">Remarks (Optional)</label>
                        <input type="text" name="refund_remarks" value="{{ old('refund_remarks') }}" maxlength="300"
                               placeholder="Anything the buyer should know"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-white outline-none focus:border-primary">
                    </div>
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="refundSent" class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary">
                    <span class="font-bold text-text-main">
                        I sent &#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }} to the GCash account above. Recording this marks the request as refunded and notifies the buyer.
                    </span>
                </label>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="!refundSent"
                            :class="refundSent ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold transition-colors">
                        Record Refund as Paid
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-primary-dark">The Buyer's Request</h3>
                    <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-text-main font-bold">
                        {{ $returnRequest->resolution_label }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Reason</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">{{ $returnRequest->reason_category }}</p>
                    </div>
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Refund Amount</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">&#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>
                    </div>
                </div>

                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Explanation</span>
                    <p class="text-sm font-semibold text-text-main mt-0.5">"{{ $returnRequest->reason }}"</p>
                </div>

                @if(!empty($evidencePhotos))
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block mb-2">Buyer Evidence Photos ({{ count($evidencePhotos) }})</span>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($evidencePhotos as $photoPath)
                                <button type="button"
                                        @click.stop="openPhoto('{{ asset('storage/' . $photoPath) }}')"
                                        class="w-20 h-20 rounded-xl overflow-hidden border border-border-subtle bg-surface-subtle hover:border-primary transition-all cursor-pointer p-1">
                                    <img src="{{ asset('storage/' . $photoPath) }}" alt="Buyer Evidence" class="w-full h-full object-cover rounded-lg">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <h3 class="text-sm font-bold text-primary-dark">Who Decided What</h3>

                <div class="space-y-3">
                    <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle">
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Seller</span>
                        @if($returnRequest->seller_decision)
                            <p class="text-sm font-semibold text-text-main mt-0.5">
                                {{ $decisionLabel($returnRequest->seller_decision) }}
                                @if($returnRequest->auto_approved)
                                    <span class="text-text-muted font-semibold">(automatic, no response in time)</span>
                                @endif
                            </p>
                            @if($returnRequest->seller_decision_reason)
                                <p class="text-text-muted mt-0.5">"{{ $returnRequest->seller_decision_reason }}"</p>
                            @endif
                            <p class="text-text-muted mt-0.5">{{ $returnRequest->seller_responded_at?->format('M d, Y h:i A') }}</p>
                        @else
                            <p class="text-text-muted mt-0.5">No response yet.
                                @if($returnRequest->seller_response_deadline_at)
                                    Due by {{ $returnRequest->seller_response_deadline_at->format('M d, Y h:i A') }}.
                                @endif
                            </p>
                        @endif
                    </div>

                    @if($returnRequest->is_escalated)
                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Escalation</span>
                            <p class="text-sm font-semibold text-text-main mt-0.5">Escalated to Storkia Admin {{ $returnRequest->escalated_at?->format('M d, Y h:i A') }}</p>
                            @if($returnRequest->escalation_note)
                                <p class="text-text-muted mt-0.5">"{{ $returnRequest->escalation_note }}"</p>
                            @endif
                        </div>
                    @endif

                    @if($returnRequest->seller_receipt_outcome)
                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2">
                            <div>
                                <span class="font-bold text-text-muted uppercase text-[10px] block">Seller's Check of the Returned Parcel</span>
                                <p class="text-sm font-semibold text-text-main mt-0.5">
                                    {{ $returnRequest->seller_receipt_outcome === \App\Models\ReturnRequest::RECEIPT_OK ? 'Received in good order' : 'Not as expected' }}
                                </p>
                                @if($returnRequest->seller_receipt_notes)
                                    <p class="text-text-muted mt-0.5">"{{ $returnRequest->seller_receipt_notes }}"</p>
                                @endif
                            </div>
                            @if(!empty($receiptPhotos))
                                <div class="flex flex-wrap items-center gap-3">
                                    @foreach($receiptPhotos as $photoPath)
                                        <button type="button"
                                                @click.stop="openPhoto('{{ asset('storage/' . $photoPath) }}')"
                                                class="w-20 h-20 rounded-xl overflow-hidden border border-border-subtle bg-surface hover:border-primary transition-all cursor-pointer p-1">
                                            <img src="{{ asset('storage/' . $photoPath) }}" alt="Returned Parcel" class="w-full h-full object-cover rounded-lg">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($returnRequest->admin_decision)
                        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200">
                            <span class="font-bold text-blue-900 uppercase text-[10px] block">Storkia Admin</span>
                            <p class="text-sm font-semibold text-blue-950 mt-0.5">{{ $decisionLabel($returnRequest->admin_decision) }}</p>
                            @if($returnRequest->admin_remarks)
                                <p class="text-blue-900 mt-0.5">"{{ $returnRequest->admin_remarks }}"</p>
                            @endif
                            <p class="text-blue-800 mt-0.5">{{ $returnRequest->admin_resolved_at?->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif

                    @if($returnRequest->closed_reason)
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                            <span class="font-bold text-gray-700 uppercase text-[10px] block">Closed</span>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ str_replace('_', ' ', ucfirst($returnRequest->closed_reason)) }}</p>
                            <p class="text-gray-700 mt-0.5">{{ $returnRequest->closed_at?->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle overflow-hidden shadow-xs">
                <div class="p-5 bg-surface-subtle/60 border-b border-border-subtle flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">Items in the Order</h3>
                    <span class="text-xs text-text-muted">{{ $sellerOrder?->items->sum('quantity') }} item(s)</span>
                </div>
                <div class="divide-y divide-border-subtle/60">
                    @foreach($sellerOrder?->items ?? [] as $item)
                        <div class="p-5 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $item->product_name }}</p>
                                @if($item->variant_name)
                                    <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-mono font-bold text-primary-dark">&times; {{ $item->quantity }}</p>
                                <p class="text-xs text-text-muted">&#8369;{{ number_format((float) $item->line_total, 2) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($returnShipment)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-primary-dark">
                            Return Parcel Timeline
                            <span class="font-mono text-xs text-primary ml-1">({{ $returnShipment->tracking_number }})</span>
                        </h3>
                        <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-text-main text-xs font-bold">
                            {{ $returnShipment->status_label }}
                        </span>
                    </div>

                    <p class="text-xs text-text-muted">
                        Rider: <strong class="text-text-main">{{ $returnShipment->rider?->full_name ?? 'Unassigned' }}</strong> &bull;
                        Courier: <strong class="text-text-main">{{ $returnShipment->courier?->full_name ?? 'Unassigned' }}</strong>
                        @if($returnShipment->pickup_attempts_count > 0)
                            &bull; Pickup attempts: <strong class="text-text-main">{{ $returnShipment->pickup_attempts_count }} / {{ \App\Models\ReturnShipment::MAX_PICKUP_ATTEMPTS }}</strong>
                        @endif
                    </p>

                    <div class="relative pl-6 border-l-2 border-primary/30 space-y-5">
                        @forelse($returnShipment->events as $idx => $event)
                            <div class="relative">
                                <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-gray-300' }}"></span>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <span class="text-xs sm:text-sm font-bold text-text-main">{{ $event->title }}</span>
                                    <span class="text-[11px] text-text-muted">{{ $event->occurred_at?->format('M d, Y h:i A') }}</span>
                                </div>
                                @if($event->description)
                                    <p class="text-xs text-text-muted mt-1">{{ $event->description }}</p>
                                @endif
                                <p class="text-[11px] text-text-muted/80 mt-0.5">
                                    Actor: {{ $event->actor_type }}
                                    @if($event->location) &bull; Location: {{ $event->location }} @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-text-muted">No tracking events yet.</p>
                        @endforelse
                    </div>

                    @if($returnShipment->buyer_pickup_photo || $returnShipment->seller_delivery_photo)
                        <div class="flex flex-wrap gap-2 pt-1">
                            @if($returnShipment->buyer_pickup_photo)
                                <button type="button"
                                        @click.stop="openPhoto('{{ asset('storage/' . $returnShipment->buyer_pickup_photo) }}')"
                                        class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                    Photo at Buyer Pickup
                                </button>
                            @endif
                            @if($returnShipment->seller_delivery_photo)
                                <button type="button"
                                        @click.stop="openPhoto('{{ asset('storage/' . $returnShipment->seller_delivery_photo) }}')"
                                        class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                    Photo at Seller Delivery
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-2 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Buyer</h3>
                <p class="text-sm font-bold text-text-main">{{ $buyer?->full_name ?? 'Buyer' }}</p>
                <p class="text-text-muted">{{ $buyer?->email }}</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-2 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Seller</h3>
                <p class="text-sm font-bold text-text-main">{{ $returnRequest->sellerProfile?->business_name ?? 'Seller' }}</p>
                @if($returnRequest->logisticsProfile)
                    <p class="text-text-muted pt-1">Logistics: <strong class="text-text-main">{{ $returnRequest->logisticsProfile->business_name }}</strong></p>
                @endif
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Refund</h3>
                <div class="flex justify-between">
                    <span class="text-text-muted">Status</span>
                    <span class="font-bold text-text-main">{{ $refundStatusLabel }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Amount</span>
                    <span class="font-bold text-primary">&#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Buyer GCash</span>
                    <span class="font-mono font-bold text-text-main">{{ $returnRequest->refund_account_number ?: 'Not provided' }}</span>
                </div>
                @if($returnRequest->refund_reference)
                    <div class="flex justify-between">
                        <span class="text-text-muted">GCash Ref #</span>
                        <span class="font-mono font-bold text-text-main">{{ $returnRequest->refund_reference }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Paid</span>
                        <span class="font-bold text-text-main">{{ $returnRequest->refunded_at?->format('M d, Y h:i A') }}</span>
                    </div>
                @endif
                @if($returnRequest->refund_remarks)
                    <p class="text-text-muted">"{{ $returnRequest->refund_remarks }}"</p>
                @endif
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Money Settlement</h3>

                @if(!empty($snapshot))
                    <p class="text-text-muted leading-relaxed">
                        Storkia's own product commission is waived on a refunded order. Shipping and delivery earnings are kept by Logistics and the Rider.
                    </p>
                    <div class="space-y-1.5">
                        <div class="flex justify-between"><span class="text-text-muted">Seller's cancelled earnings</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['seller_net_cancelled'] ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-muted">Storkia commission waived</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['admin_commission_waived'] ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-muted">Logistics shipping earnings kept</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['logistics_earning'] ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-muted">Delivery rider earnings kept</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['rider_earning'] ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-muted">Return pickup (Rider)</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['return_rider_pay'] ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-muted">Return delivery (Courier)</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) ($snapshot['return_courier_pay'] ?? 0), 2) }}</span></div>
                        @if((float) ($snapshot['gcash_refund_recovery'] ?? 0) > 0)
                            <div class="flex justify-between"><span class="text-text-muted">GCash refund recovered from seller</span><span class="font-semibold text-text-main">&#8369;{{ number_format((float) $snapshot['gcash_refund_recovery'], 2) }}</span></div>
                        @endif
                        <div class="flex justify-between pt-2 border-t border-border-subtle">
                            <span class="font-bold text-text-main">Charged to the seller's wallet</span>
                            <span class="font-extrabold text-danger">&#8369;{{ number_format((float) ($snapshot['seller_deduction_total'] ?? $returnRequest->seller_deduction_amount), 2) }}</span>
                        </div>
                    </div>
                @else
                    <p class="text-text-muted leading-relaxed">
                        The exact amounts are worked out when the refund becomes due. Storkia's own commission is waived, and shipping and delivery earnings stay with Logistics and the Rider.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="photoOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="photoOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Return Photo</h3>
                    <button type="button" @click="photoOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                    <img :src="photoUrl" alt="Return Photo" class="max-h-[60vh] w-auto object-contain">
                </div>
            </div>
        </div>
    </template>
</div>
@endsection