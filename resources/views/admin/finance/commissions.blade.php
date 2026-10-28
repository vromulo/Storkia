@extends('layouts.admin', ['title' => 'Storkia Admin - Platform Commissions'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Platform Commissions & Financial Ledger</h1>
            <p class="text-sm text-text-muted mt-1">
                Split-Base Policy: <strong>10% Admin</strong> &amp; <strong>90% Seller</strong> on Product Subtotal |
                <strong>10% Logistics</strong>, <strong>10% Rider</strong> &amp; <strong>80% Logistics Ops</strong> on Shipping Fee.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex items-center gap-2">
            @if($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Order #, seller, logistics hub..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold cursor-pointer">
                Search
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Admin Commission (10% Products)</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1.5">₱{{ number_format($metrics['admin_10_commission_total'], 2) }}</p>
            <span class="text-[11px] text-text-muted">From ₱{{ number_format($metrics['gross_product_volume'], 2) }} Product Volume</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Seller Net Earnings (90% Products)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">₱{{ number_format($metrics['seller_90_net_total'], 2) }}</p>
            <span class="text-[11px] text-text-muted">Settled: ₱{{ number_format($metrics['settled_seller_payouts'], 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Logistics Earnings (10% + 80% Ship)</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">
                ₱{{ number_format($metrics['logistics_10_commission'] + $metrics['logistics_80_operational'], 2) }}
            </p>
            <span class="text-[11px] text-text-muted">
                10%: ₱{{ number_format($metrics['logistics_10_commission'], 2) }} | 80% Ops: ₱{{ number_format($metrics['logistics_80_operational'], 2) }}
            </span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Rider Commission (10% Shipping)</span>
            <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">₱{{ number_format($metrics['rider_10_commission'], 2) }}</p>
            <span class="text-[11px] text-text-muted">From ₱{{ number_format($metrics['gross_shipping_volume'], 2) }} Shipping Volume</span>
        </div>
    </div>

    <div class="flex items-center gap-2">
        @foreach(['all' => 'All Records', 'recorded' => 'Recorded (Unsettled)', 'settled' => 'Settled'] as $key => $label)
            <a href="{{ route('admin.commissions.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
               class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $status === $key ? 'bg-primary-dark text-surface border-primary-dark' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($commissions->isEmpty())
            <div class="p-14 text-center text-xs text-text-muted">
                No commission records found. Commissions are recorded when Logistics verifies Rider COD remittances.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase font-bold text-text-muted">
                            <th class="py-3.5 px-5">Seller Order</th>
                            <th class="py-3.5 px-4">Seller &amp; Hub</th>
                            <th class="py-3.5 px-4">Product Split (10% / 90%)</th>
                            <th class="py-3.5 px-4">Shipping Split (10% / 10% / 80%)</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissions as $rec)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-primary-dark">{{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}</div>
                                    <div class="text-text-muted">Total COD: ₱{{ number_format((float) $rec->total_cod_amount, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">Seller: {{ $rec->sellerProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Hub: {{ $rec->logisticsProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Rider: {{ $rec->rider?->full_name ?? 'N/A' }}</div>
                                </td>
                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div>Subtotal: <strong>₱{{ number_format((float) $rec->product_subtotal, 2) }}</strong></div>
                                    <div class="text-primary font-bold">Admin (10%): ₱{{ number_format((float) $rec->admin_commission_amount, 2) }}</div>
                                    <div class="text-green-700 font-bold">Seller (90%): ₱{{ number_format((float) $rec->seller_net_earning, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div>Shipping: <strong>₱{{ number_format((float) $rec->shipping_fee, 2) }}</strong></div>
                                    <div>Logistics (10%): ₱{{ number_format((float) $rec->logistics_commission_amount, 2) }}</div>
                                    <div>Rider (10%): ₱{{ number_format((float) $rec->rider_commission_amount, 2) }}</div>
                                    <div class="text-text-muted">Logistics Ops (80%): ₱{{ number_format((float) $rec->logistics_operational_revenue, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $rec->status }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 align-top text-right text-text-muted">
                                    {{ $rec->calculated_at?->format('M d, Y h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $commissions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection