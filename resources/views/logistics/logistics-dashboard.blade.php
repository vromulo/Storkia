@extends('layouts.logistics', ['title' => 'Storkia - Logistics Dashboard'])

@section('content')
    <div class="max-w-7xl mx-auto">
        @if($latestApp && $latestApp->status === 'pending')
            <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                <h3 class="text-base font-bold text-yellow-900">Hub Application Under Review</h3>
                <p class="text-xs sm:text-sm text-yellow-800 mt-1">
                    Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is pending administrative approval. Dispatch and operational routing will unlock once verified.
                </p>
            </div>
        @elseif($latestApp && $latestApp->status === 'rejected')
            <div class="mb-8 p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-red-900">Application Rejected</h3>
                    <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <em>"{{ $latestApp->rejection_reason }}"</em></p>
                </div>
                <a href="{{ route('logistics.reapply') }}" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl shadow-md">
                    Update & Re-apply
                </a>
            </div>
        @endif

        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Logistics Operations</h1>
                <p class="text-primary mt-1 font-medium">Hub Manager: {{ auth()->user()->first_name ?? 'User' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'to_pickup']) : '#' }}"
               class="p-6 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all">
                <h3 class="text-xs font-bold uppercase text-text-muted">Parcels to Pick Up</h3>
                <p class="text-3xl font-serif font-bold text-primary-dark mt-2">{{ $pendingPickupsCount ?? 0 }}</p>
                <span class="text-xs text-primary font-semibold mt-1 inline-block">Assign First-Mile Courier &rarr;</span>
            </a>

            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'at_hub']) : '#' }}"
               class="p-6 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all">
                <h3 class="text-xs font-bold uppercase text-text-muted">Sorting Hub Queue</h3>
                <p class="text-3xl font-serif font-bold text-primary-dark mt-2">{{ $atHubCount ?? 0 }}</p>
                <span class="text-xs text-primary font-semibold mt-1 inline-block">Verify & Assign Rider &rarr;</span>
            </a>

            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'out_for_delivery']) : '#' }}"
               class="p-6 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all">
                <h3 class="text-xs font-bold uppercase text-text-muted">Out for Delivery</h3>
                <p class="text-3xl font-serif font-bold text-blue-700 mt-2">{{ $inTransitCount ?? 0 }}</p>
                <span class="text-xs text-text-muted mt-1 inline-block">Active Last-Mile Riders</span>
            </a>

            <a href="{{ $profile ? route('logistics.finance.index') : '#' }}"
               class="p-6 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all">
                <h3 class="text-xs font-bold uppercase text-text-muted">Pending COD Remittances</h3>
                <p class="text-3xl font-serif font-bold text-green-700 mt-2">{{ $pendingRemittancesCount ?? 0 }}</p>
                <span class="text-xs text-primary font-semibold mt-1 inline-block">Verify & Settle Sellers &rarr;</span>
            </a>
        </div>

        @if($profile && isset($recentShipments) && $recentShipments->isNotEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
                <div class="p-5 border-b border-border-subtle flex items-center justify-between">
                    <h2 class="text-base font-bold text-primary-dark">Recent Hub Shipments</h2>
                    <a href="{{ route('logistics.shipments.index') }}" class="text-xs font-bold text-primary hover:underline">View All Shipments</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Tracking #</th>
                                <th class="py-3 px-4">Seller Store</th>
                                <th class="py-3 px-4">Courier / Rider</th>
                                <th class="py-3 px-4">COD Amount</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($recentShipments as $sh)
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $sh->tracking_number }}</td>
                                    <td class="py-3.5 px-4 font-semibold text-text-main">{{ $sh->sellerProfile->business_name ?? 'N/A' }}</td>
                                    <td class="py-3.5 px-4 text-text-muted">
                                        C: {{ $sh->currentCourier?->full_name ?? '-' }} / R: {{ $sh->currentRider?->full_name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-primary">₱{{ number_format((float) $sh->cod_amount, 2) }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-brand-light/40 text-primary-dark">
                                            {{ $sh->status_label }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 text-right">
                                        <a href="{{ route('logistics.shipments.show', $sh->id) }}" class="text-primary font-bold hover:underline">Manage</a>
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