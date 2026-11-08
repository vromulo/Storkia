@extends('layouts.admin', ['title' => 'Storkia Admin - Seller Reports'])

@section('content')
@php
    $tabs = [
        'pending'      => 'Pending',
        'under_review' => 'Under Review',
        'resolved'     => 'Resolved',
        'dismissed'    => 'Dismissed',
        'all'          => 'All Reports',
    ];

    // Purely a visual hint: a seller with this many reports (any status) gets a red count.
    $repeatThreshold = 3;

    $closedCount = ($counts['resolved'] ?? 0) + ($counts['dismissed'] ?? 0);
@endphp

<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Seller Reports</h1>
            <p class="text-sm text-text-muted mt-1">
                Review buyers' reports about seller accounts. Dismiss them, or warn, suspend or ban the seller.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.seller-reports.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Report #, store, reporter name or email..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-72">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold transition-colors cursor-pointer">
                Search
            </button>
        </form>
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

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Waiting for Review</span>
            <p class="text-2xl font-serif font-bold {{ ($counts['pending'] ?? 0) > 0 ? 'text-danger' : 'text-primary-dark' }} mt-1">{{ number_format($counts['pending'] ?? 0) }}</p>
            <span class="text-[11px] text-text-muted">New reports nobody has opened yet</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Under Review</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1">{{ number_format($counts['under_review'] ?? 0) }}</p>
            <span class="text-[11px] text-text-muted">Opened and still waiting for a decision</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Closed</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ number_format($closedCount) }}</p>
            <span class="text-[11px] text-text-muted">{{ number_format($counts['resolved'] ?? 0) }} resolved &bull; {{ number_format($counts['dismissed'] ?? 0) }} dismissed</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.seller-reports.index', array_filter(['tab' => $key, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $tab === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $label }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === $key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ number_format($counts[$key] ?? 0) }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($reports->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No seller reports found</h3>
                <p class="text-xs text-text-muted">Nothing matches this tab and search.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Report</th>
                            <th class="py-3.5 px-4">Reported By</th>
                            <th class="py-3.5 px-4">Seller</th>
                            <th class="py-3.5 px-4">Reason</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($reports as $report)
                            @php
                                $badgeClass = match ($report->status) {
                                    \App\Models\SellerReport::STATUS_RESOLVED     => 'bg-green-100 text-green-700 border-green-200',
                                    \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'bg-blue-100 text-blue-700 border-blue-200',
                                    \App\Models\SellerReport::STATUS_DISMISSED    => 'bg-gray-100 text-gray-700 border-gray-200',
                                    default                                       => 'bg-amber-100 text-amber-800 border-amber-200',
                                };

                                $sellerTotal = (int) ($sellerReportTotals[$report->seller_profile_id] ?? 1);
                                $isRepeat = $sellerTotal >= $repeatThreshold;
                                $isOpen = $report->isOpen();

                                $actionLabel = $report->status === \App\Models\SellerReport::STATUS_PENDING
                                    ? 'Review'
                                    : ($isOpen ? 'Continue' : 'View');
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-sm text-primary-dark">{{ $report->report_number }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $report->created_at?->format('M d, Y h:i A') }}</div>
                                    @if($report->evidence_photos && count($report->evidence_photos) > 0)
                                        <div class="text-text-muted">{{ count($report->evidence_photos) }} photo(s)</div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $report->reporter?->full_name ?? 'Buyer' }}</div>
                                    <div class="text-text-muted">{{ $report->reporter?->email }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $report->sellerProfile?->business_name ?? 'Seller no longer exists' }}</div>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $isRepeat ? 'bg-red-100 text-red-700 border-red-200' : 'bg-surface-subtle text-text-muted border-border-subtle' }}">
                                            {{ $sellerTotal }} {{ \Illuminate\Support\Str::plural('report', $sellerTotal) }} in total
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div class="font-semibold text-text-main">{{ $report->category_label }}</div>
                                    @if($report->sellerOrder)
                                        <div class="text-text-muted">Order {{ $report->sellerOrder->seller_order_number }}</div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                        {{ $report->status_label }}
                                    </span>
                                    @if($report->admin_action_label)
                                        <div class="text-[11px] text-text-muted mt-1">{{ $report->admin_action_label }}</div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <a href="{{ route('admin.seller-reports.show', $report->id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors {{ $isOpen ? 'bg-primary hover:bg-primary-dark text-surface' : 'bg-surface border border-border-subtle text-primary-dark hover:border-primary' }}">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($reports->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $reports->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection