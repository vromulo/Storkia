<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Storkia - Rider Return Pickups</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16">
    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary-dark text-surface font-serif font-bold text-lg flex items-center justify-center shrink-0">
                    R
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-base font-bold text-primary-dark leading-tight">Return Pickups</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/50 text-primary-dark border border-border-subtle">
                            Hub: {{ $rider->assigned_hub?->hub_name ?? ($rider->logisticsProfile->business_name ?? 'Logistics Hub') }}
                        </span>
                    </div>
                    <p class="text-xs text-text-muted mt-0.5">
                        {{ $rider->full_name }} &bull; {{ $rider->vehicle_type }} ({{ $rider->plate_number }})
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('rider.dashboard') }}" class="px-3.5 py-2 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-xs font-bold text-text-main transition-colors">
                    &larr; Delivery Dashboard
                </a>
                <a href="{{ route('rider.report') }}" class="px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition-colors">
                    E-Wallet &amp; Withdrawals
                </a>
                <form action="{{ route('rider.logout') }}" method="POST" class="m-0">
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
                <span class="text-[11px] font-bold uppercase text-text-muted">To Collect From Buyers</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $toCollect->count() }}</p>
                <span class="text-[11px] text-text-muted">Assigned to you</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">On the Way to Hub</span>
                <p class="text-2xl font-serif font-bold text-blue-700 mt-1">{{ $toHub->count() }}</p>
                <span class="text-[11px] text-text-muted">Waiting for hub confirmation</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Completed Return Pickups</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $completedCount }}</p>
                <span class="text-[11px] text-text-muted">Confirmed by the hub</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Return Pickup Pay Earned</span>
                <p class="text-2xl font-serif font-bold text-primary mt-1">&#8369;{{ number_format($totalEarned, 2) }}</p>
                <span class="text-[11px] text-text-muted">Already in your E-Wallet</span>
            </div>
        </div>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">Collect From Buyer</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Go to the buyer, collect the parcel with everything that came with it, and take a clear photo as proof.
                    You are paid for this leg once the sorting hub confirms the parcel.
                </p>
            </div>

            @forelse($toCollect as $rs)
                @php
                    $pickup = $rs->pickup_address_snapshot ?? [];
                    $order = $rs->sellerOrder;
                    $attempts = (int) $rs->pickup_attempts_count;
                @endphp
                <div x-data="{ pickupOpen: false, failOpen: false, parcelChecked: false, reason: '', preview: '' }"
                     class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-5 sm:p-6 space-y-4">

                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-base text-primary-dark">{{ $rs->tracking_number }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-purple-100 text-purple-800 border border-purple-200">
                                    Return Pickup
                                </span>
                                @if($attempts > 0)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-red-100 text-red-700 border border-red-200">
                                        Attempt {{ $attempts + 1 }} of {{ $maxAttempts }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-text-muted mt-0.5">
                                Request {{ $rs->returnRequest?->request_number }} &bull; Order {{ $order?->seller_order_number }}
                                &bull; {{ number_format((float) $rs->total_weight, 2) }} kg
                            </p>
                        </div>
                        <div class="text-[11px] text-text-muted">
                            Assigned {{ $rs->rider_assigned_at?->diffForHumans() }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                            <span class="font-bold uppercase text-[10px] text-text-muted block">Collect From</span>
                            <p class="text-sm font-bold text-text-main">{{ $pickup['recipient_name'] ?? ($order?->buyer?->full_name ?? 'Buyer') }}</p>
                            <p class="text-text-muted">{{ $pickup['phone_number'] ?? '' }}</p>
                            <p class="text-text-main leading-relaxed">
                                {{ $pickup['full_address'] ?? trim(($pickup['barangay'] ?? '') . ' ' . ($pickup['municipality'] ?? '') . ', ' . ($pickup['province'] ?? '')) }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1">
                            <span class="font-bold uppercase text-[10px] text-text-muted block">Parcel Should Contain</span>
                            @foreach($order?->items ?? [] as $item)
                                <p class="text-text-main">
                                    <strong>&times; {{ $item->quantity }}</strong> {{ $item->product_name }}
                                    @if($item->variant_name)
                                        <span class="text-text-muted">({{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }})</span>
                                    @endif
                                </p>
                            @endforeach
                            <p class="text-text-muted pt-1">Reason: {{ $rs->returnRequest?->reason_category }}</p>
                        </div>
                    </div>

                    @if($attempts > 0 && $rs->pickup_failure_reason)
                        <p class="text-xs text-red-700 font-semibold">
                            Last attempt failed: "{{ $rs->pickup_failure_reason }}". After {{ $maxAttempts }} failed attempts the return is closed.
                        </p>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button type="button" @click="failOpen = true"
                                class="px-4 py-2.5 rounded-xl bg-red-50 hover:bg-red-100 text-danger text-xs font-bold transition-colors cursor-pointer">
                            Can't Collect
                        </button>
                        <button type="button" @click="pickupOpen = true"
                                class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            Confirm Pickup
                        </button>
                    </div>

                    <template x-teleport="body">
                        <div x-show="pickupOpen" x-cloak class="fixed inset-0 z-[180] flex items-center justify-center p-4">
                            <div @click="pickupOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                    <div>
                                        <h3 class="text-base font-bold text-primary-dark">Confirm Return Pickup</h3>
                                        <p class="text-xs text-text-muted font-mono">{{ $rs->tracking_number }}</p>
                                    </div>
                                    <button type="button" @click="pickupOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                                </div>

                                <form action="{{ route('rider.returns.confirm-pickup', $rs->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">Photo of the Collected Parcel <span class="text-danger">*</span></label>
                                        <input type="file"
                                               name="pickup_photo"
                                               accept="image/jpeg,image/png,image/webp"
                                               capture="environment"
                                               required
                                               @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''"
                                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs">
                                        <template x-if="preview">
                                            <img :src="preview" alt="Pickup preview" class="mt-2 max-h-40 rounded-xl border border-border-subtle object-contain">
                                        </template>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">Notes (Optional)</label>
                                        <input type="text" name="notes" maxlength="500" placeholder="e.g. Buyer handed over the box and the free gift"
                                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">
                                    </div>

                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input type="checkbox" name="parcel_checked" value="1" x-model="parcelChecked" required class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary">
                                        <span class="font-bold text-text-main">I collected the parcel from the buyer and it is with me now.</span>
                                    </label>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="pickupOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                                        <button type="submit"
                                                :disabled="!parcelChecked"
                                                :class="parcelChecked ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                                                class="px-5 py-2.5 rounded-xl font-bold transition-colors">
                                            Confirm Pickup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>

                    <template x-teleport="body">
                        <div x-show="failOpen" x-cloak class="fixed inset-0 z-[180] flex items-center justify-center p-4">
                            <div @click="failOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                                    <div>
                                        <h3 class="text-base font-bold text-danger">Could Not Collect the Parcel</h3>
                                        <p class="text-xs text-text-muted font-mono">{{ $rs->tracking_number }}</p>
                                    </div>
                                    <button type="button" @click="failOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                                </div>

                                <p class="text-xs text-text-muted leading-relaxed">
                                    This counts as attempt {{ $attempts + 1 }} of {{ $maxAttempts }}. Logistics will be asked to send a rider again.
                                    @if($attempts + 1 >= $maxAttempts)
                                        <strong class="text-danger">This is the last allowed attempt: reporting it closes the return request without a refund.</strong>
                                    @endif
                                </p>

                                <form action="{{ route('rider.returns.report-failed', $rs->id) }}" method="POST" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">What happened? <span class="text-danger">*</span></label>
                                        <select name="reason" x-model="reason" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold outline-none focus:border-primary">
                                            <option value="">Select a reason</option>
                                            @foreach($failureReasons as $reasonOption)
                                                <option value="{{ $reasonOption }}">{{ $reasonOption }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-text-muted mb-1">
                                            Details <span x-show="reason === 'Other'" class="text-danger">*</span>
                                            <span x-show="reason !== 'Other'">(Optional)</span>
                                        </label>
                                        <textarea name="notes" rows="3" maxlength="300" :required="reason === 'Other'"
                                                  placeholder="e.g. Called twice, no answer after waiting 15 minutes"
                                                  class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="failOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-danger text-white font-bold cursor-pointer">Report Failed Pickup</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
            @empty
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs py-12 px-6 text-center space-y-1">
                    <h3 class="text-base font-bold text-text-main">No return pickups assigned to you</h3>
                    <p class="text-xs text-text-muted">When your logistics hub assigns you a return parcel to collect from a buyer, it appears here.</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">On the Way to the Sorting Hub</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Deliver these parcels to the sorting hub. Your pay is credited to your E-Wallet as soon as the hub confirms receipt.
                </p>
            </div>

            @forelse($toHub as $rs)
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-5 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div>
                        <p class="font-mono font-bold text-sm text-primary-dark">{{ $rs->tracking_number }}</p>
                        <p class="text-text-muted mt-0.5">
                            Order {{ $rs->sellerOrder?->seller_order_number }} &bull;
                            Picked up {{ $rs->picked_up_from_buyer_at?->format('M d, h:i A') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-text-main">{{ $rs->hub_address_snapshot['hub_name'] ?? ($rs->hub_address_snapshot['name'] ?? 'Sorting hub') }}</p>
                        <p class="text-text-muted">{{ $rs->hub_address_snapshot['municipality'] ?? '' }}, {{ $rs->hub_address_snapshot['province'] ?? '' }}</p>
                    </div>
                </div>
            @empty
                <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs py-8 px-6 text-center">
                    <p class="text-xs text-text-muted">No parcels on the way to the hub right now.</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-primary-dark">Completed Return Pickups</h2>
                <p class="text-xs text-text-muted mt-0.5">Latest {{ $completed->count() }} of {{ $completedCount }} parcels confirmed by the sorting hub.</p>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
                @if($completed->isEmpty())
                    <div class="py-8 px-6 text-center">
                        <p class="text-xs text-text-muted">Completed return pickups will appear here.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                                    <th class="py-3 px-5">Return</th>
                                    <th class="py-3 px-4">Picked Up</th>
                                    <th class="py-3 px-4">Hub Confirmed</th>
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
                                        <td class="py-3 px-4 text-text-main">{{ $rs->picked_up_from_buyer_at?->format('M d, Y h:i A') }}</td>
                                        <td class="py-3 px-4 text-text-main">{{ $rs->arrived_at_hub_at?->format('M d, Y h:i A') }}</td>
                                        <td class="py-3 px-5 text-right font-bold text-primary">&#8369;{{ number_format((float) $rs->rider_pay, 2) }}</td>
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