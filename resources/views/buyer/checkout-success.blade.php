@extends('layouts.app', ['title' => 'Order Confirmed - Storkia'])

@section('content')
<div class="min-h-screen bg-surface-subtle/40 pb-16 pt-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <nav aria-label="Checkout Stepper" class="w-full bg-surface border border-border-subtle rounded-2xl p-4 sm:p-5 shadow-xs select-none">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary/15 text-primary text-xs font-bold flex items-center justify-center">✓</span>
                    <span class="text-sm font-sans tracking-tight">Cart</span>
                </div>
                <div class="flex-1 h-[2px] bg-primary mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary/15 text-primary text-xs font-bold flex items-center justify-center">✓</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Place Order</span>
                </div>
                <div class="flex-1 h-[2px] bg-primary mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary/15 text-primary text-xs font-bold flex items-center justify-center">✓</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Cash on Delivery</span>
                </div>
                <div class="flex-1 h-[2px] bg-primary mx-3 sm:mx-6 rounded-full"></div>

                <div class="flex items-center gap-2 text-primary font-bold">
                    <span class="w-7 h-7 rounded-full bg-primary text-surface text-xs font-bold flex items-center justify-center shadow-xs">4</span>
                    <span class="text-sm font-sans tracking-tight hidden sm:inline">Order Complete</span>
                </div>
            </div>
        </nav>

        <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-10 shadow-xs text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-green-100 text-green-700 flex items-center justify-center mx-auto border border-green-200">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-primary-dark">Thank You! Your Order Has Been Placed</h1>
            <p class="text-sm text-text-muted max-w-xl mx-auto">
                Parent Order Reference: <strong class="text-text-main font-mono">{{ $order->order_number }}</strong>.
                Please prepare <strong class="text-primary">₱{{ number_format((float) $order->total_amount, 2) }}</strong> in cash upon delivery.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a href="{{ route('user.orders') }}" class="px-6 py-3 rounded-xl bg-primary hover:bg-primary-dark text-surface text-xs sm:text-sm font-bold shadow-xs transition-colors">
                    View & Track My Orders
                </a>
                <a href="{{ route('home') }}" class="px-6 py-3 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs sm:text-sm font-bold transition-colors">
                    Continue Shopping
                </a>
            </div>
        </div>

        <div class="space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-text-muted px-1">
                Individual Seller Orders ({{ $order->sellerOrders->count() }})
            </h2>

            @foreach($order->sellerOrders as $sellerOrder)
                <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                        <div>
                            <span class="text-xs font-mono font-bold text-primary-dark bg-brand-light/40 px-2.5 py-1 rounded-lg">
                                {{ $sellerOrder->seller_order_number }}
                            </span>
                            <span class="ml-2 font-bold text-sm text-text-main">
                                {{ $sellerOrder->sellerProfile->business_name ?? 'Seller Store' }}
                            </span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">
                            {{ $sellerOrder->status_label }}
                        </span>
                    </div>

                    <div class="divide-y divide-border-subtle/50">
                        @foreach($sellerOrder->items as $item)
                            <div class="py-2.5 flex items-center justify-between gap-4 text-xs sm:text-sm">
                                <div>
                                    <p class="font-semibold text-text-main">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-text-muted">&times; {{ $item->quantity }}</span>
                                    <span class="font-bold text-text-main ml-2">₱{{ number_format((float) $item->line_total, 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-3 border-t border-border-subtle flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="text-text-muted">
                            Seller Acceptance Deadline: <strong>{{ $sellerOrder->acceptance_deadline_at?->format('M d, Y h:i A') }}</strong>
                        </span>
                        <div class="font-bold text-sm text-primary-dark">
                            Subtotal: ₱{{ number_format((float) $sellerOrder->subtotal_amount, 2) }} + Shipping: ₱{{ number_format((float) $sellerOrder->shipping_fee, 2) }} =
                            <span class="text-primary">₱{{ number_format((float) $sellerOrder->total_amount, 2) }} (COD)</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>
@endsection