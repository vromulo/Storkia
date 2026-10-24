<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Events\CartQuantityUpdated;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::with(['product.sellerProfile'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $cartItems
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'  => 'required|exists:products,id',
            'quantity'    => 'required|integer|min:1|max:1000000',
            'variant'     => 'nullable|string',
            'sub_variant' => 'nullable|string'
        ]);

        $cart = Cart::where('user_id', auth()->id())
                    ->where('product_id', $request->product_id)
                    ->where('variant', $request->variant)
                    ->where('sub_variant', $request->sub_variant)
                    ->first();

        if ($cart) {
            $newQuantity = min($cart->quantity + $request->quantity, 1000000);
            $cart->update(['quantity' => $newQuantity]);
            
            broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
        } else {
            $cart = Cart::create([
                'user_id'     => auth()->id(),
                'product_id'  => $request->product_id,
                'quantity'    => $request->quantity,
                'variant'     => $request->variant,
                'sub_variant' => $request->sub_variant
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'data' => $cart
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cart = Cart::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
        ]);

        $cart->update(['quantity' => $request->quantity]);
        
        broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));

        return response()->json([
            'success'  => true, 
            'message'  => 'Cart quantity updated successfully.',
            'data'     => $cart
        ]);
    }

    public function destroy($id)
    {
        $cart = Cart::where('user_id', auth()->id())->findOrFail($id);
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from your cart.'
        ]);
    }
}