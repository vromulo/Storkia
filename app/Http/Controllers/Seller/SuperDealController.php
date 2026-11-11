<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryStockService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SuperDealController extends Controller
{
    public function __construct(
        protected SuperDealPricingService $superDealPricingService,
        protected ?InventoryStockService $inventoryStockService = null
    ) {
        $this->inventoryStockService = $inventoryStockService ?? app(InventoryStockService::class);
    }

    protected function resolveProductBasePriceAndStock(Product $product, ?string $variantName = null, ?string $subVariantName = null): array
    {
        $raw = $product->raw_variants;
        $hasVariants = is_array($raw) && ! empty($raw['items']) && is_array($raw['items']);

        if ($hasVariants && trim((string) $variantName) !== '') {
            try {
                $resolved = $this->inventoryStockService->resolveProductSelection($product, $variantName, $subVariantName);
                return [
                    'unit_price'       => (float) $resolved['unit_price'],
                    'available_stock'  => (int) $resolved['available_stock'],
                    'variant_name'     => $resolved['variant_name'],
                    'sub_variant_name' => $resolved['sub_variant_name'],
                ];
            } catch (\Throwable $e) {
            }
        }

        $base = (float) ($product->price ?? 0);
        if ($base <= 0 && $hasVariants) {
            $minVariantPrice = null;
            foreach ($raw['items'] as $vItem) {
                if (! empty($vItem['is_archived']) || ($vItem['status'] ?? 'Approved') === 'Disapproved') {
                    continue;
                }
                if (isset($vItem['price']) && (float) $vItem['price'] > 0) {
                    $vp = (float) $vItem['price'];
                    $minVariantPrice = $minVariantPrice === null ? $vp : min($minVariantPrice, $vp);
                }
                if (! empty($vItem['subs']) && is_array($vItem['subs'])) {
                    foreach ($vItem['subs'] as $sItem) {
                        if (! empty($sItem['is_archived']) || ($sItem['status'] ?? 'Approved') === 'Disapproved') {
                            continue;
                        }
                        if (isset($sItem['price']) && (float) $sItem['price'] > 0) {
                            $sp = (float) $sItem['price'];
                            $minVariantPrice = $minVariantPrice === null ? $sp : min($minVariantPrice, $sp);
                        }
                    }
                }
            }
            if ($minVariantPrice !== null && $minVariantPrice > 0) {
                $base = round($minVariantPrice, 2);
            }
        }

        return [
            'unit_price'       => round($base, 2),
            'available_stock'  => (int) ($product->stock_quantity ?? 0),
            'variant_name'     => trim((string) $variantName) ?: null,
            'sub_variant_name' => trim((string) $subVariantName) ?: null,
        ];
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user?->sellerProfile;

        if (! $profile) {
            abort(403, 'Your seller profile must be approved to manage store promotions.');
        }

        $this->superDealPricingService->expireEndedPromotions();

        $products = Product::with('approval')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $deals = Schema::hasTable('super_deals')
            ? collect(
                DB::table('super_deals')
                    ->where('seller_profile_id', (int) $profile->id)
                    ->orderBy('starts_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
            )
            : collect();

        $bundles = Schema::hasTable('bundle_deals')
            ? collect(
                DB::table('bundle_deals')
                    ->where('seller_profile_id', (int) $profile->id)
                    ->orderBy('starts_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
            )
            : collect();

        $productsById = $products->keyBy('id');

        return view('seller.promotions.index', compact(
            'profile',
            'products',
            'productsById',
            'deals',
            'bundles'
        ));
    }

    public function storeSuperDealOrSale(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'deal_type'           => ['required', 'in:super_deal,store_sale'],
            'product_id'          => ['required', 'integer', 'exists:products,id'],
            'variant_name'        => ['nullable', 'string', 'max:100'],
            'sub_variant_name'    => ['nullable', 'string', 'max:100'],
            'title'               => ['nullable', 'string', 'max:120'],
            'discount_percentage' => ['required', 'integer', 'min:1', 'max:99'],
            'flash_quantity'      => ['required_if:deal_type,super_deal', 'nullable', 'integer', 'min:1'],
            'starts_at'           => ['bail', 'required', 'date', $this->startNotInPast()],
            'ends_at'             => ['bail', 'required', 'date', 'after:starts_at', $this->endNotInPast()],
        ], [
            'ends_at.after'                => 'End date and time must be strictly after the start date and time.',
            'discount_percentage.required' => 'Please enter a discount percentage from 1% to 99%.',
            'discount_percentage.integer'  => 'Discount percentage must be a whole number from 1% to 99%.',
            'flash_quantity.required_if'   => 'Please enter the Super Deal slot quantity.',
        ]);

        $product = Product::where('user_id', $user->id)->findOrFail($validated['product_id']);
        $resolvedInfo = $this->resolveProductBasePriceAndStock(
            $product,
            $validated['variant_name'] ?? null,
            $validated['sub_variant_name'] ?? null
        );

        $originalPrice = (float) $resolvedInfo['unit_price'];
        $availableStock = (int) $resolvedInfo['available_stock'];
        $discountPct = (int) $validated['discount_percentage'];
        $dealPrice = round($originalPrice * (1 - ($discountPct / 100)), 2);

        if ($validated['deal_type'] === 'super_deal' && (int) $validated['flash_quantity'] > $availableStock) {
            throw ValidationException::withMessages([
                'flash_quantity' => "Super Deal quantity cannot exceed the product/variant's available stock ({$availableStock}).",
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        if ($validated['deal_type'] === 'store_sale') {
            $overlapping = DB::table('super_deals')
                ->where('product_id', $product->id)
                ->where('deal_type', 'store_sale')
                ->where('status', 'active')
                ->where(function ($q) use ($startsAt, $endsAt) {
                    $q->whereBetween('starts_at', [$startsAt, $endsAt])
                      ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                      ->orWhere(function ($oq) use ($startsAt, $endsAt) {
                          $oq->where('starts_at', '<=', $startsAt)->where('ends_at', '>=', $endsAt);
                      });
                })
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'product_id' => "\"{$product->name}\" already has a scheduled Store Sale during that time period.",
                ]);
            }
        }

        $insertPayload = [
            'seller_profile_id'   => $profile->id,
            'product_id'          => $product->id,
            'deal_type'           => $validated['deal_type'],
            'title'               => ($validated['title'] ?? null) ?: ($validated['deal_type'] === 'super_deal' ? 'Super Deal Slot' : 'Scheduled Store Sale'),
            'deal_price'          => $dealPrice,
            'discount_percentage' => $discountPct,
            'flash_quantity'      => $validated['deal_type'] === 'super_deal' ? (int) $validated['flash_quantity'] : null,
            'sold_quantity'       => 0,
            'starts_at'           => $startsAt,
            'ends_at'             => $endsAt,
            'status'              => 'active',
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        if (Schema::hasColumn('super_deals', 'variant_name')) {
            $insertPayload['variant_name'] = $resolvedInfo['variant_name'];
            $insertPayload['sub_variant_name'] = $resolvedInfo['sub_variant_name'];
        }

        DB::table('super_deals')->insert($insertPayload);

        return back()->with(
            'success',
            ($validated['deal_type'] === 'super_deal' ? 'Super Deal time slot' : 'Scheduled Store Sale') . " created for \"{$product->name}\"!"
        );
    }

    public function updateRunningSale(Request $request, int $id)
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile) {
            abort(403);
        }

        $deal = DB::table('super_deals')
            ->where('id', $id)
            ->where('seller_profile_id', $profile->id)
            ->first();

        if (! $deal) {
            abort(404);
        }

        $validated = $request->validate([
            'discount_percentage' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::findOrFail($deal->product_id);
        $resolvedInfo = $this->resolveProductBasePriceAndStock(
            $product,
            $deal->variant_name ?? null,
            $deal->sub_variant_name ?? null
        );
        $basePrice = (float) $resolvedInfo['unit_price'];
        $newPrice = round($basePrice * (1 - ((int) $validated['discount_percentage'] / 100)), 2);

        DB::table('super_deals')->where('id', $id)->update([
            'discount_percentage' => (int) $validated['discount_percentage'],
            'deal_price'          => $newPrice,
            'updated_at'          => now(),
        ]);

        return back()->with('success', "Sale discount updated to {$validated['discount_percentage']}% (₱" . number_format($newPrice, 2) . ") immediately for new orders.");
    }

    public function endPromotionNow(Request $request, int $id)
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile) {
            abort(403);
        }

        DB::table('super_deals')
            ->where('id', $id)
            ->where('seller_profile_id', $profile->id)
            ->update([
                'status'     => 'ended',
                'ends_at'    => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Promotion ended immediately. Product price has returned to normal for new orders.');
    }

    public function storeBundleDeal(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'title'                      => ['required', 'string', 'min:3', 'max:120'],
            'bundle_type'                => ['required', 'in:percentage_off,buy_x_get_y'],
            'required_quantity'          => ['required', 'integer', 'min:2', 'max:20'],
            'discount_percentage'        => ['required_if:bundle_type,percentage_off', 'nullable', 'numeric', 'min:1', 'max:99'],
            'free_gift_product_id'       => ['required_if:bundle_type,buy_x_get_y', 'nullable', 'integer', 'exists:products,id'],
            'free_gift_variant_name'     => ['nullable', 'string', 'max:100'],
            'free_gift_sub_variant_name' => ['nullable', 'string', 'max:100'],
            'applicable_product_ids'     => ['required', 'array', 'min:1'],
            'applicable_product_ids.*'   => ['required', 'integer'],
            'starts_at'                  => ['bail', 'required', 'date', $this->startNotInPast()],
            'ends_at'                    => ['bail', 'required', 'date', 'after:starts_at', $this->endNotInPast()],
        ], [
            'title.required'                  => 'Please enter a Bundle Deal title.',
            'applicable_product_ids.required' => 'Please select at least one qualifying product for the bundle.',
            'applicable_product_ids.min'      => 'Please select at least one qualifying product for the bundle.',
            'ends_at.after'                   => 'End date and time must be strictly after the start date and time.',
        ]);

        $ownedProductIds = Product::where('user_id', $user->id)
            ->whereIn('id', array_map('intval', $validated['applicable_product_ids']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($ownedProductIds)) {
            throw ValidationException::withMessages([
                'applicable_product_ids' => 'You can only include your own store products in a Bundle Deal.',
            ]);
        }

        $freeGiftId = null;
        $giftVariantName = null;
        $giftSubVariantName = null;

        if ($validated['bundle_type'] === 'buy_x_get_y') {
            $freeGift = Product::where('user_id', $user->id)->find($validated['free_gift_product_id']);
            if (! $freeGift) {
                throw ValidationException::withMessages([
                    'free_gift_product_id' => 'The selected Free Gift product must belong to your store.',
                ]);
            }
            $freeGiftId = (int) $freeGift->id;

            $rawGiftVars = $freeGift->raw_variants;
            $hasGiftVars = is_array($rawGiftVars) && ! empty($rawGiftVars['items']) && is_array($rawGiftVars['items']);

            if ($hasGiftVars) {
                $giftVariantName = trim((string) ($validated['free_gift_variant_name'] ?? '')) ?: null;
                $giftSubVariantName = trim((string) ($validated['free_gift_sub_variant_name'] ?? '')) ?: null;

                if (! $giftVariantName) {
                    foreach ($rawGiftVars['items'] as $vItem) {
                        if (! empty($vItem['is_archived']) || ($vItem['status'] ?? 'Approved') !== 'Approved') {
                            continue;
                        }
                        $giftVariantName = $vItem['name'] ?? null;
                        if (! empty($vItem['subs']) && is_array($vItem['subs'])) {
                            foreach ($vItem['subs'] as $sItem) {
                                if (! empty($sItem['is_archived']) || ($sItem['status'] ?? 'Approved') !== 'Approved') {
                                    continue;
                                }
                                $giftSubVariantName = $sItem['name'] ?? null;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        $insertPayload = [
            'seller_profile_id'      => $profile->id,
            'title'                  => trim($validated['title']),
            'bundle_type'            => $validated['bundle_type'],
            'required_quantity'      => (int) $validated['required_quantity'],
            'discount_percentage'    => $validated['bundle_type'] === 'percentage_off' ? (float) $validated['discount_percentage'] : null,
            'free_gift_product_id'   => $freeGiftId,
            'applicable_product_ids' => json_encode($ownedProductIds),
            'starts_at'              => Carbon::parse($validated['starts_at']),
            'ends_at'                => Carbon::parse($validated['ends_at']),
            'status'                 => 'active',
            'created_at'             => now(),
            'updated_at'             => now(),
        ];

        if (Schema::hasColumn('bundle_deals', 'free_gift_variant_name')) {
            $insertPayload['free_gift_variant_name'] = $giftVariantName;
            $insertPayload['free_gift_sub_variant_name'] = $giftSubVariantName;
        }

        DB::table('bundle_deals')->insert($insertPayload);

        return back()->with('success', "Bundle Deal \"{$validated['title']}\" published!");
    }

    public function endBundleDealNow(Request $request, int $id)
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile) {
            abort(403);
        }

        DB::table('bundle_deals')
            ->where('id', $id)
            ->where('seller_profile_id', $profile->id)
            ->update([
                'status'     => 'ended',
                'ends_at'    => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Bundle Deal ended immediately.');
    }

    protected function startNotInPast(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (Carbon::parse($value)->lt(now()->subMinutes(5))) {
                $fail('Start date and time cannot be in the past. Choose the current time or later.');
            }
        };
    }

    protected function endNotInPast(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (Carbon::parse($value)->lte(now())) {
                $fail('End date and time must be in the future.');
            }
        };
    }

    public function toggleVacationMode(Request $request)
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile) {
            abort(403);
        }

        if (! Schema::hasColumn('seller_profiles', 'is_on_vacation')) {
            return back()->withErrors(['vacation' => 'Vacation mode column is not ready yet.']);
        }

        $nextState = ! $profile->is_on_vacation;
        $profile->update([
            'is_on_vacation' => $nextState,
        ]);

        return back()->with(
            'success',
            $nextState
                ? 'Vacation Mode is now ON. Your products remain visible with an "On Vacation" badge, and Add to Cart / Buy Now / Checkout are paused.'
                : 'Vacation Mode is now OFF. Add to Cart, Buy Now, and Checkout are re-enabled for your store!'
        );
    }
}