<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['document_number'] }} — Storkia Official Financial & Performance Statement</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-sheet {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                border-radius: 0 !important;
                margin: 0 auto !important;
                padding: 8mm !important;
                max-width: 100% !important;
                page-break-after: always;
                break-after: page;
            }
            .report-sheet:last-of-type {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-900 py-8 px-4">
@php
    $pages = !empty($report['pages']) && is_array($report['pages'])
        ? $report['pages']
        : [($report['rows'] ?? [])];
    $totalPages = max(1, count($pages));
    $verifierFullName = $report['verifier_name']
        ?? $report['account_holder_name']
        ?? (auth('admin')->user()?->name ?? auth()->user()?->full_name ?? $report['entity_name']);
    $businessLabel = $report['business_name'] ?? $report['entity_name'];
    $verifierTitle = $report['verifier_title'] ?? 'Authorized Account Holder / Verifier';
    $roleSummaries = $report['role_summaries'] ?? [];
@endphp

    <!-- Interactive Filter & PDF Export Bar (Hidden in Print / PDF Output) -->
    <div class="no-print max-w-5xl mx-auto mb-6 bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="font-bold text-gray-500 uppercase mr-1">Time Horizon:</span>
            @foreach([
                'today'        => 'Today',
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
            @if(request()->filled('role'))
                <input type="hidden" name="role" value="{{ request()->query('role') }}">
            @endif
            @if(request()->filled('entity_id'))
                <input type="hidden" name="entity_id" value="{{ request()->query('entity_id') }}">
            @endif
            @if(request()->filled('entity_name'))
                <input type="hidden" name="entity_name" value="{{ request()->query('entity_name') }}">
            @endif
            <input type="hidden" name="preset" value="custom">
            <input type="date" name="from_date" value="{{ $report['period']['from_date'] }}" class="px-2.5 py-1.5 rounded-xl border border-gray-300 font-semibold">
            <span>to</span>
            <input type="date" name="to_date" value="{{ $report['period']['to_date'] }}" class="px-2.5 py-1.5 rounded-xl border border-gray-300 font-semibold">
            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-gray-800 text-white font-bold cursor-pointer">Apply Range</button>
            <button type="button" onclick="window.print()" class="px-4 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-extrabold shadow-xs cursor-pointer">
                Print / Save PDF ({{ $totalPages }} {{ Str::plural('Page', $totalPages) }})
            </button>
        </form>
    </div>

    <!-- Paginated A4 Formal Statement Sheets -->
    <div class="space-y-8">
        @foreach($pages as $pageIdx => $pageRows)
            @php
                $currentPageNum = $pageIdx + 1;
                $isFirstPage = $currentPageNum === 1;
                $isLastPage = $currentPageNum === $totalPages;
            @endphp
            <div class="report-sheet max-w-5xl mx-auto bg-white rounded-3xl border border-gray-200 shadow-lg p-8 sm:p-11 flex flex-col justify-between min-h-[265mm] space-y-6">
                <div class="space-y-6">
                    <!-- Repeating Formal Letterhead with Page X of Y -->
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-5 border-b-2 border-gray-900">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-extrabold uppercase tracking-widest text-primary">Storkia E-Commerce Platform</span>
                                <span class="px-2 py-0.5 rounded bg-gray-900 text-white font-mono text-[10px] font-bold">
                                    Page {{ $currentPageNum }} of {{ $totalPages }}
                                </span>
                            </div>
                            <h1 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 mt-1">
                                Official Financial, Profit &amp; Performance Statement
                                @if(!$isFirstPage)
                                    <span class="text-sm font-sans font-semibold text-gray-500">(Continued)</span>
                                @endif
                            </h1>
                            <p class="text-xs text-gray-600 mt-1">
                                Operational Role: <strong>{{ $report['role'] }}</strong> &bull;
                                Entity / Store: <strong>{{ $businessLabel }}</strong> &bull;
                                Account Holder: <strong>{{ $verifierFullName }}</strong>
                            </p>
                        </div>

                        <div class="text-left sm:text-right text-xs space-y-1 font-mono bg-gray-50 p-3.5 rounded-2xl border border-gray-200 shrink-0">
                            <div><span class="text-gray-500">Document No:</span> <strong>{{ $report['document_number'] }}</strong></div>
                            <div><span class="text-gray-500">Reporting Period:</span> <strong>{{ $report['period']['from_date'] }} to {{ $report['period']['to_date'] }}</strong> ({{ $report['period']['days_count'] }} days)</div>
                            <div><span class="text-gray-500">Generated On:</span> <strong>{{ $report['generated_at'] }}</strong></div>
                        </div>
                    </div>

                    @if($isFirstPage)
                        <!-- Section 1: Executive Summary KPIs -->
                        <div>
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 mb-2.5">
                                1. Executive Financial &amp; Operational KPIs
                            </h2>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                @foreach($report['kpis'] as $kpi)
                                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-200">
                                        <span class="text-[10px] font-bold uppercase text-gray-500 block">{{ $kpi['label'] }}</span>
                                        <strong class="text-sm sm:text-base font-serif font-extrabold text-gray-900 mt-1 block">{{ $kpi['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Optional Section 1B: Multi-Role Summary Breakdown (For Admin Statement) -->
                        @if(!empty($roleSummaries))
                            <div>
                                <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 mb-2.5">
                                    2. Ecosystem Role Earnings &amp; Disbursement Summary (Seller / Logistics / Rider / Courier)
                                </h2>
                                <div class="overflow-x-auto border border-gray-200 rounded-2xl">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-gray-800 text-white uppercase text-[10px] font-bold">
                                                <th class="py-2.5 px-4">Operational Role Segment</th>
                                                <th class="py-2.5 px-4 text-center">Active Accounts</th>
                                                <th class="py-2.5 px-4 text-right">Gross Volume Base</th>
                                                <th class="py-2.5 px-4 text-right">Role Net Allocation</th>
                                                <th class="py-2.5 px-4 text-right">Withdrawn / Disbursed</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach($roleSummaries as $rs)
                                                <tr>
                                                    <td class="py-2 px-4 font-bold text-gray-900">{{ $rs['role'] }}</td>
                                                    <td class="py-2 px-4 text-center font-mono">{{ number_format((int) $rs['count']) }}</td>
                                                    <td class="py-2 px-4 text-right font-mono">₱{{ number_format((float) $rs['gross_base'], 2) }}</td>
                                                    <td class="py-2 px-4 text-right font-mono font-bold text-green-700">₱{{ number_format((float) $rs['net_share'], 2) }}</td>
                                                    <td class="py-2 px-4 text-right font-mono font-semibold text-gray-800">₱{{ number_format((float) $rs['disbursed'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endif

                    <!-- Itemized Ledger Table (Paginated per Sheet) -->
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500">
                                {{ !empty($roleSummaries) ? '3.' : '2.' }} Itemized Financial &amp; Performance Ledger ({{ count($report['rows']) }} Total Records — Sheet {{ $currentPageNum }} of {{ $totalPages }})
                            </h2>
                            <span class="text-[11px] font-mono text-gray-500">Showing {{ count($pageRows) }} record(s) on this page</span>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-2xl">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-gray-900 text-white uppercase text-[10px] font-bold">
                                        <th class="py-2.5 px-3.5">Date &amp; Time</th>
                                        <th class="py-2.5 px-3.5">Reference #</th>
                                        <th class="py-2.5 px-3.5">Transaction / Activity Description</th>
                                        <th class="py-2.5 px-3.5 text-right">Gross Base</th>
                                        <th class="py-2.5 px-3.5 text-right">Fee / Subsidy</th>
                                        <th class="py-2.5 px-3.5 text-right">Net Realized</th>
                                        <th class="py-2.5 px-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($pageRows as $row)
                                        <tr>
                                            <td class="py-2 px-3.5 font-mono text-[11px] text-gray-600 whitespace-nowrap">{{ $row['date'] }}</td>
                                            <td class="py-2 px-3.5 font-mono font-bold text-gray-900 whitespace-nowrap">{{ $row['reference'] }}</td>
                                            <td class="py-2 px-3.5 text-gray-700">{{ $row['description'] }}</td>
                                            <td class="py-2 px-3.5 text-right font-mono whitespace-nowrap">₱{{ number_format((float) $row['gross'], 2) }}</td>
                                            <td class="py-2 px-3.5 text-right font-mono text-red-600 whitespace-nowrap">-₱{{ number_format((float) $row['deduction'], 2) }}</td>
                                            <td class="py-2 px-3.5 text-right font-mono font-bold text-green-700 whitespace-nowrap">₱{{ number_format((float) $row['net'], 2) }}</td>
                                            <td class="py-2 px-3.5 text-center font-bold text-[10px]">{{ $row['status'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-gray-500">No completed transactions recorded within this date range.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if($isLastPage && !empty($report['rows']))
                                    <tfoot>
                                        <tr class="bg-gray-100 border-t-2 border-gray-900 font-extrabold text-xs">
                                            <td colspan="3" class="py-3 px-3.5 uppercase">Grand Statement Period Totals ({{ $report['period']['from_date'] }} to {{ $report['period']['to_date'] }})</td>
                                            <td class="py-3 px-3.5 text-right font-mono">₱{{ number_format($report['totals']['gross'], 2) }}</td>
                                            <td class="py-3 px-3.5 text-right font-mono text-red-600">-₱{{ number_format($report['totals']['deduction'], 2) }}</td>
                                            <td class="py-3 px-3.5 text-right font-mono text-green-700">₱{{ number_format($report['totals']['net'], 2) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sheet Footer & Signatory Block -->
                <div class="pt-6 border-t border-gray-200 space-y-4">
                    @if($isLastPage)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-xs text-gray-600">
                            <div>
                                <p class="font-bold text-gray-900 uppercase text-[10px]">System Audit Certification</p>
                                <p class="mt-1 leading-relaxed">
                                    This statement is computer-generated from Storkia's immutable double-entry Commission &amp; E-Wallet Ledger under the 10% Admin / 90% Seller merchandise split and 90% Hub / 10% Rider shipping fee policy.
                                </p>
                            </div>
                            <div class="sm:text-right space-y-1">
                                <div class="inline-block border-b-2 border-gray-900 min-w-[240px] pt-6 pb-1 text-center font-bold text-sm text-gray-900">
                                    {{ $verifierFullName }}
                                </div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-700">{{ $verifierTitle }}</div>
                                <div class="text-[10px] text-gray-500">{{ $businessLabel }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between text-[10px] font-mono text-gray-500 pt-2 border-t border-gray-100">
                        <span>{{ $report['document_number'] }} &bull; {{ $businessLabel }}</span>
                        <span>Verified by: {{ $verifierFullName }} &bull; Page {{ $currentPageNum }} of {{ $totalPages }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>