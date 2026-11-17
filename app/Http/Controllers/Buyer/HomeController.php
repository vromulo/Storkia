<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerStoreFollow;
use App\Services\BuyerRewardService;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public const HOME_BATCH_SIZE = 12;

    public function __construct(
        protected BuyerRewardService $buyerRewardService,
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index(Request $request)
    {
        $allApprovedProducts = Product::with(['approval', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($query) {
                $query->where('status', 'Approved');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $user = $request->user();
        $products = $this->rankProductsByBuyerPreferences($allApprovedProducts, $user?->id);
        $batchSize = self::HOME_BATCH_SIZE;
        $totalProductsCount = $products->count();

        if ($request->boolean('batch') || ($request->ajax() && $request->has('page'))) {
            $rawExclude = $request->query('exclude', '');
            $excludedIds = is_array($rawExclude)
                ? array_values(array_unique(array_filter(array_map('intval', $rawExclude))))
                : array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $rawExclude)))));

            if (! empty($excludedIds)) {
                $remaining = $products
                    ->reject(fn (Product $prod) => in_array((int) $prod->id, $excludedIds, true))
                    ->values();
                $batchItems = $remaining->take($batchSize)->values();
                $hasMore = $remaining->count() > $batchItems->count();
            } else {
                $page = max(1, (int) $request->query('page', 1));
                $batchItems = $products->forPage($page, $batchSize)->values();
                $hasMore = ($page * $batchSize) < $totalProductsCount;
            }

            $html = '';
            $loadedIds = [];

            foreach ($batchItems as $product) {
                $pid = (int) $product->id;
                $loadedIds[] = $pid;
                $cardHtml = Blade::render('<x-storefront.seller-product-card :product="$product" />', [
                    'product' => $product,
                ]);
                $html .= '<div data-product-id="' . $pid . '" class="transition-all duration-300 ease-out">' . $cardHtml . '</div>';
            }

            return response()->json([
                'success'    => true,
                'html'       => $html,
                'loaded_ids' => $loadedIds,
                'count'      => count($loadedIds),
                'total'      => $totalProductsCount,
                'has_more'   => $hasMore,
            ]);
        }

        $initialProducts = $products->take($batchSize)->values();
        $youMayAlsoLike = $products->take(18)->values();

        $rewardWallet = null;
        if ($user && $user->role === 'Buyer') {
            $this->orderCompletionService->runLifecycleMaintenance(buyerId: (int) $user->id);
            $grant = $this->buyerRewardService->grantDailyLoginSpinIfEligible((int) $user->id);
            $rewardWallet = $grant['wallet'];
        }

        return view('buyer.home', compact(
            'products',
            'initialProducts',
            'batchSize',
            'totalProductsCount',
            'youMayAlsoLike',
            'rewardWallet'
        ));
    }

    public static function rankProductsByBuyerPreferences(Collection $products, ?int $buyerId = null, ?Product $contextProduct = null): Collection
    {
        $preferredCategories = [];
        $preferredSubcategories = [];
        $followedSellerUserIds = [];

        if ($contextProduct) {
            if ($contextProduct->category) {
                $preferredCategories[$contextProduct->category] = ($preferredCategories[$contextProduct->category] ?? 0) + 5;
            }
            if ($contextProduct->subcategory) {
                $preferredSubcategories[$contextProduct->subcategory] = ($preferredSubcategories[$contextProduct->subcategory] ?? 0) + 8;
            }
        }

        if ($buyerId) {
            $orderedProductIds = OrderItem::whereHas('sellerOrder', fn ($q) => $q->where('buyer_id', $buyerId))
                ->latest('id')
                ->limit(40)
                ->pluck('product_id')
                ->filter()
                ->all();

            if (! empty($orderedProductIds)) {
                $orderedProducts = Product::withTrashed()->whereIn('id', $orderedProductIds)->get(['id', 'category', 'subcategory']);
                foreach ($orderedProducts as $op) {
                    if ($op->category) {
                        $preferredCategories[$op->category] = ($preferredCategories[$op->category] ?? 0) + 4;
                    }
                    if ($op->subcategory) {
                        $preferredSubcategories[$op->subcategory] = ($preferredSubcategories[$op->subcategory] ?? 0) + 6;
                    }
                }
            }

            $cartProductIds = Cart::where('user_id', $buyerId)->pluck('product_id')->filter()->all();
            if (! empty($cartProductIds)) {
                $cartProducts = Product::whereIn('id', $cartProductIds)->get(['id', 'category', 'subcategory']);
                foreach ($cartProducts as $cp) {
                    if ($cp->category) {
                        $preferredCategories[$cp->category] = ($preferredCategories[$cp->category] ?? 0) + 3;
                    }
                    if ($cp->subcategory) {
                        $preferredSubcategories[$cp->subcategory] = ($preferredSubcategories[$cp->subcategory] ?? 0) + 5;
                    }
                }
            }

            if (Schema::hasTable('seller_store_follows')) {
                $followedProfileIds = SellerStoreFollow::where('follower_user_id', $buyerId)
                    ->where('is_following', true)
                    ->pluck('seller_profile_id')
                    ->all();

                if (! empty($followedProfileIds)) {
                    $followedSellerUserIds = \App\Models\SellerProfile::whereIn('id', $followedProfileIds)
                        ->pluck('user_id')
                        ->all();
                }
            }
        }

        if (empty($preferredCategories) && empty($preferredSubcategories) && empty($followedSellerUserIds)) {
            return $products->values();
        }

        return $products->sortByDesc(function (Product $prod) use ($preferredCategories, $preferredSubcategories, $followedSellerUserIds, $contextProduct) {
            if ($contextProduct && (int) $prod->id === (int) $contextProduct->id) {
                return -999999;
            }

            $score = 0;

            if ($prod->subcategory && isset($preferredSubcategories[$prod->subcategory])) {
                $score += $preferredSubcategories[$prod->subcategory] * 100;
            }

            if ($prod->category && isset($preferredCategories[$prod->category])) {
                $score += $preferredCategories[$prod->category] * 50;
            }

            if (in_array((int) $prod->user_id, array_map('intval', $followedSellerUserIds), true)) {
                $score += 180;
            }

            if ((float) ($prod->discount ?? 0) > 0) {
                $score += 20;
            }

            $timestampFraction = (($prod->created_at?->timestamp ?? 0) / 10000000000);
            $idTieBreaker = ((int) $prod->id) / 100000000000000;

            return $score + $timestampFraction + $idTieBreaker;
        })->values();
    }
}