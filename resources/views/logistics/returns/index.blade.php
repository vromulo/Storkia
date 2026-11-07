@extends('layouts.logistics', ['title' => 'Storkia - Return Shipments'])

@section('content')
@php
    $tabs = [
        'all'          => 'All Returns',
        'needs_rider'  => 'Needs Rider',
        'rider_pickup' => 'Rider Collecting',
        'to_hub'       => 'On the Way to Hub',
        'at_hub'       => 'At Sorting Hub',
        'to_seller'    => 'Returning to Seller',
        'delivered'    => 'Delivered to Seller',
        'cancelled'    => 'Cancelled',
    ];

    $activeTab = array_key_exists($statusTab, $tabs) ? $statusTab : 'all';
@endphp

<div class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Return Shipments</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
                @if(!empty($activeBranchHub))
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary text-white">
                        {{ $activeBranchHub->hub_name }} ({{ $activeBranchHub->municipality }})
                    </span>
                @endif
            </div>
            <p class="text-sm text-text-muted mt-1">
                Parcels travelling back to sellers after an approved Return &amp; Refund. Dispatch a Rider to collect from the buyer,
                confirm the parcel at the sorting hub, then dispatch a Courier to deliver it to the seller.
            </p>
        </div>

        <form method="GET" action="{{ route('logistics.returns.index') }}" class="flex items-center gap-2">
            @if($activeTab !== 'all')
                <input type="hidden" name="status" value="{{ $activeTab }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Return #, request #, order, store..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold transition-colors cursor-pointer">
                Search
            </button>
        </form>
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

    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('logistics.returns.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $activeTab === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $label }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTab === $key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($returnShipments->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No return shipments found</h3>
                <p class="text-xs text-text-muted">
                    When a seller or Storkia Admin approves a return, the parcel appears here for {{ $activeBranchHub->hub_name ?? $profile->business_name }}.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Return &amp; Request</th>
                            <th class="py-3.5 px-4">Pickup From (Buyer)</th>
                            <th class="py-3.5 px-4">Deliver To (Seller)</th>
                            <th class="py-3.5 px-4">Hub &amp; Personnel</th>
                            <th class="py-3.5 px-4">Parcel</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($returnShipments as $rs)
                            @php
                                $pickup = $rs->pickup_address_snapshot ?? [];
                                $sellerAddr = $rs->return_address_snapshot ?? [];
                                $hubSnap = $rs->hub_address_snapshot ?? [];
                                $hubName = $hubSnap['hub_name'] ?? ($hubSnap['name'] ?? $profile->business_name);
                                $badgeClass = match($rs->status) {
                                    'delivered_to_seller' => 'bg-green-100 text-green-700 border-green-200',
                                    'pickup_failed', 'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                                    'awaiting_rider_assignment' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    default => 'bg-blue-100 text-blue-700 border-blue-200',
                                };
                                $actionLabel = match($rs->status) {
                                    'awaiting_rider_assignment', 'pickup_failed' => 'Assign Rider',
                                    'picked_up_from_buyer' => 'Receive at Hub',
                                    'received_at_hub' => 'Assign Courier',
                                    default => 'View',
                                };
                                $needsAction = in_array($rs->status, ['awaiting_rider_assignment', 'pickup_failed', 'picked_up_from_buyer', 'received_at_hub'], true);
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-sm text-primary-dark">{{ $rs->tracking_number }}</div>
                                    <div class="text-text-muted mt-0.5">Request: {{ $rs->returnRequest?->request_number ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Order: {{ $rs->sellerOrder?->seller_order_number ?? 'N/A' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $pickup['recipient_name'] ?? ($rs->sellerOrder?->buyer?->full_name ?? 'Buyer') }}</div>
                                    <div class="text-text-muted mt-0.5">
                                        @if(!empty($pickup['barangay']))
                                            Brgy. {{ $pickup['barangay'] }},
                                        @endif
                                        {{ $pickup['municipality'] ?? '' }}, {{ $pickup['province'] ?? '' }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $rs->sellerOrder?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'Seller') }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $sellerAddr['municipality'] ?? '' }}, {{ $sellerAddr['province'] ?? '' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1">
                                    <div class="text-[11px] font-bold text-primary-dark truncate max-w-[200px]" title="{{ $hubName }}">
                                        Hub: {{ $hubName }}
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Rider:</span>
                                        <span class="font-semibold text-text-main">{{ $rs->rider?->full_name ?? 'Unassigned' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Courier:</span>
                                        <span class="font-semibold text-text-main">{{ $rs->courier?->full_name ?? 'Unassigned' }}</span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div class="text-text-muted">Weight: {{ number_format((float) $rs->total_weight, 2) }} kg</div>
                                    <div class="text-text-muted">{{ $rs->returnRequest?->reason_category }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                        {{ $rs->status_label }}
                                    </span>
                                    @if($rs->pickup_attempts_count > 0)
                                        <div class="text-[11px] text-text-muted mt-1">
                                            Pickup attempts: {{ $rs->pickup_attempts_count }} / {{ \App\Models\ReturnShipment::MAX_PICKUP_ATTEMPTS }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <a href="{{ route('logistics.returns.show', $rs->id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors {{ $needsAction ? 'bg-primary hover:bg-primary-dark text-surface' : 'bg-surface border border-border-subtle text-primary-dark hover:border-primary' }}">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($returnShipments->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $returnShipments->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection