<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Rider Financial & Performance Report</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16 select-none">

    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('rider.dashboard') }}" class="px-3.5 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-xs font-bold text-text-main">
                    &larr; Back to Rider Dashboard
                </a>
                <div>
                    <h1 class="text-base font-bold text-primary-dark">Rider Financial, E-Wallet &amp; Performance Report</h1>
                    <p class="text-xs text-text-muted">{{ $rider->full_name }} &bull; Hub: {{ $rider->assigned_hub?->hub_name ?? ($rider->logisticsProfile->business_name ?? 'Logistics Hub') }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('rider.report') }}" class="flex flex-wrap items-end gap-2 text-xs">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-text-muted mb-0.5">From Date</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-text-muted mb-0.5">To Date</label>
                    <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">
                    Generate Report
                </button>
                <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-surface border border-border-subtle text-text-main font-bold cursor-pointer">
                    Print
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
                <span class="text-[11px] font-bold uppercase text-white/80">Real-Money E-Wallet Balance</span>
                <p class="text-2xl font-serif font-extrabold mt-1">₱{{ number_format((float) $riderWallet->available_balance, 2) }}</p>
                <span class="text-[10px] text-white/85">10% Shipping Comm on Completed Orders</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Pending Escrow Balance</span>
                <p class="text-2xl font-serif font-bold text-amber-600 mt-1">₱{{ number_format((float) $riderWallet->pending_balance, 2) }}</p>
                <span class="text-[10px] text-text-muted">Delivered — Awaiting Buyer Confirm</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Period Realized Profit ({{ $fromDate }} – {{ $toDate }})</span>
                <p class="text-2xl font-serif font-bold text-green-700 mt-1">₱{{ number_format($summary['period_realized_income'], 2) }}</p>
                <span class="text-[10px] text-text-muted">COD Collected: ₱{{ number_format($summary['period_cod_collected'], 2) }}</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Delivery Performance</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $summary['delivered_count'] }} / {{ $summary['total_assigned'] }}</p>
                <span class="text-[10px] text-text-muted">Delivered vs. Failed ({{ $summary['failed_count'] }})</span>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Rider E-Wallet Commission Ledger ({{ $fromDate }} to {{ $toDate }} — FIFO)</h2>
            </div>
            @if($walletTransactions->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No Rider commission transactions in the selected date range.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Ref #</th>
                                <th class="py-3 px-4">Order / Shipment</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">10% Rider Comm</th>
                                <th class="py-3 px-4">Wallet Balance After</th>
                                <th class="py-3 px-5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($walletTransactions as $wtx)
                                <tr>
                                    <td class="py-3 px-5 font-mono font-bold text-primary-dark">{{ $wtx->transaction_reference }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $wtx->sellerOrder->seller_order_number ?? '' }} ({{ $wtx->shipment->tracking_number ?? '' }})</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $wtx->entry_type === 'commission_released' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                            {{ str_replace('_', ' ', $wtx->entry_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-green-700">+₱{{ number_format((float) $wtx->amount, 2) }}</td>
                                    <td class="py-3 px-4 font-bold">₱{{ number_format((float) $wtx->available_balance_after, 2) }}</td>
                                    <td class="py-3 px-5 text-right text-text-muted">{{ $wtx->created_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>
</body>
</html>