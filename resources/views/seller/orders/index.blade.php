@extends('layouts.seller', ['title' => 'Storkia - Seller Orders'])

@section('content')
<div class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-primary-dark">Order Management</h1>
                <a href="{{ route('seller.vouchers.index') }}"
                   class="px-3.5 py-1.5 rounded-xl bg-brand-light/50 hover:bg-brand-light text-primary-dark border border-border-subtle text-xs font-bold transition-colors">
                    Manage Store Vouchers
                </a>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Review incoming customer orders (FIFO), verify GCash online payments, assign an approved local Logistics hub, and track deliveries.
            </p>
        </div>

        <form method="GET" action="{{ route('seller.orders.index') }}" class="flex items-center gap-2">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Order #, buyer, product, tracking, ref #..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
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
            <a href="{{ route('seller.orders.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : ($key === 'pending' && ($tab['count'] ?? 0) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-surface-subtle text-text-main') }}">
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($orders->isEmpty())
            <div class="py-16 px-6 text-center space-y-2">
                <h3 class="text-base font-bold text-text-main">No seller orders found</h3>
                <p class="text-xs text-text-muted">Orders matching the selected filter will appear here in First-In, First-Out (FIFO) sequence.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Seller Order</th>
                            <th class="py-3.5 px-4">Buyer & Destination</th>
                            <th class="py-3.5 px-4">Items & Note</th>
                            <th class="py-3.5 px-4">Payment & Total</th>
                            <th class="py-3.5 px-4">Status & Logistics</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach($orders as $so)
                            @php
                                $deliv = $so->delivery_address_snapshot ?? [];
                                $payMethod = $so->effective_payment_method;
                                $subm = $so->paymentSubmission;
                                $extra = $so->extra;
                                $badgeClass = match($so->status) {
                                    'completed' => 'bg-green-100 text-green-700 border-green-200',
                                    'delivered' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    'pending_buyer_response' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    'rejected', 'cancelled', 'returned_to_seller', 'disputed', 'delivery_failed' => 'bg-red-100 text-red-700 border-red-200',
                                    default => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-sm text-primary-dark">{{ $so->seller_order_number }}</div>
                                    <div class="text-text-muted mt-0.5">Placed: {{ $so->created_at?->format('M d, Y h:i A') }}</div>
                                    @if($so->status === 'pending_seller_acceptance' && $so->acceptance_deadline_at)
                                        <div class="mt-1 text-[11px] font-bold text-danger">
                                            Accept by: {{ $so->acceptance_deadline_at->format('M d, h:i A') }}
                                        </div>
                                    @elseif($so->status === 'pending_buyer_response')
                                        <div class="mt-1 text-[11px] font-bold text-amber-700">
                                            Awaiting Buyer COD Switch / Cancel
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $deliv['recipient_name'] ?? $so->buyer?->full_name }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $deliv['municipality'] ?? '' }}, {{ $deliv['province'] ?? '' }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $deliv['phone_number'] ?? '' }}</div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="space-y-1">
                                        @foreach($so->items->take(2) as $item)
                                            <div class="text-text-main font-medium truncate max-w-[220px]">
                                                {{ $item->quantity }}&times; {{ $item->product_name }}
                                            </div>
                                        @endforeach
                                        @if($so->items->count() > 2)
                                            <div class="text-[11px] text-primary font-bold">+{{ $so->items->count() - 2 }} more item(s)</div>
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
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                                            {{ $payMethod }}
                                        </span>
                                        <span class="text-[10px] uppercase font-bold text-text-muted">({{ $so->payment_status }})</span>
                                    </div>
                                    <div>Net Subtotal: <strong class="text-text-main">₱{{ number_format((float) $so->subtotal_amount, 2) }}</strong></div>
                                    <div>Shipping: <strong class="text-text-main">₱{{ number_format((float) $so->shipping_fee, 2) }}</strong></div>
                                    <div class="text-primary font-bold">Total: ₱{{ number_format((float) $so->total_amount, 2) }}</div>
                                    @if($subm)
                                        <div class="text-[10px] font-mono text-blue-800 truncate max-w-[170px]">
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
                                       class="inline-flex items-center px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs font-bold shadow-xs transition-colors">
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