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
        // Load cart items with product and nested seller relationships
        $cartItems = Cart::with(['product.user.sellerProfile'])->where('user_id', auth()->id())->latest()->get();
        
        // Fetch real products from the database for the "You Might Like" section
        $suggestedProducts = Product::where('stock_quantity', '>', 0)
            ->inRandomOrder()
            ->take(6)
            ->get();
        
        return view('buyer.cart', compact('cartItems', 'suggestedProducts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:1000000',
            'variant' => 'nullable|string',
            'sub_variant' => 'nullable|string'
        ]);

        // Look for the EXACT same product with the exact same chosen variants in the user's cart
        $cart = Cart::where('user_id', auth()->id())
                    ->where('product_id', $request->product_id)
                    ->where('variant', $request->variant)
                    ->where('sub_variant', $request->sub_variant)
                    ->first();

        // Stack the quantity if the exact item exists, otherwise create a new entry
        if ($cart) {
            $newQuantity = min($cart->quantity + $request->quantity, 1000000);
            $cart->update(['quantity' => $newQuantity]);
        } else {
            Cart::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'variant' => $request->variant,
                'sub_variant' => $request->sub_variant
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Product added to your cart.');
    }

    public function destroy(Cart $cart)
    {
        if ($cart->user_id === auth()->id()) {
            $cart->delete();
        }

        return redirect()->route('cart.index')->with('success', 'Item removed from your cart.');
    }
}