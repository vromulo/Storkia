@extends('layouts.admin', ['title' => 'Storkia Admin - Returns & Refunds'])

@section('content')
@php
    $tabs = [
        'needs_decision' => 'Needs Decision',
        'refund_queue'   => 'Refund Payouts',
        'in_progress'    => 'In Progress',
        'completed'      => 'Completed',
        'all'            => 'All Requests',
    ];
@endphp

<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Returns &amp; Refunds</h1>
            <p class="text-sm text-text-muted mt-1">
                Decide escalated requests and disputed returned parcels, and pay approved refunds to the buyer's GCash.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.returns.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Request #, order #, store, buyer email..."
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
            <span class="text-[11px] font-bold uppercase text-text-muted">Waiting for Your Decision</span>
            <p class="text-2xl font-serif font-bold {{ $counts['needs_decision'] > 0 ? 'text-danger' : 'text-primary-dark' }} mt-1">{{ $counts['needs_decision'] }}</p>
            <span class="text-[11px] text-text-muted">Escalations and disputed returned parcels</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Refunds to Pay (GCash)</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1">&#8369;{{ number_format($refundQueueTotal, 2) }}</p>
            <span class="text-[11px] text-text-muted">{{ $counts['refund_queue'] }} request(s) in the payout queue</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Overdue Payouts</span>
            <p class="text-2xl font-serif font-bold {{ $overduePayouts > 0 ? 'text-danger' : 'text-primary-dark' }} mt-1">{{ $overduePayouts }}</p>
            <span class="text-[11px] text-text-muted">Past the {{ \App\Models\ReturnRequest::REFUND_PAYOUT_HOURS }}-hour payout window</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.returns.index', array_filter(['tab' => $key, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $tab === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $label }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === $key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($returnRequests->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No return requests found</h3>
                <p class="text-xs text-text-muted">Nothing matches this tab and search.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Request</th>
                            <th class="py-3.5 px-4">Buyer</th>
                            <th class="py-3.5 px-4">Seller</th>
                            <th class="py-3.5 px-4">Reason &amp; Resolution</th>
                            <th class="py-3.5 px-4 text-right">Refund</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($returnRequests as $rr)
                            @php
                                $badgeClass = match($rr->status) {
                                    'refunded' => 'bg-green-100 text-green-700 border-green-200',
                                    'rejected', 'closed' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'escalated', 'receipt_disputed' => 'bg-red-100 text-red-700 border-red-200',
                                    'refund_pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                    default => 'bg-blue-100 text-blue-700 border-blue-200',
                                };
                                $needsDecision = in_array($rr->status, ['escalated', 'receipt_disputed'], true);
                                $isPayout = $rr->refund_status === \App\Models\ReturnRequest::REFUND_PENDING_ADMIN;
                                $overdue = $isPayout && $rr->refund_due_at && $rr->refund_due_at->isPast();
                                $actionLabel = $needsDecision ? 'Review & Decide' : ($isPayout ? 'Pay Refund' : 'View');
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-sm text-primary-dark">{{ $rr->request_number }}</div>
                                    <div class="text-text-muted mt-0.5">Order {{ $rr->sellerOrder?->seller_order_number ?? 'N/A' }}</div>
                                    <div class="text-text-muted">{{ $rr->created_at?->format('M d, Y h:i A') }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $rr->buyer?->full_name ?? 'Buyer' }}</div>
                                    <div class="text-text-muted">{{ $rr->buyer?->email }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $rr->sellerProfile?->business_name ?? 'Seller' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div class="font-semibold text-text-main">{{ $rr->reason_category }}</div>
                                    <div class="text-text-muted">{{ $rr->resolution_label }}</div>
                                </td>

                                <td class="py-4 px-4 align-top text-right">
                                    <div class="font-bold text-text-main">&#8369;{{ number_format((float) $rr->refund_amount, 2) }}</div>
                                    @if($isPayout && $rr->refund_due_at)
                                        <div class="text-[11px] {{ $overdue ? 'text-danger font-bold' : 'text-text-muted' }}">
                                            {{ $overdue ? 'Overdue since' : 'Pay by' }} {{ $rr->refund_due_at->format('M d, h:i A') }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                        {{ $rr->status_label }}
                                    </span>
                                    @if($overdue)
                                        <div class="text-[11px] font-bold text-danger mt-1">Payout overdue</div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <a href="{{ route('admin.returns.show', $rr->id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors {{ ($needsDecision || $isPayout) ? 'bg-primary hover:bg-primary-dark text-surface' : 'bg-surface border border-border-subtle text-primary-dark hover:border-primary' }}">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($returnRequests->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $returnRequests->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection