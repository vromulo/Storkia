<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['document_number'] }} — Storkia Official Financial & Performance Statement</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-sheet { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-900 py-8 px-4">

    <!-- Interactive Filter & PDF Export Bar (Hidden in PDF Output) -->
    <div class="no-print max-w-5xl mx-auto mb-6 bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="font-bold text-gray-500 uppercase mr-1">Time Horizon:</span>
            @foreach([
                'last_7_days'  => 'Last 7 Days',
                'last_30_days' => 'Last 30 Days',
                'this_month'   => 'This Month',
                'this_quarter' => 'This Quarter',
                'this_year'    => 'This Year',
            ] as $pKey => $pLabel)
                <a href="{{ request()->fullUrlWithQuery(['preset' => $pKey]) }}"
                   class="px-3 py-1.5 rounded-xl font-bold border transition-all {{ ($report['period']['preset'] ?? 'this_month') === $pKey ? 'bg-primary-dark text-white border-primary-dark' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' }}">
                    {{ $pLabel }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="preset" value="custom">
            <input type="date" name="from_date" value="{{ $report['period']['from_date'] }}" class="px-2.5 py-1.5 rounded-xl border border-gray-300 font-semibold">
            <span>to</span>
            <input type="date" name="to_date" value="{{ $report['period']['to_date'] }}" class="px-2.5 py-1.5 rounded-xl border border-gray-300 font-semibold">
            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-gray-800 text-white font-bold cursor-pointer">Apply Range</button>
            <button type="button" onclick="window.print()" class="px-4 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-extrabold shadow-xs cursor-pointer">
                Save / Download Formal PDF
            </button>
        </form>
    </div>

    <!-- Formal Executive PDF Statement Sheet -->
    <div class="report-sheet max-w-5xl mx-auto bg-white rounded-3xl border border-gray-200 shadow-lg p-8 sm:p-12 space-y-8">

        <!-- Formal Letterhead -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b-2 border-gray-900">
            <div>
                <div class="text-xs font-extrabold uppercase tracking-widest text-primary">Storkia E-Commerce Platform</div>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 mt-1">
                    Official Financial, Profit &amp; Performance Statement
                </h1>
                <p class="text-xs text-gray-600 mt-1">
                    Operational Role: <strong>{{ $report['role'] }}</strong> &bull; Account / Entity: <strong>{{ $report['entity_name'] }}</strong>
                </p>
            </div>

            <div class="text-left sm:text-right text-xs space-y-1 font-mono bg-gray-50 p-3.5 rounded-2xl border border-gray-200">
                <div><span class="text-gray-500">Document No:</span> <strong>{{ $report['document_number'] }}</strong></div>
                <div><span class="text-gray-500">Reporting Period:</span> <strong>{{ $report['period']['from_date'] }} to {{ $report['period']['to_date'] }}</strong> ({{ $report['period']['days_count'] }} days)</div>
                <div><span class="text-gray-500">Generated On:</span> <strong>{{ $report['generated_at'] }}</strong></div>
            </div>
        </div>

        <!-- Executive Summary KPIs -->
        <div>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 mb-3">1. Executive Financial &amp; Operational KPIs</h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach($report['kpis'] as $kpi)
                    <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                        <span class="text-[10px] font-bold uppercase text-gray-500 block">{{ $kpi['label'] }}</span>
                        <strong class="text-base sm:text-lg font-serif font-extrabold text-gray-900 mt-1 block">{{ $kpi['value'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Itemized Ledger Table -->
        <div>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 mb-3">
                2. Itemized Financial &amp; Performance Ledger ({{ count($report['rows']) }} Records)
            </h2>

            <div class="overflow-x-auto border border-gray-200 rounded-2xl">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-900 text-white uppercase text-[10px] font-bold">
                            <th class="py-3 px-4">Date &amp; Time</th>
                            <th class="py-3 px-4">Reference #</th>
                            <th class="py-3 px-4">Transaction / Activity Description</th>
                            <th class="py-3 px-4 text-right">Gross Base</th>
                            <th class="py-3 px-4 text-right">Fee / Deduction</th>
                            <th class="py-3 px-4 text-right">Net Realized</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td class="py-2.5 px-4 font-mono text-[11px] text-gray-600">{{ $row['date'] }}</td>
                                <td class="py-2.5 px-4 font-mono font-bold text-gray-900">{{ $row['reference'] }}</td>
                                <td class="py-2.5 px-4 text-gray-700">{{ $row['description'] }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">₱{{ number_format((float) $row['gross'], 2) }}</td>
                                <td class="py-2.5 px-4 text-right font-mono text-red-600">-₱{{ number_format((float) $row['deduction'], 2) }}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-green-700">₱{{ number_format((float) $row['net'], 2) }}</td>
                                <td class="py-2.5 px-4 text-center font-bold text-[10px]">{{ $row['status'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500">No completed transactions recorded within this date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(!empty($report['rows']))
                        <tfoot>
                            <tr class="bg-gray-100 border-t-2 border-gray-900 font-extrabold text-xs">
                                <td colspan="3" class="py-3 px-4 uppercase">Statement Period Totals</td>
                                <td class="py-3 px-4 text-right font-mono">₱{{ number_format($report['totals']['gross'], 2) }}</td>
                                <td class="py-3 px-4 text-right font-mono text-red-600">-₱{{ number_format($report['totals']['deduction'], 2) }}</td>
                                <td class="py-3 px-4 text-right font-mono text-green-700">₱{{ number_format($report['totals']['net'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <!-- Formal Attestation & Signatory Footer -->
        <div class="pt-8 border-t border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-8 text-xs text-gray-600">
            <div>
                <p class="font-bold text-gray-900 uppercase text-[10px]">System Audit Certification</p>
                <p class="mt-1 leading-relaxed">
                    This statement is computer-generated from Storkia's immutable double-entry Commission &amp; E-Wallet Ledger under the 10% Admin / 90% Seller merchandise split and distance-scaled shipping policy.
                </p>
            </div>
            <div class="sm:text-right space-y-6">
                <div class="inline-block border-b border-gray-400 w-56 pb-1 text-center font-bold text-gray-900">
                    {{ $report['entity_name'] }}
                </div>
                <div class="text-[10px] uppercase tracking-wider text-gray-500">Authorized Account Holder / Verifier</div>
            </div>
        </div>

    </div>

</body>
</html>