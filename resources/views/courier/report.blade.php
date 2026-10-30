<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Courier First-Mile Performance Report</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16 select-none">

    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('courier.dashboard') }}" class="px-3.5 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-xs font-bold text-text-main">
                    &larr; Back to Courier Dashboard
                </a>
                <div>
                    <h1 class="text-base font-bold text-primary-dark">First-Mile Courier Performance &amp; Haulage Report</h1>
                    <p class="text-xs text-text-muted">{{ $courier->full_name }} &bull; {{ $courier->vehicle_type }} ({{ $courier->plate_number }}) &bull; Hub: {{ $courier->home_hub }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('courier.report') }}" class="flex flex-wrap items-end gap-2 text-xs">
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
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Total Pickups Assigned</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $summary['total_assigned'] }}</p>
                <span class="text-[10px] text-text-muted">Range: {{ $fromDate }} to {{ $toDate }}</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Completed Hub Drop-offs</span>
                <p class="text-2xl font-serif font-bold text-green-700 mt-1">{{ $summary['completed_count'] }}</p>
                <span class="text-[10px] text-text-muted">Verified at Sorting Hub</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Active In-Progress</span>
                <p class="text-2xl font-serif font-bold text-blue-700 mt-1">{{ $summary['active_count'] }}</p>
                <span class="text-[10px] text-text-muted">Reassigned: {{ $summary['reassigned_count'] }}</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Total Weight Hauled</span>
                <p class="text-2xl font-serif font-bold text-primary mt-1">{{ number_format($summary['total_weight_hauled'], 2) }} kg</p>
                <span class="text-[10px] text-text-muted">Vehicle Capacity: {{ number_format($courier->load_capacity) }} kg</span>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">First-Mile Pickup Assignments Log ({{ $fromDate }} to {{ $toDate }} — FIFO)</h2>
            </div>
            @if($assignmentsInPeriod->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No pickup assignments recorded in this date range.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Tracking #</th>
                                <th class="py-3 px-4">Seller Store</th>
                                <th class="py-3 px-4">Weight</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-5 text-right">Assigned Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($assignmentsInPeriod as $assign)
                                <tr>
                                    <td class="py-3 px-5 font-mono font-bold text-primary-dark">{{ $assign->shipment->tracking_number ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 font-semibold text-text-main">{{ $assign->shipment->sellerProfile->business_name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4">{{ number_format((float) ($assign->shipment->total_weight ?? 0), 2) }} kg</td>
                                    <td class="py-3 px-4 uppercase font-bold">{{ $assign->status }}</td>
                                    <td class="py-3 px-5 text-right text-text-muted">{{ $assign->created_at?->format('M d, Y h:i A') }}</td>
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