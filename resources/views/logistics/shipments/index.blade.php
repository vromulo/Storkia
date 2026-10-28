@extends('layouts.logistics', ['title' => 'Storkia - Logistics Shipments'])

@section('content')
<div class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Shipments & Dispatch</h1>
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
                Assign first-mile Couriers, verify Seller Self Drop-Offs and sorting hub arrivals via QR or Manual lookup, dispatch Barangay Riders, and resolve failed deliveries.
            </p>
        </div>

        <form method="GET" action="{{ route('logistics.shipments.index') }}" class="flex items-center gap-2">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Tracking #, package ref, order, store..."
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
        @php
            $tabs = [
                'all'              => ['label' => 'All Shipments', 'count' => $counts['all']],
                'to_pickup'        => ['label' => 'To Pick Up / Drop-Off', 'count' => $counts['to_pickup']],
                'in_transit_hub'   => ['label' => 'Picked Up / To Hub', 'count' => $counts['in_transit_hub']],
                'at_hub'           => ['label' => 'At Sorting Hub', 'count' => $counts['at_hub']],
                'out_for_delivery' => ['label' => 'Out for Delivery', 'count' => $counts['out_for_delivery']],
                'delivered'        => ['label' => 'Delivered', 'count' => $counts['delivered']],
                'failed_returned'  => ['label' => 'Failed / Returned', 'count' => $counts['failed_returned']],
            ];
        @endphp

        @foreach($tabs as $key => $tab)
            <a href="{{ route('logistics.shipments.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($shipments->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No shipments found</h3>
                <p class="text-xs text-text-muted">Shipments assigned to {{ $activeBranchHub->hub_name ?? $profile->business_name }} will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Tracking & Handover</th>
                            <th class="py-3.5 px-4">Origin (Seller)</th>
                            <th class="py-3.5 px-4">Destination (Buyer)</th>
                            <th class="py-3.5 px-4">Hub & Personnel</th>
                            <th class="py-3.5 px-4">COD & Weight</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($shipments as $shipment)
                            @php
                                $pickup = $shipment->pickup_address_snapshot ?? [];
                                $deliv = $shipment->delivery_address_snapshot ?? [];
                                $hubSnap = $shipment->hub_address_snapshot ?? [];
                                $primaryPkg = $shipment->packages->first();
                                $handoverMethod = $primaryPkg?->handover_method ?? ($hubSnap['handover_method'] ?? 'courier_pickup');
                                $assignedHubName = $primaryPkg?->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? $profile->business_name);
                                $badgeClass = match($shipment->status) {
                                    'delivered' => 'bg-green-100 text-green-700 border-green-200',
                                    'delivery_failed', 'returning_to_seller', 'returned_to_seller' => 'bg-red-100 text-red-700 border-red-200',
                                    'out_for_delivery', 'received_at_hub', 'rider_assigned' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    default => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-sm text-primary-dark">{{ $shipment->tracking_number }}</div>
                                    <div class="text-text-muted mt-0.5">Order: {{ $shipment->sellerOrder->seller_order_number ?? 'N/A' }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        @if($handoverMethod === 'self_drop_off')
                                            <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 border border-purple-200 text-[10px] font-bold">
                                                Seller Self Drop-Off
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-md bg-brand-light/50 text-primary-dark border border-border-subtle text-[10px] font-bold">
                                                Courier Pickup
                                            </span>
                                        @endif
                                        @if($shipment->packages->count() > 1)
                                            <span class="px-2 py-0.5 rounded-md bg-surface-subtle border border-border-subtle text-[10px] font-mono font-bold">
                                                {{ $shipment->packages->count() }} Pkgs
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $shipment->sellerProfile->business_name ?? ($pickup['business_name'] ?? 'Seller') }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $pickup['municipality'] ?? '' }}, {{ $pickup['province'] ?? '' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $deliv['recipient_name'] ?? 'Buyer' }}</div>
                                    <div class="text-text-muted mt-0.5">
                                        @if(!empty($deliv['barangay']))
                                            Brgy. {{ $deliv['barangay'] }},
                                        @endif
                                        {{ $deliv['municipality'] ?? '' }}, {{ $deliv['province'] ?? '' }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1">
                                    <div class="text-[11px] font-bold text-primary-dark truncate max-w-[200px]" title="{{ $assignedHubName }}">
                                        Hub: {{ $assignedHubName }}
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Courier:</span>
                                        <span class="font-semibold text-text-main">
                                            {{ $handoverMethod === 'self_drop_off' ? 'N/A (Self Drop-Off)' : ($shipment->currentCourier?->full_name ?? 'Unassigned') }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Rider:</span>
                                        <span class="font-semibold text-text-main">{{ $shipment->currentRider?->full_name ?? 'Unassigned' }}</span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div class="font-bold text-primary">COD: ₱{{ number_format((float) $shipment->cod_amount, 2) }}</div>
                                    <div class="text-text-muted">Ship Fee: ₱{{ number_format((float) $shipment->shipping_fee, 2) }}</div>
                                    <div class="text-text-muted">Weight: {{ number_format((float) $shipment->total_weight, 2) }} kg</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                        {{ $shipment->status_label }}
                                    </span>
                                    @if($shipment->delivery_attempts_count > 0)
                                        <div class="text-[11px] text-text-muted mt-1">
                                            Attempts: {{ $shipment->delivery_attempts_count }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <a href="{{ route('logistics.shipments.show', $shipment->id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-xs transition-colors">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($shipments->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $shipments->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection