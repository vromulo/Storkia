<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slip / Product Manifest — {{ $shipment->tracking_number }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .manifest-sheet {
                page-break-after: always;
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                margin: 0 auto !important;
            }
            .manifest-sheet:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body class="bg-neutral-100 text-neutral-900 font-sans antialiased py-8 px-4">

    @php
        $pickup = $shipment->pickup_address_snapshot ?? [];
        $deliv = $shipment->delivery_address_snapshot ?? [];
    @endphp

    <div class="max-w-4xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl shadow-sm border border-neutral-200 no-print">
        <div>
            <h1 class="text-sm font-bold text-neutral-900">Packing Slip / Product Manifest</h1>
            <p class="text-xs text-neutral-500">
                Seller Order: <strong class="font-mono text-neutral-800">{{ $sellerOrder->seller_order_number }}</strong> &bull;
                Shipment: <strong class="font-mono text-neutral-800">{{ $shipment->tracking_number }}</strong>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('seller.orders.show', $sellerOrder->id) }}"
               class="px-4 py-2 rounded-xl border border-neutral-300 text-xs font-bold text-neutral-700 hover:bg-neutral-50">
                Back to Order
            </a>
            <a href="{{ route('seller.orders.shipping-label', $sellerOrder->id) }}"
               class="px-4 py-2 rounded-xl border border-neutral-300 text-xs font-bold text-neutral-700 hover:bg-neutral-50">
                View Shipping Label
            </a>
            <button type="button"
                    onclick="window.print()"
                    class="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold cursor-pointer">
                Print Packing Slip
            </button>
        </div>
    </div>

    <div class="space-y-8">
        @foreach($packages as $pkg)
            @php
                $manifestItems = is_array($pkg->manifest_snapshot) ? $pkg->manifest_snapshot : [];
            @endphp
            <div class="manifest-sheet max-w-4xl mx-auto bg-white border-2 border-neutral-900 rounded-2xl p-6 sm:p-8 shadow-md space-y-6">

                <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-neutral-900 pb-5">
                    <div>
                        <div class="font-serif text-3xl font-extrabold tracking-tight text-neutral-900">STORKIA</div>
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-neutral-700 mt-1">
                            Official Packing Slip & Product Manifest
                        </h2>
                    </div>

                    <div class="text-right text-xs space-y-1">
                        <div>Order Number: <strong class="font-mono text-neutral-900">{{ $sellerOrder->seller_order_number }}</strong></div>
                        <div>Parent Checkout: <strong class="font-mono text-neutral-900">{{ $sellerOrder->order->order_number ?? 'N/A' }}</strong></div>
                        <div>Shipment Tracking #: <strong class="font-mono text-neutral-900">{{ $shipment->tracking_number }}</strong></div>
                        <div>Package Reference: <strong class="font-mono text-neutral-900">{{ $pkg->package_reference }} ({{ $pkg->package_number }}/{{ $pkg->total_packages }})</strong></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-neutral-50 border border-neutral-300 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-500 block">Seller Store</span>
                        <div class="font-bold text-sm text-neutral-900">{{ $sellerOrder->sellerProfile->business_name ?? ($pickup['business_name'] ?? 'Seller') }}</div>
                        <div class="text-neutral-700">{{ $pickup['municipality'] ?? '' }}, {{ $pickup['province'] ?? '' }}</div>
                        <div class="font-mono text-neutral-600">{{ $pickup['contact_no'] ?? '' }}</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-neutral-50 border border-neutral-300 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-500 block">Assigned Logistics & Hub</span>
                        <div class="font-bold text-sm text-neutral-900">{{ $shipment->logisticsProfile->business_name ?? 'Logistics' }}</div>
                        <div class="text-neutral-700">{{ $pkg->logisticsHub?->hub_name ?? 'Main Hub' }}</div>
                        <div class="text-neutral-600">Handover: {{ $pkg->handover_method_label }}</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-neutral-50 border border-neutral-300 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-500 block">Recipient</span>
                        <div class="font-bold text-sm text-neutral-900">{{ $deliv['recipient_name'] ?? 'Buyer' }}</div>
                        <div class="text-neutral-700">{{ $deliv['barangay'] ?? '' }}, {{ $deliv['municipality'] ?? '' }}, {{ $deliv['province'] ?? '' }}</div>
                        <div class="font-mono text-neutral-600">{{ $deliv['phone_number'] ?? '' }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs border border-neutral-900">
                        <thead>
                            <tr class="bg-neutral-900 text-white uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3 border border-neutral-900">#</th>
                                <th class="py-2.5 px-3 border border-neutral-900">SKU</th>
                                <th class="py-2.5 px-3 border border-neutral-900">Product Name</th>
                                <th class="py-2.5 px-3 border border-neutral-900">Variant / Option</th>
                                <th class="py-2.5 px-3 border border-neutral-900 text-center">Quantity</th>
                                <th class="py-2.5 px-3 border border-neutral-900 text-right">Line Weight</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-300">
                            @foreach($manifestItems as $idx => $mItem)
                                <tr>
                                    <td class="py-2.5 px-3 border border-neutral-300 font-mono">{{ $idx + 1 }}</td>
                                    <td class="py-2.5 px-3 border border-neutral-300 font-mono font-bold">{{ $mItem['sku'] ?? 'N/A' }}</td>
                                    <td class="py-2.5 px-3 border border-neutral-300 font-bold text-neutral-900">{{ $mItem['product_name'] ?? 'Product' }}</td>
                                    <td class="py-2.5 px-3 border border-neutral-300 text-neutral-700">{{ $mItem['variant'] ?? 'Standard' }}</td>
                                    <td class="py-2.5 px-3 border border-neutral-300 text-center font-mono font-extrabold text-sm">{{ $mItem['quantity'] ?? 1 }}</td>
                                    <td class="py-2.5 px-3 border border-neutral-300 text-right font-mono">{{ number_format((float) ($mItem['line_weight'] ?? 0), 2) }} kg</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-neutral-100 font-bold text-xs">
                                <td colspan="4" class="py-3 px-3 border border-neutral-900 text-right uppercase">Total Package Items & Weight:</td>
                                <td class="py-3 px-3 border border-neutral-900 text-center font-mono text-sm">{{ $pkg->total_items_count }} unit(s)</td>
                                <td class="py-3 px-3 border border-neutral-900 text-right font-mono">{{ number_format((float) $pkg->package_weight, 2) }} kg</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-neutral-300 text-xs">
                    <div>
                        <span class="font-bold text-neutral-700">Package Reference:</span>
                        <span class="font-mono font-bold text-neutral-900">{{ $pkg->package_reference }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-neutral-700">COD Amount:</span>
                        <span class="font-mono font-extrabold text-neutral-900">₱{{ number_format((float) $pkg->cod_amount, 2) }}</span>
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    @if(!empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 300);
            });
        </script>
    @endif
</body>
</html>