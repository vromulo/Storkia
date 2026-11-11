<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductRestockSubscription;
use App\Models\SellerProfile;
use App\Models\SellerStoreFollow;
use App\Models\StorkVoucher;
use App\Notifications\OrderLifecycleNotification;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use Illuminate\Http\JsonResponse;
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
        StorkSchemaBootstrapService::ensureTablesExist();

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
            ? OrderItemReview::with(['buyer', 'orderItem'])
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
        $upvotedReviewIds = [];
        $hasRestockSubscription = false;

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

        if ($user && Schema::hasTable('product_review_upvotes') && $productReviews->isNotEmpty()) {
            $upvotedReviewIds = DB::table('product_review_upvotes')
                ->where('user_id', $user->id)
                ->whereIn('review_id', $productReviews->pluck('id')->all())
                ->pluck('review_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($user && Schema::hasTable('product_restock_subscriptions')) {
            $hasRestockSubscription = ProductRestockSubscription::where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
        }

        $productQuestions = Schema::hasTable('product_questions')
            ? ProductQuestion::with(['buyer', 'answeredBy'])
                ->where('product_id', $product->id)
                ->where('is_visible', true)
                ->latest('id')
                ->limit(30)
                ->get()
            : collect();

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
            'upvotedReviewIds',
            'hasRestockSubscription',
            'productQuestions',
            'storeVouchers',
            'claimedVoucherIds',
            'recommendedProducts',
            'dealPricing'
        ));
    }

    public function subscribeRestock(Request $request, Product $product)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $validated = $request->validate([
            'variant_name'     => ['nullable', 'string', 'max:100'],
            'sub_variant_name' => ['nullable', 'string', 'max:100'],
        ]);

        $variantName = trim((string) ($validated['variant_name'] ?? '')) ?: null;
        $subVariantName = trim((string) ($validated['sub_variant_name'] ?? '')) ?: null;

        ProductRestockSubscription::updateOrCreate(
            [
                'product_id'       => $product->id,
                'user_id'          => $user->id,
                'variant_name'     => $variantName,
                'sub_variant_name' => $subVariantName,
            ],
            [
                'status'      => 'active',
                'notified_at' => null,
            ]
        );

        $msg = 'You are now subscribed to Back-in-Stock alerts for "' . $product->name . '"! We will notify you as soon as stock is replenished.';

        if ($request->expectsJson()) {
            return response()->json([
                'success'    => true,
                'subscribed' => true,
                'message'    => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function askQuestion(Request $request, Product $product)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $seller = $product->user?->sellerProfile
            ?? ($product->user_id ? SellerProfile::where('user_id', $product->user_id)->first() : null);

        if (! $seller) {
            abort(404);
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'question.required' => 'Please enter your question about this product.',
            'question.min'      => 'Your question must be at least 5 characters.',
        ]);

        $q = ProductQuestion::create([
            'product_id'        => $product->id,
            'seller_profile_id' => $seller->id,
            'buyer_id'          => $user->id,
            'question'          => trim($validated['question']),
            'is_visible'        => true,
        ]);

        try {
            $seller->user?->notify(new OrderLifecycleNotification(
                title: 'New Buyer Question on ' . $product->name,
                message: "{$user->first_name} asked: \"" . substr($q->question, 0, 120) . '"',
                type: 'info',
                url: route('seller.ratings.index', ['tab' => 'questions'])
            ));
        } catch (\Throwable $e) {
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Your question has been posted! You will be notified when the seller replies.',
                'question' => [
                    'id'         => $q->id,
                    'buyer_name' => substr($user->first_name, 0, 1) . '***',
                    'question'   => $q->question,
                    'answer'     => null,
                    'created_at' => $q->created_at?->diffForHumans(),
                ],
            ]);
        }

        return back()->with('success', 'Your question has been posted! The seller has been notified.');
    }

    public function toggleReviewHelpful(Request $request, OrderItemReview $review): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $userId = (int) $request->user()->id;

        $result = DB::transaction(function () use ($review, $userId) {
            $exists = DB::table('product_review_upvotes')
                ->where('review_id', $review->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                DB::table('product_review_upvotes')
                    ->where('review_id', $review->id)
                    ->where('user_id', $userId)
                    ->delete();
                $upvoted = false;
            } else {
                DB::table('product_review_upvotes')->insert([
                    'review_id'  => $review->id,
                    'user_id'    => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $upvoted = true;
            }

            $count = (int) DB::table('product_review_upvotes')->where('review_id', $review->id)->count();
            if (Schema::hasColumn('order_item_reviews', 'helpful_count')) {
                $review->update(['helpful_count' => $count]);
            }

            return [
                'upvoted'       => $upvoted,
                'helpful_count' => $count,
            ];
        });

        return response()->json([
            'success'       => true,
            'upvoted'       => $result['upvoted'],
            'helpful_count' => $result['helpful_count'],
        ]);
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