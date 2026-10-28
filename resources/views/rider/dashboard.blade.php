<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Storkia - Rider Last-Mile Delivery Portal</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16">

    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary-dark text-surface font-serif font-bold text-lg flex items-center justify-center shrink-0">
                    R
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-base font-bold text-primary-dark leading-tight">Last-Mile Rider Portal</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/50 text-primary-dark border border-border-subtle">
                            Hub: {{ $rider->assigned_hub?->hub_name ?? ($rider->logisticsProfile->business_name ?? 'Logistics Hub') }}
                        </span>
                    </div>
                    <p class="text-xs text-text-muted mt-0.5">
                        {{ $rider->full_name }} &bull; {{ $rider->vehicle_type }} ({{ $rider->plate_number }}) &bull;
                        Barangay Coverage: <strong class="text-text-main">{{ $rider->formatted_barangay_coverage }}</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('rider.download-app') }}" class="px-3.5 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-xs font-bold text-text-main transition-colors">
                    Mobile APK Info
                </a>
                <form action="{{ route('rider.logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-red-50 text-danger hover:bg-red-100 text-xs font-bold transition-colors cursor-pointer">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">

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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Active Deliveries</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $metrics['active_deliveries'] }}</p>
                <span class="text-[11px] text-text-muted">Completed: {{ $metrics['completed_deliveries'] }}</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Unremitted COD Cash on Hand</span>
                <p class="text-2xl font-serif font-bold text-primary mt-1">₱{{ number_format($metrics['cod_on_hand'], 2) }}</p>
                <span class="text-[11px] text-text-muted">{{ $unremittedCollections->count() }} collection(s) to remit</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Pending Rider Commission (10%)</span>
                <p class="text-2xl font-serif font-bold text-blue-700 mt-1">₱{{ number_format($metrics['pending_rider_comm'], 2) }}</p>
                <span class="text-[11px] text-text-muted">10% of Shipping Fee on unremitted COD</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Verified Commission Earned</span>
                <p class="text-2xl font-serif font-bold text-green-700 mt-1">₱{{ number_format($metrics['verified_rider_earnings'], 2) }}</p>
                <span class="text-[11px] text-text-muted">Settled via verified remittances</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @foreach(['deliveries' => 'Active Deliveries (' . $activeAssignments->count() . ')', 'remittance' => 'COD Collections & Remittance (' . $unremittedCollections->count() . ')', 'history' => 'Delivery History'] as $key => $label)
                <a href="{{ route('rider.dashboard', ['tab' => $key]) }}"
                   class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all {{ $tab === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if($tab === 'deliveries')
            @if($activeAssignments->isEmpty())
                <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center space-y-2">
                    <h3 class="text-base font-bold text-text-main">No active delivery assignments</h3>
                    <p class="text-xs text-text-muted">When your Logistics sorting hub assigns a parcel in your Barangay coverage to you, it will appear here.</p>
                </div>
            @else
                <div class="space-y-5">
                    @foreach($activeAssignments as $assignment)
                        @php
                            $shipment = $assignment->shipment;
                            $sellerOrder = $assignment->sellerOrder;
                            $deliv = $shipment->delivery_address_snapshot ?? [];
                            $packages = $shipment->packages ?? collect();
                            $primaryPackage = $packages->first();
                            $riderComm = round(((float) $shipment->shipping_fee) * 0.10, 2);

                            $isTaskAccepted = in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery'], true)
                                || ($primaryPackage && $primaryPackage->rider_accepted_at !== null);

                            $allCollectionVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) use ($rider) {
                                if ($pkg->rider_verified_at !== null || $pkg->rider_collected_at !== null) {
                                    return true;
                                }
                                return $pkg->verifications
                                    ->where('stage', 'rider_collection')
                                    ->where('actor_type', 'Rider')
                                    ->where('actor_id', $rider->id)
                                    ->where('is_confirmed', true)
                                    ->isNotEmpty();
                            });

                            $isOutForDelivery = $assignment->status === 'out_for_delivery' && $shipment->status === 'out_for_delivery';

                            $allDeliveryDoorstepVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) use ($rider) {
                                if ($pkg->delivery_verified_at !== null) {
                                    return true;
                                }
                                return $pkg->verifications
                                    ->where('stage', 'delivery_confirmation')
                                    ->where('actor_type', 'Rider')
                                    ->where('actor_id', $rider->id)
                                    ->where('is_confirmed', true)
                                    ->isNotEmpty();
                            });
                        @endphp

                        <div x-data="{
                                deliverModalOpen: false,
                                failModalOpen: false,
                                verificationMethod: 'manual',
                                lookupInput: '',
                                cameraScannerOpen: false,
                                html5QrScanner: null,
                                isSubmittingAccept: false,
                                isSubmittingCollect: false,
                                isSubmittingDeliver: false,
                                isSubmittingFail: false,
                                startQrCamera(scannerRegionId) {
                                    this.verificationMethod = 'qr';
                                    this.cameraScannerOpen = true;
                                    this.$nextTick(() => {
                                        if (typeof Html5Qrcode === 'undefined') return;
                                        try {
                                            this.html5QrScanner = new Html5Qrcode(scannerRegionId);
                                            this.html5QrScanner.start(
                                                { facingMode: 'environment' },
                                                { fps: 10, qrbox: { width: 220, height: 220 } },
                                                (decodedText) => {
                                                    this.lookupInput = decodedText;
                                                    this.stopQrCamera();
                                                },
                                                () => {}
                                            ).catch(() => {});
                                        } catch (e) {}
                                    });
                                },
                                stopQrCamera() {
                                    if (this.html5QrScanner) {
                                        this.html5QrScanner.stop().then(() => {
                                            this.html5QrScanner.clear();
                                            this.html5QrScanner = null;
                                        }).catch(() => {});
                                    }
                                    this.cameraScannerOpen = false;
                                }
                             }"
                             class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5">

                            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="font-mono font-bold text-sm text-primary-dark bg-brand-light/40 px-3 py-1 rounded-xl">
                                        {{ $shipment->tracking_number }}
                                    </span>
                                    <span class="text-xs font-bold text-text-main">
                                        Attempt #{{ $assignment->attempt_number }}
                                    </span>
                                    @if(!empty($deliv['barangay']))
                                        <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-bold">
                                            Brgy. {{ $deliv['barangay'] }}
                                        </span>
                                    @endif
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 uppercase">
                                    {{ str_replace('_', ' ', $assignment->status) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                <div class="p-2.5 rounded-xl border {{ $isTaskAccepted ? 'border-green-300 bg-green-50 text-green-800' : 'border-primary bg-primary/5 text-primary-dark' }}">
                                    <span class="font-extrabold block">Step 1: Accept Task</span>
                                    <span>{{ $isTaskAccepted ? 'Accepted' : 'Pending Acceptance' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl border {{ $allCollectionVerified ? 'border-green-300 bg-green-50 text-green-800' : ($isTaskAccepted ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle bg-surface-subtle text-text-muted') }}">
                                    <span class="font-extrabold block">Step 2: QR / Ref Verify</span>
                                    <span>{{ $allCollectionVerified ? 'Package Verified' : 'Scan or Enter Ref' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl border {{ $isOutForDelivery ? 'border-green-300 bg-green-50 text-green-800' : ($allCollectionVerified ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle bg-surface-subtle text-text-muted') }}">
                                    <span class="font-extrabold block">Step 3: Collect Parcel</span>
                                    <span>{{ $isOutForDelivery ? 'Out for Delivery' : 'Confirm at Hub' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl border {{ $isOutForDelivery ? 'border-primary bg-primary/5 text-primary-dark' : 'border-border-subtle bg-surface-subtle text-text-muted' }}">
                                    <span class="font-extrabold block">Step 4: Buyer Handover</span>
                                    <span>{{ $isOutForDelivery ? 'Ready for COD / Proof' : 'Locked until Step 3' }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-primary-dark block">Buyer Delivery Destination</span>
                                    <p class="text-sm font-bold text-text-main">{{ $deliv['recipient_name'] ?? 'Buyer' }}</p>
                                    <p class="text-text-muted font-semibold">Phone: {{ $deliv['phone_number'] ?? '' }}</p>
                                    <p class="text-text-main leading-relaxed">{{ $deliv['full_address'] ?? '' }}</p>
                                </div>

                                <div class="p-4 rounded-2xl bg-brand-light/20 border border-border-subtle space-y-1.5">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-primary-dark block">Cash on Delivery (COD) To Collect</span>
                                    <p class="text-2xl font-serif font-extrabold text-primary">₱{{ number_format((float) $shipment->cod_amount, 2) }}</p>
                                    <p class="text-text-muted">
                                        Includes Shipping Fee ₱{{ number_format((float) $shipment->shipping_fee, 2) }}
                                        (Your 10% Rider Commission: <strong class="text-green-700">₱{{ number_format($riderComm, 2) }}</strong>)
                                    </p>
                                </div>
                            </div>

                            @if($isTaskAccepted)
                                <div class="p-4 rounded-2xl bg-surface-subtle/80 border border-border-subtle space-y-3 text-xs">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <span class="font-bold uppercase text-[10px] tracking-wider text-primary-dark block">
                                                {{ $isOutForDelivery ? 'Step 4 Verification: Scan QR or Verify Package at Buyer Doorstep' : 'Step 2 Verification: Scan QR Code or Enter Package Reference at Hub' }}
                                            </span>
                                            <p class="text-[11px] text-text-muted">
                                                Scanning or looking up a package verifies its manifest without advancing status automatically.
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <button type="button"
                                                    @click="startQrCamera('rider-qr-reader-{{ $assignment->id }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-primary-dark text-white font-bold text-[11px] cursor-pointer">
                                                Scan QR Camera
                                            </button>
                                            <button type="button"
                                                    @click="stopQrCamera(); verificationMethod = 'manual'"
                                                    class="px-3 py-1.5 rounded-xl bg-surface border border-border-subtle text-text-main font-bold text-[11px] cursor-pointer">
                                                Manual Input
                                            </button>
                                        </div>
                                    </div>

                                    <div x-show="cameraScannerOpen" x-cloak class="p-3 rounded-2xl bg-black/90 text-white space-y-2">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="font-bold">Point camera at the Package Shipping Label QR Code</span>
                                            <button type="button" @click="stopQrCamera()" class="px-2.5 py-1 rounded bg-red-600 text-white font-bold cursor-pointer">
                                                Close Camera
                                            </button>
                                        </div>
                                        <div id="rider-qr-reader-{{ $assignment->id }}" class="w-full max-w-xs mx-auto overflow-hidden rounded-xl bg-black"></div>
                                    </div>

                                    <form action="{{ route('rider.deliveries.verify-lookup', $assignment->id) }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="verification_method" :value="verificationMethod">
                                        <input type="hidden" name="stage" value="{{ $isOutForDelivery ? 'delivery_confirmation' : 'rider_collection' }}">
                                        <input type="text"
                                               name="lookup_input"
                                               x-model="lookupInput"
                                               required
                                               placeholder="Scan QR token or enter Tracking # ({{ $shipment->tracking_number }}) / Package Ref ({{ $primaryPackage?->package_reference }})..."
                                               class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface font-mono text-xs outline-none focus:border-primary">
                                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer shrink-0">
                                            Verify Package
                                        </button>
                                    </form>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($packages as $pkg)
                                            @php
                                                $pkgVerifiedForStage = $isOutForDelivery
                                                    ? ($pkg->delivery_verified_at !== null || $allDeliveryDoorstepVerified)
                                                    : ($pkg->rider_verified_at !== null || $allCollectionVerified);
                                            @endphp
                                            <div class="p-3 rounded-xl bg-surface border {{ $pkgVerifiedForStage ? 'border-green-300' : 'border-border-subtle' }} flex items-center justify-between gap-2">
                                                <div>
                                                    <div class="font-mono font-bold text-primary-dark">{{ $pkg->package_reference }}</div>
                                                    <div class="text-[11px] text-text-muted">
                                                        Package {{ $pkg->package_number }}/{{ $pkg->total_packages }} &bull; {{ number_format((float) $pkg->package_weight, 2) }} kg &bull; {{ $pkg->total_items_count }} item(s)
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    @if($pkgVerifiedForStage)
                                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 font-bold text-[10px]">Verified</span>
                                                    @else
                                                        <button type="button"
                                                                @click="verificationMethod = 'manual'; lookupInput = '{{ $pkg->package_reference }}'"
                                                                class="px-2 py-1 rounded bg-surface-subtle border border-border-subtle hover:border-primary text-[10px] font-bold text-primary-dark cursor-pointer">
                                                            Use Ref
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border-subtle text-xs">
                                <div class="text-text-muted">
                                    Order #{{ $sellerOrder->seller_order_number }} &bull; Weight: {{ number_format((float) $shipment->total_weight, 2) }} kg
                                </div>

                                <div class="flex flex-wrap items-center gap-2.5">
                                    @if($assignment->status === 'assigned')
                                        <form action="{{ route('rider.deliveries.accept', $assignment->id) }}"
                                              method="POST"
                                              @submit="isSubmittingAccept = true"
                                              class="m-0">
                                            @csrf
                                            <button type="submit"
                                                    :disabled="isSubmittingAccept"
                                                    class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold shadow-xs cursor-pointer disabled:opacity-50">
                                                1. Accept Delivery Assignment
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($assignment->status, ['accepted', 'collected'], true))
                                        <form action="{{ route('rider.deliveries.out-for-delivery', $assignment->id) }}"
                                              method="POST"
                                              @submit="isSubmittingCollect = true"
                                              class="m-0">
                                            @csrf
                                            <button type="submit"
                                                    :disabled="{{ $allCollectionVerified ? 'false' : 'true' }} || isSubmittingCollect"
                                                    class="px-5 py-2.5 rounded-xl font-bold shadow-xs transition-colors {{ $allCollectionVerified ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                                                3. Confirm Package Collection from Hub & Start Delivery
                                            </button>
                                        </form>
                                    @endif

                                    @if($isOutForDelivery)
                                        <button type="button"
                                                @click.stop="failModalOpen = true"
                                                class="px-4 py-2.5 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-danger font-bold cursor-pointer">
                                            Report Failed Delivery
                                        </button>

                                        <button type="button"
                                                @click.stop="deliverModalOpen = true"
                                                class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer">
                                            Confirm COD Paid & Upload Photo Proof
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <template x-teleport="body">
                                <div x-show="deliverModalOpen"
                                     x-cloak
                                     class="fixed inset-0 z-[180] flex items-center justify-center p-4">
                                    <div @click="deliverModalOpen = false"
                                         class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>

                                    <div @click.stop
                                         class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                                        <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                            <div>
                                                <h3 class="text-base font-bold text-primary-dark">Complete COD Delivery</h3>
                                                <p class="text-[11px] text-text-muted">Tracking #: {{ $shipment->tracking_number }}</p>
                                            </div>
                                            <button type="button" @click="deliverModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>

                                        <form action="{{ route('rider.deliveries.confirm-delivered', $assignment->id) }}"
                                              method="POST"
                                              enctype="multipart/form-data"
                                              @submit="isSubmittingDeliver = true"
                                              class="space-y-4 text-xs">
                                            @csrf
                                            <input type="hidden" name="verification_method" :value="verificationMethod">

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Exact COD Amount Collected (₱) <span class="text-danger">*</span></label>
                                                <input type="number"
                                                       step="0.01"
                                                       name="collected_amount"
                                                       value="{{ number_format((float) $shipment->cod_amount, 2, '.', '') }}"
                                                       readonly
                                                       class="w-full p-3 rounded-xl border border-border-subtle bg-surface-subtle font-bold text-sm text-primary">
                                            </div>

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Mandatory Photo Proof of Delivery <span class="text-danger">*</span></label>
                                                <input type="file"
                                                       name="proof_photo"
                                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                                       required
                                                       class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                                                <p class="text-[11px] text-text-muted mt-1">Upload photo evidence of the package handed to the buyer (Max 5MB).</p>
                                            </div>

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Recipient Name</label>
                                                <input type="text"
                                                       name="recipient_name"
                                                       value="{{ $deliv['recipient_name'] ?? '' }}"
                                                       class="w-full p-2.5 rounded-xl border border-border-subtle">
                                            </div>

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Delivery Notes (Optional)</label>
                                                <textarea name="notes" rows="2" placeholder="Received at front gate..." class="w-full p-2.5 rounded-xl border border-border-subtle"></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="deliverModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        :disabled="isSubmittingDeliver"
                                                        class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold cursor-pointer disabled:opacity-50">
                                                    <span x-show="!isSubmittingDeliver">Confirm Delivered</span>
                                                    <span x-show="isSubmittingDeliver" x-cloak>Submitting...</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            <template x-teleport="body">
                                <div x-show="failModalOpen"
                                     x-cloak
                                     class="fixed inset-0 z-[180] flex items-center justify-center p-4">
                                    <div @click="failModalOpen = false"
                                         class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>

                                    <div @click.stop
                                         class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                                        <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                            <div>
                                                <h3 class="text-base font-bold text-danger">Report Unsuccessful Delivery</h3>
                                                <p class="text-[11px] text-text-muted">Tracking #: {{ $shipment->tracking_number }} (Attempt #{{ $assignment->attempt_number }})</p>
                                            </div>
                                            <button type="button" @click="failModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>

                                        <form action="{{ route('rider.deliveries.report-failed', $assignment->id) }}"
                                              method="POST"
                                              @submit="isSubmittingFail = true"
                                              class="space-y-4 text-xs">
                                            @csrf
                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Failure Reason <span class="text-danger">*</span></label>
                                                <select name="failure_reason" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                                                    <option value="Buyer Unreachable / Not at Home">Buyer Unreachable / Not at Home</option>
                                                    <option value="Buyer Refused Package / COD">Buyer Refused Package / COD</option>
                                                    <option value="Incorrect / Incomplete Address">Incorrect / Incomplete Address</option>
                                                    <option value="Weather / Road Obstruction">Weather / Road Obstruction</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block font-bold text-text-muted mb-1">Additional Details</label>
                                                <textarea name="failure_notes" rows="3" placeholder="Provide details for Logistics review..." class="w-full p-3 rounded-xl border border-border-subtle"></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="failModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        :disabled="isSubmittingFail"
                                                        class="px-5 py-2.5 rounded-xl bg-danger text-white font-bold cursor-pointer disabled:opacity-50">
                                                    <span x-show="!isSubmittingFail">Submit Failed Attempt</span>
                                                    <span x-show="isSubmittingFail" x-cloak>Submitting...</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </template>

                        </div>
                    @endforeach
                </div>
            @endif

        @elseif($tab === 'remittance')
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
                    <div>
                        <h2 class="text-base font-bold text-primary-dark">Unremitted COD Collections</h2>
                        <p class="text-xs text-text-muted">
                            Remit collected COD cash to {{ $rider->logisticsProfile->business_name ?? 'your Logistics hub' }}.
                            Your 10% Rider Commission on Shipping Fees is automatically calculated.
                        </p>
                    </div>

                    @if($unremittedCollections->isNotEmpty())
                        <form action="{{ route('rider.remittances.store') }}" method="POST" class="m-0 shrink-0">
                            @csrf
                            <button type="submit" class="px-5 py-3 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm cursor-pointer">
                                Remit All Collected COD (₱{{ number_format($metrics['cod_on_hand'], 2) }})
                            </button>
                        </form>
                    @endif
                </div>

                @if($unremittedCollections->isEmpty())
                    <p class="text-xs text-text-muted py-6 text-center">You have no unremitted COD collections on hand.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                                    <th class="py-3 px-4">Collection Ref</th>
                                    <th class="py-3 px-4">Tracking / Order</th>
                                    <th class="py-3 px-4">Collected COD</th>
                                    <th class="py-3 px-4">Shipping Fee</th>
                                    <th class="py-3 px-4">Your 10% Comm</th>
                                    <th class="py-3 px-4 text-right">Collected At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                @foreach($unremittedCollections as $col)
                                    <tr class="hover:bg-surface-subtle/30">
                                        <td class="py-3 px-4 font-mono font-bold text-primary-dark">{{ $col->collection_reference }}</td>
                                        <td class="py-3 px-4">
                                            {{ $col->shipment->tracking_number ?? '' }}
                                            <span class="text-text-muted">({{ $col->sellerOrder->seller_order_number ?? '' }})</span>
                                        </td>
                                        <td class="py-3 px-4 font-bold text-text-main">₱{{ number_format((float) $col->collected_amount, 2) }}</td>
                                        <td class="py-3 px-4">₱{{ number_format((float) $col->shipping_fee, 2) }}</td>
                                        <td class="py-3 px-4 font-bold text-green-700">₱{{ number_format(round((float) $col->shipping_fee * 0.10, 2), 2) }}</td>
                                        <td class="py-3 px-4 text-right text-text-muted">{{ $col->collected_at?->format('M d, Y h:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-primary-dark">Submitted COD Remittances History</h2>
                @if($remittances->isEmpty())
                    <p class="text-xs text-text-muted py-6 text-center">No remittance batches submitted yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                                    <th class="py-3 px-4">Remittance Ref</th>
                                    <th class="py-3 px-4">Total COD</th>
                                    <th class="py-3 px-4">Your 10% Commission</th>
                                    <th class="py-3 px-4">Net Cash Remitted</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Submitted At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                @foreach($remittances as $rem)
                                    <tr>
                                        <td class="py-3 px-4 font-mono font-bold text-primary-dark">{{ $rem->remittance_reference }}</td>
                                        <td class="py-3 px-4 font-semibold">₱{{ number_format((float) $rem->total_collected_amount, 2) }}</td>
                                        <td class="py-3 px-4 font-bold text-green-700">₱{{ number_format((float) $rem->total_rider_commission, 2) }}</td>
                                        <td class="py-3 px-4 font-bold text-text-main">₱{{ number_format((float) $rem->net_remitted_amount, 2) }}</td>
                                        <td class="py-3 px-4">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rem->status === 'verified' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $rem->status }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right text-text-muted">{{ $rem->submitted_at?->format('M d, Y h:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        @else
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs">
                <h2 class="text-base font-bold text-primary-dark mb-4">Past Delivery Assignments</h2>
                @if($historyAssignments->isEmpty())
                    <p class="text-xs text-text-muted py-6 text-center">No completed or failed deliveries yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                                    <th class="py-3 px-4">Tracking #</th>
                                    <th class="py-3 px-4">Attempt #</th>
                                    <th class="py-3 px-4">COD Amount</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                @foreach($historyAssignments as $ha)
                                    <tr>
                                        <td class="py-3 px-4 font-mono font-bold text-primary-dark">{{ $ha->shipment->tracking_number ?? 'N/A' }}</td>
                                        <td class="py-3 px-4">#{{ $ha->attempt_number }}</td>
                                        <td class="py-3 px-4 font-bold">₱{{ number_format((float) ($ha->shipment->cod_amount ?? 0), 2) }}</td>
                                        <td class="py-3 px-4 uppercase font-bold">{{ $ha->status }}</td>
                                        <td class="py-3 px-4 text-right text-text-muted">{{ $ha->updated_at?->format('M d, Y h:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

    </main>

    @livewireScripts
</body>
</html>