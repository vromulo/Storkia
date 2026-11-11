<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official E-Invoice #INV-{{ str_replace('SO-', '', $sellerOrder->seller_order_number) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #FFFFFF !important; }
        }
    </style>
</head>
<body class="bg-stone-100 text-stone-900 font-sans antialiased py-8 px-4">
@php
    $invoiceNo = 'INV-' . str_replace('SO-', '', $sellerOrder->seller_order_number);
    $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
    $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
    $extra = $sellerOrder->extra;
    $payMethod = $sellerOrder->effective_payment_method;
    $originalSub = (float) ($extra?->original_subtotal_amount ?: $sellerOrder->subtotal_amount);
    $storeDiscount = (float) ($extra?->store_voucher_discount ?? 0);
    $coinsDiscount = (float) ($extra?->coins_discount_amount ?? 0);
    $rawPlatformDiscount = (float) ($extra?->platform_voucher_discount ?? 0);
    $netSub = (float) $sellerOrder->subtotal_amount;

    $platformDiscount = 0.0;
    if ($extra && !empty($extra->platform_voucher_code)) {
        if ($originalSub > 0 && abs(round($originalSub - $storeDiscount - $rawPlatformDiscount - $coinsDiscount, 2) - round($netSub, 2)) < 0.02) {
            $platformDiscount = round($rawPlatformDiscount, 2);
        } else {
            $platformDiscount = max(0.0, round($rawPlatformDiscount - $coinsDiscount, 2));
        }
    }

    $shippingDiscount = (float) ($extra?->shipping_voucher_discount ?? 0);
    $originalShip = (float) ($extra?->original_shipping_fee ?: $sellerOrder->shipping_fee);
@endphp

<div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-lg border border-stone-200 p-8 sm:p-10 space-y-6">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-stone-200">
        <a href="{{ route('user.orders.show', $sellerOrder->id) }}" class="text-xs font-bold text-stone-600 hover:text-stone-900">
            &larr; Back to Order #{{ $sellerOrder->seller_order_number }}
        </a>
        <button type="button"
                onclick="window.print()"
                class="px-5 py-2.5 rounded-xl bg-[#5D3140] hover:bg-[#CF4173] text-white text-xs font-bold shadow-sm cursor-pointer">
            Print / Download Official PDF Invoice
        </button>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-stone-200 pb-6">
        <div>
            <span class="px-2.5 py-1 rounded-full bg-[#F6D8BD]/60 text-[#5D3140] text-[10px] font-extrabold uppercase tracking-wider">
                Storkia Marketplace Official E-Receipt
            </span>
            <h1 class="text-2xl font-serif font-extrabold text-[#5D3140] mt-2">Electronic Invoice</h1>
            <p class="text-xs text-stone-500 mt-0.5">Invoice Reference: <strong class="font-mono text-stone-800">{{ $invoiceNo }}</strong></p>
        </div>
        <div class="text-left sm:text-right text-xs space-y-1">
            <div><span class="text-stone-500">Seller Order #:</span> <strong class="font-mono">{{ $sellerOrder->seller_order_number }}</strong></div>
            <div><span class="text-stone-500">Order Placed:</span> <strong>{{ $sellerOrder->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</strong></div>
            @if($sellerOrder->completed_at)
                <div><span class="text-stone-500">Completed At:</span> <strong>{{ $sellerOrder->completed_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</strong></div>
            @endif
            <div>
                <span class="text-stone-500">Payment Method:</span>
                <strong class="uppercase text-[#CF4173]">{{ $payMethod }} ({{ strtoupper($sellerOrder->payment_status) }})</strong>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
        <div class="p-4 rounded-2xl bg-stone-50 border border-stone-200 space-y-1">
            <span class="text-[10px] font-extrabold uppercase text-stone-400 block">Sold &amp; Fulfilled By</span>
            <div class="font-bold text-sm text-stone-900">{{ $sellerOrder->sellerProfile->business_name ?? ($pickupSnap['business_name'] ?? 'Seller Store') }}</div>
            <div class="text-stone-600">{{ $pickupSnap['full_address'] ?? '' }}</div>
            <div class="text-stone-500 font-mono">{{ $pickupSnap['contact_no'] ?? '' }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-stone-50 border border-stone-200 space-y-1">
            <span class="text-[10px] font-extrabold uppercase text-stone-400 block">Billed &amp; Shipped To</span>
            <div class="font-bold text-sm text-stone-900">{{ $deliverySnap['recipient_name'] ?? ($sellerOrder->buyer?->full_name ?? 'Buyer') }}</div>
            <div class="text-stone-600">{{ $deliverySnap['full_address'] ?? '' }}</div>
            <div class="text-stone-500 font-mono">{{ $deliverySnap['phone_number'] ?? '' }}</div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b-2 border-stone-200 text-stone-500 uppercase text-[11px] font-bold">
                    <th class="py-3 px-2">Item Description</th>
                    <th class="py-3 px-2 text-center">Variant</th>
                    <th class="py-3 px-2 text-center">Qty</th>
                    <th class="py-3 px-2 text-right">Unit Price</th>
                    <th class="py-3 px-2 text-right">Line Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @foreach($sellerOrder->items as $item)
                    <tr>
                        <td class="py-3.5 px-2 font-bold text-stone-900">{{ $item->product_name }}</td>
                        <td class="py-3.5 px-2 text-center text-stone-600">
                            {{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) ?: 'Standard' }}
                        </td>
                        <td class="py-3.5 px-2 text-center font-mono font-bold">{{ $item->quantity }}</td>
                        <td class="py-3.5 px-2 text-right font-mono">₱{{ number_format((float) $item->discounted_unit_price, 2) }}</td>
                        <td class="py-3.5 px-2 text-right font-mono font-bold text-stone-900">₱{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="border-t-2 border-stone-200 pt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-6 text-xs">
        <div class="space-y-1 text-stone-500 max-w-xs">
            <div>Logistics Partner: <strong class="text-stone-800">{{ $sellerOrder->logisticsProfile->business_name ?? 'Standard Partner Logistics' }}</strong></div>
            <div>Tracking Number: <strong class="font-mono text-stone-800">{{ $sellerOrder->shipment->tracking_number ?? 'N/A' }}</strong></div>
            <div>Payment Ref: <strong class="font-mono text-stone-800">{{ $sellerOrder->payment->payment_reference ?? 'N/A' }}</strong></div>
        </div>

        <div class="w-full sm:w-72 space-y-1.5">
            <div class="flex justify-between text-stone-600">
                <span>Merchandise Subtotal:</span>
                <span class="font-mono font-semibold">₱{{ number_format($originalSub, 2) }}</span>
            </div>
            @if($storeDiscount > 0)
                <div class="flex justify-between text-emerald-700 font-semibold">
                    <span>Store / Bundle Discount:</span>
                    <span class="font-mono">-₱{{ number_format($storeDiscount, 2) }}</span>
                </div>
            @endif
            @if($platformDiscount > 0)
                <div class="flex justify-between text-purple-700 font-semibold">
                    <span>Platform Voucher:</span>
                    <span class="font-mono">-₱{{ number_format($platformDiscount, 2) }}</span>
                </div>
            @endif
            @if($coinsDiscount > 0)
                <div class="flex justify-between text-[#CF4173] font-semibold">
                    <span>Storkia Coins ({{ (int) ($extra?->coins_used ?? 0) }} Coins):</span>
                    <span class="font-mono">-₱{{ number_format($coinsDiscount, 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between text-stone-600">
                <span>Shipping Fee ({{ number_format((float) $sellerOrder->total_weight, 2) }} kg):</span>
                <span class="font-mono font-semibold">₱{{ number_format($originalShip, 2) }}</span>
            </div>
            @if($shippingDiscount > 0)
                <div class="flex justify-between text-emerald-700 font-semibold">
                    <span>Shipping Voucher:</span>
                    <span class="font-mono">-₱{{ number_format($shippingDiscount, 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between pt-2 border-t border-stone-300 text-sm font-extrabold text-[#5D3140]">
                <span>Total Amount ({{ $payMethod }}):</span>
                <span class="font-mono text-base text-[#CF4173]">₱{{ number_format((float) $sellerOrder->total_amount, 2) }}</span>
            </div>
        </div>
    </div>
</div>

@if(!empty($autoPrint))
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 350);
    });
</script>
@endif
</body>
</html>