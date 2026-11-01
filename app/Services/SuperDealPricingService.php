<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SuperDealPricingService
{
    /** @var array<int, object|null>|null */
    protected static ?array $liveSuperDealCache = null;

    protected static int $liveSuperDealCachedAt = 0;

    public function expireEndedPromotions(): void
    {
        if (Schema::hasTable('super_deals')) {
            DB::table('super_deals')
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status'     => 'ended',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('bundle_deals')) {
            DB::table('bundle_deals')
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status'     => 'ended',
                    'updated_at' => now(),
                ]);
        }
    }

    public function assertStoreNotOnVacation(?SellerProfile $sellerProfile): void
    {
        if ($sellerProfile && Schema::hasColumn('seller_profiles', 'is_on_vacation') && $sellerProfile->is_on_vacation) {
            throw ValidationException::withMessages([
                'vacation' => "Store \"{$sellerProfile->business_name}\" is currently on vacation and cannot accept new orders right now.",
            ]);
        }
    }

    public function getActiveSuperDeal(int $productId): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        $deal = DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'super_deal')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->first();

        if (! $deal) {
            return null;
        }

        if ($deal->flash_quantity !== null && (int) $deal->sold_quantity >= (int) $deal->flash_quantity) {
            return null;
        }

        return $deal;
    }

    /**
     * Live Super Deals keyed by product id, loaded with ONE query and reused for the rest of the
     * request, so listing cards (home, category, search...) never query once per product.
     * Same rule as getActiveSuperDeal(): the earliest-ending live deal wins, and a sold-out
     * Super Deal slot no longer applies.
     *
     * @return array<int, object|null>
     */
    public function liveSuperDealMap(): array
    {
        if (static::$liveSuperDealCache !== null && (time() - static::$liveSuperDealCachedAt) < 30) {
            return static::$liveSuperDealCache;
        }

        $map = [];

        if (Schema::hasTable('super_deals')) {
            $rows = DB::table('super_deals')
                ->where('deal_type', 'super_deal')
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->orderBy('ends_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($rows as $row) {
                $pid = (int) $row->product_id;
                if (array_key_exists($pid, $map)) {
                    continue; // the earliest-ending deal for this product was already taken
                }

                $soldOut = $row->flash_quantity !== null
                    && (int) $row->sold_quantity >= (int) $row->flash_quantity;

                $map[$pid] = $soldOut ? null : $row;
            }
        }

        static::$liveSuperDealCache    = $map;
        static::$liveSuperDealCachedAt = time();

        return $map;
    }

    public function liveSuperDealFor(int $productId): ?object
    {
        return $this->liveSuperDealMap()[$productId] ?? null;
    }

    public function getActiveStoreSale(int $productId): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        return DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'store_sale')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->first();
    }

    public function resolveEffectiveStoreSalePrice(Product $product, float $baseUnitPrice): float
    {
        return $this->storeSaleOrProductDiscountPrice(
            $this->getActiveStoreSale((int) $product->id),
            $product,
            $baseUnitPrice
        );
    }

    /**
     * Price after the Store Sale (if any) or the product's own discount %.
     * Shared by checkout and the display resolver so both always agree.
     */
    protected function storeSaleOrProductDiscountPrice(?object $storeSale, Product $product, float $baseUnitPrice): float
    {
        if ($storeSale) {
            if ($storeSale->deal_price !== null && (float) $storeSale->deal_price > 0) {
                return min($baseUnitPrice, round((float) $storeSale->deal_price, 2));
            }
            if ($storeSale->discount_percentage !== null && (float) $storeSale->discount_percentage > 0) {
                return round($baseUnitPrice * (1 - ((float) $storeSale->discount_percentage / 100)), 2);
            }
        }

        $productDiscount = (float) ($product->discount ?? 0);
        if ($productDiscount > 0) {
            return round($baseUnitPrice * (1 - ($productDiscount / 100)), 2);
        }

        return round($baseUnitPrice, 2);
    }

    /**
     * Unit price of one unit sold under a Super Deal.
     * A fixed deal_price applies to every variant of the product (never above that variant's own price);
     * otherwise the discount percentage is taken off the variant's price.
     */
    public function superDealUnitPrice(object $deal, float $baseUnitPrice): float
    {
        if ($deal->deal_price !== null && (float) $deal->deal_price > 0) {
            return min(round($baseUnitPrice, 2), round((float) $deal->deal_price, 2));
        }

        return round($baseUnitPrice * (1 - ((float) ($deal->discount_percentage ?? 0) / 100)), 2);
    }

    /**
     * The price a buyer should SEE for a product (home card, product page, cart).
     * Priority matches checkout: Super Deal -> Store Sale -> product discount -> regular price.
     * Read-only (does not run the expiry UPDATE), so it is cheap to call per cart item.
     */
    public function resolveDisplayPricing(Product $product, float $baseUnitPrice): array
    {
        $base = round($baseUnitPrice, 2);

        $result = [
            'type'           => 'regular',
            'label'          => null,
            'original_price' => $base,
            'unit_price'     => $base,
            'percent_off'    => 0,
            'units_left'     => null,
            'ends_at'        => null,
            'deal_price'     => null,
            'deal_percent'   => null,
        ];

        $storeSale = null;
        $superDeal = null;

        if (Schema::hasTable('super_deals')) {
            $rows = DB::table('super_deals')
                ->where('product_id', $product->id)
                ->whereIn('deal_type', ['super_deal', 'store_sale'])
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->orderBy('ends_at', 'asc')
                ->get();

            $storeSale = $rows->firstWhere('deal_type', 'store_sale');
            $superDeal = $rows->firstWhere('deal_type', 'super_deal');

            // Same rule as getActiveSuperDeal(): a sold-out Super Deal slot no longer applies.
            if ($superDeal && $superDeal->flash_quantity !== null
                && (int) $superDeal->sold_quantity >= (int) $superDeal->flash_quantity) {
                $superDeal = null;
            }
        }

        if ($superDeal) {
            $result['type']         = 'super_deal';
            $result['label']        = 'Super Deal';
            $result['unit_price']   = $this->superDealUnitPrice($superDeal, $base);
            $result['units_left']   = $superDeal->flash_quantity !== null
                ? max(0, (int) $superDeal->flash_quantity - (int) $superDeal->sold_quantity)
                : null;
            $result['ends_at']      = $superDeal->ends_at;
            $result['deal_price']   = ($superDeal->deal_price !== null && (float) $superDeal->deal_price > 0)
                ? round((float) $superDeal->deal_price, 2) : null;
            $result['deal_percent'] = $result['deal_price'] === null
                ? (float) ($superDeal->discount_percentage ?? 0) : null;
        } else {
            $price = $this->storeSaleOrProductDiscountPrice($storeSale, $product, $base);

            if ($price < $base) {
                $result['type']       = $storeSale ? 'store_sale' : 'product_discount';
                $result['label']      = $storeSale ? 'Store Sale' : null;
                $result['unit_price'] = $price;
                $result['ends_at']    = $storeSale?->ends_at;

                if ($storeSale) {
                    $result['deal_price'] = ($storeSale->deal_price !== null && (float) $storeSale->deal_price > 0)
                        ? round((float) $storeSale->deal_price, 2) : null;
                    $result['deal_percent'] = $result['deal_price'] === null
                        ? (float) ($storeSale->discount_percentage ?? 0) : null;
                } else {
                    $result['deal_percent'] = (float) ($product->discount ?? 0);
                }
            }
        }

        $result['percent_off'] = ($base > 0 && $result['unit_price'] < $base)
            ? max(1, (int) round((1 - ($result['unit_price'] / $base)) * 100))
            : 0;

        return $result;
    }

    public function buildSplitCheckoutLines(
        Product $product,
        int $requestedQty,
        float $baseUnitPrice,
        float $unitWeight,
        ?string $variantName = null,
        ?string $subVariantName = null,
        ?string $imagePath = null
    ): array {
        $storeSaleUnitPrice = $this->resolveEffectiveStoreSalePrice($product, $baseUnitPrice);
        $superDeal = $this->getActiveSuperDeal((int) $product->id);

        if (! $superDeal) {
            return [[
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $requestedQty,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $storeSaleUnitPrice,
                'line_total'            => round($storeSaleUnitPrice * $requestedQty, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $requestedQty, 3),
                'super_deal_id'         => null,
                'pricing_label'         => $storeSaleUnitPrice < $baseUnitPrice ? 'Store Sale' : 'Regular',
            ]];
        }

        $superDealPrice = $this->superDealUnitPrice($superDeal, $baseUnitPrice);

        $remainingSuperQty = $superDeal->flash_quantity !== null
            ? max(0, (int) $superDeal->flash_quantity - (int) $superDeal->sold_quantity)
            : $requestedQty;

        $superUnits = min($requestedQty, $remainingSuperQty);
        $overflowUnits = max(0, $requestedQty - $superUnits);

        $lines = [];

        if ($superUnits > 0) {
            $lines[] = [
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $superUnits,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $superDealPrice,
                'line_total'            => round($superDealPrice * $superUnits, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $superUnits, 3),
                'super_deal_id'         => (int) $superDeal->id,
                'pricing_label'         => 'Super Deal',
            ];
        }

        // Units beyond the remaining Super Deal quantity are charged on a separate line at the Store Sale price (or Regular price)
        if ($overflowUnits > 0) {
            $lines[] = [
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $overflowUnits,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $storeSaleUnitPrice,
                'line_total'            => round($storeSaleUnitPrice * $overflowUnits, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $overflowUnits, 3),
                'super_deal_id'         => null,
                'pricing_label'         => $storeSaleUnitPrice < $baseUnitPrice ? 'Store Sale' : 'Regular Price',
            ];
        }

        return $lines;
    }

    public function calculateStoreBundleDiscount(int $sellerProfileId, array $packageLines): array
    {
        if (! Schema::hasTable('bundle_deals')) {
            return [
                'bundle_discount' => 0.00,
                'bundle_title'    => null,
            ];
        }

        $this->expireEndedPromotions();

        $activeBundles = DB::table('bundle_deals')
            ->where('seller_profile_id', $sellerProfileId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get();

        foreach ($activeBundles as $bundle) {
            $applicableIds = json_decode((string) $bundle->applicable_product_ids, true) ?: [];
            $applicableIds = array_map('intval', $applicableIds);

            $qualifyingQty = 0;
            $qualifyingSubtotal = 0.00;

            foreach ($packageLines as $line) {
                $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                if (in_array($prodId, $applicableIds, true)) {
                    $qualifyingQty += (int) $line['quantity'];
                    $qualifyingSubtotal = round($qualifyingSubtotal + (float) $line['line_total'], 2);
                }
            }

            if ($bundle->bundle_type === 'percentage_off' && $qualifyingQty >= (int) $bundle->required_quantity) {
                $pct = (float) ($bundle->discount_percentage ?? 0);
                $disc = round($qualifyingSubtotal * ($pct / 100), 2);
                if ($disc > 0) {
                    return [
                        'bundle_discount' => $disc,
                        'bundle_title'    => $bundle->title,
                    ];
                }
            }

            if ($bundle->bundle_type === 'buy_x_get_y' && $qualifyingQty >= (int) $bundle->required_quantity && $bundle->free_gift_product_id) {
                foreach ($packageLines as $line) {
                    $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                    if ($prodId === (int) $bundle->free_gift_product_id) {
                        $freeAmount = round((float) $line['discounted_unit_price'], 2);
                        return [
                            'bundle_discount' => $freeAmount,
                            'bundle_title'    => $bundle->title . ' (Free Gift Applied)',
                        ];
                    }
                }
            }
        }

        return [
            'bundle_discount' => 0.00,
            'bundle_title'    => null,
        ];
    }
}