@extends('layouts.seller', ['title' => 'Storkia - Seller Orders'])

@section('content')
@php
    $queueSort = ($queueSort ?? 'oldest') === 'newest' ? 'newest' : 'oldest';
    $isQueueNewestFirst = $queueSort === 'newest';
@endphp

<div class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-primary-dark">Order Management</h1>
                <a href="{{ route('seller.vouchers.index') }}"
                   class="px-3.5 py-1.5 rounded-xl bg-brand-light/50 hover:bg-brand-light text-primary-dark border border-border-subtle text-xs font-bold transition-colors cursor-pointer">
                    Manage Store Vouchers
                </a>
            </div>
            <p class="text-sm text-text-muted mt-1">
                @if($isQueueNewestFirst)
                    Active orders are shown at the top in Last-In, First-Out (LIFO) order, newest first; once completed, rejected, or cancelled, they automatically move to the bottom.
                @else
                    Active orders are prioritized at the top in First-In, First-Out (FIFO) order; once completed, rejected, or cancelled, they automatically move to the bottom sorted by most recent.
                @endif
            </p>
        </div>

        <form method="GET" action="{{ route('seller.orders.index') }}" class="flex items-center gap-2">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            @if($isQueueNewestFirst)
                <input type="hidden" name="sort" value="newest">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Order #, buyer, product, tracking, ref #..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-primary text-surface text-xs font-bold transition-colors cursor-pointer">
                Search
            </button>
        </form>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $tabs = [
                    'all'        => ['label' => 'All Orders', 'count' => $counts['all']],
                    'new'        => ['label' => 'New Orders', 'count' => $counts['new']],
                    'pending'    => ['label' => 'Pending', 'count' => $counts['pending'] ?? 0],
                    'to_process' => ['label' => 'To Process', 'count' => $counts['to_process']],
                    'to_ship'    => ['label' => 'Ready / To Ship', 'count' => $counts['to_ship']],
                    'shipped'    => ['label' => 'In Transit', 'count' => $counts['shipped']],
                    'delivered'  => ['label' => 'Delivered / Completed', 'count' => $counts['delivered']],
                    'cancelled'  => ['label' => 'Cancelled / Disputed', 'count' => $counts['cancelled']],
                ];
            @endphp

            @foreach($tabs as $key => $tab)
                <a href="{{ route('seller.orders.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search, 'sort' => $isQueueNewestFirst ? 'newest' : null])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : ($key === 'pending' && ($tab['count'] ?? 0) > 0 ? 'bg-brand-light text-primary-dark font-extrabold' : 'bg-surface-subtle text-text-main') }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        <div class="relative flex items-center gap-2"
             x-data="{
                sortOpen: false,
                currentSort: @js($queueSort),
                applySort(val) {
                    this.currentSort = val;
                    this.sortOpen = false;
                    const url = new URL(window.location.href);
                    if (val === 'newest') {
                        url.searchParams.set('sort', 'newest');
                    } else {
                        url.searchParams.delete('sort');
                    }
                    window.location.href = url.toString();
                }
             }"
             @click.away="sortOpen = false">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Sort by</span>
            <button type="button"
                    @click="sortOpen = !sortOpen"
                    class="inline-flex items-center justify-between gap-2.5 px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface text-xs font-bold text-text-main transition-colors cursor-pointer min-w-[160px]">
                <span x-text="currentSort === 'newest' ? 'Newest to Oldest' : 'Oldest to Newest'"></span>
                <svg class="w-4 h-4 text-text-muted transition-transform" :class="sortOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="sortOpen"
                 x-cloak
                 x-transition
                 class="absolute right-0 top-full mt-1.5 w-48 bg-surface border border-border-subtle rounded-2xl shadow-xl py-1 z-40">
                <button type="button"
                        @click="applySort('newest')"
                        class="w-full px-3.5 py-2 text-left text-xs font-bold hover:bg-brand-light/30 flex items-center justify-between cursor-pointer"
                        :class="currentSort === 'newest' ? 'text-primary bg-primary/5' : 'text-text-main'">
                    <span>Newest to Oldest</span>
                    <span x-show="currentSort === 'newest'">&#10003;</span>
                </button>
                <button type="button"
                        @click="applySort('oldest')"
                        class="w-full px-3.5 py-2 text-left text-xs font-bold hover:bg-brand-light/30 flex items-center justify-between cursor-pointer"
                        :class="currentSort === 'oldest' ? 'text-primary bg-primary/5' : 'text-text-main'">
                    <span>Oldest to Newest</span>
                    <span x-show="currentSort === 'oldest'">&#10003;</span>
                </button>
            </div>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($orders->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No seller orders found</h3>
                <p class="text-xs text-text-muted">
                    @if($isQueueNewestFirst)
                        Active orders appear at the top in Last-In, First-Out (LIFO) sequence, while completed or cancelled orders move to the bottom.
                    @else
                        Active orders appear at the top in First-In, First-Out (FIFO) sequence, while completed or cancelled orders move to the bottom.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Seller Order</th>
                            <th class="py-3.5 px-4">Buyer &amp; Destination</th>
                            <th class="py-3.5 px-4">Items &amp; Note</th>
                            <th class="py-3.5 px-4">Payment &amp; Total</th>
                            <th class="py-3.5 px-4">Status &amp; Logistics</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @php $activePosition = 0; @endphp
                        @foreach($orders as $so)
                            @php
                                $isTerminal = $so->isTerminalStatus();
                                if (! $isTerminal) {
                                    $activePosition++;
                                }
                                $deliv = $so->delivery_address_snapshot ?? [];
                                $payMethod = $so->effective_payment_method;
                                $subm = $so->paymentSubmission;
                                $extra = $so->extra;
                                $badgeClass = match($so->status) {
                                    'completed' => 'bg-success/15 text-success border-success/25',
                                    'delivered' => 'bg-primary/10 text-primary-dark border-primary/25',
                                    'pending_buyer_response' => 'bg-brand-light/80 text-primary-dark border-primary/30',
                                    'rejected', 'cancelled', 'returned_to_seller', 'disputed', 'delivery_failed' => 'bg-danger/10 text-danger border-danger/25',
                                    default => 'bg-brand-light/50 text-primary-dark border-border-subtle',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors {{ $isTerminal ? 'bg-surface-subtle/20 opacity-85' : '' }}">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono font-bold text-sm text-primary-dark">{{ $so->seller_order_number }}</span>
                                        @if(! $isTerminal)
                                            <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 text-[10px] font-extrabold">
                                                Active Queue
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full bg-surface-subtle text-text-muted border border-border-subtle text-[10px] font-bold">
                                                Closed
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-text-muted mt-0.5">Placed: {{ $so->created_at?->format('M d, Y h:i A') }}</div>
                                    @if($isTerminal)
                                        <div class="text-[11px] text-text-muted">
                                            Closed: {{ ($so->completed_at ?? $so->cancelled_at ?? $so->rejected_at ?? $so->updated_at)?->format('M d, Y h:i A') }}
                                        </div>
                                    @elseif($so->status === 'pending_seller_acceptance' && $so->acceptance_deadline_at)
                                        <div class="mt-1 text-[11px] font-bold text-danger">
                                            Accept by: {{ $so->acceptance_deadline_at->format('M d, h:i A') }}
                                        </div>
                                    @elseif($so->status === 'pending_buyer_response')
                                        <div class="mt-1 text-[11px] font-bold text-primary-dark">
                                            Awaiting Buyer COD Switch / Cancel
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $deliv['recipient_name'] ?? $so->buyer?->full_name }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $deliv['municipality'] ?? '' }}, {{ $deliv['province'] ?? '' }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $deliv['phone_number'] ?? '' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top" x-data="{ showAllItems: false }">
                                    <div class="space-y-1">
                                        @foreach($so->items as $iIdx => $item)
                                            <div x-show="{{ $iIdx }} < 2 || showAllItems"
                                                 class="text-text-main font-medium truncate max-w-[220px]">
                                                {{ $item->quantity }}&times; {{ $item->product_name }}
                                            </div>
                                        @endforeach
                                        @if($so->items->count() > 2)
                                            <button type="button"
                                                    @click="showAllItems = !showAllItems"
                                                    class="text-[11px] text-primary font-bold hover:underline cursor-pointer"
                                                    x-text="showAllItems ? 'See less items' : '+{{ $so->items->count() - 2 }} more item(s) (See more)'"></button>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-text-muted mt-1">Weight: {{ number_format((float) $so->total_weight, 2) }} kg</div>
                                    @if($extra && $extra->buyer_note)
                                        <div class="mt-1 px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark text-[10px] font-semibold truncate max-w-[220px]" title="{{ $extra->buyer_note }}">
                                            Note: "{{ $extra->buyer_note }}"
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $payMethod === 'GCASH' || $payMethod === 'WALLET' ? 'bg-primary/15 text-primary-dark border border-primary/25' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                                            {{ $payMethod }}
                                        </span>
                                        <span class="text-[10px] uppercase font-bold text-text-muted">({{ $so->payment_status }})</span>
                                    </div>
                                    <div>Net Subtotal: <strong class="text-text-main">&#8369;{{ number_format((float) $so->subtotal_amount, 2) }}</strong></div>
                                    <div>Shipping: <strong class="text-text-main">&#8369;{{ number_format((float) $so->shipping_fee, 2) }}</strong></div>
                                    <div class="text-price font-bold">Total: &#8369;{{ number_format((float) $so->total_amount, 2) }}</div>
                                    @if($subm)
                                        <div class="text-[10px] font-mono text-primary-dark truncate max-w-[170px]">
                                            Ref: {{ $subm->reference_number }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                                        {{ $so->status_label }}
                                    </span>
                                    @if($so->logisticsProfile)
                                        <div class="text-[11px] text-text-main font-semibold mt-1.5">
                                            Hub: {{ $so->logisticsProfile->business_name }}
                                        </div>
                                    @endif
                                    @if($so->shipment)
                                        <div class="font-mono text-[11px] text-primary-dark font-bold">
                                            {{ $so->shipment->tracking_number }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <a href="{{ route('seller.orders.show', $so->id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                        Manage Order
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection