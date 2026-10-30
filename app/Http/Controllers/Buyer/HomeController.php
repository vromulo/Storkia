<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerStoreFollow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $allApprovedProducts = Product::with(['approval', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($query) {
                $query->where('status', 'Approved');
            })
            ->latest()
            ->get();

        $user = $request->user();
        $products = $this->rankProductsByBuyerPreferences($allApprovedProducts, $user?->id);
        $youMayAlsoLike = $products->take(18)->values();

        return view('buyer.home', compact('products', 'youMayAlsoLike'));
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
            // 1. Weight categories & subcategories from Buyer's past Orders
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

            // 2. Weight categories & subcategories from Buyer's active Cart
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

            // 3. Boost products from Stores the Buyer follows
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

            return $score + (($prod->created_at?->timestamp ?? 0) / 10000000000);
        })->values();
    }
}