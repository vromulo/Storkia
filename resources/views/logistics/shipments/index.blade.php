@extends('layouts.logistics', ['title' => 'Storkia - Logistics Shipments'])

@section('content')
@php
    $queueSort = \App\Models\Shipment::normalizeQueueSort($queueSort ?? request()->query('sort'));
    $tierFilter = $tierFilter ?? request()->query('tier', '');
    $municipalityFilter = $municipalityFilter ?? request()->query('municipality', '');
    $hubFilter = (int) ($hubFilter ?? request()->query('hub_id', 0));
    $companyHubs = $companyHubs ?? collect();
    $availableMunicipalities = $availableMunicipalities ?? collect();

    $sortLabels = [
        'oldest'   => 'Oldest First (FIFO)',
        'newest'   => 'Newest First (LIFO)',
        'nearest'  => 'Nearest to Hub First (km)',
        'farthest' => 'Farthest from Hub First (km)',
        'heaviest' => 'Heaviest Parcel First (kg)',
    ];

    $tierLabels = [
        ''                  => 'All Distance Tiers',
        'same_barangay'     => 'Same Barangay',
        'same_municipality' => 'Same Municipality',
        'same_province'     => 'Same Province',
        'inter_province'    => 'Inter-Provincial',
        'inter_island'      => 'Inter-Island',
    ];

    $hubOptionsMap = ['0' => 'All Company Hubs'];
    foreach ($companyHubs as $cHub) {
        $hubOptionsMap[(string) $cHub->id] = $cHub->hub_name . ' (' . $cHub->municipality . ')';
    }
@endphp

<div x-data="{
        hubOpen: false,
        munOpen: false,
        tierOpen: false,
        sortOpen: false,
        selectedHubId: '{{ (int) $hubFilter }}',
        selectedMunicipality: @js((string) $municipalityFilter),
        selectedTier: @js((string) $tierFilter),
        selectedSort: @js((string) $queueSort),
        hubLabels: @js($hubOptionsMap),
        tierLabels: @js($tierLabels),
        sortLabels: @js($sortLabels),
        submitFilterForm() {
            this.$nextTick(() => {
                if (this.$refs.shipmentFilterForm) {
                    this.$refs.shipmentFilterForm.submit();
                }
            });
        }
     }"
     class="max-w-7xl mx-auto space-y-6 select-none">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Shipments &amp; Dispatch</h1>
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
                Active shipments are prioritized at the top while delivered or returned shipments move to the bottom. Filter by hub, municipality, or sort by nearest/farthest distance.
            </p>
        </div>

        <form method="GET" action="{{ route('logistics.shipments.index') }}" class="flex items-center gap-2">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            @if($queueSort !== 'oldest')
                <input type="hidden" name="sort" value="{{ $queueSort }}">
            @endif
            @if($tierFilter !== '')
                <input type="hidden" name="tier" value="{{ $tierFilter }}">
            @endif
            @if($municipalityFilter !== '')
                <input type="hidden" name="municipality" value="{{ $municipalityFilter }}">
            @endif
            @if($hubFilter > 0)
                <input type="hidden" name="hub_id" value="{{ $hubFilter }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Tracking #, package ref, order, store..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-primary text-surface text-xs font-bold transition-colors cursor-pointer">
                Search
            </button>
        </form>
    </div>

    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3">
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
                <a href="{{ route('logistics.shipments.index', array_filter([
                        'status'       => $key === 'all' ? null : $key,
                        'search'       => $search,
                        'sort'         => $queueSort !== 'oldest' ? $queueSort : null,
                        'tier'         => $tierFilter ?: null,
                        'municipality' => $municipalityFilter ?: null,
                        'hub_id'       => $hubFilter > 0 ? $hubFilter : null,
                   ])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        <form x-ref="shipmentFilterForm" method="GET" action="{{ route('logistics.shipments.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            @if($search !== '')
                <input type="hidden" name="search" value="{{ $search }}">
            @endif
            <input type="hidden" name="hub_id" :value="selectedHubId === '0' ? '' : selectedHubId">
            <input type="hidden" name="municipality" :value="selectedMunicipality">
            <input type="hidden" name="tier" :value="selectedTier">
            <input type="hidden" name="sort" :value="selectedSort">

            @if(empty($activeBranchHub) && $companyHubs->count() > 1)
                <div class="relative" @click.away="hubOpen = false">
                    <button type="button"
                            @click="hubOpen = !hubOpen; munOpen = false; tierOpen = false; sortOpen = false"
                            class="px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface text-xs font-bold text-text-main flex items-center gap-2 transition-colors cursor-pointer max-w-[200px]">
                        <span class="truncate" x-text="hubLabels[selectedHubId] || 'All Company Hubs'"></span>
                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0 transition-transform" :class="hubOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="hubOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-60 bg-surface border border-border-subtle rounded-2xl shadow-xl z-40 py-1.5 max-h-60 overflow-y-auto custom-scrollbar">
                        @foreach($hubOptionsMap as $hKey => $hLbl)
                            <button type="button"
                                    @click="selectedHubId = '{{ $hKey }}'; hubOpen = false; submitFilterForm()"
                                    class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                    :class="selectedHubId === '{{ $hKey }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                                <span class="truncate pr-2">{{ $hLbl }}</span>
                                <span x-show="selectedHubId === '{{ $hKey }}'" class="text-primary font-extrabold shrink-0">&#10003;</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($availableMunicipalities->isNotEmpty())
                <div class="relative" @click.away="munOpen = false">
                    <button type="button"
                            @click="munOpen = !munOpen; hubOpen = false; tierOpen = false; sortOpen = false"
                            class="px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface text-xs font-bold text-text-main flex items-center gap-2 transition-colors cursor-pointer max-w-[180px]">
                        <span class="truncate" x-text="selectedMunicipality || 'All Municipalities'"></span>
                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0 transition-transform" :class="munOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="munOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-52 bg-surface border border-border-subtle rounded-2xl shadow-xl z-40 py-1.5 max-h-60 overflow-y-auto custom-scrollbar">
                        <button type="button"
                                @click="selectedMunicipality = ''; munOpen = false; submitFilterForm()"
                                class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                :class="selectedMunicipality === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                            <span>All Municipalities</span>
                            <span x-show="selectedMunicipality === ''" class="text-primary font-extrabold">&#10003;</span>
                        </button>
                        @foreach($availableMunicipalities as $munOpt)
                            <button type="button"
                                    @click="selectedMunicipality = '{{ addslashes($munOpt) }}'; munOpen = false; submitFilterForm()"
                                    class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                    :class="selectedMunicipality === '{{ addslashes($munOpt) }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                                <span class="truncate pr-2">{{ $munOpt }}</span>
                                <span x-show="selectedMunicipality === '{{ addslashes($munOpt) }}'" class="text-primary font-extrabold shrink-0">&#10003;</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="relative" @click.away="tierOpen = false">
                <button type="button"
                        @click="tierOpen = !tierOpen; hubOpen = false; munOpen = false; sortOpen = false"
                        class="px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface text-xs font-bold text-text-main flex items-center gap-2 transition-colors cursor-pointer">
                    <span x-text="tierLabels[selectedTier] || 'All Distance Tiers'"></span>
                    <svg class="w-3.5 h-3.5 text-text-muted transition-transform" :class="tierOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="tierOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-52 bg-surface border border-border-subtle rounded-2xl shadow-xl z-40 py-1.5 overflow-hidden">
                    @foreach($tierLabels as $tVal => $tLabel)
                        <button type="button"
                                @click="selectedTier = '{{ $tVal }}'; tierOpen = false; submitFilterForm()"
                                class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                :class="selectedTier === '{{ $tVal }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                            <span>{{ $tLabel }}</span>
                            <span x-show="selectedTier === '{{ $tVal }}'" class="text-primary font-extrabold">&#10003;</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="relative" @click.away="sortOpen = false">
                <button type="button"
                        @click="sortOpen = !sortOpen; hubOpen = false; munOpen = false; tierOpen = false"
                        class="px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface text-xs font-bold text-primary-dark flex items-center gap-2 transition-colors cursor-pointer">
                    <span x-text="'Sort: ' + (sortLabels[selectedSort] || 'Oldest First (FIFO)')"></span>
                    <svg class="w-3.5 h-3.5 text-primary transition-transform" :class="sortOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="sortOpen" x-cloak x-transition class="absolute right-0 mt-1.5 w-60 bg-surface border border-border-subtle rounded-2xl shadow-xl z-40 py-1.5 overflow-hidden">
                    @foreach($sortLabels as $sVal => $sLabel)
                        <button type="button"
                                @click="selectedSort = '{{ $sVal }}'; sortOpen = false; submitFilterForm()"
                                class="w-full px-3.5 py-2 text-left hover:bg-brand-light/30 flex items-center justify-between font-semibold cursor-pointer"
                                :class="selectedSort === '{{ $sVal }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                            <span>{{ $sLabel }}</span>
                            <span x-show="selectedSort === '{{ $sVal }}'" class="text-primary font-extrabold">&#10003;</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if($tierFilter !== '' || $municipalityFilter !== '' || $hubFilter > 0 || $queueSort !== 'oldest')
                <a href="{{ route('logistics.shipments.index', array_filter(['status' => $statusTab === 'all' ? null : $statusTab, 'search' => $search])) }}"
                   class="px-3 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($shipments->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No shipments found</h3>
                <p class="text-xs text-text-muted">Shipments assigned to {{ $activeBranchHub->hub_name ?? $profile->business_name }} matching your filter will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Tracking &amp; Handover</th>
                            <th class="py-3.5 px-4">Origin (Seller)</th>
                            <th class="py-3.5 px-4">Destination (Buyer)</th>
                            <th class="py-3.5 px-4">Hub &amp; Personnel</th>
                            <th class="py-3.5 px-4">COD &amp; Weight</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($shipments as $shipment)
                            @php
                                $isTerminal = method_exists($shipment, 'isTerminalStatus')
                                    ? $shipment->isTerminalStatus()
                                    : in_array($shipment->status, ['delivered', 'returned_to_seller'], true);
                                $pickup = $shipment->pickup_address_snapshot ?? [];
                                $deliv = $shipment->delivery_address_snapshot ?? [];
                                $hubSnap = $shipment->hub_address_snapshot ?? [];
                                $primaryPkg = $shipment->packages->first();
                                $handoverMethod = $primaryPkg?->handover_method ?? ($hubSnap['handover_method'] ?? 'courier_pickup');
                                $assignedHubName = $primaryPkg?->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? $profile->business_name);
                                $destHubName = $shipment->destinationLogisticsHub?->hub_name ?? ($hubSnap['destination_hub']['hub_name'] ?? null);
                                $origDistKm = $shipment->calculated_origin_distance_km;
                                $buyerDistKm = $shipment->calculated_buyer_distance_km;
                                $badgeClass = match($shipment->status) {
                                    'delivered' => 'bg-success/15 text-success border-success/25',
                                    'delivery_failed', 'returning_to_seller', 'returned_to_seller' => 'bg-danger/10 text-danger border-danger/25',
                                    'out_for_delivery', 'received_at_hub', 'rider_assigned' => 'bg-primary/10 text-primary-dark border-primary/25',
                                    default => 'bg-brand-light/60 text-primary-dark border-border-subtle',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors {{ $isTerminal ? 'bg-surface-subtle/20 opacity-85' : '' }}">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono font-bold text-sm text-primary-dark">{{ $shipment->tracking_number }}</span>
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
                                    <div class="text-text-muted mt-0.5">Order: {{ $shipment->sellerOrder->seller_order_number ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-text-muted">Created: {{ $shipment->created_at?->format('M d, Y h:i A') }}</div>
                                    @if($isTerminal)
                                        <div class="text-[11px] text-text-muted">
                                            Closed: {{ ($shipment->delivered_at ?? $shipment->returned_at ?? $shipment->updated_at)?->format('M d, Y h:i A') }}
                                        </div>
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        @if($handoverMethod === 'self_drop_off')
                                            <span class="px-2 py-0.5 rounded-md bg-primary/15 text-primary-dark border border-primary/25 text-[10px] font-bold">
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
                                    <div class="mt-1">
                                        <span class="px-2 py-0.5 rounded bg-surface-subtle border border-border-subtle text-[10px] font-mono font-bold text-text-muted">
                                            ~{{ number_format($origDistKm, 1) }} km to Origin Hub
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $deliv['recipient_name'] ?? 'Buyer' }}</div>
                                    <div class="text-text-muted mt-0.5">
                                        @if(!empty($deliv['barangay']))
                                            Brgy. {{ $deliv['barangay'] }},
                                        @endif
                                        {{ $deliv['municipality'] ?? '' }}, {{ $deliv['province'] ?? '' }}
                                    </div>
                                    <div class="mt-1">
                                        <span class="px-2 py-0.5 rounded bg-brand-light/50 border border-border-subtle text-[10px] font-mono font-bold text-primary-dark">
                                            ~{{ number_format($buyerDistKm, 1) }} km Last-Mile
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1">
                                    <div class="text-[11px] font-bold text-primary-dark truncate max-w-[210px]" title="{{ $assignedHubName }}">
                                        Origin Hub: {{ $assignedHubName }}
                                    </div>
                                    @if($destHubName && $destHubName !== $assignedHubName)
                                        <div class="text-[11px] font-bold text-primary truncate max-w-[210px]" title="{{ $destHubName }}">
                                            Dest Hub: {{ $destHubName }}
                                        </div>
                                    @endif
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
                                    <div class="font-bold text-price">COD: &#8369;{{ number_format((float) $shipment->cod_amount, 2) }}</div>
                                    <div class="text-text-muted">Ship Fee: &#8369;{{ number_format((float) $shipment->shipping_fee, 2) }}</div>
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
                                       class="inline-flex items-center px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-xs transition-colors cursor-pointer">
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