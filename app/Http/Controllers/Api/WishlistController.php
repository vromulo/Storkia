<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerWishlist;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $wishlistItems = BuyerWishlist::with([
            'product' => fn ($q) => $q->withTrashed()->with(['approval', 'sellerProfile', 'user.sellerProfile']),
        ])
            ->where('buyer_id', $user->id)
            ->latest('id')
            ->get();

        $products = $wishlistItems->map(function (BuyerWishlist $item) {
            $prod = $item->product;
            if (! $prod) {
                return null;
            }

            $seller = $prod->user?->sellerProfile ?? $prod->sellerProfile;
            $isDeletedOrInactive = $prod->trashed()
                || ($prod->approval && $prod->approval->status !== 'Approved')
                || (Schema::hasColumn('products', 'publish_status') && in_array($prod->publish_status, ['draft', 'inactive'], true));

            $isVacation = (bool) ($seller?->is_on_vacation ?? false);
            $stock = (int) ($prod->stock_quantity ?? 0);
            $isSoldOut = ! $isDeletedOrInactive && $stock <= 0;

            $prodArray = $prod->toArray();
            $prodArray['is_wishlisted'] = true;
            $prodArray['is_unavailable'] = $isDeletedOrInactive;
            $prodArray['is_store_on_vacation'] = $isVacation;
            $prodArray['is_sold_out'] = $isSoldOut;
            if ($seller) {
                $prodArray['seller_profile'] = $seller->toArray();
            }

            return $prodArray;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $product = Product::withTrashed()->findOrFail($validated['product_id']);

        $result = DB::transaction(function () use ($user, $product) {
            $existing = BuyerWishlist::where('buyer_id', $user->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();

                return [
                    'status'     => 'removed',
                    'wishlisted' => false,
                    'message'    => "\"{$product->name}\" removed from your Wishlist.",
                ];
            }

            BuyerWishlist::firstOrCreate([
                'buyer_id'   => $user->id,
                'product_id' => $product->id,
            ]);

            return [
                'status'     => 'added',
                'wishlisted' => true,
                'message'    => "\"{$product->name}\" saved to your Wishlist!",
            ];
        });

        $totalCount = BuyerWishlist::where('buyer_id', $user->id)->count();

        return response()->json([
            'success'        => true,
            'status'         => $result['status'],
            'wishlisted'     => $result['wishlisted'],
            'wishlist_count' => $totalCount,
            'message'        => $result['message'],
        ]);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();

        BuyerWishlist::where('buyer_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from your Wishlist.',
        ]);
    }
}