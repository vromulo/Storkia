<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wishlists = Wishlist::with(['product.sellerProfile'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $products = $wishlists->map(function ($item) {
            return $item->product;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id'
        ]);

        $user = $request->user();
        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'status' => 'removed',
                'message' => 'Product removed from wish list.'
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id']
        ]);

        return response()->json([
            'success' => true,
            'status' => 'added',
            'message' => 'Product added to wish list.'
        ]);
    }
}