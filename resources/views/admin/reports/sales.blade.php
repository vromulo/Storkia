@extends('layouts.admin', ['title' => 'Storkia Admin - Sales & Operational Performance Report'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('admin.commissions.index') }}" class="px-3 py-1.5 rounded-xl bg-surface border border-border-subtle text-xs font-bold text-text-muted hover:text-primary">
                    &larr; Commissions Ledger
                </a>
                <span class="px-3 py-1 rounded-full bg-brand-light/50 text-primary-dark text-xs font-bold">
                    Range: {{ $financialSummary['from_date'] }} to {{ $financialSummary['to_date'] }}
                </span>
            </div>
            <h1 class="text-3xl font-bold text-primary-dark mt-2">Platform Sales, Profit &amp; Multi-Role Performance Report</h1>
            <p class="text-sm text-text-muted mt-1">
                Filter financial revenue, voucher subsidies, and operational performance across Sellers, Logistics Hubs, Couriers, and Riders.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.reports.sales') }}" class="flex flex-wrap items-end gap-2.5 bg-surface p-3.5 rounded-2xl border border-border-subtle shadow-2xs text-xs">
            <input type="hidden" name="role_tab" value="{{ $roleTab }}">
            <div>
                <label class="block font-bold text-text-muted text-[10px] uppercase mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-2 rounded-xl border border-border-subtle bg-surface font-semibold">
            </div>
            <div>
                <label class="block font-bold text-text-muted text-[10px] uppercase mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-2 rounded-xl border border-border-subtle bg-surface font-semibold">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer">
                Generate Report
            </button>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                Print Report
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase text-white/80">Net Admin Platform Profit</span>
            <p class="text-2xl font-serif font-extrabold mt-1.5">₱{{ number_format($financialSummary['admin_net_platform_profit'], 2) }}</p>
            <span class="text-[10px] text-white/85 block mt-1">
                Gross 10%: ₱{{ number_format($financialSummary['admin_10_gross_commission'], 2) }} | Promo Sub: -₱{{ number_format($financialSummary['admin_voucher_subsidies'], 2) }}
            </span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Period Merchandise GMV</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-1.5">₱{{ number_format($financialSummary['gross_merchandise_volume'], 2) }}</p>
            <span class="text-[10px] text-green-700 font-bold">Seller 90% Share: ₱{{ number_format($financialSummary['seller_90_total_payout'], 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Period Shipping Volume</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">₱{{ number_format($financialSummary['gross_shipping_volume'], 2) }}</p>
            <span class="text-[10px] text-text-muted">
                Logistics 90%: ₱{{ number_format($financialSummary['logistics_90_total_share'], 2) }} | Rider 10%: ₱{{ number_format($financialSummary['rider_10_total_share'], 2) }}
            </span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Order Fulfillment Rate</span>
            <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">
                {{ $financialSummary['completed_orders_in_period'] }} / {{ $financialSummary['total_orders_in_period'] }}
            </p>
            <span class="text-[10px] text-danger font-semibold">Cancelled / RTS: {{ $financialSummary['cancelled_rts_in_period'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach([
            'sellers'   => '1. Seller Stores Performance (' . $sellerPerformance->count() . ')',
            'logistics' => '2. Logistics Hubs Performance (' . $logisticsPerformance->count() . ')',
            'couriers'  => '3. First-Mile Couriers (' . $courierPerformance->count() . ')',
            'riders'    => '4. Last-Mile Riders (' . $riderPerformance->count() . ')',
        ] as $key => $label)
            <a href="{{ route('admin.reports.sales', ['from_date' => $fromDate, 'to_date' => $toDate, 'role_tab' => $key]) }}"
               class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all {{ $roleTab === $key ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($roleTab === 'sellers')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Seller Store Sales &amp; Commission Breakdown (FIFO)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Store</th>
                            <th class="py-3.5 px-4">Location</th>
                            <th class="py-3.5 px-4">Orders (Completed / RTS)</th>
                            <th class="py-3.5 px-4">Commissionable Sales</th>
                            <th class="py-3.5 px-4">Admin 10% Cut</th>
                            <th class="py-3.5 px-5 text-right">Seller 90% Net Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($sellerPerformance as $row)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-bold text-text-main">{{ $row['seller_profile']->business_name }}</td>
                                <td class="py-3.5 px-4 text-text-muted">{{ $row['seller_profile']->municipality }}, {{ $row['seller_profile']->province }}</td>
                                <td class="py-3.5 px-4">
                                    <strong>{{ $row['orders_count'] }}</strong> total
                                    (<span class="text-green-700 font-bold">{{ $row['completed_count'] }} done</span> /
                                    <span class="text-danger font-bold">{{ $row['rts_cancel_count'] }} RTS</span>)
                                </td>
                                <td class="py-3.5 px-4 font-semibold">₱{{ number_format($row['gross_sales'], 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-primary">₱{{ number_format($row['admin_10_cut'], 2) }}</td>
                                <td class="py-3.5 px-5 text-right font-bold text-green-700">₱{{ number_format($row['seller_90_net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($roleTab === 'logistics')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Logistics Hub Throughput &amp; Shipping Revenue Breakdown (FIFO)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Logistics Company</th>
                            <th class="py-3.5 px-4">Main Hub Location</th>
                            <th class="py-3.5 px-4">Shipments (Delivered / Failed)</th>
                            <th class="py-3.5 px-4">10% Hub Comm</th>
                            <th class="py-3.5 px-4">80% Hub Ops</th>
                            <th class="py-3.5 px-5 text-right">90% Total Hub Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($logisticsPerformance as $row)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-bold text-text-main">{{ $row['logistics_profile']->business_name }}</td>
                                <td class="py-3.5 px-4 text-text-muted">{{ $row['logistics_profile']->municipality }}, {{ $row['logistics_profile']->province }}</td>
                                <td class="py-3.5 px-4">
                                    <strong>{{ $row['shipments_total'] }}</strong> total
                                    (<span class="text-green-700 font-bold">{{ $row['delivered_count'] }} delivered</span> /
                                    <span class="text-danger font-bold">{{ $row['failed_rts_count'] }} failed</span>)
                                </td>
                                <td class="py-3.5 px-4 font-bold text-primary">₱{{ number_format($row['hub_10_commission'], 2) }}</td>
                                <td class="py-3.5 px-4">₱{{ number_format($row['hub_80_ops'], 2) }}</td>
                                <td class="py-3.5 px-5 text-right font-bold text-green-700">₱{{ number_format($row['hub_90_total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($roleTab === 'couriers')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">First-Mile Courier Pickup &amp; Haulage Performance (FIFO)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Courier Name</th>
                            <th class="py-3.5 px-4">Logistics Hub</th>
                            <th class="py-3.5 px-4">Vehicle</th>
                            <th class="py-3.5 px-4">Assigned Pickups</th>
                            <th class="py-3.5 px-4">Completed Hub Drop-offs</th>
                            <th class="py-3.5 px-5 text-right">Total Weight Hauled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($courierPerformance as $row)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-bold text-text-main">{{ $row['courier']->full_name }}</td>
                                <td class="py-3.5 px-4 text-text-muted">{{ $row['courier']->home_hub }}</td>
                                <td class="py-3.5 px-4">{{ $row['courier']->vehicle_type }} ({{ $row['courier']->plate_number }})</td>
                                <td class="py-3.5 px-4 font-bold">{{ $row['assigned_pickups'] }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">{{ $row['completed_dropoffs'] }}</td>
                                <td class="py-3.5 px-5 text-right font-mono font-bold text-primary-dark">{{ number_format($row['total_weight_hauled'], 2) }} kg</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Last-Mile Rider Delivery &amp; 10% Shipping Commission Performance (FIFO)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Rider Name</th>
                            <th class="py-3.5 px-4">Logistics Company</th>
                            <th class="py-3.5 px-4">Vehicle</th>
                            <th class="py-3.5 px-4">Assigned Deliveries</th>
                            <th class="py-3.5 px-4">Completed / Failed</th>
                            <th class="py-3.5 px-5 text-right">10% Rider Shipping Comm</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($riderPerformance as $row)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-bold text-text-main">{{ $row['rider']->full_name }}</td>
                                <td class="py-3.5 px-4 text-text-muted">{{ $row['rider']->logisticsProfile->business_name ?? 'N/A' }}</td>
                                <td class="py-3.5 px-4">{{ $row['rider']->vehicle_type }} ({{ $row['rider']->plate_number }})</td>
                                <td class="py-3.5 px-4 font-bold">{{ $row['assigned_deliveries'] }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="text-green-700 font-bold">{{ $row['completed_deliveries'] }} delivered</span> /
                                    <span class="text-danger font-bold">{{ $row['failed_attempts'] }} failed</span>
                                </td>
                                <td class="py-3.5 px-5 text-right font-bold text-green-700">₱{{ number_format($row['rider_10_commission'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection