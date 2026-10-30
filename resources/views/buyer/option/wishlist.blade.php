@extends('buyer.account-settings', [
    'pageTitle' => 'My Wishlist',
    'pageSubtitle' => 'Saved products from verified stores. Out-of-stock or discontinued items are flagged automatically.'
])

@section('option-content')
@php
    $initialIds = $wishlistItems->pluck('product_id')->values()->all();
@endphp

<div x-data="{
    removedIds: [],
    toastMsg: '',
    async removeItem(productId) {
        try {
            const res = await fetch(`/user/wishlist/${productId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                if (!this.removedIds.includes(productId)) {
                    this.removedIds.push(productId);
                }
                this.toastMsg = data.message || 'Removed from Wishlist.';
                setTimeout(() => this.toastMsg = '', 3500);
            }
        } catch (e) {}
    }
}" class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    <template x-if="toastMsg">
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold" x-text="toastMsg"></div>
    </template>

    @if($wishlistItems->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto border border-border-subtle">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-text-main">Your Wishlist is empty</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Tap the heart icon on any product page to save items here for quick price and stock tracking.
            </p>
            <a href="{{ route('home') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                Explore Products
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($wishlistItems as $item)
                @php
                    $prod = $item->product;
                    if (! $prod) continue;

                    $seller = $prod->user?->sellerProfile;
                    $isDeletedOrInactive = $prod->trashed()
                        || ($prod->approval && $prod->approval->status !== 'Approved')
                        || (isset($prod->publish_status) && in_array($prod->publish_status, ['draft', 'inactive'], true));

                    $isVacation = (bool) ($seller?->is_on_vacation ?? false);
                    $stock = (int) ($prod->stock_quantity ?? 0);
                    $isSoldOut = ! $isDeletedOrInactive && $stock <= 0;

                    $firstPic = (! empty($prod->pictures) && is_array($prod->pictures))
                        ? asset('storage/' . $prod->pictures[0])
                        : null;

                    $basePrice = (float) ($prod->price ?? 0);
                    $discount = (float) ($prod->discount ?? 0);
                    $effectivePrice = $discount > 0
                        ? round($basePrice - ($basePrice * ($discount / 100)), 2)
                        : round($basePrice, 2);
                @endphp

                <div x-show="!removedIds.includes({{ $prod->id }})"
                     x-transition
                     class="p-4 sm:p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex flex-col justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="relative w-20 h-20 rounded-2xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                            @if($firstPic)
                                <img src="{{ $firstPic }}" alt="{{ $prod->name }}" class="w-full h-full object-contain rounded-xl {{ ($isDeletedOrInactive || $isSoldOut) ? 'opacity-50 grayscale' : '' }}">
                            @endif

                            @if($isDeletedOrInactive)
                                <span class="absolute inset-x-1 bottom-1 py-0.5 rounded bg-gray-900/85 text-white text-[9px] font-extrabold uppercase text-center">
                                    Unavailable
                                </span>
                            @elseif($isSoldOut)
                                <span class="absolute inset-x-1 bottom-1 py-0.5 rounded bg-danger text-white text-[9px] font-extrabold uppercase text-center">
                                    Sold Out
                                </span>
                            @elseif($isVacation)
                                <span class="absolute inset-x-1 bottom-1 py-0.5 rounded bg-amber-500 text-white text-[9px] font-extrabold uppercase text-center">
                                    On Vacation
                                </span>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                @if($isDeletedOrInactive)
                                    <span class="text-sm font-bold text-text-muted line-through truncate block">{{ $prod->name }}</span>
                                @else
                                    <a href="{{ route('product.show', $prod->id) }}" class="text-sm font-bold text-text-main hover:text-primary truncate block">
                                        {{ $prod->name }}
                                    </a>
                                @endif

                                <button type="button"
                                        @click="removeItem({{ $prod->id }})"
                                        class="text-text-muted hover:text-danger text-xs font-bold shrink-0 cursor-pointer"
                                        title="Remove from Wishlist">
                                    Remove
                                </button>
                            </div>

                            <p class="text-[11px] text-text-muted mt-0.5">
                                Store: <strong>{{ $seller->business_name ?? 'Official Store' }}</strong>
                            </p>

                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-base font-extrabold text-primary">₱{{ number_format($effectivePrice, 2) }}</span>
                                @if($discount > 0)
                                    <span class="text-xs text-text-muted line-through">₱{{ number_format($basePrice, 2) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-border-subtle flex items-center justify-between gap-2 text-xs">
                        @if($isDeletedOrInactive)
                            <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-600 font-bold text-[11px]">
                                Unavailable — Discontinued or Inactive
                            </span>
                            <button type="button" disabled class="px-4 py-2 rounded-xl bg-gray-200 text-gray-400 font-bold cursor-not-allowed">
                                Unavailable
                            </button>
                        @elseif($isSoldOut)
                            <span class="px-3 py-1 rounded-full bg-red-50 text-danger font-bold text-[11px]">
                                Sold Out (0 in stock)
                            </span>
                            <button type="button" disabled class="px-4 py-2 rounded-xl bg-gray-200 text-gray-400 font-bold cursor-not-allowed">
                                Add to Cart Disabled
                            </button>
                        @elseif($isVacation)
                            <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-800 font-bold text-[11px]">
                                Store On Vacation
                            </span>
                            <button type="button" disabled class="px-4 py-2 rounded-xl bg-gray-200 text-gray-400 font-bold cursor-not-allowed">
                                On Vacation
                            </button>
                        @else
                            <span class="text-green-700 font-bold text-[11px]">{{ $stock }} in stock</span>
                            <a href="{{ route('product.show', $prod->id) }}"
                               class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors">
                                View &amp; Add to Cart
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection