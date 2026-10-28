<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Courier First-Mile Portal</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16">

    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-18 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary text-surface font-serif font-bold text-lg flex items-center justify-center">
                    C
                </div>
                <div>
                    <h1 class="text-base font-bold text-primary-dark leading-tight">First-Mile Courier Portal</h1>
                    <p class="text-xs text-text-muted">
                        {{ $courier->full_name }} &bull; {{ $courier->vehicle_type }} ({{ $courier->plate_number }}) &bull; Hub: <strong>{{ $courier->logisticsProfile->business_name ?? $courier->home_hub }}</strong>
                    </p>
                </div>
            </div>

            <form action="{{ route('courier.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-50 text-danger hover:bg-red-100 text-xs font-bold transition-colors cursor-pointer">
                    Logout
                </button>
            </form>
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

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-xs font-bold uppercase text-text-muted">Active First-Mile Pickups</span>
                <p class="text-3xl font-serif font-bold text-primary-dark mt-1">{{ $counts['active'] }}</p>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-xs font-bold uppercase text-text-muted">Completed Hub Drop-offs</span>
                <p class="text-3xl font-serif font-bold text-green-700 mt-1">{{ $counts['completed'] }}</p>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-xs font-bold uppercase text-text-muted">Vehicle Load Capacity</span>
                <p class="text-3xl font-serif font-bold text-text-main mt-1">{{ number_format($courier->load_capacity) }} kg</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @foreach(['active' => 'Active Pickups (' . $counts['active'] . ')', 'completed' => 'Completed (' . $counts['completed'] . ')', 'all' => 'All History (' . $counts['all'] . ')'] as $key => $label)
                <a href="{{ route('courier.dashboard', ['status' => $key]) }}"
                   class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $statusFilter === $key ? 'bg-primary-dark text-surface border-primary-dark' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if($assignments->isEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No pickup assignments in this queue</h3>
                <p class="text-xs text-text-muted">When your Logistics hub assigns you to collect a package from a Seller, it will appear here.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($assignments as $assignment)
                    @php
                        $shipment = $assignment->shipment;
                        $pickup = $shipment->pickup_address_snapshot ?? [];
                        $hub = $shipment->hub_address_snapshot ?? [];
                        $packages = $shipment->packages ?? collect();
                        $allVerified = $packages->every(fn($p) => $p->courier_verified_at !== null);
                    @endphp
                    <div class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono font-bold text-sm text-primary-dark bg-brand-light/40 px-3 py-1 rounded-xl">
                                    {{ $shipment->tracking_number }}
                                </span>
                                <span class="text-xs text-text-muted">
                                    Seller Order: <strong>{{ $assignment->sellerOrder->seller_order_number ?? 'N/A' }}</strong>
                                </span>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary uppercase">
                                {{ str_replace('_', ' ', $assignment->status) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-dark block">Step 1: Collect From Seller</span>
                                <p class="text-sm font-bold text-text-main">{{ $pickup['business_name'] ?? 'Seller Store' }}</p>
                                <p class="text-text-muted">Contact: {{ $pickup['contact_no'] ?? '' }}</p>
                                <p class="text-text-main leading-relaxed">{{ $pickup['full_address'] ?? '' }}</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-dark block">Step 2: Transport to Sorting Facility</span>
                                <p class="text-sm font-bold text-text-main">{{ $hub['business_name'] ?? $courier->home_hub }}</p>
                                <p class="text-text-muted">Contact: {{ $hub['contact_no'] ?? '' }}</p>
                                <p class="text-text-main leading-relaxed">{{ $hub['full_address'] ?? '' }}</p>
                            </div>
                        </div>

                        @if($packages->isNotEmpty())
                            <div class="p-4 rounded-2xl bg-brand-light/20 border border-border-subtle space-y-3 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-primary-dark uppercase text-[10px]">Package Identification & QR Verification Checklist</span>
                                    <span class="font-bold {{ $allVerified ? 'text-green-700' : 'text-danger' }}">
                                        {{ $packages->whereNotNull('courier_verified_at')->count() }} / {{ $packages->count() }} Verified
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($packages as $pkg)
                                        <div class="p-3 rounded-xl bg-surface border border-border-subtle flex items-center justify-between gap-2">
                                            <div>
                                                <div class="font-mono font-bold text-neutral-900">{{ $pkg->package_reference }}</div>
                                                <div class="text-[11px] text-text-muted">Pkg {{ $pkg->package_number }} of {{ $pkg->total_packages }} &bull; {{ number_format((float) $pkg->package_weight, 2) }} kg</div>
                                            </div>
                                            <div>
                                                @if($pkg->courier_verified_at)
                                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 font-bold text-[10px]">Verified</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800 font-bold text-[10px]">Pending Scan</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if(in_array($assignment->status, ['assigned', 'accepted'], true) && ! $allVerified)
                                    <form action="{{ route('courier.pickups.verify-lookup', $assignment->id) }}" method="POST" class="flex items-center gap-2 pt-2">
                                        @csrf
                                        <input type="text"
                                               name="lookup_input"
                                               required
                                               placeholder="Scan QR token or enter package reference..."
                                               class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary font-mono">
                                        <input type="hidden" name="verification_method" value="manual">
                                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">
                                            Verify Package
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border-subtle text-xs">
                            <div class="text-text-muted">
                                Parcel Weight: <strong class="text-text-main">{{ number_format((float) $shipment->total_weight, 2) }} kg</strong>
                                @if($assignment->notes)
                                    &bull; Note: <em>"{{ $assignment->notes }}"</em>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if($assignment->status === 'assigned')
                                    <form action="{{ route('courier.pickups.accept', $assignment->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                            1. Accept & Travel to Seller
                                        </button>
                                    </form>
                                @endif

                                @if(in_array($assignment->status, ['assigned', 'accepted'], true))
                                    <form action="{{ route('courier.pickups.confirm-pickup', $assignment->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit"
                                                @if(! $allVerified) disabled @endif
                                                class="px-5 py-2.5 rounded-xl font-bold shadow-xs cursor-pointer {{ $allVerified ? 'bg-primary hover:bg-primary-dark text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                                            2. Confirm Package Picked Up from Seller
                                        </button>
                                    </form>
                                @endif

                                @if($assignment->status === 'picked_up')
                                    <form action="{{ route('courier.pickups.transport-to-hub', $assignment->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer">
                                            3. Confirm Handover at Logistics Hub
                                        </button>
                                    </form>
                                @endif

                                @if($assignment->status === 'in_transit_to_hub')
                                    <span class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 font-bold">
                                        Handed Over — Awaiting Hub Verification
                                    </span>
                                @endif

                                @if($assignment->status === 'completed')
                                    <span class="px-3 py-1.5 rounded-xl bg-green-50 text-green-700 border border-green-200 font-bold">
                                        Verified at Hub ({{ $assignment->hub_verified_at?->format('M d, h:i A') }})
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <div>
                    {{ $assignments->links() }}
                </div>
            </div>
        @endif

    </main>
</body>
</html>