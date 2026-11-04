@extends('layouts.seller', ['title' => 'Storkia - Store & Product Ratings'])

@section('content')
@php
    $profile = $profile ?? auth()->user()?->sellerProfile;
    $activeTab = request()->query('tab', 'products');
    $starFilter = (int) request()->query('stars', 0);

    if (!isset($itemReviews) && $profile && \Illuminate\Support\Facades\Schema::hasTable('order_item_reviews')) {
        $itemQuery = \App\Models\OrderItemReview::with(['buyer', 'product', 'sellerOrder'])
            ->where('seller_profile_id', $profile->id)
            ->orderByDesc('created_at');
        if ($starFilter >= 1 && $starFilter <= 5) {
            $itemQuery->where('rating', $starFilter);
        }
        $itemReviews = $itemQuery->paginate(15, ['*'], 'prod_page')->withQueryString();
    } else {
        $itemReviews = $itemReviews ?? collect();
    }

    if (!isset($serviceRatings) && $profile && \Illuminate\Support\Facades\Schema::hasTable('order_service_ratings')) {
        $srvQuery = \App\Models\OrderServiceRating::with(['buyer', 'sellerOrder', 'logisticsProfile', 'rider'])
            ->where('seller_profile_id', $profile->id)
            ->orderByDesc('created_at');
        if ($starFilter >= 1 && $starFilter <= 5) {
            $srvQuery->where('seller_service_rating', $starFilter);
        }
        $serviceRatings = $srvQuery->paginate(15, ['*'], 'srv_page')->withQueryString();
    } else {
        $serviceRatings = $serviceRatings ?? collect();
    }

    $avgProductRating = $profile && \Illuminate\Support\Facades\Schema::hasTable('order_item_reviews')
        ? round((float) \App\Models\OrderItemReview::where('seller_profile_id', $profile->id)->avg('rating'), 2)
        : 0.00;
    $totalProductReviews = $profile && \Illuminate\Support\Facades\Schema::hasTable('order_item_reviews')
        ? \App\Models\OrderItemReview::where('seller_profile_id', $profile->id)->count()
        : 0;

    $avgStoreServiceRating = $profile && \Illuminate\Support\Facades\Schema::hasTable('order_service_ratings')
        ? round((float) \App\Models\OrderServiceRating::where('seller_profile_id', $profile->id)->avg('seller_service_rating'), 2)
        : 0.00;
    $avgParcelConditionRating = $profile && \Illuminate\Support\Facades\Schema::hasTable('order_service_ratings')
        ? round((float) \App\Models\OrderServiceRating::where('seller_profile_id', $profile->id)->avg('parcel_condition_rating'), 2)
        : 0.00;
    $totalServiceRatings = $profile && \Illuminate\Support\Facades\Schema::hasTable('order_service_ratings')
        ? \App\Models\OrderServiceRating::where('seller_profile_id', $profile->id)->count()
        : 0;
@endphp

<div x-data="{
    tab: '{{ $activeTab }}',
    replyModalOpen: false,
    replyReviewId: null,
    replyProductName: '',
    replyBuyerText: '',
    replyText: '',
    photoModalOpen: false,
    photoModalSrc: '',
    openReplyModal(id, productName, buyerText, existingReply) {
        this.replyReviewId = id;
        this.replyProductName = productName;
        this.replyBuyerText = buyerText;
        this.replyText = existingReply || '';
        this.replyModalOpen = true;
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Store &amp; Product Ratings</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name ?? 'Seller Store' }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Monitor verified buyer product reviews, parcel packaging ratings, and store service feedback, and respond directly to customers.
            </p>
        </div>
    </div>

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
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase text-white/80">Product Quality Rating</span>
            <p class="text-3xl font-serif font-extrabold mt-1">{{ number_format($avgProductRating, 2) }} / 5.0 &#9733;</p>
            <span class="text-[10px] text-white/85 mt-1 block">Based on {{ number_format($totalProductReviews) }} product review(s)</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Store Service Rating</span>
            <p class="text-3xl font-serif font-bold text-amber-500 mt-1">{{ number_format($avgStoreServiceRating, 2) }} / 5.0 &#9733;</p>
            <span class="text-[10px] text-text-muted mt-1 block">Preparation speed &amp; chat service</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Parcel Packaging Rating</span>
            <p class="text-3xl font-serif font-bold text-primary-dark mt-1">{{ number_format($avgParcelConditionRating, 2) }} / 5.0 &#9733;</p>
            <span class="text-[10px] text-text-muted mt-1 block">Outer box &amp; bubble wrap safety</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Total Service Evaluations</span>
            <p class="text-3xl font-serif font-bold text-text-main mt-1">{{ number_format($totalServiceRatings) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Completed &amp; rated orders</span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border-subtle pb-3">
        <div class="flex items-center gap-2">
            <button type="button"
                    @click="tab = 'products'"
                    :class="tab === 'products' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                Product Reviews ({{ $totalProductReviews }})
            </button>
            <button type="button"
                    @click="tab = 'service'"
                    :class="tab === 'service' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                Store &amp; Packaging Ratings ({{ $totalServiceRatings }})
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <span class="text-text-muted font-bold mr-1">Filter Stars:</span>
            <a href="{{ route('seller.ratings.index', ['tab' => $activeTab]) }}"
               class="px-3 py-1.5 rounded-xl font-bold border {{ $starFilter === 0 ? 'bg-primary text-white border-primary' : 'bg-surface text-text-muted border-border-subtle' }}">
                All
            </a>
            @for($s = 5; $s >= 1; $s--)
                <a href="{{ route('seller.ratings.index', ['tab' => $activeTab, 'stars' => $s]) }}"
                   class="px-3 py-1.5 rounded-xl font-bold border {{ $starFilter === $s ? 'bg-primary text-white border-primary' : 'bg-surface text-text-muted border-border-subtle' }}">
                    {{ $s }} &#9733;
                </a>
            @endfor
        </div>
    </div>

    <div x-show="tab === 'products'" class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($itemReviews->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No product reviews found for this filter.</div>
        @else
            <div class="divide-y divide-border-subtle">
                @foreach($itemReviews as $rev)
                    @php
                        $photos = is_array($rev->review_photos) && !empty($rev->review_photos)
                            ? $rev->review_photos
                            : ($rev->review_photo_path ? [$rev->review_photo_path] : []);
                    @endphp
                    <div class="p-5 sm:p-6 space-y-3 text-xs">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-primary-dark">{{ $rev->product->name ?? 'Product #' . $rev->product_id }}</span>
                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-surface-subtle border border-border-subtle text-text-muted">
                                        Order #{{ $rev->sellerOrder->seller_order_number ?? $rev->seller_order_id }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="font-semibold text-text-main">{{ $rev->buyer?->full_name ?? 'Verified Buyer' }}</span>
                                    <span class="text-amber-500 text-sm font-bold">{!! str_repeat('&#9733;', (int) $rev->rating) !!}{!! str_repeat('&#9734;', max(0, 5 - (int) $rev->rating)) !!}</span>
                                    <span class="text-text-muted">({{ $rev->rating }}/5)</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-text-muted">{{ $rev->created_at?->format('M d, Y h:i A') }}</span>
                                <button type="button"
                                        @click="openReplyModal({{ $rev->id }}, '{{ addslashes($rev->product->name ?? 'Product') }}', '{{ addslashes($rev->review_text ?? '') }}', '{{ addslashes($rev->seller_reply ?? '') }}')"
                                        class="px-3.5 py-1.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                                    {{ $rev->seller_reply ? 'Edit Reply' : 'Reply to Buyer' }}
                                </button>
                            </div>
                        </div>

                        @if($rev->review_text)
                            <p class="text-text-main leading-relaxed">{{ $rev->review_text }}</p>
                        @else
                            <p class="text-text-muted italic">Buyer left a {{ $rev->rating }}-star rating without written comment.</p>
                        @endif

                        @if(!empty($photos))
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach($photos as $pPath)
                                    <img src="{{ asset('storage/' . $pPath) }}"
                                         @click="photoModalSrc = '{{ asset('storage/' . $pPath) }}'; photoModalOpen = true"
                                         alt="Review Photo"
                                         class="w-16 h-16 rounded-xl object-cover border border-border-subtle cursor-pointer hover:opacity-85">
                                @endforeach
                            </div>
                        @endif

                        @if($rev->seller_reply)
                            <div class="p-3.5 rounded-2xl bg-brand-light/25 border border-border-subtle space-y-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <strong class="text-primary-dark uppercase">Your Official Store Response</strong>
                                    <span class="text-text-muted">{{ $rev->seller_replied_at?->format('M d, Y h:i A') }}</span>
                                </div>
                                <p class="text-text-main">{{ $rev->seller_reply }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if(method_exists($itemReviews, 'hasPages') && $itemReviews->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $itemReviews->links() }}
                </div>
            @endif
        @endif
    </div>

    <div x-show="tab === 'service'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($serviceRatings->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No store service ratings found yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Order &amp; Buyer</th>
                            <th class="py-3.5 px-4 text-center">Store Service</th>
                            <th class="py-3.5 px-4 text-center">Parcel Condition</th>
                            <th class="py-3.5 px-4 text-center">Product Quality</th>
                            <th class="py-3.5 px-4">Overall Experience Comment</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($serviceRatings as $sr)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <div class="font-mono font-bold text-primary-dark">#{{ $sr->sellerOrder->seller_order_number ?? $sr->seller_order_id }}</div>
                                    <div class="text-text-main font-medium">{{ $sr->buyer?->full_name ?? 'Verified Buyer' }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-center font-bold text-amber-500">{{ $sr->seller_service_rating }} / 5 &#9733;</td>
                                <td class="py-3.5 px-4 text-center font-bold text-primary-dark">{{ $sr->parcel_condition_rating }} / 5 &#9733;</td>
                                <td class="py-3.5 px-4 text-center font-bold text-green-700">{{ $sr->product_quality_rating }} / 5 &#9733;</td>
                                <td class="py-3.5 px-4 text-text-main max-w-md">{{ $sr->review_comment ?: '—' }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $sr->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($serviceRatings, 'hasPages') && $serviceRatings->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $serviceRatings->links() }}
                </div>
            @endif
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="replyModalOpen"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="replyModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Respond to Buyer Review</h3>
                    <button type="button" @click="replyModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle text-xs space-y-1">
                    <div class="font-bold text-primary-dark" x-text="replyProductName"></div>
                    <p class="text-text-muted italic" x-text="replyBuyerText || 'No written comment'"></p>
                </div>
                <form :action="`{{ url('/seller/reviews') }}/${replyReviewId}/reply`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Your Store Reply <span class="text-danger">*</span></label>
                        <textarea name="seller_reply" x-model="replyText" rows="4" required maxlength="1000" placeholder="Thank the buyer or address their feedback professionally..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="replyModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-white font-bold cursor-pointer">Publish Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="photoModalOpen"
             x-cloak
             @click="photoModalOpen = false"
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs">
            <img :src="photoModalSrc" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl" @click.stop>
        </div>
    </template>
</div>
@endsection