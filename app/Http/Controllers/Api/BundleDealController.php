<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsStorefrontProducts;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BundleDealController extends Controller
{
    use FormatsStorefrontProducts;

    private const TYPES = [
        'percentage_off' => 'Buy N Get % Off',
        'buy_x_get_y'    => 'Buy X Get Free Gift',
    ];

    public function __construct(
        protected SuperDealPricingService $pricingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        $this->pricingService->expireEndedPromotions();

        $categoryParam = $request->query('category');
        $selectedCategory = is_string($categoryParam) && $categoryParam !== '' ? $categoryParam : null;

        $typeParam = $request->query('type', 'all');
        $bundleTypeFilter = is_string($typeParam) && isset(self::TYPES[$typeParam]) ? $typeParam : 'all';

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

        $sellers = SellerProfile::whereIn('id', $rawBundles->pluck('seller_profile_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $poolIds = $rawBundles
            ->flatMap(function ($row) {
                $ids = $this->decodeProductIds($row->applicable_product_ids);

                if ($row->free_gift_product_id) {
                    $ids[] = (int) $row->free_gift_product_id;
                }

                return $ids;
            })
            ->unique()
            ->values()
            ->all();

        $pool = $poolIds === []
            ? collect()
            : $this->applyStorefrontVisibility(Product::with('approval')->whereIn('id', $poolIds))
                ->get()
                ->keyBy('id');

        $bundles = collect();
        $categoriesCount = [];

        foreach ($rawBundles as $row) {
            if ($bundleTypeFilter !== 'all' && $row->bundle_type !== $bundleTypeFilter) {
                continue;
            }

            $seller = $sellers->get($row->seller_profile_id);
            if (! $seller || (bool) ($seller->is_on_vacation ?? false)) {
                continue;
            }

            $appIds = $this->decodeProductIds($row->applicable_product_ids);
            if ($appIds === []) {
                continue;
            }

            $products = $pool
                ->filter(fn (Product $p) => in_array((int) $p->id, $appIds, true))
                ->sortBy('id')
                ->values();

            if ($products->isEmpty()) {
                continue;
            }

            $storeCategory = $seller->line_of_business ?: 'General';
            $categoriesCount[$storeCategory] = ($categoriesCount[$storeCategory] ?? 0) + 1;

            if ($selectedCategory !== null && $selectedCategory !== $storeCategory) {
                continue;
            }

            $freeGift = null;
            if ($row->bundle_type === 'buy_x_get_y' && $row->free_gift_product_id) {
                $giftProduct = $pool->get((int) $row->free_gift_product_id);
                $freeGift = $giftProduct ? $this->formatProductForBundleSelector($giftProduct) : null;
            }

            $bundles->push($this->formatBundle($row, $seller, $storeCategory, $products, $freeGift));
        }

        ksort($categoriesCount);

        [$pageItems, $pagination] = $this->storefrontPage($bundles, $request, 10);

        $types = [['key' => 'all', 'label' => 'All Bundles']];
        foreach (self::TYPES as $key => $label) {
            $types[] = ['key' => $key, 'label' => $label];
        }

        $categories = [];
        foreach ($categoriesCount as $name => $count) {
            $categories[] = ['name' => (string) $name, 'count' => (int) $count];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'bundles'     => $pageItems->all(),
                'categories'  => $categories,
                'category'    => $selectedCategory,
                'type'        => $bundleTypeFilter,
                'types'       => $types,
                'pagination'  => $pagination,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function addToCart(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();

        $bundle = Schema::hasTable('bundle_deals')
            ? DB::table('bundle_deals')
                ->where('id', $id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first()
            : null;

        if (! $bundle) {
            return $this->storefrontFailure('This Bundle Deal has ended or is no longer active.', 404, 'bundle_unavailable');
        }

        $seller = SellerProfile::find($bundle->seller_profile_id);
        if (! $seller || (bool) ($seller->is_on_vacation ?? false)) {
            return $this->storefrontFailure(
                'This store is currently on vacation and is temporarily not accepting orders.',
                422,
                'store_on_vacation'
            );
        }

        if ((int) $seller->user_id === (int) $user->id) {
            return $this->storefrontFailure('You cannot purchase a bundle deal from your own store.', 403, 'own_store');
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

        $allowedProductIds = $this->decodeProductIds($bundle->applicable_product_ids);

        $totalSelectedUnits = 0;
        $verifiedSelections = [];

        foreach ($validated['selections'] as $idx => $sel) {
            $productId = (int) $sel['product_id'];
            $quantity = (int) $sel['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            if (! in_array($productId, $allowedProductIds, true)) {
                throw ValidationException::withMessages([
                    "selections.{$idx}.product_id" => 'Selected product is not part of this Bundle Deal.',
                ]);
            }

            $product = Product::with('approval')->findOrFail($productId);

            if (! $this->isStorefrontVisible($product)) {
                throw ValidationException::withMessages([
                    "selections.{$idx}.product_id" => "\"{$product->name}\" is currently unavailable.",
                ]);
            }

            $variantCheck = $this->validateVariantSelectionAndStock(
                product: $product,
                variantName: $sel['variant'] ?? null,
                subVariantName: $sel['sub_variant'] ?? null,
                requestedQty: $quantity
            );

            $totalSelectedUnits += $quantity;
            $verifiedSelections[] = [
                'product'     => $product,
                'variant'     => $variantCheck['variant'],
                'sub_variant' => $variantCheck['sub_variant'],
                'quantity'    => $quantity,
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
                $product = $entry['product'];

                $cartItem = Cart::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->where('variant', $entry['variant'])
                    ->where('sub_variant', $entry['sub_variant'])
                    ->first();

                if ($cartItem) {
                    $cartItem->update(['quantity' => $cartItem->quantity + $entry['quantity']]);
                } else {
                    $cartItem = Cart::create([
                        'user_id'     => $user->id,
                        'product_id'  => $product->id,
                        'variant'     => $entry['variant'],
                        'sub_variant' => $entry['sub_variant'],
                        'quantity'    => $entry['quantity'],
                    ]);
                }

                $touchedCartIds[] = (int) $cartItem->id;
            }

            if ($bundle->bundle_type === 'buy_x_get_y' && $bundle->free_gift_product_id) {
                $giftProduct = Product::with('approval')->find((int) $bundle->free_gift_product_id);

                if ($giftProduct && $this->isStorefrontVisible($giftProduct)) {
                    $giftVariant = $this->resolveDefaultOrSelectedGiftVariant(
                        product: $giftProduct,
                        variantName: $validated['free_gift_variant'] ?? null,
                        subVariantName: $validated['free_gift_sub_variant'] ?? null
                    );

                    $giftCart = Cart::where('user_id', $user->id)
                        ->where('product_id', $giftProduct->id)
                        ->where('variant', $giftVariant['variant'])
                        ->where('sub_variant', $giftVariant['sub_variant'])
                        ->first();

                    if (! $giftCart) {
                        $giftCart = Cart::create([
                            'user_id'     => $user->id,
                            'product_id'  => $giftProduct->id,
                            'variant'     => $giftVariant['variant'],
                            'sub_variant' => $giftVariant['sub_variant'],
                            'quantity'    => 1,
                        ]);
                    }

                    $touchedCartIds[] = (int) $giftCart->id;
                }
            }
        });

        $redirectTo = $validated['redirect_to'] ?? 'cart';

        return response()->json([
            'success' => true,
            'message' => $redirectTo === 'checkout'
                ? "Bundle \"{$bundle->title}\" items added! Your bundle discount is applied automatically."
                : "Bundle \"{$bundle->title}\" items added to your shopping cart!",
            'data'    => [
                'bundle_id'   => (int) $bundle->id,
                'cart_ids'    => array_values(array_unique($touchedCartIds)),
                'redirect_to' => $redirectTo,
            ],
        ]);
    }

    protected function decodeProductIds(mixed $raw): array
    {
        $ids = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

        return array_values(array_unique(array_map('intval', $ids)));
    }

    protected function bundleHeadline(object $row): string
    {
        $required = max(2, (int) $row->required_quantity);

        if ($row->bundle_type === 'buy_x_get_y') {
            return "Buy {$required}, get a free gift";
        }

        $percent = rtrim(rtrim(number_format((float) $row->discount_percentage, 2, '.', ''), '0'), '.');

        return "Buy {$required}, get {$percent}% off";
    }

    protected function formatBundle(object $row, SellerProfile $seller, string $category, Collection $products, ?array $freeGift): array
    {
        $endsAt = $row->ends_at ? Carbon::parse($row->ends_at) : null;

        return [
            'id'                  => (int) $row->id,
            'title'               => $row->title,
            'headline'            => $this->bundleHeadline($row),
            'bundle_type'         => $row->bundle_type,
            'bundle_type_label'   => self::TYPES[$row->bundle_type] ?? (string) $row->bundle_type,
            'required_quantity'   => max(2, (int) $row->required_quantity),
            'discount_percentage' => $row->discount_percentage !== null ? (float) $row->discount_percentage : null,
            'starts_at'           => $row->starts_at ? Carbon::parse($row->starts_at)->toIso8601String() : null,
            'ends_at'             => $endsAt?->toIso8601String(),
            'seconds_left'        => $endsAt ? max(0, $endsAt->timestamp - now()->timestamp) : 0,
            'seller'              => $this->storefrontSeller($seller),
            'category'            => $category,
            'products'            => $products->map(fn (Product $p) => $this->formatProductForBundleSelector($p))->values()->all(),
            'free_gift_product'   => $freeGift,
        ];
    }

    protected function formatProductForBundleSelector(Product $product): array
    {
        $firstPic = (! empty($product->pictures) && is_array($product->pictures))
            ? $this->storefrontUrl($product->pictures[0])
            : null;

        $variantsData = $product->variants ?? [];
        $items = [];

        if (! empty($variantsData['items']) && is_array($variantsData['items'])) {
            foreach ($variantsData['items'] as $variant) {
                if (! empty($variant['is_archived']) || ($variant['status'] ?? 'Approved') !== 'Approved') {
                    continue;
                }

                $subs = [];
                $hasSubs = ! empty($variant['subs']) && is_array($variant['subs']);

                if ($hasSubs) {
                    foreach ($variant['subs'] as $sub) {
                        if (! empty($sub['is_archived']) || ($sub['status'] ?? 'Approved') !== 'Approved') {
                            continue;
                        }
                        if ((int) ($sub['stock'] ?? 0) <= 0) {
                            continue;
                        }

                        $subs[] = [
                            'name'  => (string) ($sub['name'] ?? ''),
                            'price' => round((float) ($sub['price'] ?? ($variant['price'] ?? $product->price)), 2),
                            'stock' => (int) ($sub['stock'] ?? 0),
                        ];
                    }
                }

                $variantStock = $hasSubs
                    ? (int) collect($subs)->sum('stock')
                    : (int) ($variant['stock'] ?? 0);

                if ($variantStock <= 0) {
                    continue;
                }

                $items[] = [
                    'name'  => (string) ($variant['name'] ?? ''),
                    'image' => ! empty($variant['image']) ? $this->storefrontUrl($variant['image']) : $firstPic,
                    'price' => round((float) ($variant['price'] ?? $product->price), 2),
                    'stock' => $variantStock,
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
            'has_variants'      => $items !== [],
            'variant_title'     => $variantsData['title'] ?? 'Variant',
            'sub_variant_title' => $variantsData['sub_title'] ?? 'Option',
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
            ? collect($variantsData['items'])
                ->reject(fn ($v) => ! empty($v['is_archived']) || ($v['status'] ?? 'Approved') !== 'Approved')
                ->values()
            : collect();

        if ($items->isEmpty()) {
            if ($requestedQty > (int) $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'selections' => "Only {$product->stock_quantity} unit(s) of \"{$product->name}\" are available in stock.",
                ]);
            }

            return ['variant' => null, 'sub_variant' => null];
        }

        $cleanVariant = trim((string) $variantName);
        $matchedVariant = $items->first(fn ($v) => strcasecmp(trim((string) ($v['name'] ?? '')), $cleanVariant) === 0);

        if (! $matchedVariant) {
            $variantTitle = $variantsData['title'] ?? 'variant';
            throw ValidationException::withMessages([
                'selections' => "Please select a valid {$variantTitle} for \"{$product->name}\".",
            ]);
        }

        $subs = isset($matchedVariant['subs']) && is_array($matchedVariant['subs'])
            ? collect($matchedVariant['subs'])
                ->reject(fn ($s) => ! empty($s['is_archived']) || ($s['status'] ?? 'Approved') !== 'Approved')
                ->values()
            : collect();

        if ($subs->isNotEmpty()) {
            $cleanSub = trim((string) $subVariantName);
            $matchedSub = $subs->first(fn ($s) => strcasecmp(trim((string) ($s['name'] ?? '')), $cleanSub) === 0);

            if (! $matchedSub) {
                $subTitle = $variantsData['sub_title'] ?? 'sub-variant';
                throw ValidationException::withMessages([
                    'selections' => "Please select a valid {$subTitle} for \"{$product->name} ({$matchedVariant['name']})\".",
                ]);
            }

            $available = (int) ($matchedSub['stock'] ?? 0);
            if ($requestedQty > $available) {
                throw ValidationException::withMessages([
                    'selections' => "Only {$available} unit(s) of \"{$product->name} ({$matchedVariant['name']} / {$matchedSub['name']})\" are available.",
                ]);
            }

            return [
                'variant'     => $matchedVariant['name'],
                'sub_variant' => $matchedSub['name'],
            ];
        }

        $available = (int) ($matchedVariant['stock'] ?? 0);
        if ($requestedQty > $available) {
            throw ValidationException::withMessages([
                'selections' => "Only {$available} unit(s) of \"{$product->name} ({$matchedVariant['name']})\" are available.",
            ]);
        }

        return [
            'variant'     => $matchedVariant['name'],
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
                $firstVariant = $formatted['variants'][0];
                $firstSub = ! empty($firstVariant['subs']) ? $firstVariant['subs'][0]['name'] : null;

                return [
                    'variant'     => $firstVariant['name'],
                    'sub_variant' => $firstSub,
                ];
            }

            return ['variant' => null, 'sub_variant' => null];
        }
    }
}