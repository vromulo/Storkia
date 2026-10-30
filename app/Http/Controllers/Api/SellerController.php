<?php

namespace App\Http\Controllers\Api;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $followedIds = ($user && Schema::hasTable('seller_store_follows'))
            ? SellerStoreFollow::where('follower_user_id', $user->id)
                ->where('is_following', true)
                ->pluck('seller_profile_id')
                ->all()
            : [];

        $sellers = SellerProfile::latest()->get()->map(function (SellerProfile $s) use ($followedIds, $user) {
            $metrics = BuyerSellerController::buildStoreMetrics($s);
            return array_merge($s->toArray(), [
                'stats'        => $metrics,
                'is_following' => in_array($s->id, $followedIds, true),
                'is_own_store' => $user && (int) $user->id === (int) $s->user_id,
            ]);
        });

        $categories = $sellers->pluck('line_of_business')->filter()->unique()->sort()->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'sellers'    => $sellers,
                'categories' => $categories,
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $seller = SellerProfile::with(['user', 'otherCategories'])->findOrFail($id);
        $user = $request->user();

        $productsQuery = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->where('user_id', $seller->user_id)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'));

        if (Schema::hasColumn('products', 'publish_status')) {
            $productsQuery->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        $products = $productsQuery->latest()->get();

        $primaryCat = $seller->line_of_business ?: 'Pet';
        $addedCategories = $seller->otherCategories
            ? $seller->otherCategories->pluck('category')->filter()->all()
            : [];
        $productMainCategories = $products->pluck('category')->filter()->all();
        $storeMainCategories = array_values(array_unique(array_merge([$primaryCat], $addedCategories, $productMainCategories)));

        $subCategories = $products->pluck('subcategory')->filter()->unique()->sort()->values();

        if ($products->isNotEmpty()) {
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

            $wishlistedIds = ($user && Schema::hasTable('buyer_wishlists'))
                ? BuyerWishlist::where('buyer_id', $user->id)
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

        $stats = BuyerSellerController::buildStoreMetrics($seller);
        $isOwnStore = $user && (int) $user->id === (int) $seller->user_id;

        $isFollowing = false;
        if ($user && Schema::hasTable('seller_store_follows')) {
            $isFollowing = SellerStoreFollow::where('seller_profile_id', $seller->id)
                ->where('follower_user_id', $user->id)
                ->where('is_following', true)
                ->exists();
        }

        $claimedVoucherIds = $user && Schema::hasTable('buyer_voucher_claims')
            ? BuyerVoucherClaim::where('buyer_id', $user->id)->pluck('voucher_id')->all()
            : [];

        $storeVouchers = Schema::hasTable('stork_vouchers')
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

        $sellerArray = $seller->toArray();
        $sellerArray['stats'] = $stats;
        $sellerArray['is_following'] = $isFollowing;
        $sellerArray['is_own_store'] = $isOwnStore;

        return response()->json([
            'success' => true,
            'data'    => [
                'seller'                => $sellerArray,
                'stats'                 => $stats,
                'is_own_store'          => $isOwnStore,
                'is_following'          => $isFollowing,
                'store_main_categories' => $storeMainCategories,
                'subcategories'         => $subCategories,
                'store_vouchers'        => $storeVouchers,
                'products'              => $products,
            ],
        ]);
    }

    public function toggleFollow(Request $request, $sellerId): JsonResponse
    {
        $user = $request->user();
        $seller = SellerProfile::findOrFail($sellerId);

        if ((int) $user->id === (int) $seller->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow your own store.',
            ], 422);
        }

        $rewardIssuedCode = null;

        $followRecord = DB::transaction(function () use ($seller, $user, &$rewardIssuedCode) {
            $record = SellerStoreFollow::where('seller_profile_id', $seller->id)
                ->where('follower_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $code = 'FLW' . $seller->id . 'U' . $user->id;
                $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $code), 0, 12));
                if (strlen($code) < 4) {
                    $code = str_pad($code, 4, 'X');
                }

                $voucher = StorkVoucher::firstOrCreate(
                    ['code' => $code],
                    [
                        'seller_profile_id' => $seller->id,
                        'created_by'        => $seller->user_id,
                        'title'             => $seller->business_name . ' Follower Welcome ₱30 Off',
                        'description'       => "1-Time Store Follower Reward: ₱30.00 off at {$seller->business_name} (Min. spend ₱150.00).",
                        'scope'             => 'store',
                        'discount_type'     => 'fixed',
                        'discount_value'    => 30.00,
                        'max_discount_cap'  => null,
                        'min_spend'         => 150.00,
                        'total_usage_limit' => 1,
                        'used_count'        => 0,
                        'per_buyer_limit'   => 1,
                        'visibility'        => 'follower_reward',
                        'starts_at'         => now()->subMinute(),
                        'ends_at'           => now()->addDays(30),
                        'status'            => 'active',
                    ]
                );

                BuyerVoucherClaim::firstOrCreate(
                    [
                        'voucher_id' => $voucher->id,
                        'buyer_id'   => $user->id,
                    ],
                    [
                        'times_used' => 0,
                        'claimed_at' => now(),
                    ]
                );

                $rewardIssuedCode = $voucher->code;

                return SellerStoreFollow::create([
                    'seller_profile_id'   => $seller->id,
                    'follower_user_id'    => $user->id,
                    'is_following'        => true,
                    'reward_voucher_id'   => $voucher->id,
                    'reward_voucher_code' => $voucher->code,
                    'reward_issued_at'    => now(),
                ]);
            }

            $record->update([
                'is_following' => ! $record->is_following,
            ]);

            return $record;
        });

        $followersCount = SellerStoreFollow::where('seller_profile_id', $seller->id)
            ->where('is_following', true)
            ->count();

        $message = $followRecord->is_following
            ? ($rewardIssuedCode
                ? "You are now following {$seller->business_name}! A 1-time Follower Voucher ({$rewardIssuedCode} ₱30 OFF, Min. ₱150) was automatically added to your Voucher Wallet!"
                : "You are now following {$seller->business_name}.")
            : "You unfollowed {$seller->business_name}.";

        return response()->json([
            'success'             => true,
            'is_following'        => (bool) $followRecord->is_following,
            'followers_count'     => $followersCount,
            'reward_voucher_code' => $rewardIssuedCode,
            'message'             => $message,
        ]);
    }

    public function followingList(Request $request): JsonResponse
    {
        $user = $request->user();

        $follows = SellerStoreFollow::with('sellerProfile')
            ->where('follower_user_id', $user->id)
            ->where('is_following', true)
            ->latest('updated_at')
            ->get()
            ->map(function (SellerStoreFollow $follow) {
                $store = $follow->sellerProfile;
                if (! $store) {
                    return null;
                }
                $metrics = BuyerSellerController::buildStoreMetrics($store);
                return [
                    'id'                  => $follow->id,
                    'seller_profile_id'   => $store->id,
                    'reward_voucher_code' => $follow->reward_voucher_code,
                    'reward_issued_at'    => $follow->reward_issued_at?->toISOString(),
                    'seller_profile'      => array_merge($store->toArray(), [
                        'stats'        => $metrics,
                        'is_following' => true,
                    ]),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $follows,
        ]);
    }
}