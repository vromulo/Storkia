@extends('layouts.logistics', ['title' => 'Storkia - Shipment ' . $shipment->tracking_number])

@section('content')
@php
    $pickup = $shipment->pickup_address_snapshot ?? [];
    $deliv = $shipment->delivery_address_snapshot ?? [];
    $hubSnap = $shipment->hub_address_snapshot ?? [];
    $packages = $shipment->packages ?? collect();
    $primaryPackage = $packages->first();
    $handoverMethod = $primaryPackage?->handover_method ?? ($hubSnap['handover_method'] ?? 'courier_pickup');
    $isSelfDropOff = $handoverMethod === 'self_drop_off' || $shipment->status === 'awaiting_seller_drop_off';
    $latestAttempt = $shipment->deliveryAttempts->first();
    $latestProof = $shipment->deliveryProofs->first();

    $allHubPackagesVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) {
        if ($pkg->hub_verified_at !== null) {
            return true;
        }
        return $pkg->verifications
            ->whereIn('stage', ['logistics_receiving', 'seller_drop_off'])
            ->where('actor_type', 'Logistics')
            ->where('is_confirmed', true)
            ->isNotEmpty();
    });
@endphp

<div x-data="{
    proofModalOpen: false,
    verificationMethod: 'manual',
    lookupInput: '',
    isSubmittingConfirm: false
}" class="max-w-6xl mx-auto space-y-6 select-none">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('logistics.shipments.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono">{{ $shipment->tracking_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/50 text-primary-dark border border-border-subtle">
                        {{ $shipment->status_label }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $isSelfDropOff ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-blue-50 text-blue-800 border border-blue-200' }}">
                        {{ $isSelfDropOff ? 'Option B: Seller Self Drop-Off' : 'Option A: Courier Pickup' }}
                    </span>
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Seller Order: <strong>{{ $shipment->sellerOrder->seller_order_number ?? 'N/A' }}</strong> &bull;
                    Parent Order: <strong>{{ $shipment->sellerOrder->order->order_number ?? 'N/A' }}</strong> &bull;
                    COD Amount: <strong class="text-primary">₱{{ number_format((float) $shipment->cod_amount, 2) }}</strong>
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

    @if(! $isSelfDropOff && in_array($shipment->status, ['pickup_requested', 'courier_assigned'], true))
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Step 1: Assign First-Mile Pickup Courier (Municipality Hub Coverage)</h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Select an active Courier assigned to the Municipality Hub servicing <strong>{{ $pickup['municipality'] ?? 'Seller Municipality' }}</strong> to collect the package from <strong>{{ $pickup['business_name'] ?? 'the Seller' }}</strong>.
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-brand-light/40 text-primary-dark self-start sm:self-auto">
                    Target Hub: {{ $primaryPackage?->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? $profile->business_name) }}
                </span>
            </div>

            @if($availableCouriers->isEmpty())
                <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-xs text-yellow-800 font-semibold">
                    No active Couriers found for this hub. <a href="{{ route('logistics.couriers.create') }}" class="underline font-bold">Create a Courier Account</a> or <a href="{{ route('logistics.employees.index') }}" class="underline font-bold">assign a Courier</a> first.
                </div>
            @else
                <form action="{{ route('logistics.shipments.assign-courier', $shipment->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Select Eligible Hub Courier <span class="text-danger">*</span></label>
                        <select name="courier_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold outline-none focus:border-primary">
                            @foreach($availableCouriers as $c)
                                <option value="{{ $c->id }}" {{ (int) $shipment->current_courier_id === (int) $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }} — {{ $c->assigned_hub?->municipality ?? 'Main Hub' }} ({{ $c->vehicle_type }} - {{ $c->plate_number }} | {{ $c->load_capacity }}kg)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Dispatch Instructions (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Verify package QR code upon arrival at seller" class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-3 px-5 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            {{ $shipment->current_courier_id ? 'Reassign Courier' : 'Assign Courier for Pickup' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if(in_array($shipment->status, ['awaiting_seller_drop_off', 'picked_up_from_seller', 'in_transit_to_hub'], true) || ($isSelfDropOff && $shipment->status === 'pickup_requested'))
        <div class="p-6 rounded-3xl bg-blue-50/90 border-2 border-blue-200 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-blue-200">
                <div>
                    @if($isSelfDropOff)
                        <h2 class="text-base font-bold text-blue-950">Step 1 & 2: Seller Self Drop-Off — Scan QR or Verify Manually & Confirm Hub Receipt</h2>
                        <p class="text-xs text-blue-800 mt-0.5">
                            Seller <strong>{{ $shipment->sellerProfile->business_name ?? ($pickup['business_name'] ?? 'Seller') }}</strong> selected Self Drop-Off at <strong>{{ $primaryPackage?->logisticsHub?->hub_name ?? $profile->business_name }}</strong>.
                            Scan the package QR Code or verify the tracking/package reference below before confirming physical receipt.
                        </p>
                    @else
                        <h2 class="text-base font-bold text-blue-950">Step 2: Courier Handover — Verify Package QR / Reference & Confirm Hub Arrival</h2>
                        <p class="text-xs text-blue-800 mt-0.5">
                            Courier <strong>{{ $shipment->currentCourier?->full_name }}</strong> picked up this parcel at {{ $shipment->picked_up_at?->format('M d, Y h:i A') }}.
                            First verify the package via QR Code or Manual lookup, review the manifest, and then click <strong>Confirm Physical Receipt at Sorting Hub</strong>.
                        </p>
                    @endif
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $allHubPackagesVerified ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-yellow-100 text-yellow-800 border border-yellow-300' }} shrink-0">
                    {{ $allHubPackagesVerified ? 'All Packages Verified — Ready to Receive' : 'Step 1 Required: Verify Package First' }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-blue-200 p-4 space-y-4 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-bold uppercase tracking-wider text-[11px] text-primary-dark">
                        Unified Package Identification (QR Scan or Manual Lookup)
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="verificationMethod = 'qr'"
                                :class="verificationMethod === 'qr' ? 'bg-primary-dark text-white' : 'bg-surface-subtle text-text-muted'"
                                class="px-3 py-1 rounded-lg font-bold text-[11px] cursor-pointer transition-colors">
                            1. QR Code Scan
                        </button>
                        <button type="button"
                                @click="verificationMethod = 'manual'"
                                :class="verificationMethod === 'manual' ? 'bg-primary-dark text-white' : 'bg-surface-subtle text-text-muted'"
                                class="px-3 py-1 rounded-lg font-bold text-[11px] cursor-pointer transition-colors">
                            2. Manual Tracking / Ref Lookup
                        </button>
                    </div>
                </div>

                <form action="{{ route('logistics.shipments.verify-lookup', $shipment->id) }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    @csrf
                    <input type="hidden" name="verification_method" :value="verificationMethod">
                    <div class="flex-1">
                        <input type="text"
                               name="lookup_input"
                               x-model="lookupInput"
                               required
                               :placeholder="verificationMethod === 'qr' ? 'Focus here and scan package QR Code token (e.g. STORK-QR-...)...' : 'Enter Tracking # ({{ $shipment->tracking_number }}) or Package Ref ({{ $primaryPackage?->package_reference }})...'"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono text-xs outline-none focus:border-primary">
                    </div>
                    <button type="submit"
                            class="px-5 py-3 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer shrink-0">
                        Verify & Inspect Package
                    </button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach($packages as $pkg)
                        @php
                            $isPkgVerifiedAtHub = $pkg->hub_verified_at !== null || $pkg->verifications
                                ->whereIn('stage', ['logistics_receiving', 'seller_drop_off'])
                                ->where('actor_type', 'Logistics')
                                ->where('is_confirmed', true)
                                ->isNotEmpty();
                        @endphp
                        <div class="p-3 rounded-xl border {{ $isPkgVerifiedAtHub ? 'border-green-300 bg-green-50/50' : 'border-border-subtle bg-surface-subtle' }} flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono font-bold text-text-main">{{ $pkg->package_reference }}</div>
                                <div class="text-[11px] text-text-muted">
                                    Package {{ $pkg->package_number }} of {{ $pkg->total_packages }} &bull; {{ number_format((float) $pkg->package_weight, 2) }} kg &bull; {{ $pkg->total_items_count }} item(s)
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($isPkgVerifiedAtHub)
                                    <span class="px-2.5 py-1 rounded-lg bg-green-100 text-green-700 font-bold text-[10px]">
                                        Verified
                                    </span>
                                @else
                                    <button type="button"
                                            @click="verificationMethod = 'manual'; lookupInput = '{{ $pkg->package_reference }}'"
                                            class="px-2.5 py-1 rounded-lg bg-white border border-border-subtle hover:border-primary text-primary-dark font-bold text-[10px] cursor-pointer">
                                        Fill Ref
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                <p class="text-xs text-blue-900">
                    @if(! $allHubPackagesVerified)
                        <strong>Note:</strong> Scanning or looking up a package verifies its identity and displays its manifest below without advancing status automatically.
                    @else
                        <strong>Verification Complete:</strong> Package identity and manifest have been reviewed. Click the button on the right to record physical receipt at the hub.
                    @endif
                </p>

                <form action="{{ route('logistics.shipments.verify-hub-arrival', $shipment->id) }}"
                      method="POST"
                      @submit="isSubmittingConfirm = true"
                      class="m-0 shrink-0">
                    @csrf
                    <button type="submit"
                            :disabled="{{ $allHubPackagesVerified ? 'false' : 'true' }} || isSubmittingConfirm"
                            class="px-6 py-3 rounded-xl text-xs font-bold shadow-sm transition-colors {{ $allHubPackagesVerified ? 'bg-blue-600 hover:bg-blue-700 text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                        Confirm Physical Receipt at Sorting Hub
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if(in_array($shipment->status, ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'], true))
        <div class="p-6 rounded-3xl bg-surface border-2 border-primary/30 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Step 3: Assign Last-Mile Delivery Rider (Barangay Coverage Match)</h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Assign an approved Rider covering <strong>Barangay {{ $deliv['barangay'] ?? 'N/A' }}, {{ $deliv['municipality'] ?? '' }}</strong> to deliver to <strong>{{ $deliv['recipient_name'] ?? 'Buyer' }}</strong>.
                    </p>
                </div>
                @if(isset($matchingBarangayRiders) && $matchingBarangayRiders->isNotEmpty())
                    <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-700 border border-green-200 self-start sm:self-auto">
                        {{ $matchingBarangayRiders->count() }} Rider(s) Match Barangay {{ $deliv['barangay'] ?? '' }}
                    </span>
                @endif
            </div>

            @if($availableRiders->isEmpty())
                <div class="p-4 rounded-2xl bg-yellow-50 border border-yellow-200 text-xs text-yellow-800 font-semibold">
                    No approved Riders found. <a href="{{ route('rider.register') }}" class="underline font-bold">Create a Rider Account</a> or <a href="{{ route('logistics.employees.index') }}" class="underline font-bold">assign Rider Barangay coverage</a> first.
                </div>
            @else
                <form action="{{ route('logistics.shipments.assign-rider', $shipment->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Select Approved Rider <span class="text-danger">*</span></label>
                        <select name="rider_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold outline-none focus:border-primary">
                            @foreach($availableRiders as $r)
                                <option value="{{ $r->id }}" {{ (int) $shipment->current_rider_id === (int) $r->id ? 'selected' : '' }}>
                                    {{ $r->full_name }} ({{ $r->vehicle_type }} - {{ $r->plate_number }} | Hub: {{ $r->assigned_hub?->municipality ?? 'Main' }} | Brgy: {{ $r->formatted_barangay_coverage }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Delivery Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Collect exact COD ₱{{ number_format((float) $shipment->cod_amount, 2) }}" class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-3 px-5 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            {{ $shipment->current_rider_id ? 'Reassign Rider' : 'Assign Delivery Rider' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if($shipment->status === 'delivery_failed')
        <div x-data="{ resolutionType: 'schedule_redelivery' }" class="p-6 rounded-3xl bg-red-50 border-2 border-red-200 space-y-4">
            <div>
                <h2 class="text-base font-bold text-red-900">Action Required: Failed Delivery Attempt #{{ $shipment->delivery_attempts_count }}</h2>
                @if($latestAttempt)
                    <p class="text-xs text-red-800 mt-1">
                        Rider <strong>{{ $latestAttempt->rider?->full_name }}</strong> reported:
                        <strong>"{{ $latestAttempt->failure_reason }}"</strong>
                        @if($latestAttempt->failure_notes)
                            — <em>"{{ $latestAttempt->failure_notes }}"</em>
                        @endif
                    </p>
                @endif
            </div>

            <form action="{{ route('logistics.shipments.resolve-failed', $shipment->id) }}" method="POST" class="space-y-4 bg-white p-5 rounded-2xl border border-red-200">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="p-3.5 rounded-xl border cursor-pointer flex items-center gap-2.5 text-xs font-bold"
                           :class="resolutionType === 'schedule_redelivery' ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle text-text-muted'">
                        <input type="radio" name="resolution" value="schedule_redelivery" x-model="resolutionType">
                        <span>Schedule Redelivery (Allow Rider Reassignment)</span>
                    </label>
                    <label class="p-3.5 rounded-xl border cursor-pointer flex items-center gap-2.5 text-xs font-bold"
                           :class="resolutionType === 'return_to_seller' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle text-text-muted'">
                        <input type="radio" name="resolution" value="return_to_seller" x-model="resolutionType">
                        <span>Initiate Return to Seller (Restores Reserved Stock)</span>
                    </label>
                </div>

                <div x-show="resolutionType === 'schedule_redelivery'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Assign / Reassign Rider for Redelivery</label>
                        <select name="rider_id" class="w-full p-2.5 rounded-xl border border-border-subtle text-xs">
                            <option value="">Assign Rider Later</option>
                            @foreach($availableRiders as $r)
                                <option value="{{ $r->id }}" {{ (int) $shipment->current_rider_id === (int) $r->id ? 'selected' : '' }}>
                                    {{ $r->full_name }} ({{ $r->vehicle_type }} - {{ $r->plate_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-muted mb-1">Resolution Remarks</label>
                        <input type="text" name="notes" placeholder="Notes for redelivery attempt..." class="w-full p-2.5 rounded-xl border border-border-subtle text-xs">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                        Confirm Resolution Decision
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            @if($packages->isNotEmpty())
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-sm font-bold text-primary-dark">Package Identification & Product Manifest</h3>
                            <p class="text-xs text-text-muted">
                                Designated Hub: <strong>{{ $primaryPackage?->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? $profile->business_name) }}</strong>
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-surface-subtle border border-border-subtle text-text-main">
                            {{ $packages->count() }} {{ Str::plural('Package', $packages->count()) }} &bull; {{ number_format((float) $shipment->total_weight, 2) }} kg
                        </span>
                    </div>

                    @foreach($packages as $pkg)
                        <div class="p-4 rounded-2xl bg-surface-subtle/60 border border-border-subtle space-y-3 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-mono font-bold text-primary-dark text-sm">{{ $pkg->package_reference }}</span>
                                    <span class="text-text-muted ml-2">(Package {{ $pkg->package_number }} of {{ $pkg->total_packages }})</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold">
                                    @if($pkg->courier_verified_at)
                                        <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800">Courier Verified ({{ strtoupper($pkg->courier_verification_method ?? 'QR') }})</span>
                                    @endif
                                    @if($pkg->hub_verified_at)
                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">Hub Verified ({{ strtoupper($pkg->hub_verification_method ?? 'QR') }})</span>
                                    @endif
                                    @if($pkg->rider_collected_at)
                                        <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800">Rider Collected ({{ strtoupper($pkg->rider_verification_method ?? 'QR') }})</span>
                                    @endif
                                </div>
                            </div>

                            @if(!empty($pkg->manifest_snapshot))
                                <div class="overflow-x-auto bg-surface rounded-xl border border-border-subtle">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="border-b border-border-subtle bg-surface-subtle text-[10px] uppercase font-bold text-text-muted">
                                                <th class="py-2 px-3">SKU</th>
                                                <th class="py-2 px-3">Product</th>
                                                <th class="py-2 px-3">Variant</th>
                                                <th class="py-2 px-3 text-right">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border-subtle">
                                            @foreach($pkg->manifest_snapshot as $mItem)
                                                <tr>
                                                    <td class="py-2 px-3 font-mono text-[11px] text-text-muted">{{ $mItem['sku'] ?? 'N/A' }}</td>
                                                    <td class="py-2 px-3 font-bold text-text-main">{{ $mItem['product_name'] ?? 'Item' }}</td>
                                                    <td class="py-2 px-3 text-text-muted">{{ $mItem['variant'] ?? 'Standard' }}</td>
                                                    <td class="py-2 px-3 text-right font-mono font-bold text-primary-dark">{{ $mItem['quantity'] ?? 1 }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-primary-dark">Shipment Tracking & Operational Audit Log</h3>
                <div class="relative pl-6 border-l-2 border-primary/30 space-y-5">
                    @foreach($shipment->trackingLogs as $idx => $log)
                        <div class="relative">
                            <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-gray-300' }}"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="text-xs sm:text-sm font-bold text-text-main">{{ $log->title }}</span>
                                <span class="text-[11px] text-text-muted">{{ $log->occurred_at?->format('M d, Y h:i A') }}</span>
                            </div>
                            <p class="text-xs text-text-muted mt-0.5">{{ $log->description }}</p>
                            <p class="text-[11px] text-text-muted/80">Actor: {{ $log->actor_type }} &bull; Location: {{ $log->location }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($shipment->deliveryAttempts->isNotEmpty())
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                    <h3 class="text-sm font-bold text-primary-dark">Delivery Attempts History ({{ $shipment->deliveryAttempts->count() }})</h3>
                    <div class="divide-y divide-border-subtle text-xs">
                        @foreach($shipment->deliveryAttempts as $attempt)
                            <div class="py-3 flex items-start justify-between gap-4">
                                <div>
                                    <span class="font-bold text-text-main">Attempt #{{ $attempt->attempt_number }} — {{ strtoupper($attempt->outcome) }}</span>
                                    <p class="text-text-muted mt-0.5">Rider: {{ $attempt->rider?->full_name }} &bull; {{ $attempt->attempted_at?->format('M d, Y h:i A') }}</p>
                                    @if($attempt->failure_reason)
                                        <p class="text-danger font-semibold mt-0.5">Reason: {{ $attempt->failure_reason }} {{ $attempt->failure_notes ? '(' . $attempt->failure_notes . ')' : '' }}</p>
                                    @endif
                                </div>
                                @if($attempt->logistics_resolution)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-light/40 text-primary-dark">
                                        Resolution: {{ ucwords(str_replace('_', ' ', $attempt->logistics_resolution)) }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        <div class="space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">First-Mile Origin (Seller)</h3>
                <p class="text-sm font-bold text-text-main">{{ $pickup['business_name'] ?? '' }}</p>
                <p class="text-text-muted">{{ $pickup['contact_no'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed">{{ $pickup['full_address'] ?? '' }}</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-text-muted">Last-Mile Destination (Buyer)</h3>
                <p class="text-sm font-bold text-text-main">{{ $deliv['recipient_name'] ?? '' }}</p>
                <p class="text-text-muted">{{ $deliv['phone_number'] ?? '' }}</p>
                <p class="text-text-main leading-relaxed">{{ $deliv['full_address'] ?? '' }}</p>
                @if(!empty($deliv['barangay']))
                    <div class="pt-1">
                        <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-bold">
                            Target Barangay: {{ $deliv['barangay'] }}
                        </span>
                    </div>
                @endif
            </div>

            @if($latestProof)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="font-bold uppercase tracking-wider text-green-700">Mandatory Delivery Proof</h3>
                    <p class="text-text-muted">Recipient: <strong class="text-text-main">{{ $latestProof->recipient_name ?: 'Buyer' }}</strong></p>
                    <p class="text-text-muted">Captured: {{ $latestProof->captured_at?->format('M d, Y h:i A') }}</p>
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="w-full py-2.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                        Inspect Delivery Photo
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if($latestProof)
        <template x-teleport="body">
            <div x-show="proofModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[190] flex items-center justify-center p-4">
                <div @click="proofModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-base font-bold text-primary-dark">Rider Delivery Photo Proof</h3>
                        <button type="button" @click="proofModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
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