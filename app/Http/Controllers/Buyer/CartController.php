<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::with(['product.user.sellerProfile'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
        
        $suggestedProducts = Product::where('stock_quantity', '>', 0)
            ->inRandomOrder()
            ->take(6)
            ->get();
        
        return view('buyer.cart', compact('cartItems', 'suggestedProducts'));
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
        } else {
            Cart::create([
                'user_id'     => auth()->id(),
                'product_id'  => $request->product_id,
                'quantity'    => $request->quantity,
                'variant'     => $request->variant,
                'sub_variant' => $request->sub_variant
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Product added to your cart.');
    }

    public function update(Request $request, $id)
    {
        // Use findOrFail to bypass implicit route model binding parameter mismatches
        $cart = Cart::findOrFail($id);

        // Use != instead of !== to prevent strict int vs string type mismatch errors
        if ($cart->user_id != auth()->id()) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
        ]);

        $cart->update(['quantity' => $request->quantity]);

        return response()->json([
            'success'  => true, 
            'message'  => 'Cart quantity updated successfully.',
            'id'       => $cart->id,
            'user_id'  => $cart->user_id,
            'quantity' => $cart->quantity
        ]);
    }

    public function destroy($id)
    {
        $cart = Cart::findOrFail($id);

        if ($cart->user_id == auth()->id()) {
            $cart->delete();
        }

        return redirect()->route('cart.index')->with('success', 'Item removed from your cart.');
    }
}