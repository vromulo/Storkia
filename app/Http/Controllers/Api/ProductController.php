<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Buyer\HomeController as BuyerHomeController;
use App\Http\Controllers\Buyer\SellerController as BuyerSellerController;
use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\BuyerWishlist;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerStoreFollow;
use App\Models\StorkVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        if (Schema::hasColumn('products', 'publish_status')) {
            $query->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('subcategory') && $request->input('subcategory') !== 'All') {
            $query->where('subcategory', $request->input('subcategory'));
        }

        if ($request->filled('seller_id')) {
            $sellerId = (int) $request->input('seller_id');
            $query->whereHas('sellerProfile', function ($q) use ($sellerId) {
                $q->where('seller_profiles.id', $sellerId);
            });
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', 10)));
        $page = max(1, (int) $request->input('page', 1));
        $user = $request->user();

        $allMatching = $query->latest()->get();

        $hasFilters = $request->filled('category')
            || ($request->filled('subcategory') && $request->input('subcategory') !== 'All')
            || $request->filled('seller_id');

        $ranked = $hasFilters
            ? $allMatching->values()
            : BuyerHomeController::rankProductsByBuyerPreferences($allMatching, $user?->id);

        $this->appendProductMetrics($ranked, $user?->id);

        $total = $ranked->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $itemsForPage = $ranked->forPage($page, $perPage)->values();

        $responsePayload = [
            'current_page' => $page,
            'data'         => $itemsForPage,
            'last_page'    => $lastPage,
            'per_page'     => $perPage,
            'total'        => $total,
        ];

        $extra = [];
        if ($page === 1 && ! $hasFilters) {
            $featuredSellers = SellerProfile::latest()->take(6)->get()->map(function (SellerProfile $s) {
                $metrics = BuyerSellerController::buildStoreMetrics($s);
                return array_merge($s->toArray(), ['stats' => $metrics]);
            });

            $youMayAlsoLike = $ranked->take(6)->values();
            $deals = $ranked->filter(fn (Product $p) => (float) ($p->discount ?? 0) > 0)->take(8)->values();

            $extra = [
                'featured_sellers'  => $featuredSellers,
                'you_may_also_like' => $youMayAlsoLike,
                'deals'             => $deals,
            ];
        }

        return response()->json(array_merge([
            'status'  => 'success',
            'success' => true,
            'data'    => $responsePayload,
        ], $extra), 200);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $product = Product::with(['approval', 'specifications', 'sellerProfile', 'user.sellerProfile'])
            ->findOrFail($id);

        if (
            $product->trashed()
            || ($product->approval && $product->approval->status !== 'Approved')
            || (Schema::hasColumn('products', 'publish_status') && in_array($product->publish_status, ['draft', 'inactive'], true))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This product is currently unavailable.',
            ], 404);
        }

        $seller = $product->user?->sellerProfile
            ?? $product->sellerProfile
            ?? ($product->user_id ? SellerProfile::where('user_id', $product->user_id)->first() : null);

        $stats = $seller ? BuyerSellerController::buildStoreMetrics($seller) : [
            'chat_response_rate'  => 98,
            'following'           => 0,
            'followers'           => 0,
            'ratings'             => '0.0',
            'total_ratings_count' => 0,
            'joined'              => 'Recently',
            'products_count'      => 1,
        ];

        $productReviews = Schema::hasTable('order_item_reviews')
            ? OrderItemReview::with('buyer')
                ->where('product_id', $product->id)
                ->latest('id')
                ->get()
            : collect();

        $productAvgRating = $productReviews->isNotEmpty()
            ? round((float) $productReviews->avg('rating'), 1)
            : 0.0;

        $totalSoldUnits = (int) OrderItem::where('product_id', $product->id)
            ->whereHas('sellerOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->sum('quantity');

        $user = $request->user();
        $isOwnStore = $user && $seller && (int) $user->id === (int) $seller->user_id;
        $isStoreOnVacation = (bool) ($seller?->is_on_vacation ?? false);
        $perBuyerLimit = (int) ($product->per_buyer_limit ?? 0);

        $isFollowingStore = false;
        if ($user && $seller && Schema::hasTable('seller_store_follows')) {
            $isFollowingStore = SellerStoreFollow::where('seller_profile_id', $seller->id)
                ->where('follower_user_id', $user->id)
                ->where('is_following', true)
                ->exists();
        }

        $isWishlisted = false;
        if ($user && Schema::hasTable('buyer_wishlists')) {
            $isWishlisted = BuyerWishlist::where('buyer_id', $user->id)
                ->where('product_id', $product->id)
                ->exists();
        }

        $claimedVoucherIds = $user && Schema::hasTable('buyer_voucher_claims')
            ? BuyerVoucherClaim::where('buyer_id', $user->id)->pluck('voucher_id')->all()
            : [];

        $storeVouchers = ($seller && Schema::hasTable('stork_vouchers'))
            ? StorkVoucher::where('seller_profile_id', $seller->id)
                ->where('scope', 'store')
                ->where('status', 'active')
                ->where('visibility', 'claimable')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->whereColumn('used_count', '<', 'total_usage_limit')
                ->orderBy('min_spend')
                ->get()
                ->map(function (StorkVoucher $v) use ($claimedVoucherIds) {
                    return array_merge($v->toArray(), [
                        'badge_label' => $v->badge_label,
                        'is_claimed'  => in_array($v->id, $claimedVoucherIds, true),
                    ]);
                })
            : collect();

        $candidatesQuery = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->where('id', '!=', $product->id)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'));

        if (Schema::hasColumn('products', 'publish_status')) {
            $candidatesQuery->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        $candidates = $candidatesQuery->latest()->limit(36)->get();

        $related = BuyerHomeController::rankProductsByBuyerPreferences($candidates, $user?->id, $product)
            ->take(6)
            ->values();
        $this->appendProductMetrics($related, $user?->id);

        $formattedReviews = $productReviews->map(function (OrderItemReview $rev) use ($user) {
            $firstName = $rev->buyer?->first_name ?? '';
            $maskedName = $firstName !== '' ? substr($firstName, 0, 1) . '***' : 'Verified Buyer';
            $photos = is_array($rev->review_photos) && ! empty($rev->review_photos)
                ? $rev->review_photos
                : ($rev->review_photo_path ? [$rev->review_photo_path] : []);

            $editCount = (int) ($rev->edit_count ?? 0);
            $withinWindow = ! $rev->created_at || $rev->created_at->gt(now()->subDays(30));
            $canEdit = $user && (int) $rev->buyer_id === (int) $user->id && $editCount < 1 && $withinWindow;

            return [
                'id'                => $rev->id,
                'buyer_id'          => $rev->buyer_id,
                'rating'            => (int) $rev->rating,
                'review_text'       => $rev->review_text,
                'review_photo_path' => $rev->review_photo_path,
                'review_photos'     => $photos,
                'edit_count'        => $editCount,
                'edited_at'         => $rev->edited_at?->toISOString(),
                'can_edit'          => $canEdit,
                'seller_reply'      => $rev->seller_reply,
                'buyer_name'        => $maskedName,
                'created_at_human'  => $rev->created_at?->format('M d, Y') ?? '',
            ];
        });

        $productArray = $product->toArray();
        $productArray['avg_rating'] = $productAvgRating;
        $productArray['reviews_count'] = $productReviews->count();
        $productArray['sold_count'] = $totalSoldUnits;
        $productArray['is_wishlisted'] = $isWishlisted;
        $productArray['per_buyer_limit'] = $perBuyerLimit;

        if ($seller) {
            $sellerArray = $seller->toArray();
            $sellerArray['stats'] = $stats;
            $sellerArray['is_following'] = $isFollowingStore;
            $sellerArray['is_own_store'] = $isOwnStore;
            $sellerArray['is_on_vacation'] = $isStoreOnVacation;
            $productArray['seller_profile'] = $sellerArray;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'product'              => $productArray,
                'seller'               => $productArray['seller_profile'] ?? null,
                'stats'                => $stats,
                'product_avg_rating'   => $productAvgRating,
                'total_sold_units'     => $totalSoldUnits,
                'is_own_store'         => $isOwnStore,
                'is_following_store'   => $isFollowingStore,
                'is_wishlisted'        => $isWishlisted,
                'is_store_on_vacation' => $isStoreOnVacation,
                'per_buyer_limit'      => $perBuyerLimit,
                'store_vouchers'       => $storeVouchers,
                'reviews'              => $formattedReviews,
                'related_products'     => $related,
            ],
        ]);
    }

    protected function appendProductMetrics($products, ?int $buyerId = null): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->all();

        $soldCounts = OrderItem::whereIn('product_id', $productIds)
            ->whereHas('sellerOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->selectRaw('product_id, SUM(quantity) as total_sold')
            ->groupBy('product_id')
            ->pluck('total_sold', 'product_id');

        $reviewStats = Schema::hasTable('order_item_reviews')
            ? OrderItemReview::whereIn('product_id', $productIds)
                ->selectRaw('product_id, AVG(rating) as avg_rating, COUNT(*) as reviews_count')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id')
            : collect();

        $wishlistedIds = ($buyerId && Schema::hasTable('buyer_wishlists'))
            ? BuyerWishlist::where('buyer_id', $buyerId)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->all()
            : [];

        foreach ($products as $prod) {
            $prod->setAttribute('sold_count', (int) ($soldCounts[$prod->id] ?? 0));
            $rStat = $reviewStats->get($prod->id);
            $prod->setAttribute('avg_rating', $rStat ? round((float) $rStat->avg_rating, 1) : 0.0);
            $prod->setAttribute('reviews_count', $rStat ? (int) $rStat->reviews_count : 0);
            $prod->setAttribute('is_wishlisted', in_array($prod->id, $wishlistedIds, true));
        }
    }
}