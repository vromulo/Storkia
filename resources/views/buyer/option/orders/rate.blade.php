@extends('buyer.account-settings', [
    'pageTitle' => 'Rate Order #' . $sellerOrder->seller_order_number,
    'pageSubtitle' => 'Rate the Product Quality, Parcel Condition, Seller Store, Logistics Hub, and Delivery Rider to unlock Buy Again.'
])

@section('option-content')
@php
    $sellerName = $sellerOrder->sellerProfile->business_name ?? 'Seller Store';
    $logisticsName = $sellerOrder->logisticsProfile->business_name ?? 'Logistics Sorting Hub';
    $riderName = $sellerOrder->shipment?->currentRider?->full_name ?? 'Assigned Last-Mile Rider';

    $initialItemRatings = [];
    $initialItemComments = [];
    foreach ($sellerOrder->items as $it) {
        $initialItemRatings[$it->id] = 5;
        $initialItemComments[$it->id] = '';
    }
@endphp

<div x-data="{
    itemRatings: @js($initialItemRatings),
    itemComments: @js($initialItemComments),
    parcelConditionRating: 5,
    sellerServiceRating: 5,
    logisticsHubRating: 5,
    riderDeliveryRating: 5,
    overallComment: '',
    isSubmitting: false,
    appendTag(targetItemId, tagText) {
        if (targetItemId) {
            const cur = this.itemComments[targetItemId] || '';
            this.itemComments[targetItemId] = cur ? (cur + ' • ' + tagText) : tagText;
        } else {
            this.overallComment = this.overallComment ? (this.overallComment + ' • ' + tagText) : tagText;
        }
    }
}" class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('user.orders', ['status' => 'to_rate']) }}"
           class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span>Back to To Rate Orders</span>
        </a>

        <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-xs font-bold">
            Step: Rate Order &amp; Services to Complete
        </span>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="p-5 rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white shadow-xs space-y-1">
        <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-0.5 rounded-full bg-white/20">
            Complete Order Feedback Guide
        </span>
        <h3 class="text-lg font-serif font-bold pt-1">Who &amp; What Are You Rating?</h3>
        <p class="text-xs text-white/90 leading-relaxed">
            Your ratings help maintain quality across Storkia: (1) <strong>Product Quality</strong> updates the product's star rating, (2) <strong>Parcel Condition</strong> evaluates packaging safety, (3) <strong>Seller Service</strong> updates <strong>{{ $sellerName }}</strong>'s store rating, (4) <strong>Logistics Hub</strong> evaluates <strong>{{ $logisticsName }}</strong>'s sorting speed, and (5) <strong>Delivery Rider</strong> recognizes <strong>{{ $riderName }}</strong>'s doorstep service.
        </p>
    </div>

    <form action="{{ route('user.orders.rate.submit', $sellerOrder->id) }}"
          method="POST"
          enctype="multipart/form-data"
          @submit="isSubmitting = true"
          class="space-y-6">
        @csrf

        <div class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5">
            <div class="border-b border-border-subtle pb-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">1. Product Quality Rating</span>
                <h3 class="text-base font-bold text-text-main">Rate Purchased Product(s) from {{ $sellerName }}</h3>
                <p class="text-xs text-text-muted">Recommended focus: Authenticity, material quality, accuracy to listing photos, and value for money.</p>
            </div>

            <div class="divide-y divide-border-subtle">
                @foreach($sellerOrder->items as $item)
                    <div class="py-4 first:pt-0 last:pb-0 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-14 h-14 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                                    @if($item->product_image)
                                        <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-text-main truncate">{{ $item->product_name }}</h4>
                                    @if($item->variant_name)
                                        <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                                    @endif
                                    <p class="text-[11px] text-text-muted">Qty: {{ $item->quantity }} &bull; ₱{{ number_format((float) $item->line_total, 2) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <input type="hidden" name="item_ratings[{{ $item->id }}]" :value="itemRatings[{{ $item->id }}]">
                                @for($star = 1; $star <= 5; $star++)
                                    <button type="button"
                                            @click="itemRatings[{{ $item->id }}] = {{ $star }}"
                                            class="p-1 focus:outline-none cursor-pointer transition-transform hover:scale-110">
                                        <svg class="w-7 h-7 transition-colors"
                                             :class="itemRatings[{{ $item->id }}] >= {{ $star }} ? 'text-amber-400' : 'text-gray-200'"
                                             fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    </button>
                                @endfor
                                <span class="ml-2 text-xs font-extrabold text-primary-dark" x-text="itemRatings[{{ $item->id }}] + '/5'"></span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="text-text-muted font-bold mr-1">Quick Recommendations:</span>
                            @foreach(['Matches description & photos', 'Excellent build quality', 'Great value for price', 'Authentic & fresh item'] as $chip)
                                <button type="button"
                                        @click="appendTag({{ $item->id }}, '{{ $chip }}')"
                                        class="px-2.5 py-1 rounded-full bg-surface-subtle hover:bg-primary/10 text-text-main hover:text-primary border border-border-subtle font-semibold transition-colors cursor-pointer">
                                    + {{ $chip }}
                                </button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div class="md:col-span-2">
                                <textarea name="item_reviews[{{ $item->id }}]"
                                          x-model="itemComments[{{ $item->id }}]"
                                          rows="2"
                                          placeholder="Write your product review for {{ $item->product_name }}..."
                                          class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                            </div>
                            <div>
                                <label class="block font-bold text-text-muted text-[11px] mb-1">Optional Product Photo (WebP)</label>
                                <input type="file"
                                       name="item_photos[{{ $item->id }}]"
                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                       class="w-full p-2 rounded-xl border border-border-subtle bg-surface text-[11px] file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">2. Parcel &amp; Packaging Condition</span>
                    <h4 class="text-sm font-bold text-text-main mt-0.5">What is being rated: Outer box, bubble wrap &amp; seal integrity</h4>
                    <p class="text-[11px] text-text-muted">Did the parcel arrive sealed, dry, and undamaged without dents?</p>
                </div>

                <div class="flex items-center gap-1">
                    <input type="hidden" name="parcel_condition_rating" :value="parcelConditionRating">
                    @for($star = 1; $star <= 5; $star++)
                        <button type="button" @click="parcelConditionRating = {{ $star }}" class="p-1 cursor-pointer">
                            <svg class="w-7 h-7" :class="parcelConditionRating >= {{ $star }} ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-xs font-extrabold text-primary-dark" x-text="parcelConditionRating + '/5'"></span>
                </div>

                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @foreach(['Well-packed with bubble wrap', 'Tamper-proof seal intact', 'Zero dents on box'] as $tag)
                        <button type="button" @click="appendTag(null, '{{ $tag }}')" class="px-2.5 py-1 rounded-full bg-surface-subtle hover:bg-primary/10 text-text-main border border-border-subtle font-semibold cursor-pointer">
                            + {{ $tag }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">3. Seller Store Service</span>
                    <h4 class="text-sm font-bold text-text-main mt-0.5">Who is being rated: {{ $sellerName }}</h4>
                    <p class="text-[11px] text-text-muted">Order preparation speed, chat responsiveness, and following your buyer note.</p>
                </div>

                <div class="flex items-center gap-1">
                    <input type="hidden" name="seller_service_rating" :value="sellerServiceRating">
                    @for($star = 1; $star <= 5; $star++)
                        <button type="button" @click="sellerServiceRating = {{ $star }}" class="p-1 cursor-pointer">
                            <svg class="w-7 h-7" :class="sellerServiceRating >= {{ $star }} ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-xs font-extrabold text-primary-dark" x-text="sellerServiceRating + '/5'"></span>
                </div>

                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @foreach(['Fast seller acceptance', 'Accommodating & polite seller', 'Followed buyer note accurately'] as $tag)
                        <button type="button" @click="appendTag(null, '{{ $tag }}')" class="px-2.5 py-1 rounded-full bg-surface-subtle hover:bg-primary/10 text-text-main border border-border-subtle font-semibold cursor-pointer">
                            + {{ $tag }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">4. Logistics Sorting Hub</span>
                    <h4 class="text-sm font-bold text-text-main mt-0.5">Who is being rated: {{ $logisticsName }}</h4>
                    <p class="text-[11px] text-text-muted">First-mile pickup coordination, sorting hub turnaround time, and accurate tracking updates.</p>
                </div>

                <div class="flex items-center gap-1">
                    <input type="hidden" name="logistics_hub_rating" :value="logisticsHubRating">
                    @for($star = 1; $star <= 5; $star++)
                        <button type="button" @click="logisticsHubRating = {{ $star }}" class="p-1 cursor-pointer">
                            <svg class="w-7 h-7" :class="logisticsHubRating >= {{ $star }} ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-xs font-extrabold text-primary-dark" x-text="logisticsHubRating + '/5'"></span>
                </div>

                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @foreach(['Fast hub sorting & dispatch', 'Real-time QR tracking logs', 'Safe parcel handling at hub'] as $tag)
                        <button type="button" @click="appendTag(null, '{{ $tag }}')" class="px-2.5 py-1 rounded-full bg-surface-subtle hover:bg-primary/10 text-text-main border border-border-subtle font-semibold cursor-pointer">
                            + {{ $tag }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs space-y-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary block">5. Last-Mile Delivery Rider</span>
                    <h4 class="text-sm font-bold text-text-main mt-0.5">Who is being rated: {{ $riderName }}</h4>
                    <p class="text-[11px] text-text-muted">Doorstep courtesy, calling/texting upon arrival, and proper photo proof of delivery.</p>
                </div>

                <div class="flex items-center gap-1">
                    <input type="hidden" name="rider_delivery_rating" :value="riderDeliveryRating">
                    @for($star = 1; $star <= 5; $star++)
                        <button type="button" @click="riderDeliveryRating = {{ $star }}" class="p-1 cursor-pointer">
                            <svg class="w-7 h-7" :class="riderDeliveryRating >= {{ $star }} ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-xs font-extrabold text-primary-dark" x-text="riderDeliveryRating + '/5'"></span>
                </div>

                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @foreach(['Polite & courteous rider', 'Called before doorstep arrival', 'Handled parcel with care'] as $tag)
                        <button type="button" @click="appendTag(null, '{{ $tag }}')" class="px-2.5 py-1 rounded-full bg-surface-subtle hover:bg-primary/10 text-text-main border border-border-subtle font-semibold cursor-pointer">
                            + {{ $tag }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-3 text-xs">
            <label class="block font-bold uppercase tracking-wider text-text-muted">Overall Order &amp; Delivery Experience Summary (Optional)</label>
            <textarea name="review_comment"
                      x-model="overallComment"
                      rows="3"
                      maxlength="1000"
                      placeholder="Share your overall experience with the store, packaging, logistics hub, and rider..."
                      class="w-full p-3.5 rounded-2xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <span class="text-text-muted">
                    Submitting this rating marks Order #{{ $sellerOrder->seller_order_number }} as fully rated and unlocks the <strong>Buy Again</strong> button.
                </span>
                <button type="submit"
                        :disabled="isSubmitting"
                        class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white font-bold shadow-md transition-colors cursor-pointer disabled:opacity-50">
                    <span x-show="!isSubmitting">Submit Ratings &amp; Unlock Buy Again</span>
                    <span x-show="isSubmitting" x-cloak>Submitting Ratings...</span>
                </button>
            </div>
        </div>
    </form>

</div>
@endsection