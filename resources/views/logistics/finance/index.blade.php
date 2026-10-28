@extends('layouts.logistics', ['title' => 'Storkia - COD Remittances & Settlements'])

@section('content')
<div class="max-w-7xl mx-auto space-y-8 select-none">

    <div>
        <h1 class="text-3xl font-bold text-primary-dark">COD Remittances, Commissions & Seller Settlements</h1>
        <p class="text-sm text-text-muted mt-1">
            Verify Rider COD remittances, track your Shipping Fee earnings (10% Logistics Commission + 80% Operational Revenue; 10% Rider Commission), and release Seller settlements (90% of Product Subtotal).
        </p>
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Total COD Collected</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">₱{{ number_format($metrics['total_cod_collected'], 2) }}</p>
            <span class="text-[11px] text-yellow-700 font-semibold">Awaiting Verification: ₱{{ number_format($metrics['pending_remittance_verify'], 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Logistics Total Shipping Share (90%)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">₱{{ number_format($metrics['logistics_total_90_shipping'], 2) }}</p>
            <span class="text-[11px] text-text-muted">10% Comm (₱{{ number_format($metrics['logistics_10_commission'], 2) }}) + 80% Ops (₱{{ number_format($metrics['logistics_80_operational'], 2) }})</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Rider Commission (10% Shipping)</span>
            <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">₱{{ number_format($metrics['rider_10_commission'], 2) }}</p>
            <span class="text-[11px] text-text-muted">Earned by Last-Mile Riders</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Pending Seller Settlements (90%)</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1.5">₱{{ number_format($metrics['pending_seller_settlement'], 2) }}</p>
            <span class="text-[11px] text-green-700 font-semibold">Released: ₱{{ number_format($metrics['released_seller_settlement'], 2) }}</span>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-primary-dark">1. Rider COD Remittances</h2>
                <p class="text-xs text-text-muted">Verify cash remitted by Riders to unlock commission calculations and Seller settlements.</p>
            </div>
        </div>

        @if($remittances->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No Rider COD remittances have been submitted yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Remittance Ref</th>
                            <th class="py-3.5 px-4">Rider</th>
                            <th class="py-3.5 px-4">Shipments</th>
                            <th class="py-3.5 px-4">Total COD Collected</th>
                            <th class="py-3.5 px-4">Rider 10% Comm</th>
                            <th class="py-3.5 px-4">Net Cash Remitted</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($remittances as $rem)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $rem->remittance_reference }}</td>
                                <td class="py-3.5 px-4 font-bold text-text-main">{{ $rem->rider?->full_name }}</td>
                                <td class="py-3.5 px-4">{{ $rem->items->count() }} parcel(s)</td>
                                <td class="py-3.5 px-4 font-semibold">₱{{ number_format((float) $rem->total_collected_amount, 2) }}</td>
                                <td class="py-3.5 px-4 text-blue-700 font-semibold">₱{{ number_format((float) $rem->total_rider_commission, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $rem->net_remitted_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rem->status === 'verified' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $rem->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($rem->status === 'submitted')
                                        <form action="{{ route('logistics.finance.verify-remittance', $rem->id) }}" method="POST" class="m-0 inline">
                                            @csrf
                                            <button type="submit" class="px-4 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold cursor-pointer">
                                                Verify Remittance
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-text-muted">Verified {{ $rem->verified_at?->format('M d, h:i A') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-border-subtle">
                {{ $remittances->links() }}
            </div>
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">2. Pending Seller Settlements (Ready to Release)</h2>
            <p class="text-xs text-text-muted">Verified COD orders grouped by Seller. Releasing payout transfers 90% of Product Subtotal to Seller and records 10% Admin commission as settled.</p>
        </div>

        @if($pendingSellerPayouts->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No verified unsettled Seller payouts pending at this time.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Store</th>
                            <th class="py-3.5 px-4">Verified Orders</th>
                            <th class="py-3.5 px-4">Gross Product Subtotal</th>
                            <th class="py-3.5 px-4">Admin Commission (10%)</th>
                            <th class="py-3.5 px-4">Net Seller Payout (90%)</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($pendingSellerPayouts as $payout)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-bold text-text-main">{{ $payout['seller_profile']->business_name ?? 'Seller' }}</td>
                                <td class="py-3.5 px-4">{{ $payout['orders_count'] }} order(s)</td>
                                <td class="py-3.5 px-4">₱{{ number_format($payout['gross_product_subtotal'], 2) }}</td>
                                <td class="py-3.5 px-4 text-danger">-₱{{ number_format($payout['admin_commission_total'], 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700 text-sm">₱{{ number_format($payout['net_settlement_amount'], 2) }}</td>
                                <td class="py-3.5 px-5 text-right">
                                    <form action="{{ route('logistics.finance.settle-seller') }}" method="POST" class="m-0 inline">
                                        @csrf
                                        <input type="hidden" name="seller_profile_id" value="{{ $payout['seller_profile']->id }}">
                                        <button type="submit" class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs cursor-pointer">
                                            Process Seller Settlement
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

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">3. Released Seller Settlements History</h2>
        </div>

        @if($settlements->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No seller settlements processed yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Settlement Ref</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Orders</th>
                            <th class="py-3.5 px-4">Gross Subtotal</th>
                            <th class="py-3.5 px-4">Admin Fee (10%)</th>
                            <th class="py-3.5 px-4">Net Released (90%)</th>
                            <th class="py-3.5 px-5 text-right">Settled Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($settlements as $stl)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $stl->settlement_reference }}</td>
                                <td class="py-3.5 px-4 font-bold text-text-main">{{ $stl->sellerProfile->business_name ?? 'N/A' }}</td>
                                <td class="py-3.5 px-4">{{ $stl->items->count() }} order(s)</td>
                                <td class="py-3.5 px-4">₱{{ number_format((float) $stl->gross_product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger">-₱{{ number_format((float) $stl->admin_commission_total, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $stl->net_settlement_amount, 2) }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $stl->settled_at?->format('M d, Y h:i A') }}</td>
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

</div>
@endsection