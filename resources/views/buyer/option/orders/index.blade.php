@extends('buyer.account-settings', [
    'pageTitle' => 'My Orders',
    'pageSubtitle' => 'Track your purchases (Last-In, First-Out), confirm deliveries, rate completed orders, or Buy Again.'
])

@section('option-content')
<div class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

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

    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 w-full min-w-0">
        <div class="flex flex-wrap items-center gap-1.5 min-w-0">
            @php
                $tabs = [
                    'all'        => ['label' => 'All', 'count' => $counts['all'] ?? 0],
                    'unpaid'     => ['label' => 'Unpaid / Action Needed', 'count' => $counts['unpaid'] ?? 0],
                    'processing' => ['label' => 'Processing', 'count' => $counts['processing'] ?? 0],
                    'shipped'    => ['label' => 'Shipped', 'count' => $counts['shipped'] ?? 0],
                    'to_confirm' => ['label' => 'To Confirm', 'count' => $counts['to_confirm'] ?? 0],
                    'to_rate'    => ['label' => 'To Rate', 'count' => $counts['to_rate'] ?? 0],
                    'completed'  => ['label' => 'Completed', 'count' => $counts['completed'] ?? ($counts['review'] ?? 0)],
                    'returns'    => ['label' => 'Cancelled / Disputed', 'count' => $counts['returns'] ?? 0],
                ];
            @endphp

            @foreach($tabs as $key => $tab)
                <a href="{{ route('user.orders', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1.5 border {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary text-surface border-primary shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : ($key === 'to_rate' && ($tab['count'] ?? 0) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-surface-subtle text-text-main') }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('user.orders') }}" class="flex items-center gap-2 w-full sm:w-auto shrink-0">
            @if($statusTab && $statusTab !== 'all')
                <input type="hidden" name="status" value="{{ $statusTab }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Order #, product, store..."
                   class="flex-1 sm:w-52 px-3.5 py-2 rounded-xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary min-w-0">
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-text-main text-surface text-xs font-bold hover:bg-dark-grey transition-colors cursor-pointer shrink-0">
                Search
            </button>
        </form>
    </div>

    @if($sellerOrders->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto border border-border-subtle">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-text-main">No orders found in this section</h3>
            <p class="text-xs text-text-muted">Your newly placed orders appear at the top in Last-In, First-Out (LIFO) sequence.</p>
            <a href="{{ route('home') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-surface text-xs font-bold hover:bg-primary-dark transition-colors">
                Browse Products
            </a>
        </div>
    @else
        <div class="space-y-4 w-full min-w-0">
            @foreach($sellerOrders as $so)
                @php
                    $payMethod = $so->effective_payment_method;
                    $subm = $so->paymentSubmission;
                    $extra = $so->extra;
                    $isRated = $so->isRatedByBuyer();
                    $badgeColor = match($so->status) {
                        'completed'                 => $isRated ? 'bg-green-100 text-green-700 border-green-200' : 'bg-amber-100 text-amber-800 border-amber-300',
                        'delivered'                 => 'bg-blue-100 text-blue-700 border-blue-200',
                        'pending_buyer_response'    => 'bg-amber-100 text-amber-800 border-amber-300',
                        'out_for_delivery', 'picked_up', 'at_logistics_hub' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                        'rejected', 'cancelled', 'returned_to_seller', 'disputed', 'delivery_failed' => 'bg-red-100 text-red-700 border-red-200',
                        default                     => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    };
                @endphp
                <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden hover:border-primary/40 transition-colors w-full min-w-0">
                    <div class="p-4 sm:px-5 bg-surface-subtle/60 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap min-w-0">
                            <span class="font-bold text-sm text-primary-dark truncate">
                                {{ $so->sellerProfile->business_name ?? 'Seller Store' }}
                            </span>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark">
                                #{{ $so->seller_order_number }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $payMethod === 'GCASH' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-surface border border-border-subtle text-text-main' }}">
                                {{ $payMethod }}
                            </span>
                            <span class="text-[11px] text-text-muted">
                                Parent: {{ $so->order->order_number ?? 'N/A' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badgeColor }}">
                                {{ $so->status_label }}
                            </span>
                        </div>
                    </div>

                    @if($so->status === 'pending_buyer_response')
                        <div class="p-4 sm:px-5 bg-amber-50 border-b border-amber-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs">
                            <div class="space-y-1 min-w-0">
                                <p class="font-extrabold text-amber-950">
                                    Action Required: Seller Reported Your GCash Payment Details as Suspicious
                                </p>
                                <p class="text-amber-900">
                                    Seller Reason: <em>"{{ $subm?->suspicious_reason ?? 'Payment details could not be verified.' }}"</em>
                                    @if($subm)
                                        &bull; Previous GCash Ref: <strong class="font-mono">#{{ $subm->reference_number }}</strong> (Sender: <strong class="font-mono">{{ $subm->sender_gcash_number }}</strong>)
                                    @endif
                                </p>
                                <p class="text-[11px] text-amber-800 font-semibold">
                                    Continuing this transaction will switch your payment method to <strong>Cash on Delivery (COD)</strong>. Or you may cancel the order immediately.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <form action="{{ route('user.orders.switch-to-cod', $so->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                        Yes, Switch to COD &amp; Continue
                                    </button>
                                </form>

                                <form action="{{ route('user.orders.cancel-flagged-payment', $so->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Cancel this order immediately? Reserved stock and any applied vouchers will be restored.');"
                                      class="m-0">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-white border border-red-300 hover:bg-red-50 text-danger text-xs font-bold transition-colors cursor-pointer">
                                        Cancel Order Immediately
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="divide-y divide-border-subtle/50">
                        @foreach($so->items as $item)
                            <div class="p-4 sm:px-5 flex items-center gap-3.5 min-w-0">
                                <div class="w-14 h-14 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                    @if($item->product_image)
                                        <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                                    @else
                                        <svg class="w-5 h-5 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-[11px] text-text-muted truncate">
                                            {{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}
                                        </p>
                                    @endif
                                    <p class="text-[11px] text-text-muted mt-0.5">Qty: {{ $item->quantity }} &times; ₱{{ number_format((float) $item->discounted_unit_price, 2) }}</p>
                                </div>

                                <div class="text-right text-xs sm:text-sm font-bold text-text-main shrink-0">
                                    ₱{{ number_format((float) $item->line_total, 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-4 sm:px-5 bg-surface-subtle/30 border-t border-border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-w-0">
                        <div class="text-xs text-text-muted space-y-0.5 min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span>Ordered {{ $so->created_at?->format('M d, Y h:i A') }}</span>
                                <span>&bull;</span>
                                <span>{{ $payMethod }} Status: <strong class="uppercase text-text-main">{{ $so->payment_status }}</strong></span>
                            </div>
                            @if($so->shipment)
                                <div class="truncate">
                                    Tracking #: <span class="font-mono font-bold text-primary-dark">{{ $so->shipment->tracking_number }}</span>
                                    ({{ $so->logisticsProfile->business_name ?? 'Logistics Hub' }})
                                </div>
                            @endif
                            @if($so->serviceRating)
                                <div class="text-amber-600 font-bold text-[11px]">
                                    Your Rating: {{ number_format((float) $so->serviceRating->overall_average_rating, 1) }} / 5.0 ★
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                            <span class="text-sm font-extrabold text-primary mr-1">
                                Total ({{ $payMethod }}): ₱{{ number_format((float) $so->total_amount, 2) }}
                            </span>

                            @if($so->status === 'delivered' && !$so->has_unresolved_dispute)
                                <form action="{{ route('user.orders.confirm-receipt', $so->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold transition-colors cursor-pointer">
                                        Order Received
                                    </button>
                                </form>
                            @elseif($so->status === 'completed' && !$isRated)
                                <a href="{{ route('user.orders.rate.show', $so->id) }}"
                                   class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold shadow-xs transition-colors">
                                    ★ To Rate
                                </a>
                            @elseif($so->status === 'completed' && $isRated)
                                <form action="{{ route('user.orders.buy-again', $so->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                        Buy Again
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('user.orders.show', $so->id) }}"
                               class="px-4 py-2 rounded-xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold transition-colors">
                                Track &amp; Details
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $sellerOrders->links() }}
        </div>
    @endif

</div>
@endsection