@extends('layouts.logistics', ['title' => 'Storkia - Return ' . $returnShipment->tracking_number])

@section('content')
@php
    $RS = \App\Models\ReturnShipment::class;

    $pickup = $returnShipment->pickup_address_snapshot ?? [];
    $hubSnap = $returnShipment->hub_address_snapshot ?? [];
    $sellerAddr = $returnShipment->return_address_snapshot ?? [];
    $hubName = $hubSnap['hub_name'] ?? ($hubSnap['name'] ?? $profile->business_name);
    $status = $returnShipment->status;

    $requestActive = $returnRequest
        && $returnRequest->status === \App\Models\ReturnRequest::STATUS_RETURN_IN_TRANSIT;

    $canAssignRider = $requestActive && in_array($status, [$RS::STATUS_AWAITING_RIDER, $RS::STATUS_PICKUP_FAILED, $RS::STATUS_RIDER_ASSIGNED], true);
    $canReceiveAtHub = $requestActive && $status === $RS::STATUS_PICKED_UP_FROM_BUYER;
    $canAssignCourier = $requestActive && in_array($status, [$RS::STATUS_RECEIVED_AT_HUB, $RS::STATUS_COURIER_ASSIGNED], true);

    $attempts = (int) $returnShipment->pickup_attempts_count;

    $badgeClass = match($status) {
        'delivered_to_seller' => 'bg-green-100 text-green-700 border-green-200',
        'pickup_failed', 'cancelled' => 'bg-red-100 text-red-700 border-red-200',
        'awaiting_rider_assignment' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        default => 'bg-blue-100 text-blue-700 border-blue-200',
    };

    $steps = [
        ['label' => 'Return Approved',      'at' => $returnShipment->created_at],
        ['label' => 'Rider Assigned',       'at' => $returnShipment->rider_assigned_at],
        ['label' => 'Picked Up From Buyer', 'at' => $returnShipment->picked_up_from_buyer_at],
        ['label' => 'Received at Hub',      'at' => $returnShipment->arrived_at_hub_at],
        ['label' => 'Courier Assigned',     'at' => $returnShipment->courier_assigned_at],
        ['label' => 'Out for Delivery',     'at' => $returnShipment->picked_up_from_hub_at],
        ['label' => 'Delivered to Seller',  'at' => $returnShipment->delivered_to_seller_at],
    ];

    $evidencePhotos = is_array($returnRequest?->evidence_photos) ? $returnRequest->evidence_photos : [];
@endphp

<div x-data="{
        photoModalOpen: false,
        photoUrl: '',
        parcelChecked: false,
        openPhoto(url) {
            this.photoUrl = url;
            this.photoModalOpen = true;
        }
     }"
     class="max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('logistics.returns.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono">{{ $returnShipment->tracking_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">
                        {{ $returnShipment->status_label }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                        Return to Seller
                    </span>
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Request: <strong>{{ $returnRequest?->request_number ?? 'N/A' }}</strong> &bull;
                    Seller Order: <strong>{{ $sellerOrder?->seller_order_number ?? 'N/A' }}</strong> &bull;
                    Parent Order: <strong>{{ $sellerOrder?->order?->order_number ?? 'N/A' }}</strong> &bull;
                    Weight: <strong>{{ number_format((float) $returnShipment->total_weight, 2) }} kg</strong>
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

    <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs">
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
            @foreach($steps as $step)
                <div class="flex flex-col items-center text-center gap-1.5">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-extrabold {{ $step['at'] ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                        @if($step['at'])
                            &#10003;
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    <span class="text-[11px] font-bold {{ $step['at'] ? 'text-primary-dark' : 'text-text-muted' }}">{{ $step['label'] }}</span>
                    <span class="text-[10px] text-text-muted">{{ $step['at']?->format('M d, h:i A') ?? 'Pending' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if($returnRequest && ! $requestActive && ! in_array($status, [$RS::STATUS_DELIVERED_TO_SELLER], true))
        <div class="p-5 rounded-3xl bg-yellow-50 border border-yellow-200 text-xs text-yellow-900 font-semibold">
            This return request is no longer active ({{ $returnRequest->status_label }}), so the parcel does not need to move any further.
        </div>
    @endif

    @if($status === $RS::STATUS_PICKUP_FAILED)
        <div class="p-6 rounded-3xl bg-red-50 border-2 border-red-200 space-y-1.5">
            <h2 class="text-base font-bold text-red-900">Return Pickup Failed (Attempt {{ $attempts }} of {{ $maxPickupAttempts }})</h2>
            <p class="text-xs text-red-800">
                Reason reported: <strong>"{{ $returnShipment->pickup_failure_reason }}"</strong>
            </p>
            <p class="text-xs text-red-800 font-semibold">
                @if($attempts >= $maxPickupAttempts - 1)
                    This is the last chance: one more failed attempt closes the request without a refund and the order completes normally.
                @else
                    Assign a rider for another attempt. After {{ $maxPickupAttempts }} failed attempts the request is closed without a refund.
                @endif
            </p>
        </div>
    @endif

    @if($canAssignRider)
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-4">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Step 1: Assign a Rider to Collect From the Buyer</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Choose an approved Rider covering
                    <strong>{{ !empty($pickup['barangay']) ? 'Barangay ' . $pickup['barangay'] . ', ' : '' }}{{ $pickup['municipality'] ?? '' }}</strong>
                    to collect the parcel from <strong>{{ $pickup['recipient_name'] ?? 'the buyer' }}</strong> and bring it to <strong>{{ $hubName }}</strong>.
                </p>
            </div>

            @if($availableRiders->isEmpty())
                <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-xs text-yellow-800 font-semibold">
                    No approved Riders cover this area. <a href="{{ route('rider.register') }}" class="underline font-bold">Create a Rider Account</a> or
                    <a href="{{ route('logistics.employees.index') }}" class="underline font-bold">assign Rider hub and barangay coverage</a> first.
                </div>
            @else
                <form action="{{ route('logistics.returns.assign-rider', $returnShipment->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Select Approved Rider <span class="text-danger">*</span></label>
                        <select name="rider_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold outline-none focus:border-primary">
                            @foreach($availableRiders as $r)
                                <option value="{{ $r->id }}" {{ (int) $returnShipment->current_rider_id === (int) $r->id ? 'selected' : '' }}>
                                    {{ $r->full_name }} ({{ $r->vehicle_type }} - {{ $r->plate_number }} | Hub: {{ $r->assigned_hub?->municipality ?? 'Main' }} | Brgy: {{ $r->formatted_barangay_coverage }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Pickup Notes (Optional)</label>
                        <input type="text" name="notes" maxlength="500" placeholder="e.g. Buyer asked for pickup after 2 PM" class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-3 px-5 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            {{ $returnShipment->current_rider_id ? 'Reassign Rider' : 'Assign Return Pickup Rider' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if($canReceiveAtHub)
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-4">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Step 2: Receive the Returned Parcel at the Sorting Hub</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Rider <strong>{{ $returnShipment->rider?->full_name }}</strong> collected this parcel from the buyer
                    @if($returnShipment->picked_up_from_buyer_at)
                        on {{ $returnShipment->picked_up_from_buyer_at->format('M d, Y h:i A') }}
                    @endif
                    . Check that the parcel is the one in the pickup photo, then confirm. The Rider is paid for the pickup leg when you confirm.
                </p>
            </div>

            <form action="{{ route('logistics.returns.receive-at-hub', $returnShipment->id) }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-text-muted mb-1">Notes (Optional)</label>
                    <input type="text" name="notes" maxlength="500" placeholder="e.g. Box slightly dented, item inside looks intact" class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="parcel_checked" value="1" x-model="parcelChecked" required class="w-4 h-4 rounded text-primary focus:ring-primary">
                    <span class="font-bold text-text-main">I physically checked the returned parcel and it is now at this sorting hub.</span>
                </label>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="!parcelChecked"
                            :class="parcelChecked ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold shadow-sm transition-colors">
                        Confirm Receipt at Sorting Hub
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($canAssignCourier)
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-4">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Step 3: Assign a Courier to Deliver the Parcel to the Seller</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    The parcel is at <strong>{{ $hubName }}</strong>. Choose an active Courier registered to this hub to bring it to
                    <strong>{{ $sellerOrder?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'the seller') }}</strong>
                    in {{ $sellerAddr['municipality'] ?? '' }}, {{ $sellerAddr['province'] ?? '' }}.
                </p>
            </div>

            @if($availableCouriers->isEmpty())
                <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-xs text-yellow-800 font-semibold">
                    No active Couriers are registered to this hub. <a href="{{ route('logistics.couriers.create') }}" class="underline font-bold">Create a Courier Account</a> or
                    <a href="{{ route('logistics.employees.index') }}" class="underline font-bold">reassign a Courier to this hub</a> first.
                </div>
            @else
                <form action="{{ route('logistics.returns.assign-courier', $returnShipment->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Select Active Courier <span class="text-danger">*</span></label>
                        <select name="courier_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold outline-none focus:border-primary">
                            @foreach($availableCouriers as $c)
                                <option value="{{ $c->id }}" {{ (int) $returnShipment->current_courier_id === (int) $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }} ({{ $c->vehicle_type }} - {{ $c->plate_number }} | Hub: {{ $c->assigned_hub?->municipality ?? 'Main' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Delivery Notes (Optional)</label>
                        <input type="text" name="notes" maxlength="500" placeholder="e.g. Seller is open until 5 PM" class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-3 px-5 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            {{ $returnShipment->current_courier_id ? 'Reassign Courier' : 'Assign Return Delivery Courier' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if($status === $RS::STATUS_OUT_FOR_RETURN_DELIVERY)
        <div class="p-5 rounded-3xl bg-blue-50 border border-blue-200 text-xs text-blue-900 font-semibold">
            Courier {{ $returnShipment->courier?->full_name }} is on the way to the seller with this parcel. Nothing to do until the Courier confirms delivery.
        </div>
    @endif

    @if($status === $RS::STATUS_DELIVERED_TO_SELLER)
        <div class="p-5 rounded-3xl bg-green-50 border border-green-200 text-xs text-green-900 font-semibold">
            Delivered to the seller on {{ $returnShipment->delivered_to_seller_at?->format('M d, Y h:i A') }}.
            Request status: <strong>{{ $returnRequest?->status_label }}</strong>. The seller now confirms the parcel before the refund is processed.
        </div>
    @endif

    @if($status === $RS::STATUS_CANCELLED)
        <div class="p-5 rounded-3xl bg-red-50 border border-red-200 text-xs text-red-900 font-semibold">
            This return was cancelled on {{ $returnShipment->cancelled_at?->format('M d, Y h:i A') }}. See the timeline below for the reason.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-primary-dark">Why the Buyer Is Returning This</h3>
                    <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-text-main font-bold">
                        {{ $returnRequest?->resolution_label }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Reason</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">{{ $returnRequest?->reason_category }}</p>
                    </div>
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Request Status</span>
                        <p class="text-sm font-semibold text-text-main mt-0.5">{{ $returnRequest?->status_label }}</p>
                    </div>
                </div>

                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer Explanation</span>
                    <p class="text-sm font-semibold text-text-main mt-0.5">"{{ $returnRequest?->reason }}"</p>
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

            <div class="bg-surface rounded-3xl border border-border-subtle overflow-hidden shadow-xs">
                <div class="p-5 bg-surface-subtle/60 border-b border-border-subtle flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">Items in the Returned Order</h3>
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
                            <div class="text-sm font-mono font-bold text-primary-dark shrink-0">&times; {{ $item->quantity }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">
                        Return Tracking Timeline
                        <span class="font-mono text-xs text-primary ml-1">({{ $returnShipment->tracking_number }})</span>
                    </h3>
                    <span class="text-[11px] font-bold text-text-muted">{{ $returnShipment->events->count() }} milestone(s)</span>
                </div>

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
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">1. Pickup From Buyer</h3>
                <p class="text-sm font-bold text-text-main">{{ $pickup['recipient_name'] ?? ($sellerOrder?->buyer?->full_name ?? 'Buyer') }}</p>
                <p class="text-text-muted">{{ $pickup['phone_number'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed">{{ $pickup['full_address'] ?? (($pickup['municipality'] ?? '') . ', ' . ($pickup['province'] ?? '')) }}</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">2. Sorting Hub</h3>
                <p class="text-sm font-bold text-text-main">{{ $hubName }}</p>
                <p class="text-text-main leading-relaxed">{{ $hubSnap['municipality'] ?? '' }}, {{ $hubSnap['province'] ?? '' }}</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">3. Return To Seller</h3>
                <p class="text-sm font-bold text-text-main">{{ $sellerOrder?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'Seller') }}</p>
                <p class="text-text-muted">{{ $sellerAddr['phone_number'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed">{{ $sellerAddr['full_address'] ?? (($sellerAddr['municipality'] ?? '') . ', ' . ($sellerAddr['province'] ?? '')) }}</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Return-Leg Pay</h3>
                <p class="text-text-muted leading-relaxed">
                    Each leg is paid to the driver as soon as it is finished. The cost is recovered from the seller when the refund is settled, so your company is not out of pocket.
                </p>
                <div class="flex justify-between">
                    <span class="text-text-muted">Rider (Buyer &rarr; Hub)</span>
                    <span class="font-bold text-text-main">
                        @if($returnShipment->rider_paid_at)
                            &#8369;{{ number_format((float) $returnShipment->rider_pay, 2) }}
                        @else
                            Paid at hub receipt
                        @endif
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Courier (Hub &rarr; Seller)</span>
                    <span class="font-bold text-text-main">
                        @if($returnShipment->courier_paid_at)
                            &#8369;{{ number_format((float) $returnShipment->courier_pay, 2) }}
                        @else
                            Paid at delivery
                        @endif
                    </span>
                </div>
            </div>

            @if($returnShipment->buyer_pickup_photo || $returnShipment->seller_delivery_photo)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="font-bold uppercase tracking-wider text-green-700">Proof Photos</h3>
                    @if($returnShipment->buyer_pickup_photo)
                        <button type="button"
                                @click.stop="openPhoto('{{ asset('storage/' . $returnShipment->buyer_pickup_photo) }}')"
                                class="w-full py-2.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                            View Photo Taken at Buyer Pickup
                        </button>
                    @endif
                    @if($returnShipment->seller_delivery_photo)
                        <button type="button"
                                @click.stop="openPhoto('{{ asset('storage/' . $returnShipment->seller_delivery_photo) }}')"
                                class="w-full py-2.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                            View Photo Taken at Seller Delivery
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="photoModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="photoModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Return Photo</h3>
                    <button type="button" @click="photoModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                    <img :src="photoUrl" alt="Return Photo" class="max-h-[60vh] w-auto object-contain">
                </div>
            </div>
        </div>
    </template>
</div>
@endsection