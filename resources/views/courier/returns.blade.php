<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Storkia - Courier Return Deliveries</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16 overflow-x-hidden">
    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary text-surface font-serif font-bold text-lg flex items-center justify-center shrink-0">
                    C
                </div>
                <div>
                    <h1 class="text-base font-bold text-primary-dark leading-tight">Return Deliveries</h1>
                    <p class="text-xs text-text-muted">
                        {{ $courier->full_name }} &bull; {{ $courier->vehicle_type }} ({{ $courier->plate_number }}) &bull;
                        Hub: <strong>{{ $courier->logisticsProfile->business_name ?? $courier->home_hub }}</strong>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('courier.dashboard') }}" class="px-3.5 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-xs font-bold text-text-main transition-colors">
                    &larr; Pickup Dashboard
                </a>
                <a href="{{ route('courier.report') }}" class="px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition-colors">
                    E-Wallet &amp; Withdrawals
                </a>
                <form action="{{ route('courier.logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-red-50 text-danger hover:bg-red-100 text-xs font-bold transition-colors cursor-pointer">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">To Collect at Sorting Hub</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $toCollect->count() }}</p>
                <span class="text-[11px] text-text-muted">Assigned to you</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Out for Delivery</span>
                <p class="text-2xl font-serif font-bold text-blue-700 mt-1">{{ $outForDelivery->count() }}</p>
                <span class="text-[11px] text-text-muted">On the way to sellers</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Completed Return Deliveries</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $completedCount }}</p>
                <span class="text-[11px] text-text-muted">Handed to sellers</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Return Delivery Pay Earned</span>
                <p class="text-2xl font-serif font-bold text-primary mt-1">&#8369;{{ number_format($totalEarned, 2) }}</p>
                <span class="text-[11px] text-text-muted">Already in your E-Wallet</span>
            </div>
        </div>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">Collect at the Sorting Hub</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Pick up the returned parcel from the hub staff, then tap Start Delivery to head to the seller.
                </p>
            </div>

            @forelse($toCollect as $rs)
                @php
                    $hubSnap = $rs->hub_address_snapshot ?? [];
                    $sellerAddr = $rs->return_address_snapshot ?? [];
                    $order = $rs->sellerOrder;
                @endphp
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-5 sm:p-6 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-base text-primary-dark">{{ $rs->tracking_number }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-purple-100 text-purple-800 border border-purple-200">
                                    Return to Seller
                                </span>
                            </div>
                            <p class="text-xs text-text-muted mt-0.5">
                                Request {{ $rs->returnRequest?->request_number }} &bull; Order {{ $order?->seller_order_number }}
                                &bull; {{ number_format((float) $rs->total_weight, 2) }} kg
                            </p>
                        </div>
                        <div class="text-[11px] text-text-muted">Assigned {{ $rs->courier_assigned_at?->diffForHumans() }}</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                            <span class="font-bold uppercase text-[10px] text-text-muted block">1. Collect At</span>
                            <p class="text-sm font-bold text-text-main">{{ $hubSnap['hub_name'] ?? ($hubSnap['name'] ?? 'Sorting hub') }}</p>
                            <p class="text-text-main leading-relaxed">{{ $hubSnap['municipality'] ?? '' }}, {{ $hubSnap['province'] ?? '' }}</p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                            <span class="font-bold uppercase text-[10px] text-text-muted block">2. Deliver To (Seller)</span>
                            <p class="text-sm font-bold text-text-main">{{ $order?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'Seller') }}</p>
                            <p class="text-text-muted">{{ $sellerAddr['phone_number'] ?? '' }}</p>
                            <p class="text-text-main leading-relaxed">
                                {{ $sellerAddr['full_address'] ?? trim(($sellerAddr['barangay'] ?? '') . ' ' . ($sellerAddr['municipality'] ?? '') . ', ' . ($sellerAddr['province'] ?? '')) }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                            <span class="font-bold uppercase text-[10px] text-text-muted block">Parcel Contains</span>
                            @foreach($order?->items ?? [] as $item)
                                <p class="text-text-main">
                                    <strong>&times; {{ $item->quantity }}</strong> {{ $item->product_name }}
                                    @if($item->variant_name)
                                        <span class="text-text-muted">({{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }})</span>
                                    @endif
                                </p>
                            @endforeach
                        </div>
                    </div>

                    <form action="{{ route('courier.returns.start-delivery', $rs->id) }}" method="POST" class="flex justify-end m-0">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            I Have the Parcel &mdash; Start Delivery
                        </button>
                    </form>
                </div>
            @empty
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs py-12 px-6 text-center space-y-1">
                    <h3 class="text-base font-bold text-text-main">No return parcels waiting for you at the hub</h3>
                    <p class="text-xs text-text-muted">When your logistics hub assigns you a returned parcel to bring back to a seller, it appears here.</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">Out for Delivery to the Seller</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Hand the parcel to the seller and take a clear photo as proof. You are paid for this leg as soon as you confirm.
                </p>
            </div>

            @forelse($outForDelivery as $rs)
                @php
                    $sellerAddr = $rs->return_address_snapshot ?? [];
                    $order = $rs->sellerOrder;
                @endphp
                <div x-data="{ deliverOpen: false, parcelHanded: false, preview: '' }"
                     class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-5 sm:p-6 space-y-4">

                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-base text-primary-dark">{{ $rs->tracking_number }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-blue-100 text-blue-700 border border-blue-200">
                                    Out for Delivery
                                </span>
                            </div>
                            <p class="text-xs text-text-muted mt-0.5">
                                Request {{ $rs->returnRequest?->request_number }} &bull; Order {{ $order?->seller_order_number }}
                                &bull; Left the hub {{ $rs->picked_up_from_hub_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1 text-xs">
                        <span class="font-bold uppercase text-[10px] text-text-muted block">Deliver To</span>
                        <p class="text-sm font-bold text-text-main">{{ $order?->sellerProfile?->business_name ?? ($sellerAddr['business_name'] ?? 'Seller') }}</p>
                        <p class="text-text-muted">{{ $sellerAddr['phone_number'] ?? '' }}</p>
                        <p class="text-text-main leading-relaxed">
                            {{ $sellerAddr['full_address'] ?? trim(($sellerAddr['barangay'] ?? '') . ' ' . ($sellerAddr['municipality'] ?? '') . ', ' . ($sellerAddr['province'] ?? '')) }}
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="deliverOpen = true"
                                class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            Confirm Delivered to Seller
                        </button>
                    </div>

                    <template x-teleport="body">
                        <div x-show="deliverOpen" x-cloak class="fixed inset-0 z-[180] flex items-center justify-center p-4">
                            <div @click="deliverOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                    <div>
                                        <h3 class="text-base font-bold text-primary-dark">Confirm Return Delivery</h3>
                                        <p class="text-xs text-text-muted font-mono">{{ $rs->tracking_number }}</p>
                                    </div>
                                    <button type="button" @click="deliverOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                                </div>

                                <form action="{{ route('courier.returns.confirm-delivered', $rs->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">Photo of the Parcel Handed to the Seller <span class="text-danger">*</span></label>
                                        <input type="file"
                                               name="delivery_photo"
                                               accept="image/jpeg,image/png,image/webp"
                                               capture="environment"
                                               required
                                               @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''"
                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs">
                                        <template x-if="preview">
                                            <img :src="preview" alt="Delivery preview" class="mt-2 max-h-40 rounded-xl border border-border-subtle object-contain">
                                        </template>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">Received By (Optional)</label>
                                        <input type="text" name="recipient_name" maxlength="120" placeholder="Name of the person who received it"
                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">Notes (Optional)</label>
                                        <input type="text" name="notes" maxlength="500" placeholder="e.g. Left with the store cashier"
                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                                    </div>

                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input type="checkbox" name="parcel_handed" value="1" x-model="parcelHanded" required class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary">
                                        <span class="font-bold text-text-main">I handed the returned parcel to the seller.</span>
                                    </label>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="deliverOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                                        <button type="submit"
                                                :disabled="!parcelHanded"
                                                :class="parcelHanded ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                                                class="px-5 py-2.5 rounded-xl font-bold transition-colors">
                                            Confirm Delivery
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
            @empty
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs py-8 px-6 text-center">
                    <p class="text-xs text-text-muted">No return parcels are out for delivery right now.</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">Completed Return Deliveries</h2>
                <p class="text-xs text-text-muted mt-0.5">Latest {{ $completed->count() }} of {{ $completedCount }} parcels handed to sellers.</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
                @if($completed->isEmpty())
                    <div class="py-8 px-6 text-center">
                        <p class="text-xs text-text-muted">Completed return deliveries will appear here.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                                    <th class="py-3 px-5">Return</th>
                                    <th class="py-3 px-4">Delivered To</th>
                                    <th class="py-3 px-4">Delivered At</th>
                                    <th class="py-3 px-5 text-right">Pay Received</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle text-xs">
                                @foreach($completed as $rs)
                                    <tr>
                                        <td class="py-3 px-5">
                                            <div class="font-mono font-bold text-primary-dark">{{ $rs->tracking_number }}</div>
                                            <div class="text-text-muted">Order {{ $rs->sellerOrder?->seller_order_number }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-text-main">{{ $rs->sellerOrder?->sellerProfile?->business_name ?? 'Seller' }}</td>
                                        <td class="py-3 px-4 text-text-main">{{ $rs->delivered_to_seller_at?->format('M d, Y h:i A') }}</td>
                                        <td class="py-3 px-5 text-right font-bold text-primary">&#8369;{{ number_format((float) $rs->courier_pay, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </main>

    @livewireScripts
</body>
</html>