@extends('layouts.seller', ['title' => 'Storkia - Seller Settlements & Earnings'])

@section('content')
<div class="max-w-7xl mx-auto space-y-8 select-none">

    <div>
        <h1 class="text-3xl font-bold text-primary-dark">Sales, Commissions & Settlements</h1>
        <p class="text-sm text-text-muted mt-1">
            Split-Base Policy: You earn <strong>90%</strong> of the Product Subtotal on every verified COD delivery (10% Admin Platform Commission).
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold text-text-muted uppercase">Gross Product Subtotal</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-2">₱{{ number_format($metrics['gross_product_sales'], 2) }}</p>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold text-text-muted uppercase">Admin Commission (10%)</span>
            <p class="text-2xl font-serif font-bold text-danger mt-2">-₱{{ number_format($metrics['admin_commission_total'], 2) }}</p>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold text-text-muted uppercase">Pending Settlement (90%)</span>
            <p class="text-2xl font-serif font-bold text-yellow-700 mt-2">₱{{ number_format($metrics['pending_settlement'], 2) }}</p>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold text-text-muted uppercase">Settled / Released Earnings</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-2">₱{{ number_format($metrics['settled_total'], 2) }}</p>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Released Seller Payouts / Settlements</h2>
        </div>

        @if($settlements->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No seller settlements have been released yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Settlement Ref</th>
                            <th class="py-3.5 px-4">Logistics Hub</th>
                            <th class="py-3.5 px-4">Orders Included</th>
                            <th class="py-3.5 px-4">Gross Product Subtotal</th>
                            <th class="py-3.5 px-4">Admin Fee (10%)</th>
                            <th class="py-3.5 px-4">Net Payout (90%)</th>
                            <th class="py-3.5 px-5 text-right">Settled Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($settlements as $settlement)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $settlement->settlement_reference }}</td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $settlement->logisticsProfile->business_name ?? 'N/A' }}</td>
                                <td class="py-3.5 px-4">{{ $settlement->items->count() }} order(s)</td>
                                <td class="py-3.5 px-4">₱{{ number_format((float) $settlement->gross_product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger">-₱{{ number_format((float) $settlement->admin_commission_total, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $settlement->net_settlement_amount, 2) }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $settlement->settled_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-border-subtle">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Per-Order Commission & Earnings Ledger</h2>
        </div>

        @if($commissionRecords->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                Commission records are generated automatically when Logistics verifies Rider COD remittances.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Order #</th>
                            <th class="py-3.5 px-4">Product Subtotal</th>
                            <th class="py-3.5 px-4">Admin 10%</th>
                            <th class="py-3.5 px-4">Your Net (90%)</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    <a href="{{ route('seller.orders.show', $rec->seller_order_id) }}" class="hover:underline">
                                        {{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">₱{{ number_format((float) $rec->product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger">-₱{{ number_format((float) $rec->admin_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $rec->seller_net_earning, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $rec->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->calculated_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-border-subtle">
                {{ $commissionRecords->links() }}
            </div>
        @endif
    </div>

</div>
@endsection