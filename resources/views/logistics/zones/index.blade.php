@extends('layouts.logistics', ['title' => 'Storkia - Delivery Zones, Rates & Exceptions'])

@section('content')
<div x-data="{
    editRateId: '',
    zoneCode: '',
    zoneName: '',
    originRegion: 'NCR',
    destRegion: 'NCR',
    firstKg: '50.00',
    addKg: '15.00',
    isServiceable: '1',
    overrideStatus: 'delivered',
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
           class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
            Open Formal Printable PDF Statement &nearr;
        </a>
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Left: Add / Update Delivery Zone & Rate -->
        <div class="lg:col-span-5 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark" x-text="editRateId ? 'Update Delivery Zone Rate #' + editRateId : 'Add Delivery Zone & Shipping Rate'"></h2>
                <button type="button" x-show="editRateId" @click="resetZoneForm()" class="text-xs font-bold text-primary cursor-pointer">New Zone</button>
            </div>

            <form action="{{ route('logistics.zones.rates.store') }}" method="POST" class="space-y-3.5 text-xs">
                @csrf
                <input type="hidden" name="rate_id" :value="editRateId">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Zone Code <span class="text-danger">*</span></label>
                        <input type="text" name="zone_code" x-model="zoneCode" required maxlength="40" placeholder="e.g. NCR-LOCAL" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Serviceability <span class="text-danger">*</span></label>
                        <select name="is_serviceable" x-model="isServiceable" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                            <option value="1">Serviceable (Active)</option>
                            <option value="0">Unserviceable (Block Checkout)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Zone Display Name <span class="text-danger">*</span></label>
                    <input type="text" name="zone_name" x-model="zoneName" required maxlength="120" placeholder="e.g. Within Metro Manila (NCR)" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Origin Region <span class="text-danger">*</span></label>
                        <input type="text" name="origin_region" x-model="originRegion" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Destination Region <span class="text-danger">*</span></label>
                        <input type="text" name="destination_region" x-model="destRegion" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">First 1 KG Rate (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="first_kg_rate" x-model="firstKg" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold text-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Each Additional KG (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="additional_kg_rate" x-model="addKg" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Audit Log Reason (10–300 chars) <span class="text-danger">*</span></label>
                    <input type="text" name="reason" required minlength="10" maxlength="300" placeholder="Reason for adding or updating this zone rate..." class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">
                        <span x-text="editRateId ? 'Save Rate Update' : 'Add Delivery Zone'"></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Configured Delivery Zones Table -->
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
                        @foreach($rates as $rate)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-primary-dark">{{ $rate->zone_code }}</div>
                                    <div class="text-text-main font-semibold">{{ $rate->zone_name }}</div>
                                </td>
                                <td class="py-3 px-4 text-text-muted">{{ $rate->origin_region }} &rarr; {{ $rate->destination_region }}</td>
                                <td class="py-3 px-4 font-bold text-primary">₱{{ number_format((float) $rate->first_kg_rate, 2) }}</td>
                                <td class="py-3 px-4 font-semibold">+₱{{ number_format((float) $rate->additional_kg_rate, 2) }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rate->is_serviceable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-danger' }}">
                                        {{ $rate->is_serviceable ? 'Serviceable' : 'Unserviceable' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button type="button"
                                            @click='populateZone(@json($rate))'
                                            class="px-3 py-1 rounded-lg bg-surface-subtle border border-border-subtle hover:border-primary font-bold text-primary-dark cursor-pointer">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Manual Status Override & Lost / Damaged Parcel Exception Reporting -->
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
                        @foreach($activeShipments->take(10) as $sh)
                            <tr x-data="{ targetState: '{{ $sh->status }}' }">
                                <td class="py-3 px-4 align-top">
                                    <a href="{{ route('logistics.shipments.show', $sh->id) }}" class="font-mono font-bold text-primary-dark hover:underline">{{ $sh->tracking_number }}</a>
                                    <div class="text-text-muted">Order #{{ $sh->sellerOrder->seller_order_number ?? 'N/A' }}</div>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/40 text-primary-dark uppercase">{{ $sh->status }}</span>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <form action="{{ route('logistics.shipments.override-status', $sh->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <select name="new_status" x-model="targetState" class="p-2 rounded-xl border border-border-subtle bg-surface font-bold">
                                            <option value="received_at_hub">At Hub</option>
                                            <option value="out_for_delivery">Out for Delivery</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="returned_to_seller">Returned to Sender (RTS)</option>
                                            <option value="lost">Mark Parcel LOST</option>
                                            <option value="damaged">Mark Parcel DAMAGED (Photo Req.)</option>
                                        </select>
                                        <input type="text" name="reason" required minlength="10" maxlength="300" placeholder="Required reason (10–300 chars)..." class="p-2 rounded-xl border border-border-subtle bg-surface w-60">
                                        <template x-if="targetState === 'damaged'">
                                            <input type="file" name="evidence_photo" required accept="image/jpeg,image/png" class="p-1.5 rounded-xl border border-red-300 bg-red-50 text-[11px]">
                                        </template>
                                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">
                                            Apply &amp; Log
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($shortRemittances->isNotEmpty())
        <div class="bg-red-50 rounded-3xl border-2 border-red-200 p-6 space-y-3">
            <h2 class="text-base font-bold text-red-900">Flagged Short COD Remittances Reconciliation</h2>
            <div class="overflow-x-auto bg-white rounded-2xl border border-red-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-red-200 bg-red-100/60 text-red-950 uppercase text-[11px] font-bold">
                            <th class="py-3 px-4">Rider</th>
                            <th class="py-3 px-4">Shipment</th>
                            <th class="py-3 px-4">Expected COD</th>
                            <th class="py-3 px-4">Remitted COD</th>
                            <th class="py-3 px-4">Shortfall Discrepancy</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @foreach($shortRemittances as $sr)
                            <tr>
                                <td class="py-3 px-4 font-bold">{{ $sr->rider?->full_name }}</td>
                                <td class="py-3 px-4 font-mono">{{ $sr->shipment?->tracking_number }}</td>
                                <td class="py-3 px-4 font-semibold">₱{{ number_format((float) $sr->expected_amount, 2) }}</td>
                                <td class="py-3 px-4 font-semibold">₱{{ number_format((float) $sr->remitted_amount, 2) }}</td>
                                <td class="py-3 px-4 font-extrabold text-danger">
                                    -₱{{ number_format(max(0, (float) $sr->expected_amount - (float) $sr->remitted_amount), 2) }} Shortfall
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection