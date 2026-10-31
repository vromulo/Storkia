<?php

namespace App\Http\Controllers\Buyer;

use App\Events\CartQuantityUpdated;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryStockService;
use App\Services\SuperDealPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected SuperDealPricingService $superDealPricingService
    ) {}

    public function index(Request $request)
    {
        $userId = (int) auth()->id();

        $cartItems = Cart::with([
            'product' => fn ($q) => $q->withTrashed()->with(['approval', 'user.sellerProfile']),
        ])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $candidates = Product::with(['approval', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->latest()
            ->limit(24)
            ->get();

        $suggestedProducts = HomeController::rankProductsByBuyerPreferences($candidates, $userId)
            ->take(6)
            ->values();

        return view('buyer.cart', compact('cartItems', 'suggestedProducts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'  => ['required', 'exists:products,id'],
            'quantity'    => ['required', 'integer', 'min:1', 'max:99999'],
            'variant'     => ['nullable', 'string'],
            'sub_variant' => ['nullable', 'string'],
        ]);

        $userId = (int) auth()->id();

        /** @var Product $product */
        $product = Product::with(['approval', 'user.sellerProfile'])->findOrFail($validated['product_id']);

        if (
            $product->trashed()
            || ($product->approval && $product->approval->status !== 'Approved')
            || (Schema::hasColumn('products', 'publish_status') && in_array($product->publish_status, ['draft', 'inactive'], true))
        ) {
            throw ValidationException::withMessages([
                'product' => "Product \"{$product->name}\" is currently unavailable.",
            ]);
        }

        // Enforce Store Vacation Mode Guard (SEL-PROF-009 / BUY-SRCH-011)
        $this->superDealPricingService->assertStoreNotOnVacation($product->user?->sellerProfile);

        $resolved = $this->inventoryStockService->resolveProductSelection(
            $product,
            $validated['variant'] ?? null,
            $validated['sub_variant'] ?? null
        );

        $cart = Cart::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->where('variant', $validated['variant'] ?? null)
            ->where('sub_variant', $validated['sub_variant'] ?? null)
            ->first();

        $newQuantity = ($cart ? (int) $cart->quantity : 0) + (int) $validated['quantity'];

        if ($newQuantity > (int) $resolved['available_stock']) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$resolved['available_stock']} unit(s) available for \"{$product->name}\".",
            ]);
        }

        // Enforce Per-Buyer Purchase Limit (BUY-PDP-005)
        $perBuyerLimit = Schema::hasColumn('products', 'per_buyer_limit')
            ? (int) ($product->per_buyer_limit ?? 0)
            : 0;

        if ($perBuyerLimit > 0) {
            $previouslyPurchased = (int) OrderItem::where('product_id', $product->id)
                ->whereHas('sellerOrder', function ($q) use ($userId) {
                    $q->where('buyer_id', $userId)
                      ->whereNotIn('status', ['cancelled', 'rejected', 'returned_to_seller']);
                })
                ->sum('quantity');

            $otherCartUnits = (int) Cart::where('user_id', $userId)
                ->where('product_id', $product->id)
                ->when($cart, fn ($q) => $q->where('id', '!=', $cart->id))
                ->sum('quantity');

            if (($previouslyPurchased + $otherCartUnits + $newQuantity) > $perBuyerLimit) {
                throw ValidationException::withMessages([
                    'quantity' => "Purchase limit reached for \"{$product->name}\": Maximum {$perBuyerLimit} unit(s) allowed per buyer.",
                ]);
            }
        }

        if ($cart) {
            $cart->update(['quantity' => $newQuantity]);
            try {
                broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
            } catch (\Throwable $e) {
            }
        } else {
            Cart::create([
                'user_id'     => $userId,
                'product_id'  => $product->id,
                'quantity'    => $newQuantity,
                'variant'     => $validated['variant'] ?? null,
                'sub_variant' => $validated['sub_variant'] ?? null,
            ]);
        }

        $cartCount = Cart::where('user_id', $userId)->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => 'Product added to your cart.',
                'cart_count' => $cartCount,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Product added to your cart.');
    }

    public function update(Request $request, $id)
    {
        $cart = Cart::with(['product.approval', 'product.user.sellerProfile'])->findOrFail($id);

        if ((int) $cart->user_id !== (int) auth()->id()) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
        ], [
            'quantity.min' => 'Quantity must be at least 1.',
        ]);

        $product = $cart->product;
        if (! $product || $product->trashed()) {
            return response()->json(['error' => 'This product is no longer available.'], 422);
        }

        $resolved = $this->inventoryStockService->resolveProductSelection(
            $product,
            $cart->variant,
            $cart->sub_variant
        );

        $requestedQty = (int) $validated['quantity'];
        if ($requestedQty > (int) $resolved['available_stock']) {
            return response()->json([
                'error'   => "Only {$resolved['available_stock']} available.",
                'message' => "Only {$resolved['available_stock']} available.",
            ], 422);
        }

        $perBuyerLimit = Schema::hasColumn('products', 'per_buyer_limit')
            ? (int) ($product->per_buyer_limit ?? 0)
            : 0;

        if ($perBuyerLimit > 0 && $requestedQty > $perBuyerLimit) {
            return response()->json([
                'error'   => "Maximum {$perBuyerLimit} unit(s) allowed per buyer.",
                'message' => "Maximum {$perBuyerLimit} unit(s) allowed per buyer.",
            ], 422);
        }

        $cart->update(['quantity' => $requestedQty]);

        try {
            broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Cart quantity updated successfully.',
            'id'       => $cart->id,
            'user_id'  => $cart->user_id,
            'quantity' => $cart->quantity,
        ]);
    }

    public function destroy($id)
    {
        $cart = Cart::findOrFail($id);

        if ((int) $cart->user_id !== (int) auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $cart->delete();

        return redirect()->route('cart.index')->with('success', 'Item removed from your cart.');
    }
}