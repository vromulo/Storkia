@extends('layouts.logistics', ['title' => 'Storkia - Logistics Hub & Rider Ratings'])

@section('content')
@php
    $tab = $tab ?? request()->query('tab', 'hub');
    $selectedRiderId = $selectedRiderId ?? request()->query('rider_id');
    $ratings = $ratings ?? collect();
    $ridersWithRatings = $ridersWithRatings ?? collect();
@endphp

<div x-data="{ activeTab: '{{ $selectedRiderId ? 'riders' : $tab }}' }" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Sorting Hub &amp; Fleet Rider Ratings</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Monitor verified buyer evaluations of your Sorting Hub's turnaround speed alongside individual Last-Mile Delivery Rider ratings.
            </p>
        </div>

        <a href="{{ route('logistics.finance.index') }}"
           class="px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-xs font-bold text-text-main transition-colors">
            &larr; Back to Finance &amp; E-Wallet
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase text-white/80">Logistics Sorting Hub Rating</span>
            <p class="text-3xl font-serif font-extrabold mt-1">{{ number_format($hubAvgRating ?? 0, 2) }} / 5.0 ★</p>
            <span class="text-[10px] text-white/85 mt-1 block">First-mile coordination &amp; sorting turnaround</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Fleet Riders Average Rating</span>
            <p class="text-3xl font-serif font-bold text-amber-500 mt-1">{{ number_format($riderAvgRatingOverall ?? 0, 2) }} / 5.0 ★</p>
            <span class="text-[10px] text-text-muted mt-1 block">Across {{ $ridersWithRatings->count() }} registered rider(s)</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Total Buyer Evaluations</span>
            <p class="text-3xl font-serif font-bold text-primary-dark mt-1">{{ number_format($totalRatingsCount ?? 0) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Completed &amp; rated shipments</span>
        </div>
    </div>

    <!-- Section Switcher -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-subtle pb-3">
        <div class="flex items-center gap-2">
            <button type="button"
                    @click="activeTab = 'hub'"
                    :class="activeTab === 'hub' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                1. Shipment &amp; Sorting Hub Ratings Log ({{ $totalRatingsCount ?? 0 }})
            </button>
            <button type="button"
                    @click="activeTab = 'riders'"
                    :class="activeTab === 'riders' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                2. Fleet Riders Rating Leaderboard ({{ $ridersWithRatings->count() }})
            </button>
        </div>

        @if($selectedRiderId)
            <a href="{{ route('logistics.ratings.index') }}" class="text-xs font-bold text-primary hover:underline">
                Clear Rider Filter &times;
            </a>
        @endif
    </div>

    <!-- TAB 1: Hub & Shipment Ratings Log -->
    <div x-show="activeTab === 'hub'" class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($ratings->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No buyer shipment ratings recorded yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Order &amp; Buyer</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Assigned Rider</th>
                            <th class="py-3.5 px-4 text-center">Hub Rating</th>
                            <th class="py-3.5 px-4 text-center">Rider Rating</th>
                            <th class="py-3.5 px-4 text-center">Parcel Condition</th>
                            <th class="py-3.5 px-4">Buyer Feedback</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($ratings as $rt)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <div class="font-mono font-bold text-primary-dark">#{{ $rt->sellerOrder->seller_order_number ?? $rt->seller_order_id }}</div>
                                    <div class="text-text-muted">{{ $rt->buyer?->full_name ?? 'Verified Buyer' }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $rt->sellerProfile->business_name ?? 'Seller' }}</td>
                                <td class="py-3.5 px-4">
                                    @if($rt->rider)
                                        <a href="{{ route('logistics.ratings.index', ['rider_id' => $rt->rider_id]) }}" class="font-bold text-primary hover:underline">
                                            {{ $rt->rider->full_name }}
                                        </a>
                                    @else
                                        <span class="text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center font-extrabold text-primary-dark">{{ $rt->logistics_hub_rating }} / 5 ★</td>
                                <td class="py-3.5 px-4 text-center font-extrabold text-amber-500">{{ $rt->rider_delivery_rating }} / 5 ★</td>
                                <td class="py-3.5 px-4 text-center font-bold text-green-700">{{ $rt->parcel_condition_rating }} / 5 ★</td>
                                <td class="py-3.5 px-4 text-text-main max-w-xs">{{ $rt->review_comment ?: '—' }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rt->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($ratings, 'hasPages') && $ratings->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $ratings->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- TAB 2: Fleet Riders Rating Leaderboard -->
    <div x-show="activeTab === 'riders'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($ridersWithRatings->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No Riders registered under your Logistics Hub yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Rider Name &amp; Contact</th>
                            <th class="py-3.5 px-4">Vehicle &amp; Plate</th>
                            <th class="py-3.5 px-4 text-center">Total Rated Deliveries</th>
                            <th class="py-3.5 px-4 text-center">Average Delivery Rating</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($ridersWithRatings as $r)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <div class="font-bold text-sm text-primary-dark">{{ $r->full_name }}</div>
                                    <div class="text-text-muted font-mono text-[11px]">{{ $r->email }} &bull; {{ $r->contact_no }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-semibold">{{ $r->vehicle_type }} ({{ $r->plate_number }})</td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold">{{ $r->ratings_count }}</td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-extrabold">
                                        {{ number_format((float) $r->avg_delivery_rating, 2) }} / 5.0 ★
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    <a href="{{ route('logistics.ratings.index', ['rider_id' => $r->id, 'tab' => 'hub']) }}"
                                       class="px-3.5 py-1.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors">
                                        Filter Rider Reviews &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection