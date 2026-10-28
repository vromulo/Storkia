<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Label — {{ $shipment->tracking_number }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
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
            .label-sheet {
                page-break-after: always;
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                margin: 0 auto !important;
            }
            .label-sheet:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body class="bg-neutral-100 text-neutral-900 font-sans antialiased py-8 px-4">

    @php
        $pickup = $shipment->pickup_address_snapshot ?? [];
        $deliv = $shipment->delivery_address_snapshot ?? [];
        $hubSnap = $shipment->hub_address_snapshot ?? [];
    @endphp

    <div class="max-w-3xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl shadow-sm border border-neutral-200 no-print">
        <div>
            <h1 class="text-sm font-bold text-neutral-900">Physical Package Shipping Label ({{ $packages->count() }} {{ Str::plural('Package', $packages->count()) }})</h1>
            <p class="text-xs text-neutral-500">
                Tracking #: <strong class="font-mono text-neutral-800">{{ $shipment->tracking_number }}</strong> &bull;
                Reprinting does not create a new shipment or tracking number.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('seller.orders.show', $sellerOrder->id) }}"
               class="px-4 py-2 rounded-xl border border-neutral-300 text-xs font-bold text-neutral-700 hover:bg-neutral-50">
                Back to Order
            </a>
            <a href="{{ route('seller.orders.packing-slip', $sellerOrder->id) }}"
               class="px-4 py-2 rounded-xl border border-neutral-300 text-xs font-bold text-neutral-700 hover:bg-neutral-50">
                View Packing Slip
            </a>
            <button type="button"
                    onclick="window.print()"
                    class="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold cursor-pointer">
                Print Shipping Label
            </button>
        </div>
    </div>

    <div class="space-y-8">
        @foreach($packages as $pkg)
            <div class="label-sheet max-w-xl mx-auto bg-white border-2 border-neutral-900 rounded-2xl p-6 shadow-md space-y-4">

                <div class="flex items-center justify-between border-b-2 border-neutral-900 pb-4">
                    <div>
                        <div class="font-serif text-3xl font-extrabold tracking-tight text-neutral-900">STORKIA</div>
                        <div class="text-[11px] font-bold uppercase tracking-widest text-neutral-600 mt-0.5">
                            Ecommerce Fulfillment &Verified Handover
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 bg-neutral-900 text-white font-mono text-xs font-bold rounded-md uppercase">
                            {{ $pkg->handover_method === 'self_drop_off' ? 'SELLER SELF DROP-OFF' : 'COURIER PICKUP' }}
                        </span>
                        <div class="text-xs font-bold text-neutral-700 mt-1">
                            PACKAGE {{ $pkg->package_number }} OF {{ $pkg->total_packages }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 items-center border-b-2 border-neutral-900 pb-4">
                    <div class="col-span-2 space-y-1.5">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block">Tracking Number</span>
                            <div class="font-mono text-xl font-extrabold text-neutral-900 tracking-wide">{{ $shipment->tracking_number }}</div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-500 block">Package Reference</span>
                            <div class="font-mono text-sm font-bold text-neutral-800">{{ $pkg->package_reference }}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-neutral-500 block">Seller Order #</span>
                                <span class="font-mono font-bold text-neutral-900">{{ $sellerOrder->seller_order_number }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-neutral-500 block">Parent Order #</span>
                                <span class="font-mono font-bold text-neutral-900">{{ $sellerOrder->order->order_number ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-center justify-center p-2 border-2 border-neutral-900 rounded-xl bg-white">
                        <div id="qr-box-{{ $pkg->id }}"
                             data-qr-token="{{ $pkg->qr_token }}"
                             class="w-28 h-28 flex items-center justify-center"></div>
                        <span class="text-[9px] font-mono font-bold text-neutral-600 mt-1 uppercase tracking-wider">Scan to Verify</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b-2 border-neutral-900 pb-4 text-xs">
                    <div class="p-3 rounded-xl bg-neutral-50 border border-neutral-300 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-500 block">Deliver To (Recipient)</span>
                        <div class="text-sm font-extrabold text-neutral-900">{{ $deliv['recipient_name'] ?? 'Buyer' }}</div>
                        <div class="font-mono font-bold text-neutral-800">{{ $deliv['phone_number'] ?? '' }}</div>
                        <div class="text-neutral-900 font-medium leading-snug">{{ $deliv['full_address'] ?? '' }}</div>
                        <div class="pt-1">
                            <span class="inline-block px-2 py-0.5 rounded bg-neutral-900 text-white text-[10px] font-bold uppercase">
                                Brgy: {{ $deliv['barangay'] ?? 'N/A' }} &bull; {{ $deliv['municipality'] ?? '' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-neutral-50 border border-neutral-300 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-500 block">Sender (Seller Store)</span>
                        <div class="text-sm font-extrabold text-neutral-900">{{ $sellerOrder->sellerProfile->business_name ?? ($pickup['business_name'] ?? 'Seller') }}</div>
                        <div class="font-mono font-bold text-neutral-800">{{ $pickup['contact_no'] ?? '' }}</div>
                        <div class="text-neutral-800 leading-snug">{{ $pickup['full_address'] ?? '' }}</div>
                        <div class="pt-1 text-[11px] font-bold text-neutral-600">
                            Origin: {{ $pickup['municipality'] ?? '' }}, {{ $pickup['province'] ?? '' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 border-b-2 border-neutral-900 pb-4 text-xs">
                    <div class="p-3 rounded-xl border border-neutral-300">
                        <span class="text-[10px] font-bold uppercase text-neutral-500 block">Logistics Provider</span>
                        <div class="font-extrabold text-neutral-900 mt-0.5">{{ $shipment->logisticsProfile->business_name ?? 'Logistics' }}</div>
                        <div class="text-[11px] text-neutral-600 mt-0.5">
                            {{ $pkg->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? 'Main Sorting Hub') }}
                        </div>
                    </div>

                    <div class="p-3 rounded-xl border border-neutral-300">
                        <span class="text-[10px] font-bold uppercase text-neutral-500 block">Package Specs</span>
                        <div class="font-extrabold text-neutral-900 mt-0.5">Weight: {{ number_format((float) $pkg->package_weight, 2) }} kg</div>
                        <div class="text-[11px] text-neutral-600 mt-0.5">Items: {{ $pkg->total_items_count }} unit(s)</div>
                    </div>

                    <div class="p-3 rounded-xl bg-neutral-900 text-white">
                        <span class="text-[10px] font-bold uppercase text-neutral-300 block">COD Amount to Collect</span>
                        <div class="text-lg font-mono font-extrabold mt-0.5">₱{{ number_format((float) $pkg->cod_amount, 2) }}</div>
                        <div class="text-[10px] text-neutral-300 mt-0.5">Cash on Delivery</div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[10px] text-neutral-500 font-mono">
                    <span>Printed: {{ now()->format('Y-m-d H:i') }} (Print #{{ max(1, (int) $pkg->label_printed_count) }})</span>
                    <span>Verify QR or Tracking # at every handover</span>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-qr-token]').forEach(function (el) {
                const token = el.getAttribute('data-qr-token');
                if (!token) return;
                if (typeof qrcode === 'function') {
                    const qr = qrcode(0, 'M');
                    qr.addData(token);
                    qr.make();
                    el.innerHTML = qr.createSvgTag({ cellSize: 2, margin: 1, scalable: true });
                    const svg = el.querySelector('svg');
                    if (svg) {
                        svg.setAttribute('width', '104');
                        svg.setAttribute('height', '104');
                    }
                } else {
                    el.innerHTML = '<div class="text-[9px] font-mono break-all text-center p-1">' + token.slice(0, 24) + '...</div>';
                }
            });

            @if(!empty($autoPrint))
                setTimeout(function () {
                    window.print();
                }, 350);
            @endif
        });
    </script>
</body>
</html>