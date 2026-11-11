<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerProfile;
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

    public function getActiveSuperDeal(int $productId, ?string $variantName = null, ?string $subVariantName = null): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        $deals = DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'super_deal')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->get();

        $normVar = strtolower(trim((string) $variantName));
        $normSub = strtolower(trim((string) $subVariantName));

        foreach ($deals as $deal) {
            if ($deal->flash_quantity !== null && (int) $deal->sold_quantity >= (int) $deal->flash_quantity) {
                continue;
            }

            $dealVar = strtolower(trim((string) ($deal->variant_name ?? '')));
            $dealSub = strtolower(trim((string) ($deal->sub_variant_name ?? '')));

            if ($dealVar !== '' && $normVar !== '' && $dealVar !== $normVar) {
                continue;
            }
            if ($dealSub !== '' && $normSub !== '' && $dealSub !== $normSub) {
                continue;
            }

            return $deal;
        }

        return null;
    }

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
                    continue;
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

    public function getActiveStoreSale(int $productId, ?string $variantName = null, ?string $subVariantName = null): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        $sales = DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'store_sale')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->get();

        $normVar = strtolower(trim((string) $variantName));
        $normSub = strtolower(trim((string) $subVariantName));

        foreach ($sales as $sale) {
            $saleVar = strtolower(trim((string) ($sale->variant_name ?? '')));
            $saleSub = strtolower(trim((string) ($sale->sub_variant_name ?? '')));

            if ($saleVar !== '' && $normVar !== '' && $saleVar !== $normVar) {
                continue;
            }
            if ($saleSub !== '' && $normSub !== '' && $saleSub !== $normSub) {
                continue;
            }

            return $sale;
        }

        return null;
    }

    public function resolveEffectiveStoreSalePrice(
        Product $product,
        float $baseUnitPrice,
        ?string $variantName = null,
        ?string $subVariantName = null
    ): float {
        return $this->storeSaleOrProductDiscountPrice(
            $this->getActiveStoreSale((int) $product->id, $variantName, $subVariantName),
            $product,
            $baseUnitPrice
        );
    }

    protected function storeSaleOrProductDiscountPrice(?object $storeSale, Product $product, float $baseUnitPrice): float
    {
        if ($storeSale) {
            if ($storeSale->discount_percentage !== null && (float) $storeSale->discount_percentage > 0) {
                return round($baseUnitPrice * (1 - ((float) $storeSale->discount_percentage / 100)), 2);
            }
            if ($storeSale->deal_price !== null && (float) $storeSale->deal_price > 0) {
                return min($baseUnitPrice, round((float) $storeSale->deal_price, 2));
            }
        }

        $productDiscount = (float) ($product->discount ?? 0);
        if ($productDiscount > 0) {
            return round($baseUnitPrice * (1 - ($productDiscount / 100)), 2);
        }

        return round($baseUnitPrice, 2);
    }

    public function superDealUnitPrice(object $deal, float $baseUnitPrice): float
    {
        if ($deal->discount_percentage !== null && (float) $deal->discount_percentage > 0) {
            return round($baseUnitPrice * (1 - ((float) $deal->discount_percentage / 100)), 2);
        }

        if ($deal->deal_price !== null && (float) $deal->deal_price > 0) {
            return min(round($baseUnitPrice, 2), round((float) $deal->deal_price, 2));
        }

        return round($baseUnitPrice, 2);
    }

    public function resolveDisplayPricing(Product $product, float $baseUnitPrice): array
    {
        $base = round($baseUnitPrice, 2);
        $result = [
            'type'             => 'regular',
            'label'            => null,
            'original_price'   => $base,
            'unit_price'       => $base,
            'percent_off'      => 0,
            'units_left'       => null,
            'ends_at'          => null,
            'deal_price'       => null,
            'deal_percent'     => null,
            'variant_name'     => null,
            'sub_variant_name' => null,
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

            if ($superDeal && $superDeal->flash_quantity !== null
                && (int) $superDeal->sold_quantity >= (int) $superDeal->flash_quantity) {
                $superDeal = null;
            }
        }

        if ($superDeal) {
            $result['type']             = 'super_deal';
            $result['label']            = 'Super Deal';
            $result['unit_price']       = $this->superDealUnitPrice($superDeal, $base);
            $result['units_left']       = $superDeal->flash_quantity !== null
                ? max(0, (int) $superDeal->flash_quantity - (int) $superDeal->sold_quantity)
                : null;
            $result['ends_at']          = $superDeal->ends_at;
            $result['deal_percent']     = (float) ($superDeal->discount_percentage ?? 0);
            $result['deal_price']       = $result['deal_percent'] > 0 ? null : (($superDeal->deal_price !== null && (float) $superDeal->deal_price > 0) ? round((float) $superDeal->deal_price, 2) : null);
            $result['variant_name']     = $superDeal->variant_name ?? null;
            $result['sub_variant_name'] = $superDeal->sub_variant_name ?? null;
        } else {
            $price = $this->storeSaleOrProductDiscountPrice($storeSale, $product, $base);
            if ($price < $base) {
                $result['type']       = $storeSale ? 'store_sale' : 'product_discount';
                $result['label']      = $storeSale ? 'Store Sale' : null;
                $result['unit_price'] = $price;
                $result['ends_at']    = $storeSale?->ends_at;
                if ($storeSale) {
                    $result['deal_percent']     = (float) ($storeSale->discount_percentage ?? 0);
                    $result['deal_price']       = $result['deal_percent'] > 0 ? null : (($storeSale->deal_price !== null && (float) $storeSale->deal_price > 0) ? round((float) $storeSale->deal_price, 2) : null);
                    $result['variant_name']     = $storeSale->variant_name ?? null;
                    $result['sub_variant_name'] = $storeSale->sub_variant_name ?? null;
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
        $storeSaleUnitPrice = $this->resolveEffectiveStoreSalePrice($product, $baseUnitPrice, $variantName, $subVariantName);
        $superDeal = $this->getActiveSuperDeal((int) $product->id, $variantName, $subVariantName);

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
            $lowestQualifyingUnitPrice = null;

            foreach ($packageLines as $line) {
                $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                if (in_array($prodId, $applicableIds, true)) {
                    $qualifyingQty += (int) $line['quantity'];
                    $qualifyingSubtotal = round($qualifyingSubtotal + (float) $line['line_total'], 2);
                    $unitPrice = round((float) $line['discounted_unit_price'], 2);
                    if ($unitPrice > 0 && ($lowestQualifyingUnitPrice === null || $unitPrice < $lowestQualifyingUnitPrice)) {
                        $lowestQualifyingUnitPrice = $unitPrice;
                    }
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

            if ($bundle->bundle_type === 'buy_x_get_y' && $qualifyingQty >= (int) $bundle->required_quantity) {
                if ($bundle->free_gift_product_id) {
                    $giftVar = strtolower(trim((string) ($bundle->free_gift_variant_name ?? '')));
                    $giftSub = strtolower(trim((string) ($bundle->free_gift_sub_variant_name ?? '')));

                    foreach ($packageLines as $line) {
                        $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                        if ($prodId !== (int) $bundle->free_gift_product_id) {
                            continue;
                        }
                        $lineVar = strtolower(trim((string) ($line['variant_name'] ?? '')));
                        $lineSub = strtolower(trim((string) ($line['sub_variant_name'] ?? '')));

                        if ($giftVar !== '' && $lineVar !== '' && $giftVar !== $lineVar) {
                            continue;
                        }
                        if ($giftSub !== '' && $lineSub !== '' && $giftSub !== $lineSub) {
                            continue;
                        }

                        $freeAmount = round((float) $line['discounted_unit_price'], 2);
                        return [
                            'bundle_discount' => $freeAmount,
                            'bundle_title'    => $bundle->title . ' (Free Gift Applied)',
                        ];
                    }
                }

                if ($lowestQualifyingUnitPrice !== null && $lowestQualifyingUnitPrice > 0) {
                    return [
                        'bundle_discount' => $lowestQualifyingUnitPrice,
                        'bundle_title'    => $bundle->title . ' (Free Item Discount Applied)',
                    ];
                }
            }
        }

        return [
            'bundle_discount' => 0.00,
            'bundle_title'    => null,
        ];
    }
}