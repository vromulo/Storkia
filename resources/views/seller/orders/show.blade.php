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
    $routeBadgeLabel = $pickupSnap['route_badge_label'] ?? null;

    $unifiedTimeline = collect();

    if ($shipment && $shipment->trackingLogs->isNotEmpty()) {
        foreach ($shipment->trackingLogs as $tLog) {
            $unifiedTimeline->push((object) [
                'title'       => $tLog->title,
                'description' => $tLog->description,
                'location'    => $tLog->location,
                'actor'       => $tLog->actor_type,
                'occurred_at' => $tLog->occurred_at ?? $tLog->created_at,
                'badge'       => 'Shipment',
            ]);
        }
    }

    if ($sellerOrder->statusHistories && $sellerOrder->statusHistories->isNotEmpty()) {
        foreach ($sellerOrder->statusHistories as $sHist) {
            $unifiedTimeline->push((object) [
                'title'       => 'Order Status: ' . ucwords(str_replace('_', ' ', (string) $sHist->to_status)),
                'description' => $sHist->remarks,
                'location'    => null,
                'actor'       => $sHist->actor_type,
                'occurred_at' => $sHist->created_at,
                'badge'       => 'Order',
            ]);
        }
    }

    if ($unifiedTimeline->isEmpty()) {
        $unifiedTimeline->push((object) [
            'title'       => 'Order Placed by Buyer',
            'description' => 'Order #' . $sellerOrder->seller_order_number . ' created and awaiting seller acceptance within 24 hours.',
            'location'    => ($deliverySnap['municipality'] ?? '') . ', ' . ($deliverySnap['province'] ?? ''),
            'actor'       => 'Buyer',
            'occurred_at' => $sellerOrder->created_at,
            'badge'       => 'Order',
        ]);
    }

    $unifiedTimeline = $unifiedTimeline->sortByDesc(fn ($e) => $e->occurred_at ? $e->occurred_at->timestamp : 0)->values();

    $hubOptions = collect($eligibleHubs ?? [])->map(function ($hub) {
        $origDist = round((float) ($hub->getAttribute('origin_distance_km') ?? 0), 1);
        $origCouriers = (int) ($hub->getAttribute('origin_couriers_count') ?? 0);
        $destHubName = $hub->getAttribute('destination_hub_name');
        $destHubMun = $hub->getAttribute('destination_hub_municipality');
        $destRiders = (int) ($hub->getAttribute('destination_riders_count') ?? 0);
        $destDist = $hub->getAttribute('destination_distance_km') !== null ? round((float) $hub->getAttribute('destination_distance_km'), 1) : null;
        $requiresInterHub = (bool) ($hub->getAttribute('requires_inter_hub_transfer') ?? false);
        $isServiceable = (bool) ($hub->getAttribute('is_route_serviceable') ?? true);
        $requiresSelfDrop = (bool) ($hub->getAttribute('requires_self_drop_off') ?? false);
        $unserviceableReason = (string) ($hub->getAttribute('unserviceable_reason') ?? '');

        $companyName = $hub->parentLogisticsProfile?->business_name ? ' (' . $hub->parentLogisticsProfile->business_name . ')' : '';
        $statusTag = $isServiceable
            ? ($requiresSelfDrop ? '[Self Drop-Off Only] ' : ($requiresInterHub ? '[Inter-Hub Transfer] ' : '[Direct Local Hub] '))
            : '[Unserviceable Route] ';

        return [
            'id'                          => (string) $hub->id,
            'label'                       => $statusTag . $hub->hub_name . ' — ' . $hub->municipality . ', ' . $hub->province . $companyName,
            'origin_distance_km'          => $origDist,
            'origin_couriers_count'       => $origCouriers,
            'destination_hub_name'        => $destHubName ?: 'None',
            'destination_hub_municipality'=> $destHubMun ?: '',
            'destination_distance_km'     => $destDist,
            'destination_riders'          => $destRiders,
            'requires_inter_hub_transfer' => $requiresInterHub,
            'is_serviceable'              => $isServiceable,
            'requires_self_drop_off'      => $requiresSelfDrop,
            'unserviceable_reason'        => $unserviceableReason,
        ];
    })->values();

    $lockedOriginHubId = (string) ($sellerOrder->origin_logistics_hub_id ?? ($pickupSnap['origin_hub_id'] ?? ''));
    $matchedLockedHub = $lockedOriginHubId !== ''
        ? $hubOptions->firstWhere('id', $lockedOriginHubId)
        : null;
    $firstServiceableHub = ($matchedLockedHub && !empty($matchedLockedHub['is_serviceable']))
        ? $matchedLockedHub
        : ($hubOptions->firstWhere('is_serviceable', true) ?? $hubOptions->first());
    $firstHubId = (string) ($firstServiceableHub['id'] ?? '');
    $initialHandover = ($firstServiceableHub && !empty($firstServiceableHub['requires_self_drop_off'])) ? 'self_drop_off' : 'courier_pickup';
@endphp

<div x-data="{
        suspiciousModalOpen: false,
        gcashScreenshotModalOpen: false,
        proofModalOpen: false,
        evidencePreviewUrl: '',
        evidenceModalOpen: false,
        handoverMethod: @js($initialHandover),
        manifestConfirmed: false,
        hubDropdownOpen: false,
        hubSortMode: 'nearest',
        selectedHubId: @js($firstHubId),
        hubOptions: @js($hubOptions),
        showAllManifestItems: false,
        showAllOrderedItems: false,
        showAllTimeline: false,
        get sortedHubOptions() {
            const list = [...this.hubOptions];
            if (this.hubSortMode === 'farthest') {
                return list.sort((a, b) => (b.origin_distance_km || 0) - (a.origin_distance_km || 0));
            }
            return list.sort((a, b) => {
                if (a.is_serviceable !== b.is_serviceable) return a.is_serviceable ? -1 : 1;
                return (a.origin_distance_km || 0) - (b.origin_distance_km || 0);
            });
        },
        get selectedHubObj() {
            return this.hubOptions.find(h => String(h.id) === String(this.selectedHubId)) || null;
        },
        get selectedHubLabel() {
            const found = this.selectedHubObj;
            return found ? found.label : 'Select approved local logistics hub...';
        },
        pickHub(hub) {
            this.selectedHubId = String(hub.id);
            if (hub.requires_self_drop_off) {
                this.handoverMethod = 'self_drop_off';
            }
            this.hubDropdownOpen = false;
        },
        openEvidenceModal(url) {
            this.evidencePreviewUrl = url;
            this.evidenceModalOpen = true;
        }
     }"
     class="max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('seller.orders.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono select-text">{{ $sellerOrder->seller_order_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $sellerOrder->status === 'pending_buyer_response' ? 'bg-brand-light/80 text-primary-dark border border-primary/30' : 'bg-brand-light/50 text-primary-dark border border-border-subtle' }}">
                        {{ $sellerOrder->status_label }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $payMethod === 'GCASH' || $payMethod === 'WALLET' ? 'bg-primary/15 text-primary-dark border border-primary/25' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                        {{ $payMethod }} ({{ strtoupper($sellerOrder->payment_status) }})
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-surface-subtle text-primary-dark border border-border-subtle">
                        ~{{ number_format($sellerOrder->calculated_distance_km, 1) }} km to Buyer
                    </span>
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Parent Order: <strong class="select-text">{{ $sellerOrder->order->order_number ?? 'N/A' }}</strong> &bull;
                    Ordered: <strong>{{ $sellerOrder->created_at?->format('M d, Y h:i A') }}</strong> &bull;
                    Order Total: <strong class="text-price">&#8369;{{ number_format((float) $sellerOrder->total_amount, 2) }}</strong>
                </p>
                @if($routeBadgeLabel)
                    <div class="mt-1.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-success/10 text-success border border-success/25 text-[11px] font-bold">
                        <span>Verified Route: {{ $routeBadgeLabel }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if($shipment)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('seller.orders.shipping-label', ['sellerOrder' => $sellerOrder->id, 'print' => 1]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Print Shipping Label ({{ $shipment->tracking_number }})
                </a>
                <a href="{{ route('seller.orders.packing-slip', ['sellerOrder' => $sellerOrder->id, 'print' => 1]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-colors cursor-pointer">
                    Print Packing Slip / Manifest
                </a>
            </div>
        @endif
    </div>

    @if($sellerOrder->is_unserviceable_exception && in_array($sellerOrder->status, ['cancelled', 'rejected'], true))
        <div class="p-6 rounded-3xl bg-brand-light/35 border-2 border-primary/30 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-primary-dark text-white text-[10px] font-extrabold uppercase">
                    Automated System Protection (0 SLA Strikes)
                </span>
                <h2 class="text-base font-bold text-primary-dark">Order Automatically Cancelled — Logistics / Rider Route Unavailable</h2>
            </div>
            <p class="text-xs text-text-main leading-relaxed">
                {{ $sellerOrder->rejection_reason ?: 'This order was automatically cancelled by the platform because no active Origin Courier + Destination Branch Hub + Local Municipality Rider route remained available.' }}
                <strong>0 SLA violation strikes</strong> were recorded against your store, and reserved product stock was restored automatically.
                @if($payMethod === 'WALLET')
                    The buyer's Storkia E-Wallet payment was refunded immediately.
                @else
                    Because the buyer selected Cash on Delivery (COD), no payment was collected or refunded.
                @endif
            </p>
        </div>
    @endif

    @if($sellerOrder->status === 'pending_buyer_response')
        <div class="p-6 rounded-3xl bg-brand-light/40 border-2 border-primary/30 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-bold text-primary-dark">Order Paused in Pending Queue — Waiting for Buyer Response</h2>
                <span class="px-3 py-1 rounded-full bg-primary-dark text-white text-xs font-extrabold uppercase">Pending Buyer Action</span>
            </div>
            <p class="text-xs text-text-main leading-relaxed">
                You reported the Buyer's GCash payment submission as suspicious on
                <strong>{{ $paymentSubmission?->suspicious_reported_at?->format('M d, Y h:i A') }}</strong>
                (Reason: <em>"{{ $paymentSubmission?->suspicious_reason }}"</em>).
                Order fulfillment is paused until the Buyer responds in their portal by either switching the payment method to <strong>Cash on Delivery (COD)</strong> or <strong>Cancelling the Order</strong>.
            </p>
        </div>
    @endif

    @if($paymentSubmission)
        <div class="p-6 rounded-3xl bg-brand-light/25 border-2 border-primary/25 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border-subtle">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-primary-dark">Buyer GCash Online Payment Submission</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $paymentSubmission->status === 'verified' ? 'bg-success/15 text-success border border-success/25' : ($paymentSubmission->is_suspicious ? 'bg-danger/10 text-danger border border-danger/25' : 'bg-primary/15 text-primary-dark border border-primary/25') }}">
                            {{ str_replace('_', ' ', $paymentSubmission->status) }}
                        </span>
                    </div>
                    <p class="text-xs text-text-muted mt-0.5">
                        Review the sender mobile number, payment timestamp, unique reference number, and WebP screenshot below.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <button type="button"
                            @click.stop="gcashScreenshotModalOpen = true"
                            class="px-4 py-2 rounded-xl bg-surface border border-border-subtle hover:border-primary text-primary-dark text-xs font-bold transition-colors cursor-pointer">
                        View Payment Screenshot (WebP)
                    </button>
                    @if(!$paymentSubmission->switched_to_cod && in_array($sellerOrder->status, ['pending_seller_acceptance', 'accepted', 'preparing'], true))
                        @if($paymentSubmission->status !== 'verified')
                            <form action="{{ route('seller.orders.verify-online-payment', $sellerOrder->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
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
                <div class="p-3.5 rounded-2xl bg-surface border border-border-subtle">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Sender GCash Number</span>
                    <p class="font-mono font-bold text-sm text-text-main mt-0.5 select-text">{{ $paymentSubmission->sender_gcash_number }}</p>
                    <span class="text-[11px] text-text-muted">To: {{ $paymentSubmission->seller_gcash_name }} ({{ $paymentSubmission->seller_gcash_number }})</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-surface border border-border-subtle">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Unique Reference Number</span>
                    <p class="font-mono font-bold text-sm text-primary-dark mt-0.5 select-text">{{ $paymentSubmission->reference_number }}</p>
                    <span class="text-[11px] text-text-muted">Ref Code: <strong class="font-mono select-text">{{ $paymentSubmission->reference_code }}</strong></span>
                </div>
                <div class="p-3.5 rounded-2xl bg-surface border border-border-subtle">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Date &amp; Time Paid</span>
                    <p class="font-bold text-sm text-text-main mt-0.5">{{ $paymentSubmission->paid_at?->format('M d, Y h:i A') }}</p>
                    <span class="text-[11px] text-text-muted">Submitted {{ $paymentSubmission->created_at?->diffForHumans() }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-surface border border-border-subtle">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Submitted Amount</span>
                    <p class="font-serif font-extrabold text-base text-price mt-0.5">&#8369;{{ number_format((float) $paymentSubmission->submitted_amount, 2) }}</p>
                    @if($paymentSubmission->switched_to_cod)
                        <span class="text-[11px] font-bold text-primary-dark">Switched to COD on {{ $paymentSubmission->switched_to_cod_at?->format('M d, h:i A') }}</span>
                    @elseif($paymentSubmission->seller_verified_at)
                        <span class="text-[11px] font-bold text-success">Verified {{ $paymentSubmission->seller_verified_at->format('M d, h:i A') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($sellerOrder->status === 'pending_seller_acceptance')
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-brand-light/80 text-primary-dark border border-primary/30 text-[10px] font-extrabold uppercase">
                        24-Hour Platform Auto-Rejection Timer Active
                    </span>
                    <h2 class="text-base font-bold text-primary-dark">Step 1: Accept Incoming Order Within 24 Hours</h2>
                </div>
                <p class="text-xs text-text-muted leading-relaxed">
                    Please accept this order before <strong>{{ $sellerOrder->acceptance_deadline_at?->format('M d, Y h:i A') }}</strong>
                    ({{ $sellerOrder->acceptance_deadline_at?->diffForHumans() }}).
                    This order's Origin Hub, Destination Branch Hub, and Last-Mile Barangay Rider coverage in <strong>Brgy. {{ $deliverySnap['barangay'] ?? '' }}, {{ $deliverySnap['municipality'] ?? '' }}, {{ $deliverySnap['province'] ?? '' }}</strong> were verified at checkout.
                </p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
                <form action="{{ route('seller.orders.accept', $sellerOrder->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-6 py-3 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                        Accept Order &amp; Start Preparing
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if(in_array($sellerOrder->status, ['accepted', 'preparing'], true))
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Step 2: Verify Product Checklist &amp; Select Serviceable Logistics Hub</h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Only Logistics partners with an active <strong>Origin Hub on your island</strong> connected to a <strong>Destination Hub in {{ $deliverySnap['municipality'] ?? '' }}, {{ $deliverySnap['province'] ?? '' }}</strong> with an approved Rider covering <strong>Brgy. {{ $deliverySnap['barangay'] ?? 'Buyer Barangay' }}</strong> can be selected.
                    </p>
                </div>
                <div class="inline-flex items-center gap-1.5 text-[11px]">
                    <span class="text-text-muted font-bold">Sort Hubs:</span>
                    <button type="button"
                            @click="hubSortMode = 'nearest'"
                            :class="hubSortMode === 'nearest' ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer">
                        Nearest First
                    </button>
                    <button type="button"
                            @click="hubSortMode = 'farthest'"
                            :class="hubSortMode === 'farthest' ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer">
                        Farthest First
                    </button>
                </div>
            </div>

            <form action="{{ route('seller.orders.ready-for-pickup', $sellerOrder->id) }}" method="POST" class="space-y-5 text-xs">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label @click="if (!selectedHubObj || !selectedHubObj.requires_self_drop_off) handoverMethod = 'courier_pickup'"
                           :class="[
                               handoverMethod === 'courier_pickup' ? 'border-primary bg-primary/5 shadow-xs' : 'border-border-subtle bg-surface-subtle/50 hover:border-primary/40',
                               (selectedHubObj && selectedHubObj.requires_self_drop_off) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                           ]"
                           class="p-4 rounded-2xl border-2 flex items-start gap-3 transition-all">
                        <input type="radio"
                               name="handover_method"
                               value="courier_pickup"
                               x-model="handoverMethod"
                               :disabled="selectedHubObj && selectedHubObj.requires_self_drop_off"
                               class="mt-0.5 text-primary">
                        <div>
                            <span class="font-bold text-sm text-primary-dark block">Option A: Request First-Mile Courier Pickup</span>
                            <span class="text-text-muted mt-0.5 block leading-relaxed">
                                The selected Origin Hub will dispatch an active Courier covering <strong>{{ $pickupSnap['municipality'] ?? 'your area' }}</strong> to collect the package from your store.
                            </span>
                            <template x-if="selectedHubObj && selectedHubObj.requires_self_drop_off">
                                <span class="text-danger font-bold text-[11px] mt-1 block">
                                    Selected Origin Hub currently has 0 active Couriers — use Option B (Self Drop-Off) or pick another hub.
                                </span>
                            </template>
                        </div>
                    </label>
                    <label @click="handoverMethod = 'self_drop_off'"
                           :class="handoverMethod === 'self_drop_off' ? 'border-primary-dark bg-brand-light/35 shadow-xs' : 'border-border-subtle bg-surface-subtle/50 hover:border-primary/40'"
                           class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 transition-all">
                        <input type="radio" name="handover_method" value="self_drop_off" x-model="handoverMethod" class="mt-0.5 text-primary-dark">
                        <div>
                            <span class="font-bold text-sm text-primary-dark block">Option B: Seller Self Drop-Off at Logistics Hub</span>
                            <span class="text-text-muted mt-0.5 block leading-relaxed">
                                Print and attach the QR Shipping Label yourself and bring the package directly to the selected Origin Sorting Hub.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2 relative" @click.away="hubDropdownOpen = false">
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Select Approved Local Logistics Hub <span class="text-danger">*</span></label>
                        <input type="hidden" name="logistics_hub_id" :value="selectedHubId" required>
                        <button type="button"
                                @click="hubDropdownOpen = !hubDropdownOpen"
                                class="w-full p-3 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold text-xs flex items-center justify-between text-left transition-colors cursor-pointer">
                            <span class="truncate pr-2 text-text-main" x-text="selectedHubLabel"></span>
                            <svg class="w-4 h-4 text-text-muted shrink-0 transition-transform" :class="hubDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="hubDropdownOpen"
                             x-cloak
                             x-transition
                             class="absolute left-0 right-0 top-full mt-1.5 bg-surface border border-border-subtle rounded-2xl shadow-xl max-h-64 overflow-y-auto custom-scrollbar z-30 divide-y divide-border-subtle/50">
                            <template x-for="hub in sortedHubOptions" :key="'hub_' + hub.id">
                                <button type="button"
                                        @click="pickHub(hub)"
                                        :class="hub.is_serviceable ? 'hover:bg-brand-light/30 cursor-pointer' : 'bg-surface-subtle/70 opacity-65 cursor-pointer'"
                                        class="w-full px-4 py-3 text-left flex flex-col gap-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate font-bold text-text-main" x-text="hub.label"></span>
                                        <span x-show="String(selectedHubId) === String(hub.id)" class="text-primary font-bold shrink-0">&#10003;</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[10px]">
                                        <span class="px-2 py-0.5 rounded bg-brand-light/50 text-primary-dark font-mono font-bold"
                                              x-text="'Origin: ' + hub.origin_distance_km + ' km (' + hub.origin_couriers_count + ' Courier(s))'"></span>
                                        <template x-if="hub.is_serviceable">
                                            <span class="px-2 py-0.5 rounded bg-success/15 text-success font-bold"
                                                  x-text="(hub.requires_inter_hub_transfer ? 'Transfer -> ' : 'Same Hub -> ') + hub.destination_hub_name + ' (' + hub.destination_riders + ' Rider(s) in ' + hub.destination_hub_municipality + ')'"></span>
                                        </template>
                                        <template x-if="!hub.is_serviceable">
                                            <span class="px-2 py-0.5 rounded bg-danger/10 text-danger font-bold"
                                                  x-text="hub.unserviceable_reason || 'No Destination Last-Mile Rider coverage'"></span>
                                        </template>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <template x-if="selectedHubObj">
                            <div class="mt-2 p-3 rounded-xl border text-[11px] flex flex-wrap items-center justify-between gap-2"
                                 :class="selectedHubObj.is_serviceable ? 'bg-brand-light/25 border-border-subtle text-text-main' : 'bg-danger/10 border-danger/30 text-danger font-semibold'">
                                <div>
                                    <span>Origin Hub Distance: <strong x-text="selectedHubObj.origin_distance_km + ' km'"></strong> (<span x-text="selectedHubObj.origin_couriers_count + ' Active Courier(s)'"></span>)</span>
                                    <span class="mx-1.5">&bull;</span>
                                    <template x-if="selectedHubObj.is_serviceable">
                                        <span>
                                            <span x-show="selectedHubObj.requires_inter_hub_transfer" class="font-bold text-primary-dark">Inter-Hub Linehaul to </span>
                                            <span x-show="!selectedHubObj.requires_inter_hub_transfer">Direct Local Hub: </span>
                                            <strong x-text="selectedHubObj.destination_hub_name"></strong>
                                            (<span x-text="selectedHubObj.destination_riders + ' Approved Rider(s) in ' + selectedHubObj.destination_hub_municipality"></span>)
                                        </span>
                                    </template>
                                    <template x-if="!selectedHubObj.is_serviceable">
                                        <span x-text="selectedHubObj.unserviceable_reason"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Total Packages Count</label>
                        <input type="number" name="package_count" min="1" max="10" value="1" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold text-xs outline-none focus:border-primary">
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
                                @foreach($sellerOrder->items as $mIdx => $item)
                                    <tr x-show="{{ $mIdx }} < 5 || showAllManifestItems">
                                        <td class="py-2.5 px-3 font-bold text-text-main">{{ $item->product_name }}</td>
                                        <td class="py-2.5 px-3 text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) ?: 'Standard' }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-dark">&times; {{ $item->quantity }}</td>
                                        <td class="py-2.5 px-3 text-right text-text-muted">{{ number_format((float) $item->line_weight, 2) }} kg</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($sellerOrder->items->count() > 5)
                        <div>
                            <button type="button"
                                    @click="showAllManifestItems = !showAllManifestItems"
                                    class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                    x-text="showAllManifestItems ? 'See Less Manifest Items' : 'See All {{ $sellerOrder->items->count() }} Manifest Items'"></button>
                        </div>
                    @endif
                    <label class="flex items-center gap-2.5 pt-1 cursor-pointer">
                        <input type="checkbox" name="manifest_confirmed" value="1" x-model="manifestConfirmed" required class="w-4 h-4 rounded text-primary focus:ring-primary">
                        <span class="font-bold text-text-main">
                            I confirm that all ordered items above have been inspected, packed, and match the packing manifest.
                        </span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="!manifestConfirmed || (selectedHubObj && !selectedHubObj.is_serviceable)"
                            :class="(manifestConfirmed && (!selectedHubObj || selectedHubObj.is_serviceable)) ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-surface-subtle text-text-muted border border-border-subtle cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold shadow-xs transition-colors">
                        Confirm Package Ready &amp; Generate QR Shipping Label
                    </button>
                </div>
            </form>
        </div>
    @endif

    @php
        $returnRequest = $sellerOrder->returnRequest;
    @endphp

    @if($returnRequest)
        @php
            $rrNeedsResponse = $returnRequest->isAwaitingSellerResponse();
            $rrNeedsReceipt  = $returnRequest->status === \App\Models\ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM;
            $rrUrgent        = $rrNeedsResponse || $rrNeedsReceipt;
            $rrDeadline      = $rrNeedsResponse
                ? $returnRequest->seller_response_deadline_at
                : ($rrNeedsReceipt ? $returnRequest->seller_receipt_deadline_at : null);
            $rrShipment      = $returnRequest->returnShipment;
        @endphp
        <div class="p-6 rounded-3xl border-2 space-y-4 {{ $rrUrgent ? 'bg-danger/10 border-danger/30' : ($returnRequest->isTerminal() ? 'bg-surface border-border-subtle' : 'bg-brand-light/35 border-primary/30') }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold {{ $rrUrgent ? 'text-danger' : 'text-primary-dark' }}">Return / Refund Request</h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        {{ $returnRequest->request_number }}
                        &bull; Requested {{ $returnRequest->created_at?->format('M d, Y h:i A') }}
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full bg-surface border border-border-subtle text-text-main text-xs font-extrabold uppercase">
                    {{ $returnRequest->status_label }}
                </span>
            </div>

            <div class="p-4 rounded-2xl bg-surface border border-border-subtle space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Reason</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">{{ $returnRequest->reason_category }}</p>
                    </div>
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Wants</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">{{ $returnRequest->resolution_label }}</p>
                    </div>
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Refund Amount</span>
                        <p class="text-sm font-semibold text-price mt-0.5">&#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>
                    </div>
                </div>
                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Explanation</span>
                    <p class="text-sm font-semibold text-text-main mt-0.5 select-text">"{{ $returnRequest->reason }}"</p>
                </div>
                @if($rrShipment)
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Return Parcel</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">
                            {{ $rrShipment->tracking_number }} &bull; {{ $rrShipment->status_label }}
                        </p>
                    </div>
                @endif
            </div>

            @if($rrNeedsResponse)
                <p class="text-xs text-danger font-semibold">
                    Respond before {{ $rrDeadline?->format('M d, Y h:i A') }}, or the request is approved automatically.
                    The exact amount that will be deducted from your wallet is shown before you approve anything.
                </p>
            @elseif($rrNeedsReceipt)
                <p class="text-xs text-danger font-semibold">
                    The returned parcel was delivered to you. Check it and confirm before {{ $rrDeadline?->format('M d, Y h:i A') }},
                    or it is confirmed automatically.
                </p>
            @endif

            <div class="flex justify-end">
                <a href="{{ route('seller.returns.show', $returnRequest->id) }}"
                   class="px-5 py-2.5 rounded-xl text-xs font-bold transition-colors cursor-pointer {{ $rrUrgent ? 'bg-primary-dark hover:bg-primary text-white' : 'bg-surface border border-border-subtle text-primary-dark hover:border-primary' }}">
                    @if($rrNeedsResponse)
                        Review &amp; Respond
                    @elseif($rrNeedsReceipt)
                        Check Parcel &amp; Confirm Receipt
                    @else
                        View Return Details
                    @endif
                </a>
            </div>
        </div>
    @elseif($sellerOrder->status === 'disputed')
        <div class="p-6 rounded-3xl bg-danger/10 border-2 border-danger/30 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-danger">Buyer Return / Delivery Dispute Request</h2>
                    <p class="text-xs text-text-main mt-0.5">
                        Reported on {{ $sellerOrder->dispute_reported_at?->format('M d, Y h:i A') }}
                        @if($extra?->dispute_reason_category)
                            &bull; Category: <strong>{{ $extra->dispute_reason_category }}</strong>
                        @endif
                    </p>
                </div>
                @if($extra?->is_escalated_to_admin)
                    <span class="px-3 py-1 rounded-full bg-danger text-white text-xs font-extrabold uppercase">Escalated to Admin</span>
                @elseif($extra?->seller_return_decision)
                    <span class="px-3 py-1 rounded-full bg-surface border border-danger/30 text-danger text-xs font-extrabold uppercase">
                        Seller Decision: {{ ucfirst($extra->seller_return_decision) }}
                    </span>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-surface border border-border-subtle space-y-3 text-xs">
                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Explanation</span>
                    <p class="text-sm font-semibold text-text-main mt-0.5 select-text">"{{ $sellerOrder->dispute_reason }}"</p>
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

            <div class="p-4 rounded-2xl bg-surface border border-border-subtle text-xs">
                <span class="font-bold text-primary-dark block">Handled by Storkia Admin</span>
                <p class="text-text-muted mt-0.5 leading-relaxed">
                    This dispute was opened before the Return &amp; Refund system and is handled by Storkia Admin.
                    No action is needed from you on this page.
                </p>
            </div>
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
                    @foreach($sellerOrder->items as $itemIdx => $item)
                        <div x-show="{{ $itemIdx }} < 4 || showAllOrderedItems"
                             x-transition
                             class="p-5 flex items-center justify-between gap-4">
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
                                    <p class="text-xs text-text-muted">&#8369;{{ number_format((float) $item->discounted_unit_price, 2) }} &times; {{ $item->quantity }}</p>
                                </div>
                            </div>
                            <div class="text-sm font-bold text-price shrink-0">
                                &#8369;{{ number_format((float) $item->line_total, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($sellerOrder->items->count() > 4)
                    <div class="px-5 py-3 bg-surface-subtle/30 border-t border-border-subtle flex justify-center">
                        <button type="button"
                                @click="showAllOrderedItems = !showAllOrderedItems"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                            <span x-text="showAllOrderedItems ? 'See Less Ordered Items' : 'See All {{ $sellerOrder->items->count() }} Ordered Items (+' + ({{ $sellerOrder->items->count() }} - 4) + ' more)'"></span>
                        </button>
                    </div>
                @endif

                @if($extra && $extra->buyer_note)
                    <div class="px-5 py-3.5 bg-brand-light/30 border-t border-border-subtle text-xs">
                        <span class="font-bold uppercase tracking-wider text-primary-dark">Buyer Note to Seller:</span>
                        <p class="text-text-main font-semibold italic mt-0.5 select-text">"{{ $extra->buyer_note }}"</p>
                    </div>
                @endif

                <div class="p-5 bg-surface-subtle/40 border-t border-border-subtle space-y-1.5 text-xs sm:text-sm">
                    @if($extra && (float) $extra->original_subtotal_amount > 0 && (float) $extra->original_subtotal_amount !== (float) $sellerOrder->subtotal_amount)
                        <div class="flex justify-between text-text-muted">
                            <span>Original Merchandise Subtotal:</span>
                            <span class="font-semibold text-text-main">&#8369;{{ number_format((float) $extra->original_subtotal_amount, 2) }}</span>
                        </div>
                    @endif
                    @if($extra && (float) $extra->store_voucher_discount > 0)
                        <div class="flex justify-between text-primary font-bold">
                            <span>Store Voucher ({{ $extra->store_voucher_code }}):</span>
                            <span>-&#8369;{{ number_format((float) $extra->store_voucher_discount, 2) }}</span>
                        </div>
                    @endif
                    @if($extra && (float) $extra->platform_voucher_discount > 0)
                        <div class="flex justify-between text-primary-dark font-bold">
                            <span>Platform Voucher Share ({{ $extra->platform_voucher_code }}):</span>
                            <span>-&#8369;{{ number_format((float) $extra->platform_voucher_discount, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-text-muted">
                        <span>Net Product Subtotal:</span>
                        <span class="font-semibold text-text-main">&#8369;{{ number_format((float) $sellerOrder->subtotal_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-text-muted">
                        <span>Shipping Fee ({{ number_format((float) $sellerOrder->total_weight, 2) }} kg):</span>
                        <span class="font-semibold text-text-main">
                            &#8369;{{ number_format((float) $sellerOrder->shipping_fee, 2) }}
                            @if($extra && (float) $extra->shipping_voucher_discount > 0)
                                <span class="text-primary font-bold">(-&#8369;{{ number_format((float) $extra->shipping_voucher_discount, 2) }} {{ $extra->shipping_voucher_code }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-border-subtle text-base font-extrabold text-primary-dark">
                        <span>Total Order Amount ({{ $payMethod }}):</span>
                        <span class="text-price">&#8369;{{ number_format((float) $sellerOrder->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">
                        Order &amp; Shipment Tracking Timeline
                        @if($shipment)
                            <span class="font-mono text-xs text-primary ml-1 select-text">({{ $shipment->tracking_number }})</span>
                        @endif
                    </h3>
                    <span class="text-[11px] font-bold text-text-muted">{{ $unifiedTimeline->count() }} milestone(s)</span>
                </div>

                <div class="relative pl-6 border-l-2 border-primary/30 space-y-5">
                    @foreach($unifiedTimeline as $idx => $log)
                        <div x-show="{{ $idx }} < 5 || showAllTimeline"
                             x-transition
                             class="relative">
                            <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-surface {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-border-subtle' }}"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase {{ $log->badge === 'Shipment' ? 'bg-primary/10 text-primary' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                                        {{ $log->badge }}
                                    </span>
                                    <span class="text-xs sm:text-sm font-bold text-text-main">{{ $log->title }}</span>
                                </div>
                                <span class="text-[11px] text-text-muted">{{ $log->occurred_at?->format('M d, Y h:i A') }}</span>
                            </div>
                            @if($log->description)
                                <p class="text-xs text-text-muted mt-1">{{ $log->description }}</p>
                            @endif
                            @if($log->location || $log->actor)
                                <p class="text-[11px] text-text-muted/80 mt-0.5">
                                    @if($log->actor) Actor: {{ $log->actor }} @endif
                                    @if($log->location) &bull; Location: {{ $log->location }} @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($unifiedTimeline->count() > 5)
                    <div class="pt-2 flex justify-center">
                        <button type="button"
                                @click="showAllTimeline = !showAllTimeline"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                            <span x-text="showAllTimeline ? 'See Less Milestones' : 'See All {{ $unifiedTimeline->count() }} Milestones (+' + ({{ $unifiedTimeline->count() }} - 5) + ' older)'"></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Buyer Delivery Snapshot</h3>
                <p class="text-sm font-bold text-text-main select-text">{{ $deliverySnap['recipient_name'] ?? 'Buyer' }}</p>
                <p class="text-text-muted font-mono select-text">{{ $deliverySnap['phone_number'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed select-text">{{ $deliverySnap['full_address'] ?? '' }}</p>
                <div class="pt-2 border-t border-border-subtle flex items-center justify-between text-[11px]">
                    <span class="text-text-muted">Distance from Store:</span>
                    <strong class="font-mono text-primary-dark">~{{ number_format($sellerOrder->calculated_distance_km, 1) }} km</strong>
                </div>
            </div>

            @if($latestProof)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="font-bold uppercase tracking-wider text-success">Rider Proof of Delivery</h3>
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

    @if($paymentSubmission)
        <template x-teleport="body">
            <div x-show="suspiciousModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[190] flex items-center justify-center p-4">
                <div @click="suspiciousModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-base font-bold text-danger">Report Suspicious GCash Payment</h3>
                        <button type="button" @click="suspiciousModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
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
                                      class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="suspiciousModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-danger hover:opacity-90 text-white font-bold transition-opacity cursor-pointer">Send Report to Buyer &amp; Pause Order</button>
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
                        <button type="button" @click="gcashScreenshotModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
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
                    <button type="button" @click="evidenceModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
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
                        <button type="button" @click="proofModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
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