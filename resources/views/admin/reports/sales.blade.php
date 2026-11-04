@extends('layouts.admin', ['title' => 'Storkia Admin - Sales & Role Financial Reports'])

@section('content')
@php
    $roleTab = $roleTab ?? request()->query('role_tab', 'sellers');
    $preset = $preset ?? request()->query('preset', 'this_month');
    $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
    $toDate = $toDate ?? now()->toDateString();
    $search = $search ?? '';

    $sellerReports = collect($sellerReports ?? []);
    $logisticsReports = collect($logisticsReports ?? []);
    $courierReports = collect($courierReports ?? []);
    $riderReports = collect($riderReports ?? []);

    $presetsList = [
        'today'        => 'Today',
        'last_7_days'  => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month'   => 'This Month',
        'this_quarter' => 'This Quarter',
        'this_year'    => 'This Year',
    ];
@endphp

<div x-data="{
    ledgerModalOpen: false,
    ledgerRole: '',
    ledgerEntityName: '',
    ledgerEntitySub: '',
    ledgerRows: [],
    ledgerPdfUrl: '',
    openLedgerModal(role, name, sub, rows, pdfUrl) {
        this.ledgerRole = role;
        this.ledgerEntityName = name;
        this.ledgerEntitySub = sub;
        this.ledgerRows = Array.isArray(rows) ? rows : [];
        this.ledgerPdfUrl = pdfUrl || '';
        this.ledgerModalOpen = true;
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <!-- Top Header & Master PDF Export -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Sales &amp; Multi-Role Financial Reports</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $fromDate }} to {{ $toDate }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Audit platform-wide GMV, 10% Admin Commission, 90% Seller &amp; Logistics Hub shares, and distance-scaled Courier &amp; Rider earnings.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.reports.formal-pdf', ['role' => 'Admin', 'preset' => $preset, 'from_date' => $fromDate, 'to_date' => $toDate]) }}"
               target="_blank"
               class="px-5 py-3 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-extrabold shadow-sm transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Open Platform Formal PDF Statement &nearr;</span>
            </a>
        </div>
    </div>

    <!-- Time Horizon Presets, Custom Date Range & Search Bar -->
    <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-4 text-xs">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="font-bold text-text-muted uppercase tracking-wider mr-1">Time Horizon:</span>
                @foreach($presetsList as $pKey => $pLabel)
                    <a href="{{ route('admin.reports.sales', array_filter(['role_tab' => $roleTab, 'preset' => $pKey, 'search' => $search])) }}"
                       class="px-3.5 py-2 rounded-xl font-bold border transition-all {{ $preset === $pKey ? 'bg-primary text-white border-primary shadow-2xs' : 'bg-surface-subtle text-text-muted border-border-subtle hover:text-text-main' }}">
                        {{ $pLabel }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.reports.sales') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="role_tab" value="{{ $roleTab }}">
                <input type="hidden" name="preset" value="custom">
                <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-2 rounded-xl border border-border-subtle bg-surface font-semibold">
                <span class="text-text-muted font-bold">to</span>
                <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-2 rounded-xl border border-border-subtle bg-surface font-semibold">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search store, hub, rider, plate #..."
                       class="px-3.5 py-2 rounded-xl border border-border-subtle bg-surface text-text-main outline-none focus:border-primary w-52">
                <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer">
                    Apply Filter
                </button>
                @if($search !== '' || $preset === 'custom')
                    <a href="{{ route('admin.reports.sales', ['role_tab' => $roleTab]) }}" class="px-3 py-2 rounded-xl bg-surface-subtle text-text-muted hover:text-danger font-bold">
                        Reset
                    </a>
                @endif
            </form>
        </div>
    </div>

    <!-- Executive Period KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Completed Orders</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ number_format($platformKpis['completed_orders'] ?? 0) }}</p>
            <span class="text-[10px] text-text-muted">In selected period</span>
        </div>

        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Merchandise (GMV)</span>
            <p class="text-xl font-serif font-bold text-text-main mt-1">₱{{ number_format($platformKpis['gross_merchandise'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-green-700 font-semibold">Seller 90%: ₱{{ number_format($platformKpis['seller_net_90'] ?? 0, 2) }}</span>
        </div>

        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Admin Comm (10%)</span>
            <p class="text-xl font-serif font-bold text-primary mt-1">₱{{ number_format($platformKpis['admin_commission_10'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-danger font-semibold">Subsidy: -₱{{ number_format($platformKpis['voucher_subsidy'] ?? 0, 2) }}</span>
        </div>

        <div class="p-4 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-white/80 block">Net Admin Profit</span>
            <p class="text-xl font-serif font-extrabold mt-1">₱{{ number_format($platformKpis['net_admin_profit'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-white/85">Realized Platform Net</span>
        </div>

        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Shipping Fees</span>
            <p class="text-xl font-serif font-bold text-text-main mt-1">₱{{ number_format($platformKpis['gross_shipping'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-indigo-700 font-semibold">Hub 90%: ₱{{ number_format($platformKpis['logistics_net_90'] ?? 0, 2) }}</span>
        </div>

        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Rider 10% Ship Share</span>
            <p class="text-xl font-serif font-bold text-emerald-700 mt-1">₱{{ number_format($platformKpis['rider_comm_10'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted">+ Distance bonuses</span>
        </div>
    </div>

    <!-- Role Segment Switcher Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-border-subtle pb-3">
        @foreach([
            'sellers'   => ['label' => '1. Seller Stores Report', 'count' => $sellerReports->count()],
            'logistics' => ['label' => '2. Logistics Sorting Hubs Report', 'count' => $logisticsReports->count()],
            'couriers'  => ['label' => '3. First-Mile Couriers Report', 'count' => $courierReports->count()],
            'riders'    => ['label' => '4. Last-Mile Riders Report', 'count' => $riderReports->count()],
        ] as $tKey => $tMeta)
            <a href="{{ route('admin.reports.sales', array_filter(['role_tab' => $tKey, 'preset' => $preset, 'from_date' => $fromDate, 'to_date' => $toDate, 'search' => $search])) }}"
               class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all inline-flex items-center gap-2 {{ $roleTab === $tKey ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                <span>{{ $tMeta['label'] }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $roleTab === $tKey ? 'bg-white/20 text-white' : 'bg-surface-subtle text-text-main' }}">
                    {{ $tMeta['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <!-- ================================================================= -->
    <!-- TAB 1: SELLER STORES REPORT                                       -->
    <!-- ================================================================= -->
    @if($roleTab === 'sellers')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Seller Stores Financial &amp; E-Wallet Breakdown ({{ $sellerReports->count() }} Stores)</h2>
                    <p class="text-xs text-text-muted">Each Seller earns 90% of Commissionable Product Subtotal while Storkia Admin retains 10%.</p>
                </div>
            </div>

            @if($sellerReports->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No Seller stores matched your filter criteria.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Store &amp; Owner</th>
                                <th class="py-3.5 px-4 text-center">Orders (Done / Cancel)</th>
                                <th class="py-3.5 px-4 text-right">Gross Sales</th>
                                <th class="py-3.5 px-4 text-right">Admin 10%</th>
                                <th class="py-3.5 px-4 text-right">Seller Net (90%)</th>
                                <th class="py-3.5 px-4 text-right">E-Wallet / Withdrawn</th>
                                <th class="py-3.5 px-4 text-center">Rating</th>
                                <th class="py-3.5 px-5 text-right">Statement / Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($sellerReports as $sr)
                                @php
                                    $sellerPdfUrl = route('admin.reports.formal-pdf', [
                                        'role'        => 'Seller',
                                        'entity_id'   => $sr['id'],
                                        'entity_name' => $sr['business_name'],
                                        'preset'      => $preset,
                                        'from_date'   => $fromDate,
                                        'to_date'     => $toDate,
                                    ]);
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5 align-top">
                                        <div class="font-bold text-sm text-primary-dark">{{ $sr['business_name'] }}</div>
                                        <div class="text-text-main font-medium">{{ $sr['owner_name'] }} &bull; <span class="text-text-muted">{{ $sr['category'] }}</span></div>
                                        <div class="text-[11px] text-text-muted">{{ $sr['location'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center font-mono">
                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 font-bold">{{ $sr['completed_orders'] }}</span>
                                        <span class="text-text-muted mx-0.5">/</span>
                                        <span class="px-2 py-0.5 rounded bg-red-50 text-danger font-bold">{{ $sr['cancelled_orders'] }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-semibold">₱{{ number_format($sr['gross_sales'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono text-primary font-bold">₱{{ number_format($sr['admin_commission_10'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-extrabold text-green-700">₱{{ number_format($sr['net_earnings_90'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono">
                                        <div class="font-bold text-text-main">Avail: ₱{{ number_format($sr['wallet_available'], 2) }}</div>
                                        <div class="text-[11px] text-amber-700">Escrow: ₱{{ number_format($sr['wallet_pending'], 2) }}</div>
                                        <div class="text-[11px] text-text-muted">Paid: ₱{{ number_format($sr['wallet_withdrawn'], 2) }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center">
                                        <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-extrabold text-[11px]">
                                            {{ number_format($sr['avg_rating'], 1) }} ★
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 align-top text-right">
                                        <div class="inline-flex flex-col sm:flex-row items-end sm:items-center gap-1.5">
                                            <button type="button"
                                                    @click="openLedgerModal('Seller', '{{ addslashes($sr['business_name']) }}', 'Owner: {{ addslashes($sr['owner_name']) }}', @js($sr['recent_ledger']), '{{ $sellerPdfUrl }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                                View Ledger
                                            </button>
                                            <a href="{{ $sellerPdfUrl }}"
                                               target="_blank"
                                               class="px-3 py-1.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold">
                                                PDF &nearr;
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-surface-subtle/80 border-t-2 border-border-subtle font-extrabold text-xs">
                                <td class="py-3.5 px-5 uppercase">Total All Listed Sellers</td>
                                <td class="py-3.5 px-4 text-center font-mono">{{ $sellerReports->sum('completed_orders') }} completed</td>
                                <td class="py-3.5 px-4 text-right font-mono">₱{{ number_format($sellerReports->sum('gross_sales'), 2) }}</td>
                                <td class="py-3.5 px-4 text-right font-mono text-primary">₱{{ number_format($sellerReports->sum('admin_commission_10'), 2) }}</td>
                                <td class="py-3.5 px-4 text-right font-mono text-green-700">₱{{ number_format($sellerReports->sum('net_earnings_90'), 2) }}</td>
                                <td class="py-3.5 px-4 text-right font-mono">₱{{ number_format($sellerReports->sum('wallet_available'), 2) }} avail</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

    <!-- ================================================================= -->
    <!-- TAB 2: LOGISTICS SORTING HUBS REPORT                              -->
    <!-- ================================================================= -->
    @elseif($roleTab === 'logistics')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Logistics Sorting Hubs Financial &amp; Fleet Report ({{ $logisticsReports->count() }} Companies)</h2>
                <p class="text-xs text-text-muted">Logistics Hubs earn 90% of Original Shipping Fees (10% Logistics Commission + 80% Operational Share).</p>
            </div>

            @if($logisticsReports->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No Logistics Hubs matched your filter criteria.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Logistics Company &amp; Manager</th>
                                <th class="py-3.5 px-4 text-center">Hubs / Couriers / Riders</th>
                                <th class="py-3.5 px-4 text-center">Delivered / Failed</th>
                                <th class="py-3.5 px-4 text-right">Gross Shipping</th>
                                <th class="py-3.5 px-4 text-right">Hub Net (90%)</th>
                                <th class="py-3.5 px-4 text-right">E-Wallet / Withdrawn</th>
                                <th class="py-3.5 px-4 text-center">Rating</th>
                                <th class="py-3.5 px-5 text-right">Statement / Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($logisticsReports as $lr)
                                @php
                                    $logiPdfUrl = route('admin.reports.formal-pdf', [
                                        'role'        => 'Logistics',
                                        'entity_id'   => $lr['id'],
                                        'entity_name' => $lr['business_name'],
                                        'preset'      => $preset,
                                        'from_date'   => $fromDate,
                                        'to_date'     => $toDate,
                                    ]);
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5 align-top">
                                        <div class="font-bold text-sm text-primary-dark">{{ $lr['business_name'] }}</div>
                                        <div class="text-text-main font-medium">{{ $lr['owner_name'] }}</div>
                                        <div class="text-[11px] text-text-muted">{{ $lr['location'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center font-mono">
                                        <span class="px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark font-bold">{{ $lr['active_hubs_count'] }} Hubs</span>
                                        <div class="text-[11px] text-text-muted mt-1">{{ $lr['couriers_count'] }} Couriers &bull; {{ $lr['riders_count'] }} Riders</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center font-mono">
                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 font-bold">{{ $lr['delivered_shipments'] }}</span>
                                        <span class="text-text-muted mx-0.5">/</span>
                                        <span class="px-2 py-0.5 rounded bg-red-50 text-danger font-bold">{{ $lr['failed_shipments'] }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-semibold">₱{{ number_format($lr['gross_shipping'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-extrabold text-green-700">₱{{ number_format($lr['hub_net_90'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono">
                                        <div class="font-bold text-text-main">Avail: ₱{{ number_format($lr['wallet_available'], 2) }}</div>
                                        <div class="text-[11px] text-amber-700">Escrow: ₱{{ number_format($lr['wallet_pending'], 2) }}</div>
                                        <div class="text-[11px] text-text-muted">Paid: ₱{{ number_format($lr['wallet_withdrawn'], 2) }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center">
                                        <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-extrabold text-[11px]">
                                            {{ number_format($lr['avg_rating'], 1) }} ★
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 align-top text-right">
                                        <div class="inline-flex flex-col sm:flex-row items-end sm:items-center gap-1.5">
                                            <button type="button"
                                                    @click="openLedgerModal('Logistics', '{{ addslashes($lr['business_name']) }}', 'Manager: {{ addslashes($lr['owner_name']) }}', @js($lr['recent_ledger']), '{{ $logiPdfUrl }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                                View Ledger
                                            </button>
                                            <a href="{{ $logiPdfUrl }}"
                                               target="_blank"
                                               class="px-3 py-1.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold">
                                                PDF &nearr;
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    <!-- ================================================================= -->
    <!-- TAB 3: FIRST-MILE COURIERS REPORT                                 -->
    <!-- ================================================================= -->
    @elseif($roleTab === 'couriers')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">First-Mile Couriers Haulage &amp; E-Wallet Report ({{ $courierReports->count() }} Couriers)</h2>
                <p class="text-xs text-text-muted">Base pickup pay of ₱30.00 + distance tier bonus + ₱5.00 per excess kg over 1 kg upon hub handover.</p>
            </div>

            @if($courierReports->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No First-Mile Couriers matched your filter criteria.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Courier &amp; Hub</th>
                                <th class="py-3.5 px-4">Vehicle &amp; Capacity</th>
                                <th class="py-3.5 px-4 text-center">Pickups (Done / Assigned)</th>
                                <th class="py-3.5 px-4 text-right">Weight Hauled</th>
                                <th class="py-3.5 px-4 text-right">Period Earnings</th>
                                <th class="py-3.5 px-4 text-right">E-Wallet / Withdrawn</th>
                                <th class="py-3.5 px-5 text-right">Statement / Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($courierReports as $cr)
                                @php
                                    $courierPdfUrl = route('admin.reports.formal-pdf', [
                                        'role'        => 'Courier',
                                        'entity_id'   => $cr['id'],
                                        'entity_name' => $cr['full_name'],
                                        'preset'      => $preset,
                                        'from_date'   => $fromDate,
                                        'to_date'     => $toDate,
                                    ]);
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5 align-top">
                                        <div class="font-bold text-sm text-primary-dark">{{ $cr['full_name'] }}</div>
                                        <div class="font-mono text-[11px] text-text-muted">{{ $cr['email'] }}</div>
                                        <div class="text-[11px] font-semibold text-text-main mt-0.5">Hub: {{ $cr['hub_name'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-bold text-text-main">{{ $cr['vehicle'] }}</div>
                                        <div class="text-[11px] text-text-muted">Max Load: {{ number_format($cr['load_capacity'], 0) }} kg &bull; {{ $cr['status'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center font-mono">
                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 font-bold">{{ $cr['completed_pickups'] }}</span>
                                        <span class="text-text-muted mx-0.5">/</span>
                                        <span class="px-2 py-0.5 rounded bg-surface-subtle text-text-main font-bold">{{ $cr['assigned_pickups'] }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-semibold">{{ number_format($cr['weight_hauled_kg'], 2) }} kg</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-extrabold text-green-700">₱{{ number_format($cr['period_earnings'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono">
                                        <div class="font-bold text-text-main">Avail: ₱{{ number_format($cr['wallet_available'], 2) }}</div>
                                        <div class="text-[11px] text-text-muted">Paid: ₱{{ number_format($cr['wallet_withdrawn'], 2) }}</div>
                                    </td>
                                    <td class="py-3.5 px-5 align-top text-right">
                                        <div class="inline-flex flex-col sm:flex-row items-end sm:items-center gap-1.5">
                                            <button type="button"
                                                    @click="openLedgerModal('Courier', '{{ addslashes($cr['full_name']) }}', 'Hub: {{ addslashes($cr['hub_name']) }} • {{ addslashes($cr['vehicle']) }}', @js($cr['recent_ledger']), '{{ $courierPdfUrl }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                                View Ledger
                                            </button>
                                            <a href="{{ $courierPdfUrl }}"
                                               target="_blank"
                                               class="px-3 py-1.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold">
                                                PDF &nearr;
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    <!-- ================================================================= -->
    <!-- TAB 4: LAST-MILE RIDERS REPORT                                    -->
    <!-- ================================================================= -->
    @else
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Last-Mile Delivery Riders Performance &amp; E-Wallet Report ({{ $riderReports->count() }} Riders)</h2>
                <p class="text-xs text-text-muted">Base delivery pay of ₱50.00 (or 10% shipping fee, whichever is higher) + distance tier bonus + ₱5.00 per excess kg.</p>
            </div>

            @if($riderReports->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No Last-Mile Riders matched your filter criteria.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Rider &amp; Hub</th>
                                <th class="py-3.5 px-4">Vehicle &amp; Status</th>
                                <th class="py-3.5 px-4 text-center">Deliveries (Done / Failed)</th>
                                <th class="py-3.5 px-4 text-right">Period Earnings</th>
                                <th class="py-3.5 px-4 text-right">E-Wallet / Withdrawn</th>
                                <th class="py-3.5 px-4 text-center">Rating</th>
                                <th class="py-3.5 px-5 text-right">Statement / Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($riderReports as $rr)
                                @php
                                    $riderPdfUrl = route('admin.reports.formal-pdf', [
                                        'role'        => 'Rider',
                                        'entity_id'   => $rr['id'],
                                        'entity_name' => $rr['full_name'],
                                        'preset'      => $preset,
                                        'from_date'   => $fromDate,
                                        'to_date'     => $toDate,
                                    ]);
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5 align-top">
                                        <div class="font-bold text-sm text-primary-dark">{{ $rr['full_name'] }}</div>
                                        <div class="font-mono text-[11px] text-text-muted">{{ $rr['email'] }}</div>
                                        <div class="text-[11px] font-semibold text-text-main mt-0.5">Hub: {{ $rr['hub_name'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-bold text-text-main">{{ $rr['vehicle'] }}</div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rr['status'] === 'Approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-danger' }}">
                                            {{ $rr['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center font-mono">
                                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 font-bold">{{ $rr['completed_deliveries'] }}</span>
                                        <span class="text-text-muted mx-0.5">/</span>
                                        <span class="px-2 py-0.5 rounded bg-red-50 text-danger font-bold">{{ $rr['failed_deliveries'] }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono font-extrabold text-green-700">₱{{ number_format($rr['period_earnings'], 2) }}</td>
                                    <td class="py-3.5 px-4 align-top text-right font-mono">
                                        <div class="font-bold text-text-main">Avail: ₱{{ number_format($rr['wallet_available'], 2) }}</div>
                                        <div class="text-[11px] text-amber-700">Escrow: ₱{{ number_format($rr['wallet_pending'], 2) }}</div>
                                        <div class="text-[11px] text-text-muted">Paid: ₱{{ number_format($rr['wallet_withdrawn'], 2) }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 align-top text-center">
                                        <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-extrabold text-[11px]">
                                            {{ number_format($rr['avg_rating'], 1) }} ★
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 align-top text-right">
                                        <div class="inline-flex flex-col sm:flex-row items-end sm:items-center gap-1.5">
                                            <button type="button"
                                                    @click="openLedgerModal('Rider', '{{ addslashes($rr['full_name']) }}', 'Hub: {{ addslashes($rr['hub_name']) }} • {{ addslashes($rr['vehicle']) }}', @js($rr['recent_ledger']), '{{ $riderPdfUrl }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                                View Ledger
                                            </button>
                                            <a href="{{ $riderPdfUrl }}"
                                               target="_blank"
                                               class="px-3 py-1.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold">
                                                PDF &nearr;
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- Per-Entity Recent Activity Drilldown Modal -->
    <template x-teleport="body">
        <div x-show="ledgerModalOpen"
             x-cloak
             @keydown.escape.window="ledgerModalOpen = false"
             class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div @click.away="ledgerModalOpen = false"
                 class="bg-surface rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[88vh] flex flex-col">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-border-subtle">
                    <div>
                        <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase" x-text="ledgerRole + ' Ledger Drilldown'"></span>
                        <h3 class="text-base font-bold text-primary-dark mt-1" x-text="ledgerEntityName"></h3>
                        <p class="text-xs text-text-muted" x-text="ledgerEntitySub"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="ledgerPdfUrl">
                            <a :href="ledgerPdfUrl" target="_blank" class="px-4 py-2 rounded-xl bg-primary-dark text-white text-xs font-bold">
                                Open Full Printable PDF &nearr;
                            </a>
                        </template>
                        <button type="button" @click="ledgerModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <template x-if="ledgerRows.length === 0">
                        <div class="p-10 text-center text-xs text-text-muted">No completed transactions recorded for this account in the selected period.</div>
                    </template>

                    <template x-if="ledgerRows.length > 0">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle text-text-muted uppercase text-[10px] font-bold">
                                    <th class="py-2.5 px-3">Date</th>
                                    <th class="py-2.5 px-3">Reference</th>
                                    <th class="py-2.5 px-3">Details</th>
                                    <th class="py-2.5 px-3 text-right">Net Earned</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <template x-for="(row, rIdx) in ledgerRows" :key="rIdx">
                                    <tr>
                                        <td class="py-2.5 px-3 font-mono text-[11px] text-text-muted" x-text="row.date"></td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-primary-dark" x-text="row.order_no || row.tracking"></td>
                                        <td class="py-2.5 px-3 text-text-main">
                                            <template x-if="ledgerRole === 'Seller'">
                                                <span x-text="'Gross ₱' + Number(row.gross || 0).toFixed(2) + ' • Admin 10% -₱' + Number(row.admin_10 || 0).toFixed(2)"></span>
                                            </template>
                                            <template x-if="ledgerRole === 'Logistics'">
                                                <span x-text="'Ship Fee ₱' + Number(row.gross_ship || 0).toFixed(2) + ' • Rider 10% ₱' + Number(row.rider_10 || 0).toFixed(2)"></span>
                                            </template>
                                            <template x-if="ledgerRole === 'Courier'">
                                                <span x-text="(row.seller || 'Seller') + ' • ' + (row.tier || '') + ' (' + Number(row.weight || 0).toFixed(2) + ' kg)'"></span>
                                            </template>
                                            <template x-if="ledgerRole === 'Rider'">
                                                <span x-text="(row.tier || '') + ' • COD ₱' + Number(row.cod || 0).toFixed(2)"></span>
                                            </template>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-extrabold text-green-700"
                                            x-text="'₱' + Number(row.net_90 ?? row.hub_90 ?? row.pay ?? 0).toFixed(2)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection