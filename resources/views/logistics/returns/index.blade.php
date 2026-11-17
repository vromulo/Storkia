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
    $activeTab = array_key_exists($statusTab, $tabs) ?$statusTab : 'all';
@endphp

<div x-data="{ visibleReturnsLimit: 8 }"
     class="max-w-7xl mx-auto space-y-6 select-none">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Return Shipments</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
                @if(!empty($activeBranchHub))
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary text-white">
                        {{ $activeBranchHub->hub_name }} ({{$activeBranchHub->municipality }})
                    </span>
                @endif
            </div>
            <p class="text-sm text-text-muted mt-1">
                Active return parcels are prioritized at the top in First-In, First-Out (FIFO) sequence; once delivered back to the seller or cancelled, they automatically move to the bottom sorted by most recent.
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
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-primary text-surface text-xs font-bold transition-colors cursor-pointer">
                Search
            </button>
        </form>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key =>$label)
            <a href="{{ route('logistics.returns.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' =>$search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $activeTab ===$key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $label }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTab ===$key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($returnShipments->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-primary-dark">No return shipments found</h3>
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
                        @foreach($returnShipments as $rIdx =>$rs)
                            @php
                                $isTerminal = in_array($rs->status, ['delivered_to_seller', 'cancelled'], true);
                                $pickup =$rs->pickup_address_snapshot ?? [];
                                $sellerAddr =$rs->return_address_snapshot ?? [];
                                $hubSnap =$rs->hub_address_snapshot ?? [];
                                $hubName =$hubSnap['hub_name'] ?? ($hubSnap['name'] ?? $profile->business_name);
                                $badgeClass = match($rs->status) {
                                    'delivered_to_seller' => 'bg-success/15 text-success border-success/25',
                                    'pickup_failed', 'cancelled' => 'bg-danger/10 text-danger border-danger/25',
                                    'awaiting_rider_assignment' => 'bg-brand-light/70 text-primary-dark border-primary/30',
                                    default => 'bg-primary/10 text-primary-dark border-primary/25',
                                };
                                $actionLabel = match($rs->status) {
                                    'awaiting_rider_assignment', 'pickup_failed' => 'Assign Rider',
                                    'picked_up_from_buyer' => 'Receive at Hub',
                                    'received_at_hub' => 'Assign Courier',
                                    default => 'View',
                                };
                                $needsAction = in_array($rs->status, ['awaiting_rider_assignment', 'pickup_failed', 'picked_up_from_buyer', 'received_at_hub'], true);
                            @endphp
                            <tr x-show="{{ $rIdx }} < visibleReturnsLimit"
                                class="hover:bg-surface-subtle/40 transition-colors {{ $isTerminal ? 'bg-surface-subtle/20 opacity-85' : '' }}">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono font-bold text-sm text-primary-dark select-text">{{ $rs->tracking_number }}</span>
                                        @if(! $isTerminal)
                                            <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 text-[10px] font-extrabold">
                                                Active Queue
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full bg-surface-subtle text-text-muted border border-border-subtle text-[10px] font-bold">
                                                Closed
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-text-muted mt-0.5">Request: {{ $rs->returnRequest?->request_number ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Order: {{ $rs->sellerOrder?->seller_order_number ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-text-muted">
                                        {{ $isTerminal ? 'Closed: ' . ($rs->delivered_to_seller_at ?? $rs->cancelled_at ?? $rs->updated_at)?->format('M d, Y h:i A') : 'Created: ' .$rs->created_at?->format('M d, Y h:i A') }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $pickup['recipient_name'] ?? ($rs->sellerOrder?->buyer?->full_name ?? 'Buyer') }}</div>
                                    <div class="text-text-muted mt-0.5">
                                        @if(!empty($pickup['barangay']))
                                            Brgy. {{ $pickup['barangay'] }},
                                        @endif
                                        {{ $pickup['municipality'] ?? '' }}, {{$pickup['province'] ?? '' }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $rs->sellerOrder?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'Seller') }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $sellerAddr['municipality'] ?? '' }}, {{$sellerAddr['province'] ?? '' }}</div>
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
                                       class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors cursor-pointer {{ $needsAction ? 'bg-primary hover:bg-primary-dark text-surface' : 'bg-surface border border-border-subtle text-primary-dark hover:border-primary' }}">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($returnShipments->count() > 8)
                <div class="p-4 border-t border-border-subtle bg-surface-subtle/30 flex justify-center">
                    <button type="button"
                            @click="visibleReturnsLimit = visibleReturnsLimit >= {{ $returnShipments->count() }} ? 8 : visibleReturnsLimit + 8"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleReturnsLimit >= {{ $returnShipments->count() }} ? 'See Less Return Shipments' : 'See More Return Shipments (' + ({{$returnShipments->count() }} - visibleReturnsLimit) + ' remaining on this page)'"></span>
                    </button>
                </div>
            @endif

            @if($returnShipments->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $returnShipments->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection