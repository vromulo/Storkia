<?php

namespace App\Http\Controllers\Api;

use App\Events\CartQuantityUpdated;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cartItems = Cart::with(['product.approval', 'product.sellerProfile', 'product.user.sellerProfile'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function (Cart $item) {
                $prod = $item->product;
                if ($prod && ! $prod->relationLoaded('sellerProfile') && $prod->user?->sellerProfile) {
                    $prod->setRelation('sellerProfile', $prod->user->sellerProfile);
                }
                return $item;
            });

        $suggestedQuery = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'));

        if (Schema::hasColumn('products', 'publish_status')) {
            $suggestedQuery->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        $suggestedProducts = $suggestedQuery->inRandomOrder()->take(6)->get();

        return response()->json([
            'success'            => true,
            'data'               => $cartItems,
            'suggested_products' => $suggestedProducts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'  => ['required', 'exists:products,id'],
            'quantity'    => ['required', 'integer', 'min:1', 'max:1000000'],
            'variant'     => ['nullable', 'string'],
            'sub_variant' => ['nullable', 'string'],
        ]);

        $product = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->findOrFail($request->input('product_id'));

        $seller = $product->user?->sellerProfile ?? $product->sellerProfile;
        if ($seller && (bool) ($seller->is_on_vacation ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'This store is currently on vacation and is temporarily not accepting orders.',
            ], 422);
        }

        $perBuyerLimit = (int) ($product->per_buyer_limit ?? 0);
        $maxCap = $perBuyerLimit > 0 ? $perBuyerLimit : 1000000;

        $userId = $request->user()->id;
        $variant = $request->input('variant');
        $subVariant = $request->input('sub_variant');

        $cart = Cart::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->where('variant', $variant)
            ->where('sub_variant', $subVariant)
            ->first();

        if ($cart) {
            $newQuantity = min($cart->quantity + (int) $request->input('quantity'), $maxCap);
            $cart->update(['quantity' => $newQuantity]);

            try {
                broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
            } catch (\Throwable $e) {
            }
        } else {
            $cart = Cart::create([
                'user_id'     => $userId,
                'product_id'  => $product->id,
                'quantity'    => min((int) $request->input('quantity'), $maxCap),
                'variant'     => $variant,
                'sub_variant' => $subVariant,
            ]);
        }

        $cart->load(['product.sellerProfile', 'product.user.sellerProfile']);

        return response()->json([
            'success' => true,
            'message' => 'Product added to your cart.',
            'data'    => $cart,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $cart = Cart::with('product')->where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $perBuyerLimit = (int) ($cart->product?->per_buyer_limit ?? 0);
        $newQty = (int) $request->input('quantity');
        if ($perBuyerLimit > 0 && $newQty > $perBuyerLimit) {
            return response()->json([
                'success' => false,
                'message' => "Purchase limit reached: You can buy at most {$perBuyerLimit} unit(s) of this item.",
            ], 422);
        }

        $cart->update(['quantity' => $newQty]);

        try {
            broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Cart quantity updated successfully.',
            'data'     => $cart,
            'id'       => $cart->id,
            'user_id'  => $cart->user_id,
            'quantity' => $cart->quantity,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->findOrFail($id);
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from your cart.',
        ]);
    }
}