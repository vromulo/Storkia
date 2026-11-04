<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BundleDealController extends Controller
{
    public function __construct(
        protected SuperDealPricingService $pricingService
    ) {}

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        $this->pricingService->expireEndedPromotions();

        $selectedCategory = $request->query('category');
        $bundleTypeFilter = $request->query('type', 'all');

        $rawBundles = Schema::hasTable('bundle_deals')
            ? collect(
                DB::table('bundle_deals')
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->orderBy('ends_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
            )
            : collect();

        $bundles = collect();
        $categoriesCount = [];

        foreach ($rawBundles as $rb) {
            if ($bundleTypeFilter !== 'all' && $rb->bundle_type !== $bundleTypeFilter) {
                continue;
            }

            $seller = SellerProfile::find($rb->seller_profile_id);
            if (! $seller || (bool) ($seller->is_on_vacation ?? false)) {
                continue;
            }

            $appIds = is_string($rb->applicable_product_ids)
                ? (json_decode($rb->applicable_product_ids, true) ?: [])
                : (array) $rb->applicable_product_ids;

            $appIds = array_values(array_unique(array_map('intval', $appIds)));
            if (empty($appIds)) {
                continue;
            }

            $products = Product::with('approval')
                ->whereIn('id', $appIds)
                ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
                ->get()
                ->filter(fn (Product $p) => (int) $p->stock_quantity > 0)
                ->values();

            if ($products->isEmpty()) {
                continue;
            }

            $storeCat = $seller->line_of_business ?: 'General';
            $categoriesCount[$storeCat] = ($categoriesCount[$storeCat] ?? 0) + 1;

            if ($selectedCategory && $selectedCategory !== $storeCat) {
                continue;
            }

            $freeGiftProduct = null;
            if ($rb->bundle_type === 'buy_x_get_y' && $rb->free_gift_product_id) {
                $fg = Product::with('approval')->find((int) $rb->free_gift_product_id);
                if ($fg && (int) $fg->stock_quantity > 0) {
                    $freeGiftProduct = $this->formatProductForBundleSelector($fg);
                }
            }

            $formattedProducts = $products->map(fn (Product $p) => $this->formatProductForBundleSelector($p))->values()->all();

            $bundles->push((object) [
                'id'                  => (int) $rb->id,
                'title'               => $rb->title,
                'bundle_type'         => $rb->bundle_type,
                'required_quantity'   => max(2, (int) $rb->required_quantity),
                'discount_percentage' => $rb->discount_percentage !== null ? (float) $rb->discount_percentage : null,
                'starts_at'           => $rb->starts_at,
                'ends_at'             => $rb->ends_at,
                'seller'              => $seller,
                'category'            => $storeCat,
                'products'            => $formattedProducts,
                'free_gift_product'   => $freeGiftProduct,
            ]);
        }

        return view('buyer.bundles.index', compact(
            'bundles',
            'categoriesCount',
            'selectedCategory',
            'bundleTypeFilter'
        ));
    }

    public function addBundleToCart(Request $request, int $id)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $bundle = DB::table('bundle_deals')
            ->where('id', $id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        if (! $bundle) {
            return back()->withErrors(['bundle' => 'This Bundle Deal has ended or is no longer active.']);
        }

        $seller = SellerProfile::find($bundle->seller_profile_id);
        if (! $seller || (bool) ($seller->is_on_vacation ?? false)) {
            return back()->withErrors(['bundle' => 'This store is currently on vacation and is temporarily not accepting orders.']);
        }

        if ((int) $seller->user_id === (int) $user->id) {
            return back()->withErrors(['bundle' => 'You cannot purchase a bundle deal from your own store.']);
        }

        $validated = $request->validate([
            'selections'               => ['required', 'array', 'min:1'],
            'selections.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'selections.*.variant'     => ['nullable', 'string', 'max:100'],
            'selections.*.sub_variant' => ['nullable', 'string', 'max:100'],
            'selections.*.quantity'    => ['required', 'integer', 'min:1', 'max:50'],
            'free_gift_variant'        => ['nullable', 'string', 'max:100'],
            'free_gift_sub_variant'    => ['nullable', 'string', 'max:100'],
            'redirect_to'              => ['nullable', 'in:cart,checkout'],
        ]);

        $allowedProductIds = is_string($bundle->applicable_product_ids)
            ? (json_decode($bundle->applicable_product_ids, true) ?: [])
            : (array) $bundle->applicable_product_ids;
        $allowedProductIds = array_map('intval', $allowedProductIds);

        $totalSelectedUnits = 0;
        $verifiedSelections = [];

        foreach ($validated['selections'] as $idx => $sel) {
            $prodId = (int) $sel['product_id'];
            $qty = (int) $sel['quantity'];

            if ($qty <= 0) {
                continue;
            }

            if (! in_array($prodId, $allowedProductIds, true)) {
                throw ValidationException::withMessages([
                    "selections.{$idx}.product_id" => 'Selected product is not part of this Bundle Deal.',
                ]);
            }

            $product = Product::findOrFail($prodId);
            $variantCheck = $this->validateVariantSelectionAndStock(
                product: $product,
                variantName: $sel['variant'] ?? null,
                subVariantName: $sel['sub_variant'] ?? null,
                requestedQty: $qty
            );

            $totalSelectedUnits += $qty;
            $verifiedSelections[] = [
                'product'     => $product,
                'variant'     => $variantCheck['variant'],
                'sub_variant' => $variantCheck['sub_variant'],
                'quantity'    => $qty,
            ];
        }

        $requiredQty = max(2, (int) $bundle->required_quantity);
        if ($totalSelectedUnits < $requiredQty) {
            throw ValidationException::withMessages([
                'selections' => "Please select at least {$requiredQty} qualifying item(s) to activate \"{$bundle->title}\" (you selected {$totalSelectedUnits}).",
            ]);
        }

        $touchedCartIds = [];

        DB::transaction(function () use ($user, $bundle, $verifiedSelections, $validated, &$touchedCartIds) {
            foreach ($verifiedSelections as $entry) {
                /** @var Product $prod */
                $prod = $entry['product'];
                $cartItem = Cart::where('user_id', $user->id)
                    ->where('product_id', $prod->id)
                    ->where('variant', $entry['variant'])
                    ->where('sub_variant', $entry['sub_variant'])
                    ->first();

                if ($cartItem) {
                    $cartItem->update([
                        'quantity' => $cartItem->quantity + $entry['quantity'],
                    ]);
                } else {
                    $cartItem = Cart::create([
                        'user_id'     => $user->id,
                        'product_id'  => $prod->id,
                        'variant'     => $entry['variant'],
                        'sub_variant' => $entry['sub_variant'],
                        'quantity'    => $entry['quantity'],
                    ]);
                }

                $touchedCartIds[] = $cartItem->id;
            }

            if ($bundle->bundle_type === 'buy_x_get_y' && $bundle->free_gift_product_id) {
                $giftProd = Product::find((int) $bundle->free_gift_product_id);
                if ($giftProd && (int) $giftProd->stock_quantity > 0) {
                    $giftVariantInfo = $this->resolveDefaultOrSelectedGiftVariant(
                        product: $giftProd,
                        variantName: $validated['free_gift_variant'] ?? null,
                        subVariantName: $validated['free_gift_sub_variant'] ?? null
                    );

                    $giftCart = Cart::where('user_id', $user->id)
                        ->where('product_id', $giftProd->id)
                        ->where('variant', $giftVariantInfo['variant'])
                        ->where('sub_variant', $giftVariantInfo['sub_variant'])
                        ->first();

                    if (! $giftCart) {
                        $giftCart = Cart::create([
                            'user_id'     => $user->id,
                            'product_id'  => $giftProd->id,
                            'variant'     => $giftVariantInfo['variant'],
                            'sub_variant' => $giftVariantInfo['sub_variant'],
                            'quantity'    => 1,
                        ]);
                    }

                    $touchedCartIds[] = $giftCart->id;
                }
            }
        });

        if (($validated['redirect_to'] ?? 'cart') === 'checkout' && ! empty($touchedCartIds)) {
            return redirect()->route('checkout.index', [
                'mode'     => 'cart',
                'cart_ids' => array_values(array_unique($touchedCartIds)),
            ])->with('success', "Bundle \"{$bundle->title}\" items added! Your bundle discount is applied automatically.");
        }

        return redirect()
            ->route('cart.index')
            ->with('success', "Bundle \"{$bundle->title}\" items added to your shopping cart!");
    }

    protected function formatProductForBundleSelector(Product $product): array
    {
        $firstPic = (! empty($product->pictures) && is_array($product->pictures))
            ? asset('storage/' . $product->pictures[0])
            : '';

        $variantsData = $product->variants ?? [];
        $variantTitle = $variantsData['title'] ?? 'Variant';
        $subVariantTitle = $variantsData['sub_title'] ?? 'Option';
        $items = [];

        if (! empty($variantsData['items']) && is_array($variantsData['items'])) {
            foreach ($variantsData['items'] as $v) {
                if (! empty($v['is_archived']) || ($v['status'] ?? 'Approved') !== 'Approved') {
                    continue;
                }

                $subs = [];
                $hasSubs = ! empty($v['subs']) && is_array($v['subs']);
                if ($hasSubs) {
                    foreach ($v['subs'] as $s) {
                        if (! empty($s['is_archived']) || ($s['status'] ?? 'Approved') !== 'Approved') {
                            continue;
                        }
                        if ((int) ($s['stock'] ?? 0) <= 0) {
                            continue;
                        }
                        $subs[] = [
                            'name'  => (string) ($s['name'] ?? ''),
                            'price' => round((float) ($s['price'] ?? ($v['price'] ?? $product->price)), 2),
                            'stock' => (int) ($s['stock'] ?? 0),
                        ];
                    }
                }

                $vStock = $hasSubs
                    ? collect($subs)->sum('stock')
                    : (int) ($v['stock'] ?? 0);

                if ($vStock <= 0) {
                    continue;
                }

                $items[] = [
                    'name'  => (string) ($v['name'] ?? ''),
                    'image' => ! empty($v['image']) ? asset('storage/' . $v['image']) : $firstPic,
                    'price' => round((float) ($v['price'] ?? $product->price), 2),
                    'stock' => $vStock,
                    'subs'  => $subs,
                ];
            }
        }

        return [
            'id'                => (int) $product->id,
            'name'              => $product->name,
            'price'             => round((float) $product->price, 2),
            'stock'             => (int) $product->stock_quantity,
            'image'             => $firstPic,
            'url'               => route('product.show', $product->id),
            'has_variants'      => ! empty($items),
            'variant_title'     => $variantTitle,
            'sub_variant_title' => $subVariantTitle,
            'variants'          => $items,
        ];
    }

    protected function validateVariantSelectionAndStock(
        Product $product,
        ?string $variantName,
        ?string $subVariantName,
        int $requestedQty
    ): array {
        $variantsData = $product->variants ?? [];
        $items = isset($variantsData['items']) && is_array($variantsData['items'])
            ? collect($variantsData['items'])->reject(fn ($v) => ! empty($v['is_archived']) || ($v['status'] ?? 'Approved') !== 'Approved')->values()
            : collect();

        if ($items->isEmpty()) {
            if ($requestedQty > (int) $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'selections' => "Only {$product->stock_quantity} unit(s) of \"{$product->name}\" are available in stock.",
                ]);
            }

            return ['variant' => null, 'sub_variant' => null];
        }

        $cleanVar = trim((string) $variantName);
        $matchedVar = $items->first(fn ($v) => strcasecmp(trim((string) ($v['name'] ?? '')), $cleanVar) === 0);

        if (! $matchedVar) {
            $varTitle = $variantsData['title'] ?? 'variant';
            throw ValidationException::withMessages([
                'selections' => "Please select a valid {$varTitle} for \"{$product->name}\".",
            ]);
        }

        $subs = isset($matchedVar['subs']) && is_array($matchedVar['subs'])
            ? collect($matchedVar['subs'])->reject(fn ($s) => ! empty($s['is_archived']) || ($s['status'] ?? 'Approved') !== 'Approved')->values()
            : collect();

        if ($subs->isNotEmpty()) {
            $cleanSub = trim((string) $subVariantName);
            $matchedSub = $subs->first(fn ($s) => strcasecmp(trim((string) ($s['name'] ?? '')), $cleanSub) === 0);

            if (! $matchedSub) {
                $subTitle = $variantsData['sub_title'] ?? 'sub-variant';
                throw ValidationException::withMessages([
                    'selections' => "Please select a valid {$subTitle} for \"{$product->name} ({$matchedVar['name']})\".",
                ]);
            }

            $avail = (int) ($matchedSub['stock'] ?? 0);
            if ($requestedQty > $avail) {
                throw ValidationException::withMessages([
                    'selections' => "Only {$avail} unit(s) of \"{$product->name} ({$matchedVar['name']} / {$matchedSub['name']})\" are available.",
                ]);
            }

            return [
                'variant'     => $matchedVar['name'],
                'sub_variant' => $matchedSub['name'],
            ];
        }

        $avail = (int) ($matchedVar['stock'] ?? 0);
        if ($requestedQty > $avail) {
            throw ValidationException::withMessages([
                'selections' => "Only {$avail} unit(s) of \"{$product->name} ({$matchedVar['name']})\" are available.",
            ]);
        }

        return [
            'variant'     => $matchedVar['name'],
            'sub_variant' => null,
        ];
    }

    protected function resolveDefaultOrSelectedGiftVariant(
        Product $product,
        ?string $variantName,
        ?string $subVariantName
    ): array {
        try {
            return $this->validateVariantSelectionAndStock($product, $variantName, $subVariantName, 1);
        } catch (\Throwable $e) {
            $formatted = $this->formatProductForBundleSelector($product);
            if (! empty($formatted['variants'])) {
                $firstVar = $formatted['variants'][0];
                $firstSub = ! empty($firstVar['subs']) ? $firstVar['subs'][0]['name'] : null;
                return [
                    'variant'     => $firstVar['name'],
                    'sub_variant' => $firstSub,
                ];
            }
            return ['variant' => null, 'sub_variant' => null];
        }
    }
}