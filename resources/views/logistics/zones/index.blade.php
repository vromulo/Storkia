@extends('layouts.logistics', ['title' => 'Storkia - Delivery Zones, Rates & Exceptions'])

@section('content')
@php
    $overrideStatusLabels = [
        'received_at_hub'    => 'At Hub',
        'out_for_delivery'   => 'Out for Delivery',
        'delivered'          => 'Delivered',
        'returned_to_seller' => 'Returned to Sender (RTS)',
        'lost'               => 'Mark Parcel LOST',
        'damaged'            => 'Mark Parcel DAMAGED (Photo Req.)',
    ];
@endphp

<div x-data="{
    editRateId: '',
    zoneCode: '',
    zoneName: '',
    originRegion: 'NCR',
    destRegion: 'NCR',
    firstKg: '50.00',
    addKg: '15.00',
    isServiceable: '1',
    serviceableOpen: false,
    visibleZonesLimit: 8,
    visibleShipmentsLimit: 6,
    visibleShortfallsLimit: 5,
    overrideStatusLabels: @js($overrideStatusLabels),
    populateZone(rate) {
        this.editRateId = rate.id;
        this.zoneCode = rate.zone_code;
        this.zoneName = rate.zone_name;
        this.originRegion = rate.origin_region || 'NCR';
        this.destRegion = rate.destination_region || 'NCR';
        this.firstKg = rate.first_kg_rate;
        this.addKg = rate.additional_kg_rate;
        this.isServiceable = rate.is_serviceable ? '1' : '0';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    resetZoneForm() {
        this.editRateId = '';
        this.zoneCode = '';
        this.zoneName = '';
        this.originRegion = 'NCR';
        this.destRegion = 'NCR';
        this.firstKg = '50.00';
        this.addKg = '15.00';
        this.isServiceable = '1';
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Delivery Zones, Shipping Rates &amp; Exception Handling</h1>
            <p class="text-sm text-text-muted mt-1">
                Manage serviceable delivery zones and per-kg shipping rates, log manual shipment overrides or Lost/Damaged exceptions, and audit COD remittance shortfalls.
            </p>
        </div>

        <a href="{{ route('logistics.reports.formal-pdf') }}"
           target="_blank"
           class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-primary text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
            Open Formal Printable PDF Statement &nearr;
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-5 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark" x-text="editRateId ? 'Update Delivery Zone Rate #' + editRateId : 'Add Delivery Zone & Shipping Rate'"></h2>
                <button type="button" x-show="editRateId" @click="resetZoneForm()" class="text-xs font-bold text-primary hover:underline cursor-pointer">New Zone</button>
            </div>

            <form action="{{ route('logistics.zones.rates.store') }}" method="POST" class="space-y-3.5 text-xs">
                @csrf
                <input type="hidden" name="rate_id" :value="editRateId">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Zone Code <span class="text-danger">*</span></label>
                        <input type="text" name="zone_code" x-model="zoneCode" required maxlength="40" placeholder="e.g. NCR-LOCAL" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold outline-none focus:border-primary">
                    </div>
                    <div class="relative" @click.away="serviceableOpen = false">
                        <label class="block font-bold text-text-muted uppercase mb-1">Serviceability <span class="text-danger">*</span></label>
                        <input type="hidden" name="is_serviceable" :value="isServiceable">
                        <button type="button"
                                @click="serviceableOpen = !serviceableOpen"
                                class="w-full p-2.5 rounded-xl border border-border-subtle hover:border-primary bg-surface font-bold flex items-center justify-between text-left cursor-pointer">
                            <span :class="isServiceable === '1' ? 'text-success' : 'text-danger'"
                                  x-text="isServiceable === '1' ? 'Serviceable (Active)' : 'Unserviceable (Block Checkout)'"></span>
                            <svg class="w-4 h-4 text-primary shrink-0 transition-transform" :class="serviceableOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="serviceableOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl z-30 overflow-hidden">
                            <button type="button"
                                    @click="isServiceable = '1'; serviceableOpen = false"
                                    class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                <span class="text-success font-bold">Serviceable (Active)</span>
                                <span x-show="isServiceable === '1'" class="text-primary font-extrabold">&#10003;</span>
                            </button>
                            <button type="button"
                                    @click="isServiceable = '0'; serviceableOpen = false"
                                    class="w-full px-3.5 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer">
                                <span class="text-danger font-bold">Unserviceable (Block Checkout)</span>
                                <span x-show="isServiceable === '0'" class="text-primary font-extrabold">&#10003;</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Zone Display Name <span class="text-danger">*</span></label>
                    <input type="text" name="zone_name" x-model="zoneName" required maxlength="120" placeholder="e.g. Within Metro Manila (NCR)" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold outline-none focus:border-primary">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Origin Region <span class="text-danger">*</span></label>
                        <input type="text" name="origin_region" x-model="originRegion" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Destination Region <span class="text-danger">*</span></label>
                        <input type="text" name="destination_region" x-model="destRegion" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">First 1 KG Rate (&#8369;) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="first_kg_rate" x-model="firstKg" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold text-price outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Each Additional KG (&#8369;) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="additional_kg_rate" x-model="addKg" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Audit Log Reason (10–300 chars) <span class="text-danger">*</span></label>
                    <input type="text" name="reason" required minlength="10" maxlength="300" placeholder="Reason for adding or updating this zone rate..." class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors cursor-pointer">
                        <span x-text="editRateId ? 'Save Rate Update' : 'Add Delivery Zone'"></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-7 bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Configured Delivery Zones &amp; Per-KG Rate Table</h2>
                <p class="text-xs text-text-muted">Rate changes apply only to new checkouts started after the update; existing orders retain their locked checkout shipping snapshot.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-4">Zone</th>
                            <th class="py-3 px-4">Route</th>
                            <th class="py-3 px-4">1st KG</th>
                            <th class="py-3 px-4">+Add'l KG</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($rates as $zIdx => $rate)
                            <tr x-show="{{ $zIdx }} < visibleZonesLimit" class="hover:bg-surface-subtle/30">
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-primary-dark">{{ $rate->zone_code }}</div>
                                    <div class="text-text-main font-semibold">{{ $rate->zone_name }}</div>
                                </td>
                                <td class="py-3 px-4 text-text-muted">{{ $rate->origin_region }} &rarr; {{ $rate->destination_region }}</td>
                                <td class="py-3 px-4 font-bold text-price">&#8369;{{ number_format((float) $rate->first_kg_rate, 2) }}</td>
                                <td class="py-3 px-4 font-semibold">+&#8369;{{ number_format((float) $rate->additional_kg_rate, 2) }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rate->is_serviceable ? 'bg-success/15 text-success' : 'bg-danger/10 text-danger' }}">
                                        {{ $rate->is_serviceable ? 'Serviceable' : 'Unserviceable' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button type="button"
                                            @click='populateZone(@json($rate))'
                                            class="px-3 py-1 rounded-lg bg-surface-subtle border border-border-subtle hover:border-primary font-bold text-primary-dark transition-colors cursor-pointer">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($rates->count() > 8)
                <div class="p-4 border-t border-border-subtle bg-surface-subtle/30 flex justify-center">
                    <button type="button"
                            @click="visibleZonesLimit = visibleZonesLimit >= {{ $rates->count() }} ? 8 : visibleZonesLimit + 8"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleZonesLimit >= {{ $rates->count() }} ? 'See Less Delivery Zones' : 'See More Delivery Zones (' + ({{ $rates->count() }} - visibleZonesLimit) + ' remaining)'"></span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
        <div class="border-b border-border-subtle pb-3">
            <h2 class="text-base font-bold text-primary-dark">Shipment Exception &amp; Manual Status Override Console (Audit Logged)</h2>
            <p class="text-xs text-text-muted">
                Every manual override or Lost/Damaged exception requires a 10–300 character reason and writes an immutable entry to the Platform Audit Log. Marking a parcel <strong>Damaged</strong> requires a JPG/PNG photo (max 5 MB).
            </p>
        </div>

        @if($activeShipments->isEmpty())
            <p class="text-xs text-text-muted">No shipments found for this hub.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-4">Tracking / Order</th>
                            <th class="py-3 px-4">Current Status</th>
                            <th class="py-3 px-4">Override / Exception Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($activeShipments as $shIdx => $sh)
                            @php
                                $initialState = array_key_exists($sh->status, $overrideStatusLabels) ? $sh->status : 'received_at_hub';
                            @endphp
                            <tr x-show="{{ $shIdx }} < visibleShipmentsLimit"
                                x-data="{ targetState: '{{ $initialState }}', statusDropdownOpen: false }">
                                <td class="py-3 px-4 align-top">
                                    <a href="{{ route('logistics.shipments.show', $sh->id) }}" class="font-mono font-bold text-primary-dark hover:underline cursor-pointer">{{ $sh->tracking_number }}</a>
                                    <div class="text-text-muted">Order #{{ $sh->sellerOrder->seller_order_number ?? 'N/A' }}</div>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/40 text-primary-dark uppercase">{{ $sh->status }}</span>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <form action="{{ route('logistics.shipments.override-status', $sh->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="new_status" :value="targetState">

                                        <div class="relative" @click.away="statusDropdownOpen = false">
                                            <button type="button"
                                                    @click="statusDropdownOpen = !statusDropdownOpen"
                                                    class="px-3 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-bold text-xs flex items-center justify-between gap-2 min-w-[205px] cursor-pointer">
                                                <span x-text="overrideStatusLabels[targetState] || targetState"></span>
                                                <svg class="w-3.5 h-3.5 text-primary shrink-0 transition-transform" :class="statusDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <div x-show="statusDropdownOpen" x-cloak x-transition class="absolute left-0 top-full mt-1 w-60 bg-surface border border-border-subtle rounded-2xl shadow-xl z-40 py-1 overflow-hidden">
                                                @foreach($overrideStatusLabels as $stKey => $stLbl)
                                                    <button type="button"
                                                            @click="targetState = '{{ $stKey }}'; statusDropdownOpen = false"
                                                            class="w-full px-3.5 py-2 text-left text-xs hover:bg-brand-light/30 flex items-center justify-between cursor-pointer"
                                                            :class="targetState === '{{ $stKey }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium'">
                                                        <span>{{ $stLbl }}</span>
                                                        <span x-show="targetState === '{{ $stKey }}'" class="text-primary font-extrabold">&#10003;</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <input type="text" name="reason" required minlength="10" maxlength="300" placeholder="Required reason (10–300 chars)..." class="p-2 rounded-xl border border-border-subtle bg-surface w-60 outline-none focus:border-primary">
                                        <template x-if="targetState === 'damaged'">
                                            <input type="file" name="evidence_photo" required accept="image/jpeg,image/png" class="p-1.5 rounded-xl border border-danger/30 bg-danger/10 text-[11px] cursor-pointer">
                                        </template>
                                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer">
                                            Apply &amp; Log
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($activeShipments->count() > 6)
                <div class="pt-2 flex justify-center">
                    <button type="button"
                            @click="visibleShipmentsLimit = visibleShipmentsLimit >= {{ $activeShipments->count() }} ? 6 : visibleShipmentsLimit + 6"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleShipmentsLimit >= {{ $activeShipments->count() }} ? 'See Less Shipments' : 'See More Shipments (' + ({{ $activeShipments->count() }} - visibleShipmentsLimit) + ' remaining)'"></span>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if($shortRemittances->isNotEmpty())
        <div class="bg-danger/10 rounded-3xl border-2 border-danger/30 p-6 space-y-3">
            <h2 class="text-base font-bold text-danger">Flagged Short COD Remittances Reconciliation</h2>
            <div class="overflow-x-auto bg-surface rounded-2xl border border-border-subtle">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle text-primary-dark uppercase text-[11px] font-bold">
                            <th class="py-3 px-4">Rider</th>
                            <th class="py-3 px-4">Shipment</th>
                            <th class="py-3 px-4">Expected COD</th>
                            <th class="py-3 px-4">Remitted COD</th>
                            <th class="py-3 px-4">Shortfall Discrepancy</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($shortRemittances as $srIdx => $sr)
                            <tr x-show="{{ $srIdx }} < visibleShortfallsLimit">
                                <td class="py-3 px-4 font-bold text-text-main">{{ $sr->rider?->full_name }}</td>
                                <td class="py-3 px-4 font-mono text-primary-dark">{{ $sr->shipment?->tracking_number }}</td>
                                <td class="py-3 px-4 font-semibold">&#8369;{{ number_format((float) $sr->expected_amount, 2) }}</td>
                                <td class="py-3 px-4 font-semibold">&#8369;{{ number_format((float) $sr->remitted_amount, 2) }}</td>
                                <td class="py-3 px-4 font-extrabold text-danger">
                                    -&#8369;{{ number_format(max(0, (float) $sr->expected_amount - (float) $sr->remitted_amount), 2) }} Shortfall
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($shortRemittances->count() > 5)
                <div class="pt-1 flex justify-center">
                    <button type="button"
                            @click="visibleShortfallsLimit = visibleShortfallsLimit >= {{ $shortRemittances->count() }} ? 5 : visibleShortfallsLimit + 5"
                            class="text-xs font-extrabold text-danger hover:underline cursor-pointer"
                            x-text="visibleShortfallsLimit >= {{ $shortRemittances->count() }} ? 'See Less Shortfalls' : 'See All {{ $shortRemittances->count() }} Shortfalls'"></button>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection