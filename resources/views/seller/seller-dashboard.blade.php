@extends('layouts.seller', ['title' => 'Storkia - Seller Dashboard'])

@section('content')
    <div class="max-w-7xl mx-auto space-y-8 select-none">
        @if($latestApp && $latestApp->status === 'pending')
            <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-yellow-100 text-yellow-700 rounded-2xl shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-yellow-900">Application Under Review</h3>
                        <p class="text-xs sm:text-sm text-yellow-800 mt-1 leading-relaxed">
                            Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is currently being reviewed by the Storkia administration. Seller catalog privileges will be unlocked once approved.
                        </p>
                    </div>
                </div>
            </div>
        @elseif($latestApp && $latestApp->status === 'rejected')
            <div class="p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-red-100 text-danger rounded-2xl shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-red-900">Application Not Approved</h3>
                            <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <strong class="italic">"{{ $latestApp->rejection_reason }}"</strong></p>
                            <p class="text-xs text-text-muted mt-1">You may correct the issues and submit a revised application.</p>
                        </div>
                    </div>
                    <a href="{{ route('seller.reapply') }}" class="px-5 py-3 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-md transition-colors shrink-0 text-center">
                        Update &amp; Re-apply
                    </a>
                </div>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Seller Portal</h1>
                <p class="text-sm text-text-muted">Welcome back, {{ auth()->user()->first_name ?? 'Seller' }} &bull; {{ $profile->business_name ?? '' }}</p>
            </div>

            @if($profile)
                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('seller.settlements.index') }}"
                       class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors">
                        E-Wallet &amp; Generate Financial Report
                    </a>
                    <a href="{{ route('seller.vouchers.index') }}"
                       class="px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-colors">
                        Manage Store Vouchers
                    </a>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
                <h3 class="text-white/80 text-[11px] uppercase font-bold mb-1">Real-Money E-Wallet (90%)</h3>
                <p class="text-2xl font-serif font-extrabold">₱{{ number_format($walletAvailable ?? 0, 2) }}</p>
                <span class="text-[10px] text-white/85 mt-1 block">Unlocked on Buyer Order Received</span>
            </div>

            <div class="p-5 bg-white rounded-2xl shadow-sm border border-border-subtle">
                <h3 class="text-text-muted text-[11px] uppercase font-bold mb-1">Pending Escrow (Delivered)</h3>
                <p class="text-2xl text-amber-600 font-serif font-bold">₱{{ number_format($walletPendingEscrow ?? 0, 2) }}</p>
                <span class="text-[10px] text-text-muted mt-1 block">Awaiting 72h Buyer Confirmation</span>
            </div>

            <div class="p-5 bg-white rounded-2xl shadow-sm border border-border-subtle">
                <h3 class="text-text-muted text-[11px] uppercase font-bold mb-1">Gross Delivered Sales</h3>
                <p class="text-2xl text-primary font-serif font-bold">₱{{ number_format($totalSales ?? 0, 2) }}</p>
                <span class="text-[10px] text-text-muted mt-1 block">Net 90%: ₱{{ number_format($netEarnings ?? 0, 2) }}</span>
            </div>

            <div class="p-5 bg-white rounded-2xl shadow-sm border border-border-subtle">
                <h3 class="text-text-muted text-[11px] uppercase font-bold mb-1">Active Products</h3>
                <p class="text-2xl text-primary-dark font-serif font-bold">{{ $activeProductsCount ?? 0 }}</p>
                <span class="text-[10px] text-text-muted mt-1 block">Approved in Storefront</span>
            </div>

            <div class="p-5 bg-white rounded-2xl shadow-sm border border-border-subtle">
                <h3 class="text-text-muted text-[11px] uppercase font-bold mb-1">Orders Needing Action</h3>
                <p class="text-2xl text-primary font-serif font-bold">{{ $pendingOrdersCount ?? 0 }}</p>
                <span class="text-[10px] text-text-muted mt-1 block">Total All Orders: {{ $totalOrdersCount ?? 0 }}</span>
            </div>
        </div>

        @if($profile && isset($recentOrders) && $recentOrders->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-border-subtle overflow-hidden">
                <div class="p-5 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-primary-dark">Store Orders Queue (First-In, First-Out — {{ $totalOrdersCount ?? $recentOrders->count() }} Total)</h2>
                        <p class="text-xs text-text-muted">Synchronized with Order Management so all {{ $totalOrdersCount ?? $recentOrders->count() }} order(s) are accounted for.</p>
                    </div>
                    <a href="{{ route('seller.orders.index') }}" class="text-xs font-bold text-primary hover:underline">Open Full Order Management &rarr;</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Order #</th>
                                <th class="py-3 px-4">Buyer</th>
                                <th class="py-3 px-4">Payment &amp; Total</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($recentOrders as $ro)
                                @php
                                    $payMethod = $ro->effective_payment_method;
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5">
                                        <div class="font-mono font-bold text-primary-dark">{{ $ro->seller_order_number }}</div>
                                        <div class="text-[11px] text-text-muted">{{ $ro->created_at?->format('M d, Y h:i A') }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium text-text-main">{{ $ro->buyer?->full_name }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800' : 'bg-surface-subtle text-text-main' }}">
                                            {{ $payMethod }}
                                        </span>
                                        <span class="font-bold text-primary ml-1">₱{{ number_format((float) $ro->total_amount, 2) }}</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $ro->status === 'pending_buyer_response' ? 'bg-amber-100 text-amber-800' : 'bg-brand-light/40 text-primary-dark' }}">
                                            {{ $ro->status_label }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 text-right">
                                        <a href="{{ route('seller.orders.show', $ro->id) }}" class="text-primary font-bold hover:underline">Manage</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(method_exists($recentOrders, 'hasPages') && $recentOrders->hasPages())
                    <div class="px-5 py-3 border-t border-border-subtle">
                        {{ $recentOrders->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection