@extends('layouts.logistics', ['title' => 'Storkia - Logistics Dashboard'])

@section('content')
    <div class="max-w-7xl mx-auto space-y-8 select-none">
        @if($latestApp && $latestApp->status === 'pending')
            <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                <h3 class="text-base font-bold text-yellow-900">Hub Application Under Review</h3>
                <p class="text-xs sm:text-sm text-yellow-800 mt-1">
                    Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is pending administrative approval. Dispatch and operational routing will unlock once verified.
                </p>
            </div>
        @elseif($latestApp && $latestApp->status === 'rejected')
            <div class="p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-red-900">Application Rejected</h3>
                    <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <em>"{{ $latestApp->rejection_reason }}"</em></p>
                </div>
                <a href="{{ route('logistics.reapply') }}" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl shadow-md">
                    Update &amp; Re-apply
                </a>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Logistics Operations</h1>
                <p class="text-primary mt-1 font-medium text-sm">
                    Hub Manager: {{ auth()->user()->first_name ?? 'User' }} &bull;
                    {{ auth()->user()->activeLogisticsHub()?->hub_name ?? ($profile->business_name ?? '') }}
                </p>
            </div>

            @if($profile)
                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('logistics.finance.index') }}"
                       class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors">
                        E-Wallet &amp; Generate Financial Report
                    </a>
                    <a href="{{ route('logistics.shipments.index') }}"
                       class="px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-colors">
                        All Hub Shipments ({{ $totalShipmentsCount ?? 0 }})
                    </a>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
                <h3 class="text-[11px] font-bold uppercase text-white/80">Real-Money E-Wallet (90%)</h3>
                <p class="text-2xl font-serif font-extrabold mt-1.5">₱{{ number_format($walletAvailable ?? 0, 2) }}</p>
                <span class="text-[10px] text-white/85 mt-1 block">10% Comm + 80% Ops Released</span>
            </div>

            <div class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle">
                <h3 class="text-[11px] font-bold uppercase text-text-muted">Pending Escrow (Delivered)</h3>
                <p class="text-2xl font-serif font-bold text-amber-600 mt-1.5">₱{{ number_format($walletPendingEscrow ?? 0, 2) }}</p>
                <span class="text-[10px] text-text-muted mt-1 block">Awaiting Buyer Confirmation</span>
            </div>

            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'to_pickup']) : '#' }}"
               class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
                <h3 class="text-[11px] font-bold uppercase text-text-muted">Parcels to Pick Up</h3>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">{{ $pendingPickupsCount ?? 0 }}</p>
                <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Assign Courier &rarr;</span>
            </a>

            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'at_hub']) : '#' }}"
               class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
                <h3 class="text-[11px] font-bold uppercase text-text-muted">Sorting Hub Queue</h3>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">{{ $atHubCount ?? 0 }}</p>
                <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Verify &amp; Dispatch &rarr;</span>
            </a>

            <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'out_for_delivery']) : '#' }}"
               class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
                <h3 class="text-[11px] font-bold uppercase text-text-muted">Out for Delivery</h3>
                <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">{{ $inTransitCount ?? 0 }}</p>
                <span class="text-[11px] text-text-muted mt-1 inline-block">Failed/RTS: {{ $failedDeliveriesCount ?? 0 }}</span>
            </a>

            <a href="{{ $profile ? route('logistics.finance.index') : '#' }}"
               class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
                <h3 class="text-[11px] font-bold uppercase text-text-muted">Pending Remittances</h3>
                <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">{{ $pendingRemittancesCount ?? 0 }}</p>
                <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Verify COD &rarr;</span>
            </a>
        </div>

        @if($profile && isset($recentShipments) && $recentShipments->isNotEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
                <div class="p-5 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-primary-dark">Assigned Hub Shipments Queue (First-In, First-Out — {{ $totalShipmentsCount ?? $recentShipments->count() }} Total)</h2>
                        <p class="text-xs text-text-muted">Strictly scoped to shipments routed to your hub.</p>
                    </div>
                    <a href="{{ route('logistics.shipments.index') }}" class="text-xs font-bold text-primary hover:underline">Open Full Shipments Console &rarr;</a>
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
                                    <td class="py-3.5 px-5">
                                        <div class="font-mono font-bold text-primary-dark">{{ $sh->tracking_number }}</div>
                                        <div class="text-[11px] text-text-muted">{{ $sh->created_at?->format('M d, Y h:i A') }}</div>
                                    </td>
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

                @if(method_exists($recentShipments, 'hasPages') && $recentShipments->hasPages())
                    <div class="px-5 py-3 border-t border-border-subtle">
                        {{ $recentShipments->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection