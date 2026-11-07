@extends('layouts.seller', ['title' => 'Storkia - Return ' . $returnRequest->request_number])

@section('content')
@php
    $rr = $returnRequest;
    $d = $deduction;
    $fmt = fn ($date) => $date?->format('M d, Y h:i A');
    $money = fn ($value) => '₱' . number_format((float) $value, 2);

    $buyerPhotos = is_array($rr->evidence_photos) ? $rr->evidence_photos : [];
    $receiptPhotos = is_array($rr->seller_receipt_photos) ? $rr->seller_receipt_photos : [];
    $payMethod = $sellerOrder->effective_payment_method;

    $tone = match ($rr->status) {
        'refunded'                                  => 'bg-green-50 border-green-200 text-green-900',
        'rejected', 'closed', 'rejected_by_seller'  => 'bg-red-50 border-red-200 text-red-900',
        'refund_pending'                            => 'bg-blue-50 border-blue-200 text-blue-900',
        default                                     => 'bg-amber-50 border-amber-200 text-amber-900',
    };

    $badge = match ($rr->status) {
        'refunded'                                  => 'bg-green-600 text-white',
        'rejected', 'closed', 'rejected_by_seller'  => 'bg-red-600 text-white',
        'refund_pending'                            => 'bg-blue-600 text-white',
        default                                     => 'bg-amber-500 text-white',
    };

    [$nextTitle, $nextText] = match ($rr->status) {
        'pending_seller_response' => [
            'Action needed: respond to this request',
            'Please respond by ' . $fmt($rr->seller_response_deadline_at) . '. If you do not respond in time, the request is approved automatically and the deduction below still applies.',
        ],
        'rejected_by_seller' => [
            'You rejected this request',
            'The buyer can escalate your decision to Storkia Admin. Nothing is deducted from your wallet unless Admin overrules you.',
        ],
        'escalated' => [
            'Storkia Admin is reviewing this case',
            'The buyer escalated your rejection. Admin will review both sides and make the final decision. No action is needed from you.',
        ],
        'return_in_transit' => [
            'The item is on its way back to you',
            'A Storkia rider collects it from the buyer and it travels back through the sorting hub. '
                . ($rr->handover_deadline_at ? 'The buyer must hand it over by ' . $fmt($rr->handover_deadline_at) . '. ' : '')
                . 'If the buyer never hands it over, the request is closed and you keep your earnings.',
        ],
        'awaiting_seller_confirmation' => [
            'Action needed: confirm the returned parcel',
            'The parcel was delivered to you. Check it and confirm by ' . $fmt($rr->seller_receipt_deadline_at) . ', or it is confirmed automatically as received in good order.',
        ],
        'receipt_disputed' => [
            'Returned parcel under admin review',
            'You reported a problem with the returned parcel. Storkia Admin will review your notes and photos and decide. No action is needed from you.',
        ],
        'refund_pending' => [
            'Refund settled',
            'The refund was settled on your wallet. Storkia is now sending the buyer\'s refund' . ($rr->refund_due_at ? ' by ' . $fmt($rr->refund_due_at) : '') . '.',
        ],
        'refunded' => [
            'Refund completed',
            'The buyer was refunded on ' . $fmt($rr->refunded_at) . '. This request is closed.',
        ],
        'rejected' => [
            'Request rejected by Storkia Admin',
            'The order was completed without a refund and your earnings were released.',
        ],
        'closed' => [
            'Request closed',
            'The request ended without a refund and your earnings were released.',
        ],
        default => ['Status: ' . $rr->status_label, ''],
    };

    $steps = ['Return approved', 'Rider assigned', 'Picked up from buyer', 'At sorting hub', 'On the way to you', 'Delivered to you'];
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

    $returnAddress = $returnShipment ? (array) ($returnShipment->return_address_snapshot ?? []) : [];
    $returnAddressText = $returnAddress['full_address']
        ?? trim(implode(', ', array_filter([
            $returnAddress['barangay'] ?? null,
            $returnAddress['municipality'] ?? null,
            $returnAddress['province'] ?? null,
        ])));

    $showDeduction = ! in_array($rr->status, ['rejected', 'closed', 'rejected_by_seller'], true);
    $undecided = $rr->approved_resolution === null;
    $defaultAction = ($rr->requested_resolution === 'refund_only' || ! $canApproveReturn) ? 'approve_refund_only' : 'approve_return';

    // Wallet impact that matters most while the seller is still choosing: the worst case.
    $worstAfter = $d['settled'] ? null : ($canApproveReturn ? $d['after_return_refund'] : $d['after_refund_only']);
    $afterNow = $d['settled'] ? null : ($undecided ? $worstAfter : $d['after_now']);
@endphp

<div x-data="{
        evidencePreviewUrl: '',
        evidenceModalOpen: false,
        openEvidence(url) {
            this.evidencePreviewUrl = url;
            this.evidenceModalOpen = true;
        }
     }"
     class="max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('seller.orders.show', $sellerOrder->id) }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono">{{ $rr->request_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase {{ $badge }}">{{ $rr->status_label }}</span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                        {{ $payMethod }}
                    </span>
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Order <strong>{{ $sellerOrder->seller_order_number }}</strong> &bull;
                    {{ $rr->resolution_label }} &bull;
                    Requested {{ $fmt($rr->created_at) }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-[10px] font-bold uppercase text-text-muted">Refund to buyer</p>
            <p class="text-xl font-extrabold text-primary">{{ $money($rr->refund_amount) }}</p>
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

    <!-- What happens next -->
    <div class="p-5 rounded-2xl border {{ $tone }} text-xs">
        <h2 class="text-sm font-bold">{{ $nextTitle }}</h2>
        @if($nextText)
            <p class="mt-1 leading-relaxed">{{ $nextText }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <!-- Buyer's request -->
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <h3 class="text-sm font-bold text-primary-dark">Buyer's Request</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <p class="font-bold text-text-muted uppercase text-[10px]">Reason</p>
                        <p class="text-text-main font-semibold">{{ $rr->reason_category }}</p>
                    </div>
                    <div>
                        <p class="font-bold text-text-muted uppercase text-[10px]">Buyer asked for</p>
                        <p class="text-text-main font-semibold">
                            {{ $rr->requested_resolution === 'refund_only' ? 'Refund only (keeps the item)' : 'Return the item and get refunded' }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="font-bold text-text-muted uppercase text-[10px]">Buyer's description</p>
                    <p class="text-sm font-semibold text-text-main mt-0.5 leading-relaxed whitespace-pre-line">"{{ $rr->reason }}"</p>
                </div>

                @if(!empty($buyerPhotos))
                    <div>
                        <p class="font-bold text-text-muted uppercase text-[10px] mb-2">Buyer's photo evidence ({{ count($buyerPhotos) }})</p>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($buyerPhotos as $photoPath)
                                <button type="button"
                                        @click.stop="openEvidence('{{ asset('storage/' . ltrim($photoPath, '/')) }}')"
                                        class="w-20 h-20 rounded-xl overflow-hidden border border-border-subtle bg-surface-subtle hover:border-primary transition-all cursor-pointer p-1">
                                    <img src="{{ asset('storage/' . ltrim($photoPath, '/')) }}" alt="Buyer evidence" class="w-full h-full object-cover rounded-lg">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Respond -->
            @if($rr->status === 'pending_seller_response')
                <form action="{{ route('seller.returns.respond', $rr->id) }}"
                      method="POST"
                      x-data="{ action: @js(old('action', $defaultAction)), submitting: false }"
                      @submit="submitting = true"
                      class="bg-surface rounded-3xl border-2 border-red-200 p-6 shadow-xs space-y-4 text-xs">
                    @csrf
                    <h3 class="text-sm font-bold text-primary-dark">Your Response</h3>

                    <div class="grid grid-cols-1 gap-3">
                        @if($canApproveReturn)
                            <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5"
                                   :class="action === 'approve_return' ? 'border-green-600 bg-green-50' : 'border-border-subtle'">
                                <input type="radio" name="action" value="approve_return" x-model="action" class="mt-0.5">
                                <span>
                                    <span class="block font-bold text-text-main">Approve &mdash; the buyer sends the item back</span>
                                    <span class="block text-text-muted mt-0.5 leading-relaxed">
                                        A Storkia rider collects it and it travels back to you. You confirm the parcel when it arrives; the refund and your deduction are applied then, and your stock is restored.
                                    </span>
                                </span>
                            </label>
                        @endif

                        <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5"
                               :class="action === 'approve_refund_only' ? 'border-green-600 bg-green-50' : 'border-border-subtle'">
                            <input type="radio" name="action" value="approve_refund_only" x-model="action" class="mt-0.5">
                            <span>
                                <span class="block font-bold text-text-main">Approve &mdash; refund only, the buyer keeps the item</span>
                                <span class="block text-text-muted mt-0.5 leading-relaxed">
                                    The refund and your deduction are applied immediately. There are no return-trip fees, but the item does not come back to you.
                                </span>
                            </span>
                        </label>

                        <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5"
                               :class="action === 'reject' ? 'border-danger bg-red-50' : 'border-border-subtle'">
                            <input type="radio" name="action" value="reject" x-model="action" class="mt-0.5">
                            <span>
                                <span class="block font-bold text-text-main">Reject this request</span>
                                <span class="block text-text-muted mt-0.5 leading-relaxed">
                                    Nothing is deducted. The buyer can escalate to Storkia Admin, who makes the final decision.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div x-show="action === 'reject'" x-cloak>
                        <label class="block font-bold text-text-muted mb-1">Why are you rejecting? (10 to 300 characters) <span class="text-danger">*</span></label>
                        <textarea name="reason"
                                  rows="3"
                                  minlength="10"
                                  maxlength="300"
                                  :required="action === 'reject'"
                                  :disabled="action !== 'reject'"
                                  placeholder="Explain clearly why this request is declined. The buyer and Admin will read this."
                                  class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary">{{ old('reason') }}</textarea>
                    </div>

                    <div x-show="action !== 'reject'" x-cloak class="space-y-3">
                        <div>
                            <label class="block font-bold text-text-muted mb-1">Note to the buyer (optional)</label>
                            <textarea name="reason"
                                      rows="2"
                                      maxlength="300"
                                      :disabled="action === 'reject'"
                                      placeholder="For example: how to pack the item for the rider."
                                      class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary"></textarea>
                        </div>

                        <label class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200 cursor-pointer">
                            <input type="checkbox" name="acknowledge_deduction" value="1" :required="action !== 'reject'" class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary">
                            <span class="font-semibold text-amber-950 leading-relaxed">
                                I understand that approving this request cancels my earnings for this order and charges the deduction shown under
                                "What this costs you" to my wallet.
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                :disabled="submitting"
                                :class="action === 'reject' ? 'bg-danger' : 'bg-primary-dark hover:bg-text-main'"
                                class="px-5 py-2.5 rounded-xl text-white font-bold cursor-pointer disabled:opacity-50">
                            <span x-show="!submitting">Submit Response</span>
                            <span x-show="submitting" x-cloak>Submitting...</span>
                        </button>
                    </div>
                </form>
            @endif

            <!-- Confirm the returned parcel -->
            @if($rr->status === 'awaiting_seller_confirmation')
                <form action="{{ route('seller.returns.confirm-receipt', $rr->id) }}"
                      method="POST"
                      enctype="multipart/form-data"
                      x-data="{
                          outcome: @js(old('outcome', 'received_ok')),
                          fileNames: [],
                          filesError: '',
                          submitting: false,
                          validateFiles(event) {
                              this.filesError = '';
                              this.fileNames = [];
                              const files = Array.from(event.target.files || []);
                              if (files.length > 5) {
                                  this.filesError = 'Please select at most 5 photos.';
                                  event.target.value = '';
                                  return;
                              }
                              for (const f of files) {
                                  if (f.size > 5 * 1024 * 1024) {
                                      this.filesError = 'File \'' + f.name + '\' is larger than 5MB.';
                                      event.target.value = '';
                                      this.fileNames = [];
                                      return;
                                  }
                                  this.fileNames.push(f.name);
                              }
                          }
                      }"
                      @submit="if (filesError) { $event.preventDefault(); } else { submitting = true; }"
                      class="bg-surface rounded-3xl border-2 border-amber-300 p-6 shadow-xs space-y-4 text-xs">
                    @csrf
                    <h3 class="text-sm font-bold text-primary-dark">Confirm the Returned Parcel</h3>

                    <div class="grid grid-cols-1 gap-3">
                        <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5"
                               :class="outcome === 'received_ok' ? 'border-green-600 bg-green-50' : 'border-border-subtle'">
                            <input type="radio" name="outcome" value="received_ok" x-model="outcome" class="mt-0.5">
                            <span>
                                <span class="block font-bold text-text-main">I received the item in good order</span>
                                <span class="block text-text-muted mt-0.5 leading-relaxed">
                                    Your product stock is restored, the buyer is refunded, and the deduction is applied to your wallet now.
                                </span>
                            </span>
                        </label>

                        <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5"
                               :class="outcome === 'received_not_as_expected' ? 'border-danger bg-red-50' : 'border-border-subtle'">
                            <input type="radio" name="outcome" value="received_not_as_expected" x-model="outcome" class="mt-0.5">
                            <span>
                                <span class="block font-bold text-text-main">Something is wrong with the returned parcel</span>
                                <span class="block text-text-muted mt-0.5 leading-relaxed">
                                    For example a different item, a damaged item, missing parts or an empty box. Storkia Admin reviews your notes and photos and makes the final decision.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">
                            <span x-show="outcome === 'received_not_as_expected'">What is wrong? (at least 10 characters) <span class="text-danger">*</span></span>
                            <span x-show="outcome !== 'received_not_as_expected'" x-cloak>Notes (optional)</span>
                        </label>
                        <textarea name="notes"
                                  rows="3"
                                  maxlength="500"
                                  :required="outcome === 'received_not_as_expected'"
                                  :minlength="outcome === 'received_not_as_expected' ? 10 : 0"
                                  placeholder="Describe the condition of the parcel when it arrived."
                                  class="w-full p-3 rounded-xl border border-border-subtle outline-none focus:border-primary">{{ old('notes') }}</textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">
                            <span x-show="outcome === 'received_not_as_expected'">Photos of the parcel (1 to 5, max 5MB each) <span class="text-danger">*</span></span>
                            <span x-show="outcome !== 'received_not_as_expected'" x-cloak>Photos of the parcel (optional, up to 5)</span>
                        </label>
                        <input type="file"
                               name="receipt_photos[]"
                               multiple
                               :required="outcome === 'received_not_as_expected'"
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               @change="validateFiles($event)"
                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
                        <template x-if="filesError">
                            <p class="text-danger font-bold mt-1" x-text="filesError"></p>
                        </template>
                        <template x-if="fileNames.length && !filesError">
                            <p class="text-text-muted mt-1" x-text="fileNames.length + ' photo(s) selected'"></p>
                        </template>
                    </div>

                    <div x-show="outcome === 'received_ok'" x-cloak>
                        <label class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200 cursor-pointer">
                            <input type="checkbox" name="acknowledge_deduction" value="1" :required="outcome === 'received_ok'" class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary">
                            <span class="font-semibold text-amber-950 leading-relaxed">
                                I understand that confirming now charges
                                <strong>{{ $money($d['total_now'] ?? $d['total_return_refund']) }}</strong>
                                to my wallet and cancels my earnings for this order.
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                :disabled="submitting || !!filesError"
                                :class="outcome === 'received_ok' ? 'bg-primary-dark hover:bg-text-main' : 'bg-danger'"
                                class="px-5 py-2.5 rounded-xl text-white font-bold cursor-pointer disabled:opacity-50">
                            <span x-show="!submitting">Submit</span>
                            <span x-show="submitting" x-cloak>Submitting...</span>
                        </button>
                    </div>
                </form>
            @endif

            <!-- Return tracking -->
            @if($returnShipment)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                        <div>
                            <h3 class="text-sm font-bold text-primary-dark">Return Tracking</h3>
                            <p class="text-xs text-text-muted mt-0.5">
                                <span class="font-mono font-bold text-text-main">{{ $returnShipment->tracking_number }}</span>
                                &bull; {{ $returnShipment->status_label }}
                            </p>
                        </div>
                        <div class="text-xs text-text-muted text-right space-y-0.5">
                            @if($returnShipment->rider)
                                <p>Pickup rider: <strong class="text-text-main">{{ $returnShipment->rider->full_name }}</strong></p>
                            @endif
                            @if($returnShipment->courier)
                                <p>Delivery courier: <strong class="text-text-main">{{ $returnShipment->courier->full_name }}</strong></p>
                            @endif
                        </div>
                    </div>

                    @if($returnShipment->status === 'cancelled')
                        <p class="text-xs font-bold text-red-700">This return was cancelled.</p>
                    @else
                        <ol class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                            @foreach($steps as $i => $label)
                                @php $done = $stepIndex !== null && $i <= $stepIndex; @endphp
                                <li class="flex sm:flex-col items-center sm:text-center gap-2">
                                    <span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[11px] font-extrabold
                                                 {{ $done ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                                        {{ $done ? '✓' : $i + 1 }}
                                    </span>
                                    <span class="text-[11px] font-bold {{ $done ? 'text-text-main' : 'text-text-muted' }}">{{ $label }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if($returnAddressText)
                        <p class="text-xs text-text-muted">
                            Delivering back to: <strong class="text-text-main">{{ $returnAddressText }}</strong>
                        </p>
                    @endif

                    @if($returnShipment->events->isNotEmpty())
                        <div class="space-y-3 pt-1">
                            @foreach($returnShipment->events as $event)
                                <div class="flex gap-3 text-xs">
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
                        </div>
                    @endif
                </div>
            @endif

            <!-- Decisions -->
            @if($rr->seller_decision || $rr->admin_decision || $rr->seller_receipt_outcome)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="text-sm font-bold text-primary-dark">Decisions</h3>

                    @if($rr->seller_decision)
                        <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle">
                            <p class="font-bold text-text-main">
                                You:
                                @switch($rr->seller_decision)
                                    @case('approved_return') Approved the return. @break
                                    @case('approved_refund_only') Approved a refund only. @break
                                    @default Rejected the request.
                                @endswitch
                                @if($rr->auto_approved) <span class="text-text-muted font-semibold">(automatic, no response in time)</span> @endif
                            </p>
                            @if($rr->seller_decision_reason)
                                <p class="text-text-muted mt-0.5">"{{ $rr->seller_decision_reason }}"</p>
                            @endif
                        </div>
                    @endif

                    @if($rr->seller_receipt_outcome)
                        <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle space-y-2">
                            <p class="font-bold text-text-main">
                                Your check of the returned parcel:
                                {{ $rr->seller_receipt_outcome === 'received_ok' ? 'Received in good order.' : 'Reported a problem.' }}
                            </p>
                            @if($rr->seller_receipt_notes)
                                <p class="text-text-muted">"{{ $rr->seller_receipt_notes }}"</p>
                            @endif
                            @if(!empty($receiptPhotos))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($receiptPhotos as $photoPath)
                                        <button type="button"
                                                @click.stop="openEvidence('{{ asset('storage/' . ltrim($photoPath, '/')) }}')"
                                                class="w-16 h-16 rounded-xl overflow-hidden border border-border-subtle bg-white p-0.5 cursor-pointer">
                                            <img src="{{ asset('storage/' . ltrim($photoPath, '/')) }}" alt="Returned parcel" class="w-full h-full object-cover rounded-lg">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($rr->admin_decision)
                        <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle">
                            <p class="font-bold text-text-main">
                                Storkia Admin:
                                @switch($rr->admin_decision)
                                    @case('approved_return') Approved the return. @break
                                    @case('approved_refund_only') Approved a refund. @break
                                    @default Rejected the request.
                                @endswitch
                            </p>
                            @if($rr->admin_remarks)
                                <p class="text-text-muted mt-0.5">"{{ $rr->admin_remarks }}"</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Right column -->
        <div class="space-y-6">

            <!-- What this costs you -->
            @if($showDeduction)
                <div class="bg-surface rounded-3xl border-2 {{ $d['settled'] ? 'border-border-subtle' : 'border-amber-300' }} p-6 shadow-xs space-y-3 text-xs">
                    <div>
                        <h3 class="text-sm font-bold text-primary-dark">
                            {{ $d['settled'] ? 'What Was Charged to You' : 'What This Costs You' }}
                        </h3>
                        @if(! $d['settled'])
                            <p class="text-text-muted mt-0.5 leading-relaxed">
                                {{ match ($rr->status) {
                                    'pending_seller_response'      => 'Read this before you respond. A refunded order is not a sale.',
                                    'awaiting_seller_confirmation' => 'Read this before you confirm. A refunded order is not a sale.',
                                    default                        => 'This is what a refund on this order costs your wallet. A refunded order is not a sale.',
                                } }}
                            </p>
                        @endif
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-text-muted">Your earnings for this order</span>
                        <span class="font-bold text-text-main line-through">{{ $money($d['earnings_cancelled']) }}</span>
                    </div>
                    <p class="text-[11px] text-text-muted -mt-2">{{ $d['settled'] ? 'Cancelled.' : 'Cancelled if refunded.' }} You will not receive them.</p>

                    <div class="pt-2 border-t border-border-subtle space-y-1.5">
                        <p class="font-bold uppercase text-[10px] text-text-muted">Charged to your wallet</p>

                        <div class="flex justify-between gap-3">
                            <span class="text-text-muted">Shipping earning paid to Logistics</span>
                            <span class="font-semibold text-text-main">{{ $money($d['logistics']) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-text-muted">Delivery earning paid to the Rider</span>
                            <span class="font-semibold text-text-main">{{ $money($d['rider']) }}</span>
                        </div>

                        @if($payMethod === 'GCASH' || $d['gcash_recovery'] > 0)
                            <div class="flex justify-between gap-3">
                                <span class="text-text-muted">Refund amount (the buyer paid you directly by GCash)</span>
                                <span class="font-semibold text-text-main">{{ $money($d['gcash_recovery']) }}</span>
                            </div>
                        @endif

                        @if($d['settled'])
                            @if($d['return_paid'] > 0)
                                <div class="flex justify-between gap-3">
                                    <span class="text-text-muted">Return trip (Rider + Courier)</span>
                                    <span class="font-semibold text-text-main">{{ $money($d['return_paid']) }}</span>
                                </div>
                            @endif
                        @elseif($rr->approved_resolution === 'return_refund')
                            <div class="flex justify-between gap-3">
                                <span class="text-text-muted">
                                    Return trip (Rider + Courier){{ $d['return_estimate'] > 0 ? ', estimated until both legs are done' : '' }}
                                </span>
                                <span class="font-semibold text-text-main">{{ $money($d['return_paid'] + $d['return_estimate']) }}</span>
                            </div>
                        @elseif($rr->approved_resolution === 'refund_only')
                            @if($d['return_paid'] > 0)
                                <div class="flex justify-between gap-3">
                                    <span class="text-text-muted">Return trip already paid (Rider + Courier)</span>
                                    <span class="font-semibold text-text-main">{{ $money($d['return_paid']) }}</span>
                                </div>
                            @endif
                        @elseif($canApproveReturn)
                            <div class="flex justify-between gap-3">
                                <span class="text-text-muted">Return trip (Rider + Courier), only if approved as a return (estimate)</span>
                                <span class="font-semibold text-text-main">{{ $money($d['return_estimate']) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="pt-2 border-t border-border-subtle space-y-1.5">
                        @if($d['settled'])
                            <div class="flex justify-between gap-3 text-sm font-extrabold text-primary-dark">
                                <span>Total deducted</span>
                                <span class="text-danger">{{ $money($d['total_now']) }}</span>
                            </div>
                        @elseif($d['total_now'] !== null)
                            <div class="flex justify-between gap-3 text-sm font-extrabold text-primary-dark">
                                <span>Total to be deducted</span>
                                <span class="text-danger">{{ $money($d['total_now']) }}</span>
                            </div>
                        @elseif($canApproveReturn)
                            <div class="flex justify-between gap-3 font-extrabold text-primary-dark">
                                <span>If approved as a refund only</span>
                                <span class="text-danger">{{ $money($d['total_refund_only']) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 font-extrabold text-primary-dark">
                                <span>If approved as a return</span>
                                <span class="text-danger">{{ $money($d['total_return_refund']) }}</span>
                            </div>
                        @else
                            <div class="flex justify-between gap-3 text-sm font-extrabold text-primary-dark">
                                <span>Total if approved</span>
                                <span class="text-danger">{{ $money($d['total_refund_only']) }}</span>
                            </div>
                        @endif
                    </div>

                    @if(! $d['settled'])
                        <div class="pt-2 border-t border-border-subtle space-y-1.5">
                            <div class="flex justify-between gap-3">
                                <span class="text-text-muted">Your available balance now</span>
                                <span class="font-bold text-text-main">{{ $money($d['wallet_available']) }}</span>
                            </div>
                            @if($afterNow !== null)
                                <div class="flex justify-between gap-3">
                                    <span class="text-text-muted">
                                        {{ $undecided && $canApproveReturn ? 'After a return (worst case)' : 'Balance after the refund' }}
                                    </span>
                                    <span class="font-bold {{ $afterNow < 0 ? 'text-danger' : 'text-text-main' }}">{{ $money($afterNow) }}</span>
                                </div>
                            @endif
                        </div>

                        @if($afterNow !== null && $afterNow < 0)
                            <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-900 leading-relaxed space-y-1">
                                <p class="font-bold">Your balance will go negative by {{ $money(abs($afterNow)) }}.</p>
                                <p>
                                    That amount is recorded as arrears. At {{ $money($d['arrears_cap']) }} or more, GCash payments are blocked for your store until it is paid.
                                    You can remit arrears from
                                    <a href="{{ route('seller.settlements.index') }}" class="font-bold underline">Sales &amp; Settlements</a>.
                                </p>
                            </div>
                        @endif
                    @endif

                    <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted leading-relaxed">
                        Storkia waives its own {{ $d['commission_waived'] > 0 ? '(' . $money($d['commission_waived']) . ') ' : '' }}product commission on a refunded order.
                        Logistics, the Rider and the Courier keep their earnings: that is why their share is charged to you.
                    </div>
                </div>
            @endif

            <!-- Order -->
            <div class="bg-surface rounded-3xl border border-border-subtle overflow-hidden shadow-xs">
                <div class="p-5 bg-surface-subtle/60 border-b border-border-subtle flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">Order Items</h3>
                    <a href="{{ route('seller.orders.show', $sellerOrder->id) }}" class="text-xs font-bold text-primary hover:underline">View order</a>
                </div>
                <div class="divide-y divide-border-subtle/60">
                    @foreach($sellerOrder->items as $item)
                        <div class="p-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                    @if($item->product_image)
                                        <img src="{{ asset('storage/' . ltrim($item->product_image, '/')) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-text-main truncate">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-[11px] text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                                    @endif
                                    <p class="text-[11px] text-text-muted">Qty {{ $item->quantity }}</p>
                                </div>
                            </div>
                            <div class="text-xs font-bold text-text-main shrink-0">{{ $money($item->line_total) }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="p-4 bg-surface-subtle/40 border-t border-border-subtle flex justify-between text-xs font-extrabold text-primary-dark">
                    <span>Order total ({{ $payMethod }})</span>
                    <span class="text-primary">{{ $money($sellerOrder->total_amount) }}</span>
                </div>
            </div>

            <!-- Buyer's refund -->
            @if($rr->refund_status !== 'not_due')
                <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-1.5 text-xs">
                    <h3 class="text-sm font-bold text-primary-dark">Buyer's Refund</h3>
                    @if($rr->refund_status === 'refunded')
                        <p class="text-green-700 font-bold">Paid on {{ $fmt($rr->refunded_at) }}</p>
                    @else
                        <p class="text-blue-700 font-bold">
                            Queued for Storkia payout@if($rr->refund_due_at) &bull; due by {{ $fmt($rr->refund_due_at) }}@endif
                        </p>
                    @endif
                    <p class="text-text-muted">Storkia pays this refund to the buyer. You do not need to send anything.</p>
                </div>
            @endif
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="evidenceModalOpen"
             x-cloak
             @keydown.escape.window="evidenceModalOpen = false"
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="evidenceModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Photo Evidence</h3>
                    <button type="button" @click="evidenceModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                    <img :src="evidencePreviewUrl" alt="Evidence photo" class="max-h-[60vh] w-auto object-contain">
                </div>
            </div>
        </div>
    </template>
</div>
@endsection