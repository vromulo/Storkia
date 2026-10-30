<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\Category;
use App\Models\Message;
use App\Models\OrderItemReview;
use App\Models\OrderServiceRating;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerStoreFollow;
use App\Models\StorkVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerController extends Controller
{
    private function getMasterCategories(): array
    {
        return [
            'Pet' => ['Dog Food & Treats', 'Cat Litter & Accessories', 'Aquariums & Fish Supplies', 'Bird Feeders & Food', 'Pet Grooming Products', 'Pet Health & Wellness'],
            'Kids' => ['Baby Clothes & Accessories', 'Toys & Games', 'Educational Materials', 'Strollers & Gear', 'Nursery Furniture', 'Safety and Health'],
            'Electronics' => ['Mobile Phones & Accessories', 'Laptops, Desktops & Monitors', 'Audio & Video Equipment', 'Smart Home Devices', 'Cameras & Photography', 'Wearable Technology'],
            'Home & Garden' => ['Kitchen Appliances', 'Furniture & Decor', 'Gardening Tools', 'Outdoor Living', 'Home Improvement Tools', 'Bedding & Bath'],
            'Women\'s' => ['Dresses & Skirts', 'Tops & Blouses', 'Activewear & Yoga Pants', 'Lingerie & Sleepwear', 'Jackets & Coats', 'Shoes & Accessories'],
            'Men\'s' => ['Suits & Blazers', 'Casual Shirts & Pants', 'Outerwear & Jackets', 'Activewear & Fitness Gear', 'Shoes & Accessories', 'Grooming Products'],
            'Health & Beauty' => ['Skincare Products', 'Haircare Solutions', 'Makeup & Cosmetics', 'Personal Care Appliances', "Men's Grooming", 'Health Supplements'],
            'Books & Media' => ['Fiction & Non-Fiction Books', 'Magazines & Periodicals', 'Music CDs & Vinyl Records', 'Movie DVDs & Blu-ray', 'Video Games & Consoles', 'Educational DVDs'],
            'Sports & Outdoors' => ['Fitness Equipment', 'Camping & Hiking Gear', 'Sports Apparel', 'Cycling & Bikes', 'Water Sports', 'Team Sports Equipment'],
            'Food & Gourmet' => ['Baking Supplies & Ingredients', 'Coffee, Tea & Beverages', 'Snacks & Candy', 'Specialty Foods', 'Organic and Health Foods', 'Meal Kits & Prepped Foods'],
            'Furniture & Office' => ['Office Desks & Chairs', 'Storage Cabinets & Shelving', 'Conference & Meeting Furniture', 'Computer Tables & Workstations', 'Ergonomic Accessories', 'Office Lighting & Fixtures'],
            'Jewelry & Watches' => ['Necklaces & Pendants', 'Rings & Earrings', 'Rings & Engagement', 'Earrings & Studs', 'Bracelets & Bangles', 'Watches for Men & Women', "Men's Watches", "Women's Watches", 'Fashion Jewelry', 'Fine Jewelry Sets', 'Jewelry Storage & Care'],
        ];
    }

    public static function buildStoreMetrics(SellerProfile $seller): array
    {
        $followersCount = Schema::hasTable('seller_store_follows')
            ? SellerStoreFollow::where('seller_profile_id', $seller->id)->where('is_following', true)->count()
            : 0;

        $followingCount = Schema::hasTable('seller_store_follows')
            ? SellerStoreFollow::where('follower_user_id', $seller->user_id)->where('is_following', true)->count()
            : 0;

        $itemReviewAvg = Schema::hasTable('order_item_reviews')
            ? (float) OrderItemReview::where('seller_profile_id', $seller->id)->avg('rating')
            : 0.0;
        $itemReviewCount = Schema::hasTable('order_item_reviews')
            ? OrderItemReview::where('seller_profile_id', $seller->id)->count()
            : 0;

        $serviceRatingAvg = Schema::hasTable('order_service_ratings')
            ? (float) OrderServiceRating::where('seller_profile_id', $seller->id)->avg('overall_average_rating')
            : 0.0;
        $serviceRatingCount = Schema::hasTable('order_service_ratings')
            ? OrderServiceRating::where('seller_profile_id', $seller->id)->count()
            : 0;

        $totalRatingsCount = $itemReviewCount + $serviceRatingCount;
        if ($totalRatingsCount > 0) {
            $weightedSum = ($itemReviewAvg * $itemReviewCount) + ($serviceRatingAvg * $serviceRatingCount);
            $avgRating = round($weightedSum / $totalRatingsCount, 1);
        } else {
            $avgRating = 0.0;
        }

        $chatResponseRate = 98;
        if ($seller->user_id && class_exists(Message::class) && Schema::hasTable('messages')) {
            try {
                $inboundBuyers = Message::where('receiver_id', $seller->user_id)
                    ->distinct('sender_id')
                    ->pluck('sender_id');

                if ($inboundBuyers->isNotEmpty()) {
                    $repliedBuyers = Message::where('sender_id', $seller->user_id)
                        ->whereIn('receiver_id', $inboundBuyers)
                        ->distinct('receiver_id')
                        ->count('receiver_id');

                    $chatResponseRate = max(50, min(100, (int) round(($repliedBuyers / $inboundBuyers->count()) * 100)));
                }
            } catch (\Throwable $e) {
                $chatResponseRate = 98;
            }
        }

        $productsCount = Product::where('user_id', $seller->user_id)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->count();

        return [
            'chat_response_rate'  => $chatResponseRate,
            'following'           => $followingCount,
            'followers'           => $followersCount,
            'ratings'             => number_format($avgRating, 1),
            'total_ratings_count' => $totalRatingsCount,
            'joined'              => $seller->created_at ? $seller->created_at->diffForHumans() : 'Recently',
            'products_count'      => $productsCount,
        ];
    }

    public function index()
    {
        $sellers = SellerProfile::latest()->get();
        $masterCategories = $this->getMasterCategories();

        $mainCategories = collect(array_keys($masterCategories))->sort()->values();
        $activeLines = $sellers->pluck('line_of_business')->filter();
        $mainCategories = $mainCategories->merge($activeLines)->unique()->sort()->values();

        return view('buyer.sellers', compact('sellers', 'mainCategories'));
    }

    public function show($id)
    {
        $seller = SellerProfile::with(['user', 'otherCategories'])->findOrFail($id);
        $masterCategories = $this->getMasterCategories();

        $products = Product::with('approval')
            ->where('user_id', $seller->user_id)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->latest()
            ->get();

        $categoriesById = collect();
        if (class_exists(Category::class) && Schema::hasTable('categories')) {
            $categoriesById = Category::all()->keyBy('id');
        }

        $primaryCat = $seller->line_of_business ?: 'Pet';
        $addedCategories = $seller->otherCategories ? $seller->otherCategories->pluck('category')->filter()->all() : [];
        $productMainCategories = $products->pluck('category')->filter()->all();

        $storeMainCategories = array_values(array_unique(array_merge([$primaryCat], $addedCategories, $productMainCategories)));

        $storeCategoryTree = [];
        foreach ($storeMainCategories as $mCat) {
            $storeCategoryTree[$mCat] = $masterCategories[$mCat] ?? [];
        }

        $products->each(function ($product) use ($categoriesById, $primaryCat, &$storeCategoryTree) {
            $mainCategoryName = ! empty($product->category) ? $product->category : $primaryCat;
            $subCategoryName = null;

            if (! empty($product->subcategory)) {
                $subCategoryName = $product->subcategory;
            } elseif (! empty($product->category_id) && $categoriesById->has($product->category_id)) {
                $cat = $categoriesById->get($product->category_id);
                if ($cat && $cat->name !== $mainCategoryName) {
                    $subCategoryName = $cat->name;
                }
            }

            $product->resolved_main_category = $mainCategoryName;
            $product->resolved_sub_category = $subCategoryName ?: 'Uncategorized';

            if (! isset($storeCategoryTree[$mainCategoryName])) {
                $storeCategoryTree[$mainCategoryName] = [];
            }
            if ($subCategoryName && ! in_array($subCategoryName, $storeCategoryTree[$mainCategoryName], true)) {
                $storeCategoryTree[$mainCategoryName][] = $subCategoryName;
            }
        });

        $subCategories = collect($storeCategoryTree)->flatten()->unique()->sort()->values();
        $stats = self::buildStoreMetrics($seller);

        $user = auth()->user();
        $isOwnStore = $user && (int) $user->id === (int) $seller->user_id;
        $isFollowing = false;

        if ($user && Schema::hasTable('seller_store_follows')) {
            $isFollowing = SellerStoreFollow::where('seller_profile_id', $seller->id)
                ->where('follower_user_id', $user->id)
                ->where('is_following', true)
                ->exists();
        }

        $storeVouchers = StorkVoucher::where('seller_profile_id', $seller->id)
            ->where('scope', 'store')
            ->where('status', 'active')
            ->where('visibility', 'claimable')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->orderBy('min_spend')
            ->get();

        $claimedVoucherIds = $user
            ? BuyerVoucherClaim::where('buyer_id', $user->id)->pluck('voucher_id')->all()
            : [];

        return view('buyer.seller-profile-show', [
            'title'               => $seller->business_name,
            'seller'              => $seller,
            'products'            => $products,
            'storeMainCategories' => $storeMainCategories,
            'storeCategoryTree'   => $storeCategoryTree,
            'subCategories'       => $subCategories,
            'stats'               => $stats,
            'isOwnStore'          => $isOwnStore,
            'isFollowing'         => $isFollowing,
            'storeVouchers'       => $storeVouchers,
            'claimedVoucherIds'   => $claimedVoucherIds,
        ]);
    }

    public function toggleFollow(Request $request, $sellerId)
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Authentication required.'], 401);
            }
            return redirect()->route('login');
        }

        $seller = SellerProfile::findOrFail($sellerId);

        if ((int) $user->id === (int) $seller->user_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow your own store.',
                ], 422);
            }
            return back()->withErrors(['follow' => 'You cannot follow your own store.']);
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
                ? "You are now following {$seller->business_name}! A 1-time Follower Voucher ({$rewardIssuedCode} — ₱30 OFF, Min. ₱150) was automatically added to your Voucher Wallet!"
                : "You are now following {$seller->business_name}.")
            : "You unfollowed {$seller->business_name}.";

        if ($request->expectsJson()) {
            return response()->json([
                'success'             => true,
                'is_following'        => (bool) $followRecord->is_following,
                'followers_count'     => $followersCount,
                'reward_voucher_code' => $rewardIssuedCode,
                'message'             => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function followingList(Request $request)
    {
        $user = $request->user();
        $follows = SellerStoreFollow::with('sellerProfile')
            ->where('follower_user_id', $user->id)
            ->where('is_following', true)
            ->latest('updated_at')
            ->get();

        return view('buyer.option.following', compact('follows'));
    }
}