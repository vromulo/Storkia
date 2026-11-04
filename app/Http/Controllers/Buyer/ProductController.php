<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerStoreFollow;
use App\Models\StorkVoucher;
use App\Services\SuperDealPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    public function __construct(
        protected SuperDealPricingService $superDealPricingService
    ) {}

    public function show(Request $request, Product $product)
    {
        $product->loadMissing(['specifications', 'approval', 'user.sellerProfile']);

        if (
            $product->trashed()
            || ($product->approval && $product->approval->status !== 'Approved')
            || (Schema::hasColumn('products', 'publish_status') && in_array($product->publish_status, ['draft', 'inactive'], true))
        ) {
            abort(404, 'This product is currently unavailable.');
        }

        $recentIds = (array) $request->session()->get('recently_viewed_product_ids', []);
        array_unshift($recentIds, (int) $product->id);
        $recentIds = array_slice(array_values(array_unique(array_map('intval', $recentIds))), 0, 24);
        $request->session()->put('recently_viewed_product_ids', $recentIds);

        $seller = $product->user?->sellerProfile
            ?? ($product->user_id ? SellerProfile::where('user_id', $product->user_id)->first() : null);

        $stats = $seller ? SellerController::buildStoreMetrics($seller) : [
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

        $user = auth()->user();
        $isOwnStore = $user && $seller && (int) $user->id === (int) $seller->user_id;
        $isFollowingStore = false;
        $isWishlisted = false;

        if ($user && $seller && Schema::hasTable('seller_store_follows')) {
            $isFollowingStore = SellerStoreFollow::where('seller_profile_id', $seller->id)
                ->where('follower_user_id', $user->id)
                ->where('is_following', true)
                ->exists();
        }

        if ($user && Schema::hasTable('buyer_wishlists')) {
            $isWishlisted = DB::table('buyer_wishlists')
                ->where('buyer_id', $user->id)
                ->where('product_id', $product->id)
                ->exists();
        }

        $storeVouchers = $seller
            ? StorkVoucher::where('seller_profile_id', $seller->id)
                ->where('scope', 'store')
                ->where('status', 'active')
                ->where('visibility', 'claimable')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->whereColumn('used_count', '<', 'total_usage_limit')
                ->orderBy('min_spend')
                ->get()
            : collect();

        $claimedVoucherIds = $user
            ? BuyerVoucherClaim::where('buyer_id', $user->id)->pluck('voucher_id')->all()
            : [];

        $dealPricing = $this->superDealPricingService->resolveDisplayPricing($product, (float) ($product->price ?? 0));

        $candidates = Product::with(['approval', 'user.sellerProfile'])
            ->where('id', '!=', $product->id)
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->latest()
            ->limit(36)
            ->get();

        $recommendedProducts = HomeController::rankProductsByBuyerPreferences($candidates, $user?->id, $product)
            ->take(6)
            ->values();

        return view('buyer.product-show', compact(
            'product',
            'seller',
            'stats',
            'productReviews',
            'productAvgRating',
            'totalSoldUnits',
            'isOwnStore',
            'isFollowingStore',
            'isWishlisted',
            'storeVouchers',
            'claimedVoucherIds',
            'recommendedProducts',
            'dealPricing'
        ));
    }

    public function recentlyViewed(Request $request)
    {
        $recentIds = array_values(array_filter(array_map('intval', (array) $request->session()->get('recently_viewed_product_ids', []))));
        $recentProducts = collect();

        if (! empty($recentIds)) {
            $fetched = Product::with(['approval', 'user.sellerProfile'])
                ->whereIn('id', $recentIds)
                ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
                ->get()
                ->keyBy('id');

            foreach ($recentIds as $pid) {
                if ($fetched->has($pid)) {
                    $recentProducts->push($fetched->get($pid));
                }
            }
        }

        return view('buyer.option.recently-viewed', compact('recentProducts'));
    }
}