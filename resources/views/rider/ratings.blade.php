<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Rider Delivery Ratings</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16 select-none">
    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('rider.dashboard') }}" class="px-3.5 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-xs font-bold text-text-main">
                    &larr; Back to Rider Dashboard
                </a>
                <div>
                    <h1 class="text-base font-bold text-primary-dark">My Doorstep Delivery Ratings &amp; Buyer Feedback</h1>
                    <p class="text-xs text-text-muted">{{ $rider->full_name }} &bull; {{ $rider->vehicle_type }} ({{ $rider->plate_number }})</p>
                </div>
            </div>
            <a href="{{ route('rider.report') }}" class="px-4 py-2 rounded-xl bg-primary-dark text-white text-xs font-bold">
                E-Wallet &amp; Financial Report &rarr;
            </a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-6 bg-gradient-to-br from-primary-dark to-primary text-white rounded-3xl shadow-sm">
                <span class="text-xs font-bold uppercase text-white/80">Average Doorstep Delivery Rating</span>
                <p class="text-3xl font-serif font-extrabold mt-1">{{ number_format((float) $avgRating, 2) }} / 5.0 &#9733;</p>
                <span class="text-xs text-white/85 mt-1 block">Submitted by Buyers upon order completion</span>
            </div>
            <div class="p-6 bg-surface rounded-3xl border border-border-subtle shadow-xs">
                <span class="text-xs font-bold uppercase text-text-muted">Total Rated Deliveries</span>
                <p class="text-3xl font-serif font-bold text-primary-dark mt-1">{{ number_format((int) $totalRatings) }}</p>
                <span class="text-xs text-text-muted mt-1 block">Verified buyer feedback records</span>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Buyer Delivery Evaluations Log</h2>
            </div>
            @if($ratings->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No buyer delivery ratings submitted yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Order #</th>
                                <th class="py-3.5 px-4">Buyer</th>
                                <th class="py-3.5 px-4 text-center">Rider Delivery Rating</th>
                                <th class="py-3.5 px-4 text-center">Parcel Condition</th>
                                <th class="py-3.5 px-4">Buyer Comment</th>
                                <th class="py-3.5 px-5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($ratings as $rt)
                                <tr>
                                    <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">#{{ $rt->sellerOrder->seller_order_number ?? $rt->seller_order_id }}</td>
                                    <td class="py-3.5 px-4 font-semibold">{{ $rt->buyer?->first_name ? substr($rt->buyer->first_name, 0, 1) . '***' : 'Verified Buyer' }}</td>
                                    <td class="py-3.5 px-4 text-center font-extrabold text-amber-500">{{ $rt->rider_delivery_rating }} / 5 &#9733;</td>
                                    <td class="py-3.5 px-4 text-center font-bold text-green-700">{{ $rt->parcel_condition_rating }} / 5 &#9733;</td>
                                    <td class="py-3.5 px-4 text-text-main max-w-md">{{ $rt->review_comment ?: '—' }}</td>
                                    <td class="py-3.5 px-5 text-right text-text-muted">{{ $rt->created_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(method_exists($ratings, 'hasPages') && $ratings->hasPages())
                    <div class="px-5 py-3 border-t border-border-subtle">
                        {{ $ratings->links() }}
                    </div>
                @endif
            @endif
        </div>
    </main>
</body>
</html>