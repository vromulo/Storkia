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
            $cart = Cart::create([
                'user_id'     => $userId,
                'product_id'  => $product->id,
                'quantity'    => $newQuantity,
                'variant'     => $validated['variant'] ?? null,
                'sub_variant' => $validated['sub_variant'] ?? null,
            ]);
        }

        $dropdownPayload = $this->buildDropdownPayload($userId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Product added to your cart.',
                'cart_count'   => $dropdownPayload['cart_count'],
                'cart_items'   => $dropdownPayload['items'],
                'total_amount' => $dropdownPayload['total_amount'],
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

        $rawQty = (int) $request->input('quantity', 1);
        if ($rawQty < 1) {
            $rawQty = 1;
        }

        $product = $cart->product;
        if (! $product || $product->trashed()) {
            return response()->json(['error' => 'This product is no longer available.'], 422);
        }

        $resolved = $this->inventoryStockService->resolveProductSelection(
            $product,
            $cart->variant,
            $cart->sub_variant
        );

        $maxAvailable = max(1, (int) $resolved['available_stock']);
        $perBuyerLimit = Schema::hasColumn('products', 'per_buyer_limit')
            ? (int) ($product->per_buyer_limit ?? 0)
            : 0;

        $effectiveMax = $perBuyerLimit > 0 ? min($maxAvailable, $perBuyerLimit) : $maxAvailable;
        $clampedQuantity = min($rawQty, $effectiveMax);
        $wasClamped = $rawQty > $effectiveMax;

        $cart->update(['quantity' => $clampedQuantity]);

        try {
            broadcast(new CartQuantityUpdated($cart->id, $cart->user_id, $cart->quantity));
        } catch (\Throwable $e) {
        }

        $dropdownPayload = $this->buildDropdownPayload((int) $cart->user_id);

        return response()->json([
            'success'      => true,
            'clamped'      => $wasClamped,
            'max_stock'    => $effectiveMax,
            'message'      => $wasClamped
                ? "Maximum available quantity is {$effectiveMax}."
                : 'Cart quantity updated successfully.',
            'id'           => (int) $cart->id,
            'user_id'      => (int) $cart->user_id,
            'quantity'     => (int) $cart->quantity,
            'cart_count'   => $dropdownPayload['cart_count'],
            'cart_items'   => $dropdownPayload['items'],
            'total_amount' => $dropdownPayload['total_amount'],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $cart = Cart::findOrFail($id);
        $userId = (int) auth()->id();

        if ((int) $cart->user_id !== $userId) {
            abort(403, 'Unauthorized action.');
        }

        $deletedId = (int) $cart->id;
        $cart->delete();

        $dropdownPayload = $this->buildDropdownPayload($userId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'      => true,
                'deleted_id'   => $deletedId,
                'message'      => 'Item removed from your cart.',
                'cart_count'   => $dropdownPayload['cart_count'],
                'cart_items'   => $dropdownPayload['items'],
                'total_amount' => $dropdownPayload['total_amount'],
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Item removed from your cart.');
    }

    protected function buildDropdownPayload(int $userId): array
    {
        $cartItems = Cart::with('product')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $items = [];
        $totalAmount = 0.0;

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $basePrice = (float) ($product->price ?? 0);
            $variantStock = (int) ($product->stock_quantity ?? 0);
            $variantImage = (! empty($product->pictures) && is_array($product->pictures))
                ? asset('storage/' . $product->pictures[0])
                : null;

            if ($item->variant && isset($product->variants['items']) && is_array($product->variants['items'])) {
                foreach ($product->variants['items'] as $v) {
                    if (($v['name'] ?? '') === $item->variant) {
                        if (! $item->sub_variant) {
                            $basePrice = (float) ($v['price'] ?? $basePrice);
                            $variantStock = (int) ($v['stock'] ?? $variantStock);
                            $variantImage = (! empty($v['image'])) ? asset('storage/' . $v['image']) : $variantImage;
                        } elseif (isset($v['subs']) && is_array($v['subs'])) {
                            foreach ($v['subs'] as $sub) {
                                if (($sub['name'] ?? '') === $item->sub_variant) {
                                    $basePrice = (float) ($sub['price'] ?? $v['price'] ?? $basePrice);
                                    $variantStock = (int) ($sub['stock'] ?? $variantStock);
                                    $variantImage = (! empty($sub['image']))
                                        ? asset('storage/' . $sub['image'])
                                        : ((! empty($v['image'])) ? asset('storage/' . $v['image']) : $variantImage);
                                }
                            }
                        }
                    }
                }
            }

            $displayPricing = $this->superDealPricingService->resolveDisplayPricing($product, $basePrice);
            $itemPrice = (float) ($displayPricing['unit_price'] ?? $basePrice);
            $safeQuantity = min((int) $item->quantity, max(1, $variantStock));
            if ($variantStock <= 0) {
                $safeQuantity = 0;
            }

            $variantLabel = implode(' / ', array_filter([$item->variant, $item->sub_variant]));

            $items[] = [
                'id'           => (int) $item->id,
                'user_id'      => (int) $item->user_id,
                'product_id'   => (int) $product->id,
                'name'         => (string) $product->name,
                'url'          => route('product.show', $product->id),
                'destroy_url'  => route('cart.destroy', $item->id),
                'variantLabel' => $variantLabel !== '' ? $variantLabel : null,
                'quantity'     => $safeQuantity,
                'price'        => round($itemPrice, 2),
                'maxStock'     => $variantStock,
                'image'        => $variantImage,
                'basePrice'    => round($basePrice, 2),
                'isSyncing'    => false,
            ];

            $totalAmount += ($itemPrice * $safeQuantity);
        }

        return [
            'cart_count'   => $cartItems->count(),
            'items'        => $items,
            'total_amount' => round($totalAmount, 2),
        ];
    }
}