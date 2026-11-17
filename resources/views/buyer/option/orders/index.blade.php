@extends('buyer.account-settings', [
    'pageTitle' => 'My Orders',
    'pageSubtitle' => 'Track your purchases (Last-In, First-Out), confirm deliveries, rate completed orders, or Buy Again.'
])

@section('option-content')
<div x-data="{
        proofLightboxOpen: false,
        proofData: {
            url: '',
            orderNo: '',
            trackingNo: '',
            recipient: '',
            relation: '',
            rider: '',
            capturedAt: '',
            notes: ''
        },
        cancelFlaggedModalOpen: false,
        cancelFlaggedActionUrl: '',
        cancelFlaggedOrderNo: '',
        openProofLightbox(payload) {
            this.proofData = payload;
            this.proofLightboxOpen = true;
        },
        openCancelFlaggedConfirm(actionUrl, orderNo) {
            this.cancelFlaggedActionUrl = actionUrl;
            this.cancelFlaggedOrderNo = orderNo;
            this.cancelFlaggedModalOpen = true;
        }
     }"
     class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

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
                   class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1.5 border cursor-pointer {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-primary text-surface border-primary shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusTab === $key || ($key === 'all' && !$statusTab) ? 'bg-surface/20 text-surface' : ($key === 'to_rate' && ($tab['count'] ?? 0) > 0 ? 'bg-brand-light text-primary-dark font-extrabold' : 'bg-surface-subtle text-text-main') }}">
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
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary-dark hover:bg-primary text-surface text-xs font-bold transition-colors cursor-pointer shrink-0">
                Search
            </button>
        </form>
    </div>

    @if($sellerOrders->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-brand-light/30 text-primary flex items-center justify-center mx-auto border border-primary/20">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-primary-dark">No orders found in this section</h3>
            <p class="text-xs text-text-muted">Your newly placed orders appear at the top in Last-In, First-Out (LIFO) sequence.</p>
            <a href="{{ route('home') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-surface text-xs font-bold hover:bg-primary-dark transition-colors cursor-pointer">
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
                    $cardProof = $so->shipment?->latestDeliveryProof ?? $so->shipment?->deliveryProofs?->first();
                    $badgeColor = match($so->status) {
                        'completed'                 => $isRated ? 'bg-success/15 text-success border-success/25' : 'bg-brand-light/70 text-primary-dark border-primary/30',
                        'delivered'                 => 'bg-primary/10 text-primary-dark border-primary/25',
                        'pending_buyer_response'    => 'bg-brand-light/80 text-primary-dark border-primary/30',
                        'out_for_delivery', 'picked_up', 'at_logistics_hub' => 'bg-primary/10 text-primary border-primary/20',
                        'rejected', 'cancelled', 'returned_to_seller', 'disputed', 'delivery_failed' => 'bg-danger/10 text-danger border-danger/25',
                        default                     => 'bg-brand-light/50 text-primary-dark border-border-subtle',
                    };
                @endphp

                <div x-data="{ showAllItems: false }"
                     class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden hover:border-primary/40 transition-colors w-full min-w-0">
                    <div class="p-4 sm:px-5 bg-surface-subtle/60 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap min-w-0">
                            <span class="font-bold text-sm text-primary-dark truncate">
                                {{ $so->sellerProfile->business_name ?? 'Seller Store' }}
                            </span>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark">
                                #{{ $so->seller_order_number }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $payMethod === 'GCASH' || $payMethod === 'WALLET' ? 'bg-primary/15 text-primary-dark border border-primary/25' : 'bg-surface border border-border-subtle text-text-main' }}">
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
                        <div class="p-4 sm:px-5 bg-brand-light/35 border-b border-primary/30 flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs">
                            <div class="space-y-1 min-w-0">
                                <p class="font-extrabold text-primary-dark">
                                    Action Required: Seller Reported Your GCash Payment Details as Suspicious
                                </p>
                                <p class="text-text-main">
                                    Seller Reason: <em class="text-danger">"{{ $subm?->suspicious_reason ?? 'Payment details could not be verified.' }}"</em>
                                    @if($subm)
                                        &bull; Previous GCash Ref: <strong class="font-mono">#{{ $subm->reference_number }}</strong> (Sender: <strong class="font-mono">{{ $subm->sender_gcash_number }}</strong>)
                                    @endif
                                </p>
                                <p class="text-[11px] text-text-muted font-semibold">
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
                                <button type="button"
                                        @click="openCancelFlaggedConfirm('{{ route('user.orders.cancel-flagged-payment', $so->id) }}', '{{ $so->seller_order_number }}')"
                                        class="px-3.5 py-2 rounded-xl bg-surface border border-danger/30 hover:bg-danger/10 text-danger text-xs font-bold transition-colors cursor-pointer">
                                    Cancel Order Immediately
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="divide-y divide-border-subtle/50">
                        @foreach($so->items as $itemIdx => $item)
                            <div x-show="{{ $itemIdx }} < 2 || showAllItems"
                                 class="p-4 sm:px-5 flex items-center gap-3.5 min-w-0">
                                <div class="w-14 h-14 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                    @if($item->product_image)
                                        <img src="{{ asset('storage/' . ltrim($item->product_image, '/')) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
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
                                    <p class="text-[11px] text-text-muted mt-0.5">Qty: {{ $item->quantity }} &times; &#8369;{{ number_format((float) $item->discounted_unit_price, 2) }}</p>
                                </div>
                                <div class="text-right text-xs sm:text-sm font-bold text-price shrink-0">
                                    &#8369;{{ number_format((float) $item->line_total, 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($so->items->count() > 2)
                        <div class="px-5 py-2 bg-surface-subtle/20 border-t border-border-subtle/50 text-center">
                            <button type="button"
                                    @click="showAllItems = !showAllItems"
                                    class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                    x-text="showAllItems ? 'See Less Items' : 'See All {{ $so->items->count() }} Items (+' + ({{ $so->items->count() }} - 2) + ' more)'"></button>
                        </div>
                    @endif

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
                                <div class="text-primary font-bold text-[11px]">
                                    Your Rating: {{ number_format((float) $so->serviceRating->overall_average_rating, 1) }} / 5.0 &#9733;
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                            <span class="text-sm font-extrabold text-price mr-1">
                                Total ({{ $payMethod }}): &#8369;{{ number_format((float) $so->total_amount, 2) }}
                            </span>

                            @if($cardProof)
                                <button type="button"
                                        @click.stop="openProofLightbox({
                                            url: '{{ route('user.orders.proof-image', $cardProof->id) }}',
                                            orderNo: '{{ $so->seller_order_number }}',
                                            trackingNo: '{{ $so->shipment->tracking_number ?? 'N/A' }}',
                                            recipient: '{{ addslashes($cardProof->recipient_name ?: 'Recipient') }}',
                                            relation: '{{ addslashes($cardProof->recipient_relation ?: '') }}',
                                            rider: '{{ addslashes($cardProof->rider?->full_name ?? 'Assigned Rider') }}',
                                            capturedAt: '{{ ($cardProof->captured_at ?? $so->delivered_at)?->format('M d, Y h:i A') ?? '' }}',
                                            notes: '{{ addslashes($cardProof->notes ?? '') }}'
                                        })"
                                        class="px-3.5 py-2 rounded-xl bg-surface border border-primary/40 hover:bg-primary/10 text-primary-dark text-xs font-bold transition-colors cursor-pointer">
                                    Inspect Delivery Photo
                                </button>
                            @endif

                            @if($so->status === 'delivered' && !$so->has_unresolved_dispute)
                                <form action="{{ route('user.orders.confirm-receipt', $so->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition-colors cursor-pointer">
                                        Order Received
                                    </button>
                                </form>
                            @elseif($so->status === 'completed' && !$isRated)
                                <a href="{{ route('user.orders.rate.show', $so->id) }}"
                                   class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-extrabold shadow-xs transition-colors cursor-pointer">
                                    &#9733; To Rate
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
                               class="px-4 py-2 rounded-xl bg-primary-dark hover:bg-primary text-surface text-xs font-bold transition-colors cursor-pointer">
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

    <template x-teleport="body">
        <div x-show="cancelFlaggedModalOpen"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="cancelFlaggedModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-border-subtle text-center space-y-4">
                <h3 class="text-base font-bold text-primary-dark">Cancel Order #<span x-text="cancelFlaggedOrderNo"></span>?</h3>
                <p class="text-xs text-text-muted leading-relaxed">
                    Cancel this order immediately? Reserved stock and any applied vouchers or Storkia Coins will be restored.
                </p>
                <form :action="cancelFlaggedActionUrl" method="POST" class="flex gap-2.5 pt-2 m-0">
                    @csrf
                    <button type="button" @click="cancelFlaggedModalOpen = false" class="flex-1 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs font-bold transition-colors cursor-pointer">
                        Keep Order
                    </button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-danger hover:opacity-90 text-white text-xs font-bold transition-opacity cursor-pointer">
                        Cancel Order
                    </button>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="proofLightboxOpen"
             x-cloak
             @keydown.escape.window="proofLightboxOpen = false"
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="proofLightboxOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-base font-bold text-primary-dark">Proof of Delivery</h3>
                        <p class="text-xs text-text-muted" x-text="'Order #' + proofData.orderNo + ' • Tracking #' + proofData.trackingNo"></p>
                    </div>
                    <button type="button" @click="proofLightboxOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer text-lg leading-none">&times;</button>
                </div>

                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[55vh] p-2">
                    <img :src="proofData.url" alt="Proof of Delivery" class="max-h-[52vh] w-auto object-contain rounded-xl shadow-xs">
                </div>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-text-muted block">Received By:</span>
                        <strong class="text-text-main" x-text="proofData.recipient + (proofData.relation ? ' (' + proofData.relation + ')' : '')"></strong>
                    </div>
                    <div>
                        <span class="text-text-muted block">Delivered At:</span>
                        <strong class="text-text-main" x-text="proofData.capturedAt || 'Recorded'"></strong>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="text-text-muted block">Delivered By Rider:</span>
                        <strong class="text-text-main" x-text="proofData.rider"></strong>
                    </div>
                    <template x-if="proofData.notes">
                        <div class="sm:col-span-2">
                            <span class="text-text-muted block">Rider Handover Notes:</span>
                            <p class="text-text-main italic" x-text="proofData.notes"></p>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-border-subtle text-xs">
                    <a :href="proofData.url" target="_blank" class="font-bold text-primary hover:underline cursor-pointer">
                        Open Full Image in New Tab &nearr;
                    </a>
                    <button type="button" @click="proofLightboxOpen = false" class="px-5 py-2 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection