<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerWishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlistItems = BuyerWishlist::with([
            'product' => fn ($q) => $q->withTrashed()->with(['approval', 'user.sellerProfile']),
        ])
            ->where('buyer_id', $user->id)
            ->latest('id')
            ->get();

        return view('buyer.option.wishlist', compact('wishlistItems'));
    }

    public function toggle(Request $request, int $productId)
    {
        $user = $request->user();

        $product = Product::withTrashed()->findOrFail($productId);

        $result = DB::transaction(function () use ($user, $product) {
            $existing = BuyerWishlist::where('buyer_id', $user->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                return [
                    'wishlisted' => false,
                    'message'    => "\"{$product->name}\" removed from your Wishlist.",
                ];
            }

            BuyerWishlist::firstOrCreate([
                'buyer_id'   => $user->id,
                'product_id' => $product->id,
            ]);

            return [
                'wishlisted' => true,
                'message'    => "\"{$product->name}\" saved to your Wishlist!",
            ];
        });

        $totalCount = BuyerWishlist::where('buyer_id', $user->id)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'success'        => true,
                'wishlisted'     => $result['wishlisted'],
                'wishlist_count' => $totalCount,
                'message'        => $result['message'],
            ]);
        }

        return back()->with('success', $result['message']);
    }

    public function destroy(Request $request, int $productId)
    {
        $user = $request->user();

        BuyerWishlist::where('buyer_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from your Wishlist.',
            ]);
        }

        return back()->with('success', 'Item removed from your Wishlist.');
    }
}